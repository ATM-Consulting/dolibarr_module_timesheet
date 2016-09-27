<?php

class TTimesheet extends TObjetStd {
	function __construct() { /* declaration */
		global $langs,$db;

		parent::set_table(MAIN_DB_PREFIX.'timesheet');
		parent::add_champs('entity,fk_project,fk_societe,status,fk_facture,fk_facture_ligne','type=entier;index;');
		parent::add_champs('date_deb,date_fin','type=date;');
		parent::add_champs('TLineLabel',array('type'=>'array'));
		parent::add_champs('libelleFactureLigne');
		
		parent::_init_vars();
		parent::start();

		$this->libelleFactureLigne = $langs->trans('TimeConsumme');

		$this->TStatus = array(
			0=>$langs->trans('Draft'),
			1=>$langs->trans('Valid'),
			2=>$langs->trans('Billed')
		);
		
		
		//Tableau de tâches
		$this->TTask = array();
		
		//Tableau de temps en seconde permettant de calculer la quantité à facturer
		$this->TQty = array();

	}
	
	function load(&$PDOdb,$id='',$id_Projet=0){
		global $db;
		
		if(!empty($id_Projet)){
			parent::loadBy($PDOdb,$id_Projet,'fk_project');
		}
		else{
			parent::load($PDOdb,$id);
		}

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
	
	function loadProjectTask(&$PDOdb, $fk_user=0){
		global $db,$conf,$user;
		
		$date_deb = date('Y-m-d 00:00:00',$this->date_deb);
		$date_fin =  date('Y-m-d 23:59:59',$this->date_fin);

		$this->TTask=$Tid=array();
		
		if($fk_user>0) {
			$task=new Task($db);
			$user_temp = new User($db);
			$user_temp->fetch($fk_user);

			$TTask = $task->getTasksArray($user_temp, $user_temp);

			foreach($TTask as $t){
				if(empty($t->date_end)) $t->date_end = time();
				
				if($t->date_start <= $this->date_fin && $t->date_end >= $this->date_deb){
					$Tid[] = $t->id;
				}
			}
		}
		else{
			
			$sql = 'SELECT rowid 
					FROM '.MAIN_DB_PREFIX.'projet_task 
					WHERE dateo <= "'.$date_fin.'"
						AND (datee >= "'.$date_deb.'" OR datee IS NULL)';
			
			if(!empty($this->project->id)) $sql .= " AND fk_projet = ".$this->project->id;
			else $sql.=" AND entity=".$conf->entity;
			
			$sql .= ' ORDER BY label ASC';

			$Tid = TRequeteCore::_get_id_by_sql($PDOdb, $sql);
		}
	
		foreach($Tid as $id){

			$task = new Task($db);
			$task->fetch($id);
			$task->fetch_optionals($task->id);

			$this->TTask[$task->id] = $task;
			
			$this->loadTimeSpentByTask($PDOdb,$task->id,$fk_user);
		}
		
	}
	
	function sortByProject($a, $b) {
		
		if($a->fk_project<$b->fk_project) return -1;
		else if($a->fk_project>$b->fk_project) return 1;
		else return 0;
		
	}

	function loadTimeSpentByTask(&$PDOdb,$taskid,$fk_user=0){
		global $db;
		
		$sql = "SELECT t.rowid, t.task_date, t.task_duration, t.fk_user, t.note, u.lastname, u.firstname
				FROM ".MAIN_DB_PREFIX."projet_task_time as t
					LEFT JOIN ".MAIN_DB_PREFIX."user as u ON (t.fk_user = u.rowid)
				WHERE t.fk_task =".$taskid." AND t.task_date BETWEEN '".$this->get_date('date_deb', 'Y-m-d')."' AND '".$this->get_date('date_fin', 'Y-m-d')."'";
		if($fk_user > 0) $sql .= " AND t.fk_user = ".$fk_user; 
		$sql .= " ORDER BY t.fk_user,t.task_date DESC";

		$PDOdb->Execute($sql);
		$this->TTask[$taskid]->TTime=array();
		while ($row = $PDOdb->Get_line()) {
			$this->TTask[$taskid]->TTime[$row->task_date.'_'.$row->fk_user] = $row;
		}
		
	}
	
	function savetimevalues(&$PDOdb,$Tab){
		global $db,$user,$conf;

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
					

					if($idTask > 0){
						$task->fetch($idTask);
						
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
						
						if($PDOdb->Get_line() && !$conf->global->TIMESHEET_CREATE_TASK_DOUBLE){
						
							//Une tache associé à ce service existe dans le projet, on ajoute alors le temps de l'utilisateur concerné
							$rowid = $PDOdb->Get_field('rowid');
							$TTemps[$rowid] = $TTemps[0];
							$Tab['serviceid_'.$rowid] = $Tab['serviceid_0'];
							$Tab['userid_'.$rowid] = $Tab['userid_0']; // il y a tellement de manière de faire ça plus mieux zolie ! 
							
							
							$task->fetch($rowid);
							
							$this->TLineLabel[$task->id][$Tab['userid_0']] = $Tab['lineLabel_0']; 
							
							//On vide le contenu du tableau correspondant à l'ajout de ligne sinon ça va bugger
							// même sans ça tu sais...
							unset($TTemps[0]);
							unset($Tab['serviceid_0']);
							unset($Tab['userid_0']);
							
							$this->_updatetimespent($PDOdb,$Tab,$TTemps,$task,$rowid,$Tab['userid_'.$rowid]);
						}
						else{
							
							$this->_addTask($PDOdb,$Tab,$TTemps,$idTask,$Tab['userid_0']);
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
				
				$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet_task_time 
				WHERE fk_task = ".$idTask." AND fk_user = ".$idUser." AND task_date = '".$date."' LIMIT 1";
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

	function loadLines(&$PDOdb,&$TJours,&$doliform,&$formATM,$mode='view',$freemode=false, $affiche_id_user_dans_tableau=false){
		global $db, $user, $conf, $langs;
		
		$TLigneTimesheet=$THidden=array();
		
		usort($this->TTask, array('TTimesheet', 'sortByProject'));
		$TLigneTimesheet_total_jour=array();
		
		foreach($this->TTask as $task){
			//Comptabilisation des temps + peuplage de $TligneJours
			
			if(!empty($task->TTime) || $freemode){

				$productstatic = new Product($db);
				
				if($task->array_options['options_fk_service']>0 && !$freemode) { //et oui, y avait un mind map
					$productstatic->fetch((int)$task->array_options['options_fk_service']);
					$productstatic->ref = $productstatic->ref." - ".$productstatic->label;
					
					$url_service = ($mode=='print') ? $productstatic->ref : $productstatic->getNomUrl(1); 
				}
				else{
					$url_service =($mode=='print') ?  $task->ref.' - '.$task->label : $task->getNomUrl(1).' - '.$task->label;
				}
					
				if($freemode) $task->TTime = $this->fillWithJour($TJours, $task->TTime);
				
				foreach($task->TTime as $time){
				
					if($user->rights->timesheet->all->read || $user->id == $time->fk_user) {

						$userstatic = new User($db);
						$userstatic->fetch($time->fk_user);

						if(empty($TLigneTimesheet[$task->id.'_'.$userstatic->id]) ) $TLigneTimesheet[$task->id.'_'.$userstatic->id]=array();

						if($freemode) {
							$project = new Project($db);
							$project->fetch($task->fk_project);
							$TLigneTimesheet[$task->id.'_'.$userstatic->id]['project'] = $project->getNomUrl(1);	
							if($freemode) $TLigneTimesheet[$task->id.'_'.$userstatic->id]['project'] .= $project->title;
						}

						$TLigneTimesheet[$task->id.'_'.$userstatic->id]['service'] = $url_service;
						$TLigneTimesheet[$task->id.'_'.$userstatic->id]['consultant'] = ($mode=='print') ? $userstatic->getFullName($langs) : $userstatic->getNomUrl(1);	
						
						if($affiche_id_user_dans_tableau) $TLigneTimesheet[$task->id.'_'.$userstatic->id]['id_consultant'] = $userstatic->id;
						
						if(!strpos($_SERVER['PHP_SELF'], 'timesheetusertimes.php')) {
							$linelabel = !empty($this->TLineLabel[$task->id][$userstatic->id] ) ? $this->TLineLabel[$task->id][$userstatic->id] : '';
							$TLigneTimesheet[$task->id.'_'.$userstatic->id]['TLineLabel'] = ($mode=='print') ? $linelabel : $formATM->texte('', 'TLineLabel['.$task->id.']['.$userstatic->id.']', $linelabel, 30,255);	
						}
						
						//$TLigneTimesheet[$task->id.'_'.$userstatic->id]['total_jours'] += $time->task_duration;
						$TLigneTimesheet[$task->id.'_'.$userstatic->id]['total'] += $time->task_duration; // TODO mais c'est la même chose ?!
						$TTimeTemp[$task->id.'_'.$time->fk_user][$time->task_date] = $time->task_duration;
						
						$TLigneTimesheet_total_jour[$time->task_date] += $time->task_duration;
						
						foreach($TJours as $date=>$val){ // TODO C'est moche, ça passe 50 fois la dedans, cela devrait être extrait de la boucle pour un traitement après
							if($mode == 'edittime'){
								$chaine = $formATM->timepicker('', 'temps['.$task->id.'_'.$userstatic->id.']['.$date.']', ($TTimeTemp[$task->id.'_'.$userstatic->id][$date]) ? convertSecondToTime($TTimeTemp[$task->id.'_'.$userstatic->id][$date],'allhourmin'): '',5);
							}
							else{
								$chaine = ($TTimeTemp[$task->id.'_'.$userstatic->id][$date]) ? convertSecondToTime($TTimeTemp[$task->id.'_'.$userstatic->id][$date],'allhourmin') : '';
							}
							
							if($conf->absence->enabled && empty($conf->global->TIMESHEET_RH_NO_CHECK) && $mode!='print'  ) {
								
								dol_include_once('/absence/class/absence.class.php');
								$absence=new TRH_Absence;
								$absence->fk_user = $userstatic->id;
								if(!$absence->isWorkingDay($PDOdb, $date)){
									$chaine.=img_picto($langs->trans('TimeSheetShoulNotWorkThisDay'), 'warning');
								}
								
							}
							
							if(!empty($chaine) && $mode!='edittime' && $mode!='print' && $conf->ndfp->enabled && $user->rights->timesheet->ndf->read ) {
								
								//tablelines
								
								$chaine.=' <a title="'.$langs->trans('TimeSheetaddNdfExpense').'" href="javascript:get_ndfp('.$userstatic->id.','.$task->id.','.$this->rowid.', \''.dol_print_date(strtotime($date), 'day').'\');">+</a>';

							}
							
							if(empty($TLigneTimesheet[$task->id.'_'.$userstatic->id][$date]) || $TTimeTemp[$task->id.'_'.$userstatic->id][$date]>0) {
								$TLigneTimesheet[$task->id.'_'.$userstatic->id][$date]= $chaine ;	
							}
							
							if(!array_key_exists($date, $TLigneTimesheet_total_jour)){
								$TLigneTimesheet_total_jour[$date] = ' ';
							}
							
						}
						
						if($user->rights->timesheet->user->delete && $user->rights->timesheet->user->add && $this->status<2 && $mode!='print') {
							$TLigneTimesheet[$task->id.'_'.$userstatic->id]['action'] = '<a href="#" onclick="if(confirm(\''.addslashes($langs->transnoentities('ConfirmDeleteTimeline')).'\')) document.location.href=\'?id='.$this->getId().'&fk_task='.$task->id.'&fk_user='.$userstatic->id.'&action=deleteligne\'; ">'.img_delete().'</a>';
						}
						elseif($mode!='print'){
							$TLigneTimesheet[$task->id.'_'.$userstatic->id]['action'] = '';
						}
						
					}
					else{
						if($mode!='view' && $mode!='print') $THidden[$task->id.'_'.$time->fk_user] = $formATM->hidden('TLineLabel['.$task->id.']['.$time->fk_user.']', !empty($this->TLineLabel[$task->id][$time->fk_user] ) ? $this->TLineLabel[$task->id][$time->fk_user] : '');	
						
					}
						
				}

			}
			
		}
		//Mise en forme du total par colonne
		if(!empty($TLigneTimesheet)){
			
			ksort($TLigneTimesheet_total_jour,SORT_STRING);
			foreach ($TLigneTimesheet_total_jour as $key => $value) {
				$TLigneTimesheet_total_jour[$key] = '<strong>'.convertSecondToTime($value,'allhourmin').'</strong>';
				
			}

			$TLigneTimesheet['total_jour'] = array_merge(array('project'=>'','service'=>'','consultant'=>'','commentaire'=>'<strong>'.$langs->transnoentities('Total').'</strong>'),$TLigneTimesheet_total_jour);
		}
		
		
		return array($TLigneTimesheet, $THidden);
	}
	
	function deleteAllTimeForTaskUser(&$PDOdb, $fk_task, $fk_user) {
		global $db, $user;
		
		$task=new Task($db);
		$task->fetch($fk_task);
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet_task_time 
		WHERE fk_task=".$task->id." AND fk_user=".$fk_user." AND task_date BETWEEN '".$this->get_date('date_deb', 'Y-m-d')."' AND '".$this->get_date('date_fin', 'Y-m-d')."'";
		
		$Tab = $PDOdb->ExecuteAsArray($sql);
		foreach($Tab as $row) {
			$task->fetchTimeSpent($row->rowid);
			$task->delTimeSpent($user);
		}
		
		$task->fetch($fk_task);
		
		$sql = "SELECT SUM(task_duration) as task_duration
					FROM ".MAIN_DB_PREFIX."projet_task_time 
					WHERE fk_task =".$fk_task ;
		
		$res = $db->query($sql);
		$obj=$db->fetch_object($res);
		
		if($obj->task_duration==0) {
			$task->delete($user);
		}

	}
	
	function delete(&$PDOdb) {
		global $db,$user;
		
		$this->loadProjectTask($PDOdb);
		foreach($this->TTask as $task) {
			
			foreach($task->TTime as $time) {
				
				$this->deleteAllTimeForTaskUser($PDOdb, $task->id, $time->fk_user);
				
			}
			
		}
		
		
		$this->loadProjectTask($PDOdb);
		if(empty($this->TTask)) {
			/*
			$project=new Project($db);
			$project->fetch($this->project->id);
			$project->delete($user);
			*/	
		}
		
		parent::delete($PDOdb);
		
	}
	
	function loadTJours(){
		global $conf, $langs;
		
		$TJours = array();

		$date_deb = new DateTime($this->get_date('date_deb','Y-m-d'));
		$date_fin = new DateTime($this->get_date('date_fin','Y-m-d'));
		$diff = $date_deb->diff($date_fin);

		$date_deb->sub(new DateInterval('P1D'));

		$TJourNonTravaille=array();
		if($conf->global->RH_JOURS_NON_TRAVAILLE) {
			$TJourNonTravaille = explode(',', $conf->global->RH_JOURS_NON_TRAVAILLE);
		}
		
		for($i=0;$i<=$diff->days;$i++){
			$date_deb->add(new DateInterval('P1D'));
			
			$jourSemaine = strtolower ($langs->trans($date_deb->format('l')));
			
			if(!in_array($jourSemaine, $TJourNonTravaille))$TJours[$date_deb->format('Y-m-d')] = $langs->trans($date_deb->format('l'));
			
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
			
			$this->TLineLabel[$task->id][$idUser] = GETPOST('lineLabel_0'); 			

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
					FROM ".MAIN_DB_PREFIX."timesheet
					WHERE fk_facture = ".$facture->id;

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
		// pose problème ne 3.6 avec le load automatique des objets liés (ici inexistant)
		// $PDOdb->Execute('REPLACE INTO '.MAIN_DB_PREFIX.'element_element (fk_source,sourcetype,fk_target,targettype) VALUES ('.$this->rowid.',"timesheet",'.$facture->id.',"facture")');
		
	}
	
	function _addFactureLine(&$PDOdb,&$facture,$update=false){
		global $db,$user,$conf;

		$label = $this->libelleFactureLigne."<br>";

		if($update){
			//MAJ de la ligne de facture

			foreach ($facture->lines as $factureLine) {
				if($factureLine->label == $this->libelleFactureLigne){ // TODO so moche !
					
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
	
	function _makeFactureLigne(&$PDOdb,&$facture, $devise_taux=1, $devise_code='EUR'){
		global $db, $conf, $user, $langs;

		$description = '';
		$pu_ht = 0;
		
		$lastIdTask = null;

		$TIdLine=array();

		$soc=new Societe($db);
		$soc->fetch($facture->socid);

		foreach($this->TTask as $idTask=>$task){
			$fk_service = (int)$task->array_options['options_fk_service'];

			if($fk_service>0) {
				$product = new Product($db);
				$product->fetch($fk_service);
	
				if (! empty($conf->global->PRODUIT_MULTIPRICES)) {
					$price = $product->multiprices[$this->societe->price_level];
				}
				else{
					$price = $product->price;
				}
	
				$tx_tva = $product->tva_tx;
	
			}
			else{
				$price=0;
			}

			$TTimeUser=array();
			
			if(!empty($task->TTime)) {
				foreach($task->TTime as $Time){
				
					$TTimeUser[$Time->fk_user] += $Time->task_duration;
				
				}
				
			}
			
			foreach($TTimeUser as $fk_user => $timevalue){
				
				$userTemp = new User($db);
				$userTemp->fetch($fk_user);
				$nom = $userTemp->getFullName($langs);
				
				if($fk_service>0){
					$qty = $this->_getQty($product,$timevalue);	
					
					$desc_line= $product->label." : ".$nom/*.", ".$qty." x ".price($price)*/;
				}
				else{
					$hour_per_day = !empty($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY) ? $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY : 8;
					$nb_second_per_day = $hour_per_day * 3600;
					$qty = $timevalue / $nb_second_per_day;
					$desc_line= $task->label." : ".$nom;
				}
				
				
				
				
				if(!empty($this->TLineLabel[$idTask][$fk_user])) {
					$desc_line.=', '.$this->TLineLabel[$idTask][$fk_user];
				}
			
				$remise=0;
				if($conf->tarif->enabled && $fk_service>0) {
					dol_include_once('/tarif/class/tarif.class.php');
					
					$TFk_categorie = TTarif::getCategClient($soc->id);
					$TRes = TTarif::getRemise($db, $fk_service,$qty,1,69, $soc->country_id, $TFk_categorie);
					if($TRes[0]!==false){
						$remise = $TRes[0];
					}
					
					$TRes = TTarif::getPrix($db,$fk_service,$qty,1,69,$price,$devise_taux,$devise_code,$soc->price_level,$soc->country_id, $TFk_categorie);
					
					if($TRes[0]!==false){
						list($price, $tx_tva) = $TRes;
					}
					
				
				}
				
				//$idNewLine = $facture->addline($desc_line, $price, $qty, $tx_tva, 0, 0, $product->id);
				$TIdLine[]=array(
					'price'=>$price / $devise_taux
					,'tx_tva'=>$tx_tva
					,'desc'=>$desc_line
					,'qty'=>$qty
					,'fk_product'=>$product->id
					,'remise'=>$remise
				);
			}
	
		}
		
		$tx_tva = null;
		foreach($TIdLine as $line) {
			
			$qty = $line['qty'];
			$subprice_currency = $line['price'] * (1-( $line['remise'] / 100)) * $devise_taux;
			$price = $subprice_currency*$qty;
			
			$currency = $devise_code;
			
			$description.=$line['desc'];

			if($line['fk_product']) {
				$description.=', '.$qty.' x '.price(round($subprice_currency,2)).$currency.'<br />';
			}
			else{
				$description.=', '.$qty.'<br />';
			}
			
			$pu_ht+=$price;
			
			if(is_null($tx_tva))$tx_tva = $line['tx_tva'];
			if($line['tx_tva']!=$tx_tva)$tx_tva=0;
			
			
		}
		
	//	var_dump(array($pu_ht,$description));exit;
		return array($pu_ht,$description,$tx_tva);
	}
	
	function _getQty(&$product,$timevalue){
		global $db, $user, $conf;

		$hour_per_day = !empty($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY) ? $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY : 8;

		$nb_second_per_day = $hour_per_day * 3600;

		$qty = 0;

		switch ($product->duration_unit) {
			case 'h':
				$qty += ($timevalue / 3600);
				break;
			case 'd':
				$qty += ($timevalue / $nb_second_per_day);
				break;
			case 'w':
				$qty += ($timevalue / ($nb_second_per_day * 5)); // five open days
				break;
			case 'm':
				$qty += ($timevalue / ($nb_second_per_day*20)); // 20 working day per month
				break;
			case 'y':
				$qty += ($timevalue / $nb_second_per_day * 200); // 200 working day per year
				break;
			default:
				
				break;
		}

		return round($qty,1);

	}

}


class TTimesheetNdfp extends TObjetStd {
	function __construct() { /* declaration */
		global $langs,$db;

		parent::set_table(MAIN_DB_PREFIX.'ndfp_det');
		parent::add_champs('fk_task','type=entier;');
		
		parent::_init_vars();
		parent::start();
	}
}
