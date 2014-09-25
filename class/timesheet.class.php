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
	
	function set_timevalues($Tab){
		
		//Parcours des tâches existantes pour MAJ temps
		foreach($Tab as $cle => $value){
			if($cle == 'service_0' && $value === '0'){
				return 1;
			}
			else{
				if(strpos($cle,'_0')){
					//Chargement temporaire d'une tache si nouvelle ligne à ajouter
				}
				elseif(strpos($cle, '_')){
					//Ligne déjà existante => MAJ des temps
					$Tcle = explode('_', $cle);
					
					
				}
			}
		}
		
		
	}
	
	function savetime(&$PDOdb){
		
	}
}