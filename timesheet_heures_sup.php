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
	$timesheet = new TTimesheet;

	$date_deb=GETPOST('date_deb');
	$date_fin=GETPOST('date_fin');
	$userid=GETPOST('userid');

	$timesheet->date_deb = ($date_deb) ? dol_stringtotime($date_deb) : strtotime('last Monday');
	$timesheet->date_fin = ($date_fin) ? dol_stringtotime($date_fin) : strtotime('next Sunday');

	$timesheet->loadProjectTask($PDOdb, $userid);
	
	/*******************************************************************
	* ACTIONS
	*
	* Put here all code to do according to value of "action" parameter
	********************************************************************/
	
	$action=GETPOST('action');

	llxHeader('',$langs->trans('TimeshettUserTimes'),'','',0,0,array('/timesheet/js/timesheet.js.php'));

	
	if($action) {
		switch($action) {
			
		
			case 'edit'	:
			case 'edittime'	:
			case 'changedate' :
				
				_fiche($timesheet,GETPOST('action'),$date_deb,$date_fin);
				break;

			case 'savetime':
				
				if($_REQUEST['save'] !== "Visualiser")
					_saveHeuresSupplementaires();
				
				_fiche($timesheet,'edittime',$date_deb,$date_fin);
				break;
				
			
			case 'deleteligne':
				$timesheet->load($PDOdb, $_REQUEST['id']);
				
				$timesheet->deleteAllTimeForTaskUser($PDOdb, GETPOST('fk_task'), GETPOST('fk_user'));
			
				setEventMessage("Ligne de temps supprimée");
			
				$timesheet->load($PDOdb, $_REQUEST['id']);
				
			
				_fiche($timesheet,'view',$date_deb,$date_fin);
				break;
			
		}
		
	}
	else{
				
		
		_fiche($timesheet, 'edittime',$date_deb,$date_fin);
		
	}


	llxFooter();
	
}
	

function _fiche(&$timesheet, $mode='view', $date_deb="",$date_fin="") {
	
	global $langs,$db,$conf,$user;
	$PDOdb = new TPDOdb;
	$date_deb = (empty($date_deb)) ? date('Y-m-d 00:00:00',strtotime('last Monday')) : $date_deb ;
	$date_fin = (empty($date_fin)) ? date('Y-m-d 00:00:00',strtotime('next Sunday')) : $date_fin ;
	
	print dol_get_fiche_head(timesheetPrepareHead( $timesheet, 'hsup') , 'fiche', $langs->trans('TimeshettUserTimes'));

	$form=new TFormCore();
	$doliform = new Form($db);
	
	if($mode != "edittime"){
		$form->Set_typeaff($mode);
	}
	else{
		$form->Set_typeaff("view");
	}
	
	
	$TBS=new TTemplateTBS();
	$TBS->TBS->protect=false;
	$TBS->TBS->noerr=true;
	
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
		
		if(GETPOST('userid')){
			$lastuser = $user;
			$user->fetch(GETPOST('userid'));
		}
		list($TligneTimesheet,$THidden) = $timesheet->loadLines($PDOdb,$TJours,$doliform,$form2,$mode, true, true);
		if(GETPOST('userid')) $user = $lastuser;
		
		$TligneTimesheetNew = array();
		
		// Pour l'affichage des lignes
		foreach($TligneTimesheet as $line_tab) {

			if($line_tab['id_consultant'] > 0) {
				$total = $TligneTimesheetNew[$line_tab['id_consultant']]['total'] + $line_tab['total'];
				$TligneTimesheetNew[$line_tab['id_consultant']] = array(
																	'consultant' => $line_tab['consultant']
																	,'total' => $total
																	,'total_hsup_souhaité' => _retourneTotalHeuresPeriode($timesheet)
																	,'total_hsup' => _retourneTotalHeuresSupplementaires($timesheet, $total)
																	,'total_hsup_remunerees' => $form2->texte("", "TTimesUser[".$line_tab['id_consultant']."][total_hsup_remunerees]", "", $pTaille)
																	,'total_hsup_rattrapees' => $form2->texte("", "TTimesUser[".$line_tab['id_consultant']."][total_hsup_rattrapees]", "", $pTaille)
																);
			}
		}
		
		// Création des hidden :
		/*foreach($TligneTimesheetNew as $id_user => $line_tab) {
			
			if($id_user > 0) {
			
				$THidden[] .= $form2->hidden("TTimesUser[".$id_user."][remunerees]", $TligneTimesheetNew[$line_tab['id_consultant']]['total_hsup_remunerees']);
				$THidden[] .= $form2->hidden("TTimesUser[".$id_user."][rattrapees]", $TligneTimesheetNew[$line_tab['id_consultant']]['total_hsup_rattrapees']);
				
			}
		}*/

		/*$THidden[] = $form2->hidden("TTimesUser[".$line_tab['id_consultant']."][remunerees]", $TligneTimesheetNew[$line_tab['id_consultant']]['total_hsup_remunerees']);
		$THidden[] = $form->hidden("TTimesUser[".$line_tab['id_consultant']."][rattrapees]", $TligneTimesheetNew[$line_tab['id_consultant']]['total_hsup_rattrapees']);*/
		
		$TligneTimesheet = $TligneTimesheetNew;

		$hour_per_day = !empty($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY) ? $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY : 8;
		$nb_second_per_day = $hour_per_day * 3600;
		
		foreach($TligneTimesheet as $cle => $val){
			//$TligneTimesheet[$cle]['total_jours'] = round(convertSecondToTime($val['total_jours'],'allhourmin',$nb_second_per_day)/24);
			$TligneTimesheet[$cle]['total'] = convertSecondToTime($val['total'],'allhour', $nb_second_per_day);
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
	
	$form->Set_typeaff("edit");
	
	$time = Tools::get_time($date_deb);
	$date_deb = date('d/m/Y', $time);
	
	$time = Tools::get_time($date_fin);
	$date_fin = date('d/m/Y', $time);
	
	if($mode!='new' && $mode != "edit"){
		/*
		 * Affichage tableau de saisie des temps
		 */
		$disabled = 0;
		if(!$user->rights->timesheet->all->read) $disabled = true;

		print $TBS->render('tpl/fiche_saisie_heures_sup.tpl.php'
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
					,'date_deb'=>$form->calendrier('', "date_deb", $date_deb)
					,'date_fin'=>$form->calendrier('', "date_fin", $date_fin)
					,'liste_user'=>$doliform->select_dolusers(((GETPOST('userid')) ? GETPOST('userid') : $user->id),'userid',0,'',$disabled)
					,'tous'=>(GETPOST('userid') == 0) ? 'true' : 'false'
				)
				
			)
			
		);
	}
	 
	echo $form2->end_form();
}

function _saveHeuresSupplementaires() {
	
	global $db,$user;
	
	foreach($_REQUEST['TTimesUser'] as $id_user => $TTimesUser) {
		
		$u = new User($db);
		$u->fetch($id_user);
		$u->fetch_optionals($u->id);
		
		foreach ($TTimesUser as $k => $v) {
			$u->array_options['options_'.$k] += $v;
		}
		
		// Mise à jour de la dernière date enregistrée
		$last_date_saved = strtotime($u->array_options['options_date_last_hsup']);
		$datetime_of_request_datefin = strtotime(implode("/",array_reverse(explode("/", $_REQUEST['date_fin']))));
		
		if($datetime_of_request_datefin > $last_date_saved) $u->array_options['options_date_last_hsup'] = $datetime_of_request_datefin;
		
		$u->update($user);
		
	}
	
	setEventMessage("Heures supplémentaires sauvegardées");
	
}

function _retourneTotalHeuresPeriode(&$timesheet) {
	
	$TDatesJours = $timesheet->loadTJours();
	
	$db = 0;
	
	foreach($TDatesJours as $date => $lib_day) {
		
		if($lib_day !== "Samedi" && $lib_day !== "Dimanche") $nb++;
		
	}
	
	return $nb*7;
	
}

function _retourneTotalHeuresSupplementaires(&$timesheet, $total_heures_travaillees_user) {

	$nb_heures_periode = _retourneTotalHeuresPeriode($timesheet);
	$diff = $total_heures_travaillees_user-$nb_heures_periode*3600;
	
	return ($diff > 0) ? $diff / 3600 : 0;
	
}

?>
