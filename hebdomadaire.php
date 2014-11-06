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
	global $user,$langs,$conf,$mysoc;

	$PDOdb=new TPDOdb;
	
	/*******************************************************************
	* ACTIONS
	*
	* Put here all code to do according to value of "action" parameter
	********************************************************************/
	
	$action=GETPOST('action');
	$date_deb = GETPOST('date_deb');
	$date_fin = GETPOST('date_fin');
	$user = GETPOST('user');

	llxHeader('',$langs->trans('SaisieHebdo'),'','',0,0,array('/timesheet/js/timesheet.js.php'));

	if($action) {
		switch($action) {

			case 'save'	:
				
				break;
		}
		
	}
	else{
		_fiche($date_deb,$date_fin,$user,'view');
	}


	llxFooter();
	
}

function _fiche($mode='view') {
	
	global $langs,$db,$conf,$user;
	$PDOdb = new TPDOdb;
	
	print dol_get_fiche_head(timesheetPrepareHead( $timesheet, 'timesheet') , 'fiche', $langs->trans('SaisieHebdo'));

	$form=new TFormCore($_SERVER['PHP_SELF'],'form','POST');
	$doliform = new Form($db);
	
	if($mode != "edittime"){
		$form->Set_typeaff($mode);
	}
	else{
		$form->Set_typeaff("view");
	}

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
				,'status'=>$timesheet->TStatus[$timesheet->status]
				,'date_deb'=>$form->calendrier('', 'date_deb', $timesheet->date_deb)
				,'date_fin'=>$form->calendrier('', 'date_fin', $timesheet->date_fin)
				,'libelleFactureLigne'=>$form->texte('','libelleFactureLigne', $timesheet->libelleFactureLigne, 50,255)
			)
			,'fiche'=>array(
				'mode'=>$mode
				,'statusval'=>$timesheet->status
				,'link'=>'' //dol_buildpath('/ndfp/js/functions.js.php',2)
				,'righttomodify'=>$user->rights->timesheet->user->edit
				,'righttodelete'=>$user->rights->timesheet->user->delete
				,'righttoapprove'=>$user->rights->timesheet->user->approve
				,'righttoprint'=>$conf->abricot->enabled
			)
		)
	);

	echo $form->end_form();
	
	//Construction du nombre de colonne correspondant aux jours
	$TJours = array(); //Tableau des en-tête pour les jours de la période
	$TFormJours = array(); //Formulaire de saisis nouvelle ligne de temps
	$TligneJours = array(); //Tableau des lignes de temps déjà existante
	
	$TJours = $timesheet->loadTJours(); 
	
	$form2=new TFormCore($_SERVER['PHP_SELF'],'formtime','POST');

	//transformation de $TJours pour jolie affichage
	foreach ($TJours as $key => $value) {
		$TKey = explode('-', $key);
		$TJoursVisu[$TKey[2].'/'.$TKey[1]] = $value;
	}
	
	//Charger les lignes existante dans le timeSheet
	
	if($mode!='new' && $mode!='edit'){
			
		if($mode=='edittime')$form2->Set_typeaff('edit');
		else $form2->Set_typeaff('view');
		
		list($TligneTimesheet,$THidden) = $timesheet->loadLines($PDOdb,$TJours,$doliform,$form2,$mode);
		
		$hour_per_day = !empty($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY) ? $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY : 8;
		$nb_second_per_day = $hour_per_day * 3600;
		
		foreach($TligneTimesheet as $cle => $val){
			//$TligneTimesheet[$cle]['total_jours'] = round(convertSecondToTime($val['total_jours'],'allhourmin',$nb_second_per_day)/24);
			$TligneTimesheet[$cle]['total'] = convertSecondToTime($val['total'],'all', $nb_second_per_day);
		}
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
	

	foreach($TJours as $date=>$jour){
		$TFormJours['temps'.$date] = $form2->timepicker('', 'temps[0]['.$date.']', '',5);
	}
	
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
				,'THidden'=>$THidden
			)
			,array(
				'timesheet'=>array(
					'rowid'=>0
					,'id'=>$timesheet->rowid
					,'services'=>$doliform->select_produits_list('','serviceid_0','1')
					,'consultants'=>(($user->rights->timesheet->all->read) ? $doliform->select_dolusers('','userid_0') : $form2->hidden('userid_0', $user->id).$user->getNomUrl(1))
					,'commentaireNewLine'=>$form2->texte('', 'lineLabel_0', '', 30,255)
				)
				,'view'=>array(
					'mode'=>$mode
					,'nbChamps'=>count($asset->TField)
					,'head'=>dol_get_fiche_head(timesheetPrepareHead($asset)  , 'field', $langs->trans('AssetType'))
					,'onglet'=>dol_get_fiche_head(array()  , '', $langs->trans('AssetType'))
					,'righttoedit'=>($user->rights->timesheet->user->add && $timesheet->status<2)
					,'TimesheetYouCantIsEmpty'=>addslashes( $langs->transnoentitiesnoconv('TimesheetYouCantIsEmpty') )
				)
				
			)
			
		);
	}
	 
	echo $form2->end_form();
}

function _fiche_visu_project(&$project, $mode){
	global $db;

	if($mode=='edit' || $mode=='new') {
		ob_start();
		$html=new FormProjets($db);
		$html->select_projects($project->fk_soc, $project->rowid, 'fk_project');

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

?>
