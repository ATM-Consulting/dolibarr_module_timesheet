<?php

require('config.php');

if(!$user->rights->timesheet->user->read) accessforbidden();

_action();

// Protection if external user
if ($user->societe_id > 0)
{
	accessforbidden();
}

function _action() {
	global $user,$langs,$conf;

	$PDOdb=new TPDOdb;
	$timesheet = new TTimesheet;
	
	/*******************************************************************
	* ACTIONS
	*
	* Put here all code to do according to value of "action" parameter
	********************************************************************/
	llxHeader('',$langs->trans('Timesheet'),'','',0,0,array('/timesheet/js/timesheet.js.php'));

	
	if(isset($_REQUEST['action'])) {
		switch($_REQUEST['action']) {
			case 'new':
			case 'add':
				$timesheet->set_values($_REQUEST);
				_fiche($timesheet,'new');
				break;

			case 'edit'	:
			case 'edittime'	:
				$timesheet->load($PDOdb, $_REQUEST['id']);
				_fiche($timesheet,$_REQUEST['action']);
				break;

			case 'save':
				if(!empty($_REQUEST['id'])) $timesheet->load($PDOdb, $_REQUEST['id']);
				$timesheet->set_values($_REQUEST);
				$timesheet->save($PDOdb);
				
				setEventMessage('TimeSheetSaved');
				
				_fiche($timesheet);
				break;
			
			case 'savetime':
				if(!empty($_REQUEST['id'])) $timesheet->load($PDOdb, $_REQUEST['id']);
				//pre($_REQUEST);
				$timesheet->savetimevalues($PDOdb,$_REQUEST);
				
				setEventMessage('TimeSheetSaved');
				_fiche($timesheet,'edittime');
				break;
				
			case 'facturer':
				if(!empty($_REQUEST['id'])) $timesheet->load($PDOdb, $_REQUEST['id']);
				//$timesheet->status = 2;
				$timesheet->save($PDOdb);
				$timesheet->createFacture($PDOdb);
				_fiche($timesheet);
				break;

			case 'delete':
				$timesheet->load($PDOdb, $_REQUEST['id']);
				$timesheet->delete($PDOdb);
				
				_liste();
				
				break;
		}
		
	}
	elseif(isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
		$timesheet->load($PDOdb, $_REQUEST['id']);
		_fiche($timesheet, 'view');
	}
	else{
		_liste();
	}


	llxFooter();
	
}

function _liste() {
	global $langs,$db,$user,$conf;

	$langs->Load('timesheet@timesheet');

	$TPDOdb=new TPDOdb;
	$TTimesheet = new TTimesheet;

	$sql = "SELECT t.rowid, p.ref, s.nom, t.fk_project, t.fk_societe, t.date_deb, t.date_fin
			FROM ".MAIN_DB_PREFIX."timesheet as t
				LEFT JOIN ".MAIN_DB_PREFIX."projet as p ON (p.rowid = t.fk_project)
				LEFT JOIN ".MAIN_DB_PREFIX."projet_task as pt ON (pt.fk_projet = p.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = t.fk_societe)
			WHERE t.entity = ".$conf->entity."
			GROUP BY p.rowid
			ORDER BY t.date_cre DESC";

	$THide = array(
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
			'fk_societe'=>'<a href="'.dol_buildpath('/societe/soc.php?socid=@fk_societe@',2).'">'.img_picto('','object_company.png','',0).' @nom@</a>'
			,'fk_project'=>'<a href="'.dol_buildpath('/projet/fiche.php?id=@fk_project@',2).'">'.img_picto('','object_project.png','',0).' @ref@</a>'
			,'rowid'=>'<a href="'.dol_buildpath('/timesheet/timesheet.php?id=@rowid@',2).'">'.img_picto('','object_calendar.png','',0).' @rowid@</a>'
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
			,'fk_project'=>'Projet'
			,'fk_societe'=>'Société'
			,'rowid'=>'Identifiant'
		)
	));

	if($user->rights->timesheet->user->add){
		echo '<div class="tabsAction">';
		echo '<a class="butAction" href="?action=new">'.$langs->trans('CreateTimesheet').'</a>';
		echo '</div>';
	}
	
	$TPDOdb->close();

	

}
function _fiche(&$timesheet, $mode='view') {
	
	global $langs,$db,$conf,$user;
	
	$PDOdb = new TPDOdb;
	
	print dol_get_fiche_head(timesheetPrepareHead( $timesheet, 'timesheet') , 'fiche', $langs->trans('FicheTimesheet'));

	$form=new TFormCore($_SERVER['PHP_SELF'],'form','POST');
	$doliform = new Form($db);
	
	if($mode != "edittime"){
		$form->Set_typeaff($mode);
	}
	else{
		$form->Set_typeaff("view");
	}
	
	echo $form->hidden('id', $timesheet->rowid);

	if ($mode=='new' || $mode=='edit'){
		echo $form->hidden('action', 'save');
	}
	else{
		echo $form->hidden('action', 'edit');
	}
	
	echo $form->hidden('entity', $conf->entity);

	$TBS=new TTemplateTBS();
	$liste=new TListviewTBS('timesheet');

	$TBS->TBS->protect=false;
	$TBS->TBS->noerr=true;

	/*
	 * Affichage informations générales
	 */
	print $TBS->render('tpl/fiche.tpl.php'
		,array()
		,array(
			'timesheet'=>array(
				'id'=>$timesheet->rowid
				,'project'=>_fiche_visu_project($timesheet,$mode)
				,'societe'=>_fiche_visu_societe($timesheet,$mode)
				,'status'=>$form->combo('', 'status', $timesheet->TStatus, $timesheet->status)
				,'date_deb'=>$form->calendrier('', 'date_deb', $timesheet->date_deb)
				,'date_fin'=>$form->calendrier('', 'date_fin', $timesheet->date_fin)
			)
			,'fiche'=>array(
				'mode'=>$mode
				,'statusval'=>$timesheet->status
			)
		)
	);

	echo $form->end_form();
	
	//Construction du nombre de colonne correspondant aux jours
	$TJours = array(); //Tableau des en-tête pour les jours de la période
	$TFormJours = array(); //Formulaire de saisis nouvelle ligne de temps
	$TligneJours = array(); //Tableau des lignes de temps déjà existante
	
	$TJours = $timesheet->loadTJours(); 
	
	//transformation de $TJours pour jolie affichage
	foreach ($TJours as $key => $value) {
		$TKey = explode('-', $key);
		$TJoursVisu[$TKey[2].'/'.$TKey[1]] = $value;
	}
	
	$form2=new TFormCore($_SERVER['PHP_SELF'],'formq','POST');

	//Charger les lignes existante dans le timeSheet
	
	if($mode!='new' && $mode!='edit'){
		list($TligneTimesheet) = $timesheet->loadLines($PDOdb,$TJours,$doliform,$form2,$mode);
	}

	foreach($TligneTimesheet as $cle => $val){
		$TligneTimesheet[$cle]['total_jours'] = round(convertSecondToTime($val['total_jours'],'allhourmin',28800)/24);
		$TligneTimesheet[$cle]['total_heures'] = convertSecondToTime($val['total_heures'],'allhourmin');
	}
	
	$TBS=new TTemplateTBS();
	
	if($mode=='edittime'){
		$form2->Set_typeaff('edit');
	}
	else{
		$form->Set_typeaff("view");
	}
	
	echo $form2->hidden('id', $timesheet->rowid);
	
	if ($mode=='edittime'){
		echo $form2->hidden('action', 'savetime');
	}
	
	echo $form2->hidden('entity', $conf->entity);
	
	$date_deb = new DateTime($timesheet->get_date('date_deb','Y-m-d'));
	$date_fin = new DateTime($timesheet->get_date('date_fin','Y-m-d'));
	$diff = $date_deb->diff($date_fin);
	$diff = $diff->format('%d') +1;

	$date_deb->sub(new DateInterval('P1D')); // une utilisation intéressante, reste que l'absence de comm m'empêche d'en comprendre le but

	for($i=1;$i<=$diff;$i++){
		$date_temp = $date_deb->add(new DateInterval('P1D'));
		//Chargement du formulaire se saisie des temps		
		$TFormJours['temps'.$i] = $form2->timepicker('', 'temps[0]['.$date_deb->format('Y-m-d').']', '',5);
	}
	
	/*echo '<pre>';
	print_r($TligneTimesheet);exit;
	echo '</pre>';*/
	if($mode!='new' && $mode != "edit"){
		/*
		 * Affichage tableau de saisie des temps
		 */
		
		 
		print $TBS->render('tpl/fiche_saisie.tpl.php'
			,array(
				'ligneTimesheet'=>$TligneTimesheet,
				'lignejours'=>$TligneJours,
				'jours'=>$TJours,
				'joursVisu'=>$TJoursVisu,
				'formjour'=>$TFormJours
			)
			,array(
				'timesheet'=>array(
					'rowid'=>0
					,'id'=>$timesheet->rowid
					,'services'=>$doliform->select_produits_list('','serviceid_0','1')
					,'consultants'=>$doliform->select_dolusers('','userid_0')
				)
				,'view'=>array(
					'mode'=>$mode
					,'nbChamps'=>count($asset->TField)
					,'head'=>dol_get_fiche_head(timesheetPrepareHead($asset)  , 'field', $langs->trans('AssetType'))
					,'onglet'=>dol_get_fiche_head(array()  , '', $langs->trans('AssetType'))
				)
				
			)	
			
		);
	}
	 
	echo $form2->end_form();
}

function _fiche_visu_project(&$timesheet, $mode){
	global $db;

	if($mode=='edit' || $mode=='new') {
		ob_start();
		$html=new FormProjets($db);
		$html->select_projects($timesheet->fk_societe, $timesheet->fk_project, 'fk_project');

		return ob_get_clean();

	}
	else {
		if($timesheet->fk_project > 0) {
			require_once(DOL_DOCUMENT_ROOT.'/projet/class/project.class.php');

			$project = new Project($db);
			$project->fetch($timesheet->fk_project);
			
			return $project->getNomUrl(1);
			
		} else {
			return 'Non défini';
		}
	}
}

function _fiche_visu_societe(&$timesheet, $mode) {
	global $db;

	if($mode=='edit' || $mode=='new') {
		ob_start();

		$html=new Form($db);
		echo $html->select_company($timesheet->fk_societe,'fk_societe','',1);

		return ob_get_clean();

	}
	else {
		if($timesheet->fk_societe > 0) {
			require_once(DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php');

			$soc = new Societe($db);
			$soc->fetch($timesheet->fk_societe);

			return '<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$timesheet->fk_societe.'" style="font-weight:bold;">'.img_picto('','object_company.png', '', 0).' '.$soc->nom.'</a>';
		} else {
			return 'Non défini';
		}
	}
}

?>
