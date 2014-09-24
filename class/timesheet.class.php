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
	}
}