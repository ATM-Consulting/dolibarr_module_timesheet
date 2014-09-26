<?php

class TTimesheet extends TObjetStd {
	function __construct() { /* declaration */
		global $langs,$db;

		parent::set_table(MAIN_DB_PREFIX.'timesheet');
		parent::add_champs('entity,fk_project,fk_societe','type=entier;');
		parent::add_champs('date_deb,date_fin','type=date;');

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
	
	function sate(&$PDOdb){
		global $db,$user,$conf;

		if(is_null($this->fk_project)){
			
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
}