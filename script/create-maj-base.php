<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 */
 
if(!defined('INC_FROM_DOLIBARR')) {
	define('INC_FROM_CRON_SCRIPT', true);
	require('../config.php');
	
}
else{
	dol_include_once('/timesheet/config.php');
	
	global $db,$conf,$user;
}
 
$PDOdb=new TPDOdb;

$o=new TTimesheet;
$o->init_db_by_vars($PDOdb);

$o=new TTimesheetNdfp;
$o->init_db_by_vars($PDOdb);

dol_include_once('/core/class/extrafields.class.php');
$extrafields=new ExtraFields($db);
$res = $extrafields->addExtraField('fk_service', 'Service lié', 'sellist', 0, '', 'projet_task',0,0,'','a:1:{s:7:"options";a:1:{s:19:"product:label:rowid";N;}}');
$res = $extrafields->addExtraField('is_timesheet', 'Est une feuille de temps', 'select', 0, '', 'projet',0, 0,'', array("options"=> array('non','oui')));
	
		