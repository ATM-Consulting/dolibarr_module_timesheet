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
	
	function loadProjectTask(&$PDOdb){
		global $db;
		
		$sql = "SELECT rowid 
				FROM ".MAIN_DB_PREFIX."projet_task 
				WHERE fk_projet = ".$this->project->id.'
					AND dateo > "'.$this->get_date('date_deb','Y-m-d 00:00:00').'" AND dateo < "'.$this->get_date('date_fin','Y-m-d 23:59:59').'"
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
					
					$task = new Task($db);
					$task->fetch($idTask);

					foreach($TTemps as $date=>$temps){
						
						if($temps != ''){
							$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet_task_time WHERE fk_task = ".$idTask." AND fk_user = ".$Tab['userid_'.$idTask]." AND task_date = '".$date."' LIMIT 1";
							$PDOdb->Execute($sql);

							$timespent_duration_temp = explode(':',$temps);
							$timespent_duration_temp = convertTime2Seconds((int)$timespent_duration_temp[0],(int)$timespent_duration_temp[1]);

							//Un temps a déjà été saisi pour ce projet, cette tache et cet utilisateur
							if($PDOdb->Get_line()){
	
								$task->fetchTimeSpent($PDOdb->Get_field('rowid'));
								$task->timespent_duration = $timespent_duration_temp;

								$task->updateTimeSpent($user);
							}
							//Un temps pour ce projet, cette tache et cet utilisateur n'existe pas encore, il faut l'ajouter
							else{
	
								$task->timespent_date = $date;
								$task->timespent_duration = $timespent_duration_temp;
								$task->timespent_fk_user = $Tab['userid_'.$idTask];

								$task->addTimeSpent($user);
							}
						}
					}
				}
			}
		}
		
		
	}
}