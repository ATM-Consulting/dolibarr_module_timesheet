<?php

class TTimesheet extends TObjetStd {
	function __construct() { /* declaration */
		global $langs,$db;

		parent::set_table(MAIN_DB_PREFIX.'timesheet');
		parent::add_champs('entity,fk_project,fk_societe,status','type=entier;');
		parent::add_champs('date_deb,date_fin','type=date;');
		
		$this->TStatus = array(
			'0'=>'Brouillon',
			'1'=>'Validé',
			'2'=>'Facturé'
		);
		
		$this->libelleFactureLigne = "Temps de réalisation";
		
		//Tableau de tâches
		$this->TTask = array();
		
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
		global $db,$user,$conf;
		
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
	        $projet->title = $this->societe->name." - Feuille de saisie des temps";
	        $projet->description = "";
	        $projet->socid = $this->fk_societe;
	        $projet->date_start= $this->date_deb;
	        $projet->date_end= $this->date_fin;

			$idProjet = $projet->create($user);

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
			$this->TTask[$taskid]->TTime[$row->rowid] = $row;
		}
	}
	
	function savetimevalues(&$PDOdb,$Tab){
		global $db,$user;

		//Parcours des tâches existantes pour MAJ temps
		foreach($Tab as $cle => $value){

			if(is_array($value)){
				
				foreach($value as $idTask => $TTemps){
					
					if($Tid = explode('_',$idTask)){
						$idTask = $Tid[0];
						$idUser = $Tid[1];
					}
					//echo $idTask;exit;
					$task = new Task($db);
					$task->fetch($idTask);

					if($idTask != 0){
						
						$this->_updatetimespent($PDOdb,$Tab,$TTemps,$task,$idTask,$idUser);

					}
					else{
						$product = new Product($db);
						$product->fetch($Tab['serviceid_0']);
						//La tâche n'existe peux être pas encore mais une tache associé au service pour ce projet existe déjà peux être
						$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet_task WHERE fk_projet = ".$this->project->id." AND label = '".$product->label."'";
						$PDOdb->Execute($sql);
						
						if($PDOdb->Get_line()){
							//Une tache associé à ce service existe dans le projet, on ajoute alors le temps de l'utilisateur concerné
							$rowid = $PDOdb->Get_field('rowid');
							$TTemps[$rowid] = $TTemps[0];
							$Tab['serviceid_'.$rowid] = $Tab['serviceid_0'];
							$Tab['userid_'.$rowid] = $Tab['userid_0'];
							
							//On vide le contenu du tableau correspondant à l'ajout de ligne sinon ça va bugger
							unset($TTemps[0]);
							unset($Tab['serviceid_0']);
							unset($Tab['userid_0']);
							
							$task->fetch($rowid);
							
							$this->_updatetimespent($PDOdb,$Tab,$TTemps,$task,$rowid,$Tab['userid_'.$rowid]);
						}
						else{
							//pre($Tab);exit;
							$this->_addTask($PDOdb,$Tab,$TTemps,$idTask);
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

	function _loadLines(&$PDOdb, &$TligneTimesheet,&$TJours,$doliform,$form2,$mode='view'){
		global $db, $user, $conf;
		
		foreach($this->TTask as $task){
	
			$PDOdb->Execute('SELECT rowid FROM '.MAIN_DB_PREFIX.'product WHERE label = "'.$task->label.'" LIMIT 1');
			$PDOdb->Get_line();
			
			$productstatic = new Product($db);
			$productstatic->fetch($PDOdb->Get_field('rowid'));
			$productstatic->ref = $productstatic->ref." - ".$productstatic->label;
	
			//Comptabilisation des temps + peuplage de $TligneJours
			if(!empty($task->TTime)){
				foreach($task->TTime as $idtime => $time){
					
					$userstatic = new User($db);
					$userstatic->id         = $time->fk_user;
					$userstatic->lastname	= $time->lastname;
					$userstatic->firstname 	= $time->firstname;

					$TligneTimesheet[$task->id.'_'.$time->fk_user]['service'] = ($mode == 'edittime') ? $doliform->select_produits_list($productstatic->id,'serviceid_'.$task->id.'_'.$time->fk_user.'','1') : $productstatic->getNomUrl(1,'',48);
					$TligneTimesheet[$task->id.'_'.$time->fk_user]['consultant'] = ($mode == 'edittime') ? $doliform->select_dolusers($userstatic->id,'userid_'.$task->id.'_'.$time->fk_user) : $userstatic->getNomUrl(1);
					$TligneTimesheet[$task->id.'_'.$time->fk_user]['total_jours'] += $time->task_duration;
					$TligneTimesheet[$task->id.'_'.$time->fk_user]['total_heures'] += $time->task_duration;

					$TTimeTemp[$task->id.'_'.$time->fk_user][$time->task_date] = $time->task_duration;

					foreach($TJours as $cle=>$val){
						if($mode == 'edittime'){
							$chaine = $form2->timepicker('', 'temps['.$task->id.'_'.$time->fk_user.']['.$cle.']', $TTimeTemp[$task->id.'_'.$time->fk_user][$cle],5);
						}
						else{
							$chaine = ($TTimeTemp[$task->id.'_'.$time->fk_user][$cle]) ? convertSecondToTime($TTimeTemp[$task->id.'_'.$time->fk_user][$cle],'allhourmin') : '';
						}
						$TligneTimesheet[$task->id.'_'.$time->fk_user][$cle]= $chaine ;

						$Tcle = explode('-',$cle);
						$TJourstemp[$Tcle[2].'/'.$Tcle[1]] = $val;
					}
				}
			}
		}
		
		return array($TJourstemp,$TligneTimesheet);
	}

	function _loadTJours(){
		
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
	
	function _addtask(&$PDOdb,&$Tab,&$TTemps,$idTask){
		global $db,$user,$conf;

		$product = new Product($db);

		if($Tab['serviceid_0'] != 0 && $product->fetch($Tab['serviceid_0'])){
			
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

			$idTask = $task->create($user);
			
			$this->TTask[$task->id] = $task;
			
			//exit($idTask);
			$this->_updatetimespent($PDOdb,$Tab,$TTemps,$task,$idTask);
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
					AND fk_status = 0";

		$PDOdb->Execute($sql);
		if($PDOdb->Get_line()){
				
			$facture->fetch($PDOdb->Get_field('rowid'));	
			
			//Si oui vérifier si une ligne associé au timesheet n'existe pas déjà (présence d'un TUple dans llx_element_element)
			$sql = "SELECT rowid 
					FROM ".MAIN_DB_PREFIX."element_element
					WHERE ee.sourcetype = 'timesheet' 
						AND ee.fk_target = 'facture' 
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
			
			$facture->create($user);
			
			//Ajouter la ligne à la facture
			$this->_addFactureLine($PDOdb,$facture);
			
			//Ajouter la liaison element_element entre la facture et la feuille de temps
			$PDOdb->Execute('INSERT INTO '.MAIN_DB_PREFIX.'element_element (fk_source,sourcetype,fk_target,targettype) VALUES ('.$this->rowid.',"timesheet",'.$facture->rowid.',"facture")');
		}
		
	}
	
	function _addFactureLine(&$PDOdb,&$facture,$update=false){
		global $db,$user,$conf;
		
		if($update){
			//MAJ de la ligne de facture
			foreach ($facture->lines as $factureLine) {
				if($factureLine->label == $this->libelleFactureLigne){
					
					$description = $this->_makeFactureDescription($PDOdb);

					$facture->updateline($factureLine->rowid, $description, $factureLine->subprice, $factureLine->qty, 
											$factureLine->remise_percent, $this->date_deb, $this->date_fin, $factureLine->tva_tx);
				}
			}
		}
		else{
			//Ajout de la ligne de facture
			$description = $this->_makeFactureDescription($PDOdb);
			
			//Calculer la quantité
			$qty = $this->_getQuantité();

			$facture->addline($description, $pu_ht, $qty, $txtva);
		}
		
		
	}
	
	function _makeFactureDescription(&$PDOdb){
		global $db, $conf, $user;

		$description = "";

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

				$description .= $product->label." : ".$userTemp->lastname." ".$userTemp->firstname." - ".convertSecondToTime($timevalue,'all')."<br>";
			}
		}
		
		return $description;
	}

}