<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 */
define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');


$PDOdb=new TPDOdb;
$PDOdb->db->debug=true;

$o=new TTimesheet;
$o->init_db_by_vars($PDOdb);

dol_include_once('/core/class/extrafields.class.php');
$extrafields=new ExtraFields($db);
$res = $extrafields->addExtraField('fk_service', 'Service lié', 'sellist', 0, '', 'projet_task',0,0,'','a:1:{s:7:"options";a:1:{s:19:"product:label:rowid";N;}}');
$res = $extrafields->addExtraField('is_timesheet', 'Est une feuille de temps', 'select', 0, '', 'projet',0, 0,'', array("options"=> array('non','oui')));
	
		