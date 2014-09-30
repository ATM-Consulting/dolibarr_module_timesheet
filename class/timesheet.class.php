<?php

class TTimesheet extends TObjetStd {
	function __construct() { /* declaration */
		global $langs,$db;

		parent::set_table(MAIN_DB_PREFIX.'timesheet');
		parent::add_champs('entity,fk_project,fk_societe,status','type=entier;');
		parent::add_champs('date_deb,date_fin','type=date;');
		
		$this->TStatus = array(
			0=>'Brouillon',
			1=>'Validée',
			2=>'Facturée'
		);
		
		$this->libelleFactureLigne = "Temps de réalisation";
		
		//Tableau de tâches
		$this->TTask = array();
		
		//Tableau de temps en seconde permettant de calculer la quantité à facturer
		$this->TQty = array();
		
		parent::_init_vars();
		parent::start();
	}
	
	function load(&$PDOdb,$id){
		global $db;

		parent::load($PDOdb,$id);

		$this->project = new Project($db);
		$this->project->fetch($this->fk_project);

		$this->societe = new Societe($db);
		$this->societe->fetch($this->fk_societe);
		
		$this->loadProjectTask($PDOdb);
	}
	
	function save(&$PDOdb){
		global $db,$user,$conf,$langs;
		
		if($this->fk_project == 0){
			
			$this->societe = new Societe($db);
			$this->societe->fetch($this->fk_societe);
			
			$projet = new Project($db);
			
			$defaultref='';
		    $obj = empty($conf->global->PROJECT_ADDON)?'mod_project_simple':$conf->global->PROJECT_ADDON;
		    if (! empty($conf->global->PROJECT_ADDON) && is_readable(DOL_DOCUMENT_ROOT ."/core/modules/project/".$conf->global->PROJECT_ADDON.".php"))
		    {
		        require_once DOL_DOCUMENT_ROOT ."/core/modules/project/".$conf->global->PROJECT_ADDON.'.php';
		        $modProject = new $obj;
		        $defaultref = $modProject->getNextValue($this->societe,$projet);
		    }
			
			$projet->ref = $defaultref;
	        $projet->title = $this->societe->name." - ".$langs->trans('Timesheet');
	        $projet->description = "";
	        $projet->socid = $this->fk_societe;
	        $projet->date_start= $this->date_deb;
	        $projet->date_end= $this->date_fin;

			$projet->array_options['options_is_timesheet']=1;

			$idProjet = $projet->create($user);
			
			$projet->setValid($user);

			$this->fk_project = $idProjet;
		}
		
		parent::save($PDOdb);
	}
	
	function loadProjectTask(&$PDOdb){
		global $db;
		
		$sql = "SELECT rowid 
				FROM ".MAIN_DB_PREFIX."projet_task 
				WHERE fk_projet = ".$this->project->id.'
					AND dateo >= "'.$this->get_date('date_deb','Y-m-d 00:00:00').'" AND dateo <= "'.$this->get_date('date_fin','Y-m-d 23:59:59').'"
				ORDER BY dateo ASC';

		//echo $sql;exit;
		$Tid = TRequeteCore::_get_id_by_sql($PDOdb, $sql);

		foreach($Tid as $id){

			$task = new Task($db);
			$task->fetch($id);
			$task->fetch_optionals($task->id);

			$this->TTask[$task->id] = $task;
			
			$this->loadTimeSpentByTask($PDOdb,$task->id);
		}
	}

	function loadTimeSpentByTask(&$PDOdb,$taskid){
		global $db;
		
		$sql = "SELECT t.rowid, t.task_date, t.task_duration, t.fk_user, t.note, u.lastname, u.firstname
				FROM ".MAIN_DB_PREFIX."projet_task_time as t
					LEFT JOIN ".MAIN_DB_PREFIX."user as u ON (t.fk_user = u.rowid)
				WHERE t.fk_task =".$taskid." 
					AND t.fk_user = u.rowid
				ORDER BY t.task_date DESC";

		$PDOdb->Execute($sql);

		while ($row = $PDOdb->Get_line()) {
			$this->TTask[$taskid]->TTime[$row->task_date] = $row;
		}
	}
	
	function savetimevalues(&$PDOdb,$Tab){
		global $db,$user;

		//Parcours des tâches existantes pour MAJ temps
		foreach($Tab as $cle => $TValue){

			if(is_array($TValue)){
				
				foreach($TValue as $idTask => $TTemps){
					
					if($Tid = explode('_',$idTask)){ // forcément... Je me demandais bien en regardant le code de créa de la ligne pourquoi ce n'était pas simplement une clef... Je me demande toujours
						$idTask = $Tid[0];
						$idUser = $Tid[1];
					}
					//echo $idTask;exit;
					$task = new Task($db);
					$task->fetch($idTask);

					if($idTask > 0){
						
						$this->_updatetimespent($PDOdb,$Tab,$TTemps,$task,$idTask,$idUser);

					}
					else if(!empty($Tab['serviceid_0'])){
						$product = new Product($db);
						$product->fetch($Tab['serviceid_0']);
						//La tâche n'existe peux être pas encore mais une tache associé au service pour ce projet existe déjà peux être
						$sql = "SELECT t.rowid 
						FROM ".MAIN_DB_PREFIX."projet_task t INNER JOIN ".MAIN_DB_PREFIX."projet_task_extrafields ex ON (ex.fk_object=t.rowid)
						WHERE t.fk_projet = ".$this->project->id." AND ex.fk_service=".$product->id;
						$PDOdb->Execute($sql);
						
						if($PDOdb->Get_line()){
							//Une tache associé à ce service existe dans le projet, on ajoute alors le temps de l'utilisateur concerné
							$rowid = $PDOdb->Get_field('rowid');
							$TTemps[$rowid] = $TTemps[0];
							$Tab['serviceid_'.$rowid] = $Tab['serviceid_0'];
							$Tab['userid_'.$rowid] = $Tab['userid_0']; // il y a tellement de manière de faire ça plus mieux zolie ! 
							
							//On vide le contenu du tableau correspondant à l'ajout de ligne sinon ça va bugger
							// même sans ça tu sais...
							unset($TTemps[0]);
							unset($Tab['serviceid_0']);
							unset($Tab['userid_0']);
							
							$task->fetch($rowid);
							
							$this->_updatetimespent($PDOdb,$Tab,$TTemps,$task,$rowid,$Tab['userid_'.$rowid]);
						}
						else{
							//pre($Tab);exit;
							$this->_addTask($PDOdb,$Tab,$TTemps,$idTask,$idUser);
						}
					}
					
				}
			}
		}

	}
	
	function _updatetimespent(&$PDOdb,&$Tab,&$TTemps,&$task,$idTask,$idUser){
		global $db, $user;

		if(!in_array($Tab['userid_'.$idTask.'_'.$idUser], $Tab)) $Tab['userid_'.$idTask.'_'.$idUser] = $Tab['userid_0'];
		foreach($TTemps as $date=>$temps){
							
			if($temps != ''){
				
				$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet_task_time WHERE fk_task = ".$idTask." AND fk_user = ".$idUser." AND task_date = '".$date."' LIMIT 1";
				$PDOdb->Execute($sql);

				$timespent_duration_temp = explode(':',$temps);
				$timespent_duration_temp = convertTime2Seconds((int)$timespent_duration_temp[0],(int)$timespent_duration_temp[1]);

				//Un temps a déjà été saisi pour ce projet, cette tache et cet utilisateur
				if($PDOdb->Get_line()){
					$task->fetchTimeSpent($PDOdb->Get_field('rowid'));

					$task->timespent_duration = $timespent_duration_temp;
					$task->timespent_fk_user = $idUser;

					$task->updateTimeSpent($user);
				}
				//Un temps pour ce projet, cette tache et cet utilisateur n'existe pas encore, il faut l'ajouter
				else{
					
					$task->timespent_date = $date;
					$task->timespent_duration = $timespent_duration_temp;
					$task->timespent_fk_user = $idUser;

					$task->addTimeSpent($user);
				}
			}
		}

	}

	private function fillWithJour($TJours, $TTime) {
	global $user;							
						
		foreach($TJours as $date=>$dummy) {
							
			$o=new stdClass;
			$o->fk_user = $user->id;			
			$o->task_duration = 0;
			$o->task_date = $date;
					
			if(empty($TTime[$date])) $TTime[$date] = $o;	
			
		}
		
		return $TTime;
		
	}

	function loadLines(&$PDOdb,&$TJours,$doliform,$formATM,$mode='view'){
		global $db, $user, $conf;
		
		$TLigneTimesheet=array();
	
		foreach($this->TTask as $task){
			$productstatic = new Product($db);
			
			if($task->array_options['options_fk_service']>0) { //et oui, y avait un mind map
				$productstatic->fetch((int)$task->array_options['options_fk_service']);
				$productstatic->ref = $productstatic->ref." - ".$productstatic->label;
				$url_service = $productstatic->getNomUrl(1); 
			}
			else{
				$url_service = $task->getNomUrl(1).' - '.$task->label;
			}
	
			$task->TTime = $this->fillWithJour($TJours, $task->TTime);
	
			//Comptabilisation des temps + peuplage de $TligneJours
			if(!empty($task->TTime)){
				foreach($task->TTime as $time){
				
						$userstatic = new User($db);
						$userstatic->fetch($time->fk_user);
					
						$TLigneTimesheet[$task->id.'_'.$userstatic->id]['service'] = $url_service;
						$TLigneTimesheet[$task->id.'_'.$userstatic->id]['consultant'] = $userstatic->getNomUrl(1);	
						
							
						$TLigneTimesheet[$task->id.'_'.$userstatic->id]['total_jours'] += $time->task_duration;
						$TLigneTimesheet[$task->id.'_'.$userstatic->id]['total_heures'] += $time->task_duration;
						$TTimeTemp[$task->id.'_'.$time->fk_user][$time->task_date] = $time->task_duration;
						
						foreach($TJours as $date=>$val){
							if($mode == 'edittime'){
								$chaine = $formATM->timepicker('', 'temps['.$task->id.'_'.$userstatic->id.']['.$date.']', convertSecondToTime($TTimeTemp[$task->id.'_'.$userstatic->id][$date],'allhourmin'),5);
							}
							else{
								$chaine = ($TTimeTemp[$task->id.'_'.$userstatic->id][$date]) ? convertSecondToTime($TTimeTemp[$task->id.'_'.$userstatic->id][$date],'allhourmin') : '';
							}
							
							if(!empty($chaine) && $mode!='edittime' && $conf->ndfp->enabled) {
								
								//tablelines
								
								$chaine.=' <a href="javascript:addNdf('.$userstatic->id.','.$task->id.');">+</a>';
								
							}
							
							$TLigneTimesheet[$task->id.'_'.$userstatic->id][$date]= $chaine ;
	
							
						}
						
					}
				
			}
			
		}
		
		return array($TLigneTimesheet);
	}

	function loadTJours(){
		
		$TJours = array();

		$date_deb = new DateTime($this->get_date('date_deb','Y-m-d'));
		$date_fin = new DateTime($this->get_date('date_fin','Y-m-d'));
		$diff = $date_deb->diff($date_fin);
		$diff = $diff->format('%d') +1;

		$date_deb->sub(new DateInterval('P1D'));

		for($i=1;$i<=$diff;$i++){
			$date_deb->add(new DateInterval('P1D'));
			$TJours[$date_deb->format('Y-m-d')] = $date_deb->format('D');
		}
		
		return $TJours;
	}
	
	function _addtask(&$PDOdb,&$Tab,&$TTemps,$idTask,$idUser){
		global $db,$user,$conf;

		$product = new Product($db);

		if($product->fetch((int)$Tab['serviceid_0'])){
			
			$task = new Task($db);
			$task->label = $product->label;

			$defaultref='';
			$obj = empty($conf->global->PROJECT_TASK_ADDON)?'mod_task_simple':$conf->global->PROJECT_TASK_ADDON;
			if (! empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.".php"))
			{
				require_once DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.'.php';
				$modTask = new $obj;
				$defaultref = $modTask->getNextValue($this->societe,$task);
			}
			
			$task->ref = $defaultref;
			$task->fk_project = $this->project->id;
			$task->description = '';
			$task->date_start = $this->date_deb;
			$task->date_end = $this->date_fin;
			$task->fk_task_parent = 0;

			$task->array_options['options_fk_service'] = $product->id;

			$idTask = $task->create($user);
			
			$this->TTask[$task->id] = $task;
			
			//exit($idTask);
			$this->_updatetimespent($PDOdb,$Tab,$TTemps,$task,$idTask,$idUser);
		}
	}

	function createFacture(&$PDOdb){
		global $db,$conf,$user;

		$facture = new Facture($db);
		
		//Voir si une facture au status brouillon associé au tier et au projet existe
		$sql = "SELECT rowid 
				FROM ".MAIN_DB_PREFIX."facture
				WHERE fk_projet = ".$this->project->id." 
					AND fk_soc = ".$this->societe->id." 
					AND fk_statut = 0";
		
		$PDOdb->Execute($sql);
		if($PDOdb->Get_line()){
			$facture->fetch($PDOdb->Get_field('rowid'));	
			
			//Si oui vérifier si une ligne associé au timesheet n'existe pas déjà (présence d'un TUple dans llx_element_element)
			$sql = "SELECT rowid 
					FROM ".MAIN_DB_PREFIX."element_element
					WHERE sourcetype = 'timesheet' 
						AND targettype = 'facture'
						AND fk_target = ".$facture->id."
						AND fk_source = ".$this->rowid;

			$PDOdb->Execute($sql);
			if($PDOdb->Get_line()){
				//Si ligne déjà présente dans facture alors update line
				$this->_addFactureLine($PDOdb,$facture,true);
			}
			else{
				//Ajouter la ligne à la facture
				$this->_addFactureLine($PDOdb,$facture);
			}
			
		}
		else{
			//Sinon la créer
			$facture->type = 0;
			$facture->socid = $this->societe->id;
			$facture->fk_project = $this->project->id;
			$facture->date = $db->idate(dol_now());

			$res = $facture->create($user);

			//Ajouter la ligne à la facture
			$this->_addFactureLine($PDOdb,$facture);
		}
		
		//Ajouter la liaison element_element entre la facture et la feuille de temps
		$PDOdb->Execute('REPLACE INTO '.MAIN_DB_PREFIX.'element_element (fk_source,sourcetype,fk_target,targettype) VALUES ('.$this->rowid.',"timesheet",'.$facture->id.',"facture")');
		
	}
	
	function _addFactureLine(&$PDOdb,&$facture,$update=false){
		global $db,$user,$conf;

		$label = $this->libelleFactureLigne."<br>";

		if($update){
			//MAJ de la ligne de facture

			foreach ($facture->lines as $factureLine) {
				if($factureLine->label == $this->libelleFactureLigne){
					
					list($pu_ht,$description) = $this->_makeFactureLigne($PDOdb);

					$facture->updateline($factureLine->rowid, $description, $pu_ht, $factureLine->qty, 
											$factureLine->remise_percent, $this->date_deb, $this->date_fin, $factureLine->tva_tx,
											0, 0, 'HT', 0, 1, 0, 0, null, 0, $label);
				}
			}

		}
		else{
			//Ajout de la ligne de facture
			list($pu_ht,$description) = $this->_makeFactureLigne($PDOdb);

			$facture->addline($description, $pu_ht, 1, 0,0,0,0,0,$date_deb,$date_fin,0,0,'','HT',0,1,0,0,'',0,0,null,0,$label);
		}
		
		
	}
	
	function _makeFactureLigne(&$PDOdb){
		global $db, $conf, $user;

		$description = "";
		$pu_ht = 0;
		
		$lastIdTask = null;

		foreach($this->TTask as $idTask=>$Task){
			
			$PDOdb->Execute('SELECT rowid FROM '.MAIN_DB_PREFIX.'product WHERE label ="'.$Task->label.'"');
			$PDOdb->Get_line();

			$product = new Product($db);
			$product->fetch($PDOdb->Get_field('rowid'));

			foreach($Task->TTime as $Time){
				$TTimeUser[$Time->fk_user] += $Time->task_duration;
			}
			
			foreach($TTimeUser as $fk_user => $timevalue){
				
				$userTemp = new User($db);
				$userTemp->fetch($fk_user);
				
				$pu_ht += $this->_getQty($product,$timevalue) * $product->multiprices[$this->societe->price_level];
				$description .= $product->label." : ".$userTemp->lastname." ".$userTemp->firstname." - ".convertSecondToTime($timevalue,'all')."<br>";
			}
		}
		
		return array($pu_ht,$description);
	}
	
	function _getQty(&$product,$timevalue){
		global $db, $user, $conf;

		$qty = 0;

		switch ($product->duration_unit) {
			case 'h':
				$qty += ($timevalue / 3600);
				break;
			case 'd':
				$qty += ($timevalue / 86400);
				break;
			case 'w':
				$qty += ($timevalue / 604800);
				break;
			case 'm':
				$qty += ($timevalue / 18144000);
				break;
			case 'd':
				$qty += ($timevalue / 217728000);
				break;
			default:
				
				break;
		}

		return round($qty,1);

	}

}