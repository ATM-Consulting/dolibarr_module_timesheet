<?php
	require('config.php');
	require('./class/timesheet.class.php');
	
	if(!$user->rights->timesheet->all->read) accessforbidden();

	_liste();

function _liste() {
	global $langs,$db,$user,$conf;

	$langs->Load('timesheet@timesheet');

	$TPDOdb=new TPDOdb;
	$TTimesheet = new TTimesheet;

	llxHeader('',$langs->trans('ListTimesheet'),'','');

	$sql = "SELECT t.rowid, p.ref, s.nom, t.fk_project, t.fk_societe, t.date_deb, t.date_fin
			FROM ".MAIN_DB_PREFIX."timesheet as t
				LEFT JOIN ".MAIN_DB_PREFIX."project as p ON (p.rowid = t.fk_project)
				LEFT JOIN ".MAIN_DB_PREFIX."project_task as pt ON (pt.fk_project = p.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = t.fk_societe)
			WHERE t.entity = ".$conf->entity."
			ORDER BY t.date_cre DESC";

	$THide = array(
			'rowid',
			'ref',
			'nom'
		);

	$r = new TSSRenderControler($TTimesheet);

	$r->liste($TPDOdb, $sql, array(
		'limit'=>array(
			'nbLine'=>'30'
		)
		,'subQuery'=>array()
		,'link'=>array(
			'fk_societe'=>'<a href="'.dol_buildpath('/societe/soc.php?socid=@fk_societe@').'">'.img_picto('','object_company.png','',0).' @nom@</a>'
			,'fk_project'=>'<a href="'.dol_buildpath('/project/fiche.php?id=@fk_project@').'">'.img_picto('','object_project.png','',0).' @ref@</a>'
		)
		,'translate'=>array()
		,'hide'=>$THide
		,'type'=>array(
			'date_deb'=>'date'
			,'date_fin'=>'date'
		)
		,'liste'=>array(
			'titre'=>$langs->trans('ListTimesheet')
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','previous.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> 0
			,'messageNothing'=>$langs->trans('AnyTimesheet')
			,'picto_search'=>img_picto('','search.png', '', 0)
		)
		,'title'=>array(
			'date_deb'=>'Date début période'
			,'date_fin'=>'Date fin période'
		)
	));

	if($user->rights->timesheet->user->add){
		echo '<div class="tabsAction">';
		echo '<a class="butAction" href="fiche.php?action=new">'.$langs->trans('CreateTimesheet').'</a>';
		echo '</div>';
	}
	
	$TPDOdb->close();

	llxFooter('$Date: 2011/07/31 23:19:25 $ - $Revision: 1.152 $');

}
?>