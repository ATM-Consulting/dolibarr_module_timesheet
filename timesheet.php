<?php

require('config.php');

if(!$user->rights->timesheet->user->read) accessforbidden();

$hookmanager = new HookManager($db);
$hookmanager->initHooks('timesheetcard');

_action();

// Protection if external user
if ($user->societe_id > 0)
{
	accessforbidden();
}

function _action() {
	global $user,$langs,$conf,$mysoc,$hookmanager;

	$PDOdb=new TPDOdb;
	$timesheet = new TTimesheet;
	
	/*******************************************************************
	* ACTIONS
	*
	* Put here all code to do according to value of "action" parameter
	********************************************************************/
	
	$action=GETPOST('action');
	$idTimesheet = GETPOST('id', 'int');

	if(! empty($idTimesheet)) { // load() remonté et factorisé pour être effectué avant le hook
		$timesheet->load($PDOdb, $idTimesheet);
	}

	$parameters = array();
	$reshook = $hookmanager->executeHooks('doActions', $parameters, $timesheet, $action);
	if($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

	if(empty($reshook)) {
	
		if($action=='print') {
			
			$formATM=new TFormCore;
			$doliform = new Form($db);
			$TJours = $timesheet->loadTJours(); 
			
			//transformation de $TJours pour jolie affichage
			foreach ($TJours as $key => $value) {
				$TKey = explode('-', $key);
				$TJoursVisu[$TKey[2].'/'.$TKey[1]] = $value;
			}
			
			
			list($TligneTimesheet,$THidden) = $timesheet->loadLines($PDOdb,$TJours,$doliform,$formATM,'print');
			$hour_per_day = !empty($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY) ? $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY : 8;
			$nb_second_per_day = $hour_per_day * 3600;
			
			foreach($TligneTimesheet as $cle => $val){
				//$TligneTimesheet[$cle]['total_jours'] = round(convertSecondToTime($val['total_jours'],'allhourmin',$nb_second_per_day)/24);
				$TligneTimesheet[$cle]['total'] = convertSecondToTime($val['total'],'all', $nb_second_per_day);
			}
			
	
			$TBS=new TTemplateTBS;
			$TBS->render('./tpl/approve.ods'
				,array(
					'ligneTimesheet'=>$TligneTimesheet,
					'joursVisu'=>$TJoursVisu,
				)
				,array(
					'timesheet'=>array(
						'socname'=>$timesheet->societe->name
						,'mysocname'=>$mysoc->name
						,'date_dates'=>utf8_decode( $langs->transnoentitiesnoconv('TimeSheetDates', dol_print_date($timesheet->date_deb), dol_print_date($timesheet->date_fin) ) )
						,'project'=>utf8_decode( $langs->transnoentitiesnoconv('TimeSheetproject', $timesheet->project->title))
					)
					,'langs'=>getLangTranslate()
				)
				,array()
			);
			
			
			exit;
		}
	
		
		if($action) {
			switch($action) {
				
				case 'new':
				case 'add':
					$timesheet->set_values($_REQUEST);
					_fiche($timesheet,'new');
					break;
	
				case 'approve':
					$timesheet->status=1;
					$timesheet->save($PDOdb);
					
					_fiche($timesheet);
	
					break;
				case 'edit'	:
				case 'edittime'	:
					_fiche($timesheet, $action);
					break;
	
				case 'save':
					$timesheet->set_values($_REQUEST);
					$timesheet->save($PDOdb);
					
					setEventMessage('TimeSheetSaved');
					
					_fiche($timesheet);
					break;
				
				case 'savetime':
					//pre($_REQUEST,true);exit;
					$timesheet->set_values($_REQUEST);
					$timesheet->savetimevalues($PDOdb,$_REQUEST);
					$timesheet->save($PDOdb);
					
					$timesheet->load($PDOdb,$timesheet->rowid);
					setEventMessage('TimeSheetSaved');
					_fiche($timesheet,'edittime');
					break;
					
				case 'facturer':
					//$timesheet->status = 2;
					$timesheet->save($PDOdb);
					$timesheet->createFacture($PDOdb);
					_fiche($timesheet);
					break;
	
				case 'delete':
					$timesheet->delete($PDOdb);
					
					_liste();
					
					break;
				case 'deleteligne':
					
					$timesheet->deleteAllTimeForTaskUser($PDOdb, GETPOST('fk_task'), GETPOST('fk_user'));
				
					setEventMessage($langs->trans('LineDeleted'));
				
					$timesheet->load($PDOdb, $idTimesheet);
					
				
					_fiche($timesheet, GETPOST('mode'));
					break;	
			
			}
			
		}
		elseif(isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
			_fiche($timesheet, 'view');
		}
		else{
			_liste();
		}
	} else {
		if(! empty($idTimesheet)) {
			_fiche($timesheet);
		} else {
			_liste();
		}
	}
	
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

	llxHeader('',$langs->trans('Timesheet'),'','',0,0,array('/timesheet/js/timesheet.js.php'));

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
			'fk_societe'=>'<a href="'.dol_buildpath('/societe/soc.php?socid=@fk_societe@',1).'">'.img_picto('','object_company.png','',0).' @nom@</a>'
			,'fk_project'=>'<a href="'.dol_buildpath('/projet/'.((float) DOL_VERSION >= 3.7 ? 'card.php' : 'fiche.php').'?id=@fk_project@',1).'">'.img_picto('','object_project.png','',0).' @ref@</a>'
			,'rowid'=>'<a href="'.dol_buildpath('/timesheet/timesheet.php?id=@rowid@',1).'">'.img_picto('','object_calendar.png','',0).' @rowid@</a>'
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
			'date_deb'=>$langs->trans('DateStart')
			,'date_fin'=>$langs->trans('DateEnd')
			,'fk_project'=>$langs->trans('Project')
			,'fk_societe'=>$langs->trans('Thirdparty')
			,'rowid'=>'Id.'
			,'status'=>$langs->trans('Status')
		)
	));

	if($user->rights->timesheet->user->edit){
		echo '<div class="tabsAction">';
		echo '<a class="butAction" href="?action=new">'.$langs->trans('CreateTimesheet').'</a>';
		echo '</div>';
	}
	
	$TPDOdb->close();


	llxFooter();
}

function _selectProjectTasksByMoi(&$PDOdb,&$timehseet)
{
	
	$sql = "SELECT rowid,ref,label FROM ".MAIN_DB_PREFIX."projet_task WHERE fk_projet = ".$timehseet->fk_project;
	$PDOdb->Execute($sql);
	
	$chaine = '<select class="flat" name="serviceid_0">
				<option value="0"></option>';
	
	while($PDOdb->Get_line()){
		$chaine .= '<option value="'.$PDOdb->Get_field('rowid').'">'.$PDOdb->Get_field('ref').' - '.$PDOdb->Get_field('label').'</option>';
	}
	
	$chaine .= '</select>';
   return $chaine;
   
}


function _fiche(&$timesheet, $mode='view') {
	
	global $langs,$db,$conf,$user,$hookmanager;
	
	$PDOdb = new TPDOdb;

	llxHeader('',$langs->trans('Timesheet'),'','',0,0,array('/timesheet/js/timesheet.js.php'));
	
	print dol_get_fiche_head(timesheetPrepareHead( $timesheet, 'timesheet') , $langs->trans('Card'), $langs->trans('FicheTimesheet'));

	$form=new TFormCore($_SERVER['PHP_SELF'],'form','POST');
	$doliform = new Form($db);

	$form->hidden('mode', $mode);
	
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
	
	$TBS->TBS->protect=false;
	$TBS->TBS->noerr=true;
	
	if(!$conf->global->TIMESHEET_USE_SERVICES){
		$freemode = true;
	}
	
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
			,'langs'=>$langs
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
		
		list($TligneTimesheet,$THidden) = $timesheet->loadLines($PDOdb,$TJours,$doliform,$form2,$mode,$freemode);
		
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
	
	if($freemode){
		?>
		<script type="text/javascript">
			$(document).ready(function(){
				$('tr[id=0] select[name^=serviceid_], tr[id=0] select[name^=userid_]').change(function(){
					
					tache = $('tr[id=0] select[name^=serviceid_]');
					user = $('tr[id=0] select[name^=userid_]');
					
					$(tache).attr('name','serviceid_'+$(tache).find(':selected').val());
					$(user).attr('name','userid_'+$(user).find(':selected').val());
					
					
					$('tr[id=0] input[id^=temps_]').each(function(i) {
						name = $(this).attr('name');
						temp = name.substr(-12);
						name = 'temps['+$(tache).find(':selected').val()+'_'+$(user).find(':selected').val()+']'+temp;
						$(this).attr('name',name);
					});
				});

			});
		</script>
		<?php
	}
	
	//pre($TligneTimesheet,true);exit;
	
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
					,'services'=>(!$freemode) ? $doliform->select_produits_list('','serviceid_0','1') : _selectProjectTasksByMoi($PDOdb,$timesheet)
					,'consultants'=>(($user->rights->timesheet->all->read) ? $doliform->select_dolusers($user,'userid_0',1) : $form2->hidden('userid_0', $user->id).$user->getNomUrl(1))
					,'commentaireNewLine'=>$form2->texte('', 'lineLabel_0', '', 30,255)
				)
				,'view'=>array(
					'mode'=>$mode
					,'nbChamps'=>count($asset->TField)
					,'head'=>dol_get_fiche_head(timesheetPrepareHead($asset)  , 'field', $langs->trans('AssetType'))
					,'onglet'=>dol_get_fiche_head(array()  , '', $langs->trans('AssetType'))
					,'righttoedit'=>($user->rights->timesheet->user->add && $timesheet->status<2)
					,'TimesheetYouCantIsEmpty'=>addslashes( $langs->transnoentitiesnoconv('TimesheetYouCantIsEmpty') )
					,'freemode'=>$freemode
				)
				,'langs'=>$langs
			)
			
		);
	}
	 
	echo $form2->end_form();

	if($mode == 'edittime' && ! empty($conf->absence->enabled) && empty($conf->global->TIMESHEET_RH_NO_CHECK)) {
		?>
	<script type="text/javascript">
		$(document).ready(function() {
			$('#userid_0').change(function() {
				var userid = parseInt($(this).val());
				var timesheetid = parseInt($('input#id').val());

				if(timesheetid > 0 && userid > 0) {
					$.ajax({
						method: 'GET'
						, url: '<?php echo dol_buildpath('/timesheet/script/interface.php', 1); ?>'
						, data: {
							get: 'get_emploi_du_temps'
							, fk_timesheet: timesheetid
							, fk_user: userid
						}
						, dataType: 'json'
						, success: function(data) {
							for(var i = 0; i < data.length; i++) {
								var elem = $('input#temps_0__' + data[i].date + '_');
								if(elem.length > 0) {
									elem.val(data[i].time);
								}
							}
						}
					});
				}
			})
			.trigger('change'); // Permet de remplir à l'init de la page avec l'utilisateur pré-sélectionné
		});
	</script>

<?php
	}

	$parameters = array();
	$hookmanager->executeHooks('afterCard', $parameters, $timesheet, $mode); // pas 'addMoreActionsButtons' car boutons ajoutés plus haut via TBS


	llxFooter();
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
	global $db, $langs;

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
		
		
		<?php

		return ob_get_clean();

	}
	else {
		if($timesheet->fk_societe > 0) {
			require_once(DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php');

			$soc = new Societe($db);
			$soc->fetch($timesheet->fk_societe);

			return '<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$timesheet->fk_societe.'" style="font-weight:bold;">'.img_picto('','object_company.png', '', 0).' '.$soc->nom.'</a>';
		} else {
			return $langs->trans('NotDefined');
		}
	}
}

?>
