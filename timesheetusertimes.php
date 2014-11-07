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
	$timesheet->date_fin = strtotime('+7day');
	
	$timesheet->loadProjectTask($PDOdb, $user->id);
	
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
				
				_fiche($timesheet,GETPOST('action'));
				break;

			case 'savetime':
				
				$timesheet->savetimevalues($PDOdb,$_REQUEST);
				setEventMessage('TimeSheetSaved');
				
				$timesheet->loadProjectTask($PDOdb, $user->id);
				
				_fiche($timesheet,'edittime');
				break;
				
			
			case 'deleteligne':
				$timesheet->load($PDOdb, $_REQUEST['id']);
				
				$timesheet->deleteAllTimeForTaskUser($PDOdb, GETPOST('fk_task'), GETPOST('fk_user'));
			
				setEventMessage("Ligne de temps supprimée");
			
				$timesheet->load($PDOdb, $_REQUEST['id']);
				
			
				_fiche($timesheet);
					
			
		}
		
	}
	else{
				
		
		_fiche($timesheet, 'view');
		
	}


	llxFooter();
	
}
function getLangTranslate() {
	global $langs;
	
	$Tab=array();
	foreach($langs->tab_translate as $k=>$v) {
		$Tab[$k] = utf8_decode($v);
	}
	
	return $Tab;
	
}
	
	
function _liste() {
	global $langs,$db,$user,$conf;

	$langs->Load('timesheet@timesheet');


	$TPDOdb=new TPDOdb;
	$TTimesheet = new TTimesheet;

	$sql = "SELECT DISTINCT t.rowid, p.ref, s.nom, t.fk_project, t.fk_societe, t.status, t.date_deb, t.date_fin
			FROM ".MAIN_DB_PREFIX."timesheet as t
				LEFT JOIN ".MAIN_DB_PREFIX."projet as p ON (p.rowid = t.fk_project)
				LEFT JOIN ".MAIN_DB_PREFIX."projet_task as pt ON (pt.fk_projet = p.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = t.fk_societe)
			WHERE t.entity = ".$conf->entity."
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
		,'translate'=>array(
			'status'=>$TTimesheet->TStatus		
		)
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
			,'status'=>$langs->trans('Status')
		)
	));

	if($user->rights->timesheet->user->edit){
		echo '<div class="tabsAction">';
		echo '<a class="butAction" href="?action=new">'.$langs->trans('CreateTimesheet').'</a>';
		echo '</div>';
	}
	
	$TPDOdb->close();

	

}
function _fiche(&$timesheet, $mode='view') {
	
	global $langs,$db,$conf,$user;
	$PDOdb = new TPDOdb;
	
	print dol_get_fiche_head(timesheetPrepareHead( $timesheet, 'timesheet') , 'fiche', $langs->trans('TimeshettUserTimes'));

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
		
		list($TligneTimesheet,$THidden) = $timesheet->loadLines($PDOdb,$TJours,$doliform,$form2,$mode, true);
		
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
		
		print $TBS->render('tpl/fiche_saisie_usertimes.tpl.php'
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
		echo $html->select_company($timesheet->fk_societe,'fk_societe','',1,0,1);

		?>
		<script type="text/javascript">
			
			$('#fk_societe').change(function() {
				
				_select_other_project();
				
			});
			
			function _select_other_project() {
				
				$('#timesheet-project-list').load('<?php echo $_SERVER['PHP_SELF'] ?>?action=new&fk_societe='+$('#fk_societe').val()+' #timesheet-project-list');
				
			}
			
		</script>
		
		
		<?

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
