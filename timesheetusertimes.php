<?php

require('config.php');

if(!$user->rights->timesheet->user->read) accessforbidden();

$hookmanager = new HookManager($db);
$hookmanager->initHooks('timesheetusertimescard');


// Protection if external user
if ($user->societe_id > 0)
{
	accessforbidden();
}

_action();

function _action() {
	global $user,$langs,$conf,$mysoc,$hookmanager;

	$PDOdb=new TPDOdb;
	$timesheet = new TTimesheet;

	$date_deb=GETPOST('date_deb');
	$date_fin=GETPOST('date_fin');
	$userid=(int)GETPOST('userid');

	if(empty($userid)) {
		$userid = -1;
	}

	if(empty($user->rights->timesheet->all->read)) $userid = $user->id;


	if(! empty($date_deb)) $date_deb = date('Y-m-d 00:00:00', dol_stringtotime($date_deb));
	if(! empty($date_fin)) $date_fin = date('Y-m-d 00:00:00', dol_stringtotime($date_fin));
	
	$date_deb = (empty($date_deb)) ? date('Y-m-d 00:00:00', strtotime('Monday this week')) : $date_deb ;
	$date_fin = (empty($date_fin)) ? date('Y-m-d 00:00:00', strtotime('Sunday this week')) : $date_fin ;
	
	$timesheet->set_date('date_deb', $date_deb);
	$timesheet->set_date('date_fin', $date_fin);
	
	$timesheet->loadProjectTask($PDOdb, $userid,$date_deb,$date_fin);
	
	/*******************************************************************
	* ACTIONS
	*
	* Put here all code to do according to value of "action" parameter
	********************************************************************/
	
	$action=GETPOST('action');

	$parameters = array();
	$reshook = $hookmanager->executeHooks('doActions', $parameters, $timesheet, $action);
	if($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

	if(empty($reshook)) {

		if($action) {
			switch($action) {
				
				case 'view' :
				case 'changedate' :

					_fiche($timesheet,'changedate',$date_deb,$date_fin,$userid);
					break;
				
				case 'edit'	:
				case 'edittime'	:

					_fiche($timesheet,'edittime',$date_deb,$date_fin,$userid);
					break;

				case 'savetime':

					$timesheet->savetimevalues($PDOdb,$_REQUEST);
					setEventMessage('TimeSheetSaved');

					$timesheet->loadProjectTask($PDOdb, $userid,$date_deb,$date_fin);

					_fiche($timesheet,'edittime',$date_deb,$date_fin,$userid);
					break;

				
				case 'deleteligne':
					$timesheet->deleteAllTimeForTaskUser($PDOdb, GETPOST('fk_task'), GETPOST('fk_user'));
				
					setEventMessage($langs->trans('LineDeleted'));
				
					$timesheet->load($PDOdb, $idTimesheet);
				
					_fiche($timesheet,GETPOST('mode'),$date_deb,$date_fin,$userid);
					break;
				
			}
			
		}
		else{
			
			_fiche($timesheet, 'changedate',$date_deb,$date_fin, $userid);
			
		}
	} else {
		_fiche($timesheet, 'changedate',$date_deb, $date_fin, $userid);
	}
	
}


function _fiche(TTimesheet &$timesheet, $mode, $date_deb, $date_fin, $userid_selected = -1) {

	global $langs,$db,$conf,$user,$hookmanager;
	$PDOdb = new TPDOdb;

	$date = date_create(date($date_deb));
	$date_deb = date_format($date, 'd/m/Y');

	$date = date_create(date($date_fin));
	$date_fin = date_format($date, 'd/m/Y');

	
	$user_selected = new User($db);
	if(! empty($userid_selected) && $userid_selected > 0) {
		$user_selected->fetch($userid_selected);
	}


	llxHeader('',$langs->trans('TimeshettUserTimes'),'','',0,0,array('/timesheet/js/timesheet.js.php'));

	print dol_get_fiche_head(timesheetPrepareHead( $timesheet, 'timesheet') , 'fiche', $langs->trans('TimeshettUserTimes'));


	// Filtres

	$doliform = new Form($db);
	$formCoreFilter = new TFormCore();

	$linkback = $_SERVER['PHP_SELF'].'?date_deb='.$date_deb.'&date_fin='.$date_fin.(empty($userid_selected) || $userid_selected < 0  ? '' : '&userid='.$userid_selected);

	if($mode == 'edittime') {
		$formCoreFilter->Set_typeaff('view');
	} else {
		$formCoreFilter->Set_typeaff('edit');
		echo $formCoreFilter->begin_form($linkback, 'usertimefilter', 'GET');
	}

	echo $langs->trans('Period').' : '.$langs->trans('Of').' '.$formCoreFilter->calendrier('', "date_deb", $date_deb).' ';
	echo $langs->trans('to').' '.$formCoreFilter->calendrier('', 'date_fin', $date_fin).' - '.$langs->trans('UserFilter'). ' : ';

	if($mode == 'edittime') {
		echo (empty($userid_selected) || $userid_selected == -1 ? $langs->trans('None') : $user_selected->getNomUrl(1));
	} else {
		echo (empty($user->rights->timesheet->all->read) ? $user->firstname.' '.$user->lastname : $doliform->select_dolusers($userid_selected, 'userid', 1));
		echo ' '.$formCoreFilter->btsubmit($langs->trans('ToFilter'), 'save');
		echo $formCoreFilter->end_form();
	}

	echo '<br><br>';

	// Feuille de temps

	$formTimes = new TFormCore;

	if($mode == "edittime"){
		$formTimes->Set_typeaff('edit');
		echo $formTimes->begin_form($linkback, 'formtime', 'POST');

		echo $formTimes->hidden('mode', $mode);
		echo $formTimes->hidden('date_deb', $date_deb);
		echo $formTimes->hidden('date_deb', $date_deb);
		echo $formTimes->hidden('action', 'savetime');
		echo $formTimes->hidden('entity', $conf->entity);
	} else {
		$formTimes->Set_typeaff('view');
	}
	
	
	$TBS=new TTemplateTBS();
	$TBS->TBS->protect=false;
	$TBS->TBS->noerr=true;
	
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

	
	//Charger les lignes existante dans le timeSheet

	list($TligneTimesheet,$THidden) = $timesheet->loadLines($PDOdb,$TJours,$doliform,$formTimes,$mode, true);

	$hour_per_day = !empty($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY) ? $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY : 8;
	$nb_second_per_day = $hour_per_day * 3600 * 3600;
	
	foreach($TligneTimesheet as $cle => $val){
		//$TligneTimesheet[$cle]['total_jours'] = round(convertSecondToTime($val['total_jours'],'allhourmin',$nb_second_per_day)/24);
		$TligneTimesheet[$cle]['total'] = convertSecondToTime($val['total'],'allhourmin', $nb_second_per_day);
	}

	$TBS=new TTemplateTBS();


	foreach($TJours as $date=>$jour){
		$TFormJours['temps'.$date] = $formTimes->timepicker('', 'temps[0]['.$date.']', '',5);
	}


	$freemode = empty($conf->global->TIMESHEET_USE_SERVICES);

	if($freemode){
		?>
		<script type="text/javascript">
			$(document).ready(function(){
				$('tr#0').on('change', 'select[name^=serviceid_], select[name^=userid_]', function(){

					tache = $('tr#0 select[name^=serviceid_]');
					user = $('tr#0 select[name^=userid_]');

					$(tache).attr('name','serviceid_'+$(tache).find(':selected').val());
					$(user).attr('name','userid_'+$(user).find(':selected').val());

					$('tr#0 input[id^=temps_]').each(function(i) {
						name = $(this).attr('name');
						temp = name.substr(-12);
						name = 'temps['+$(tache).find(':selected').val()+'_'+$(user).find(':selected').val()+']'+temp;
						$(this).attr('name',name);
					});
				});

				$('#projectid_0').change(function() {
					var projectid = parseInt($(this).val());
					if(projectid > 0) {
						$.ajax({
							method: 'GET'
							, url: '<?php echo dol_buildpath('/timesheet/script/interface.php', 1); ?>'
							, data: {
								get: 'get_project_tasks'
								, projectid: projectid
							}
							, dataType: 'json'
							, success: function(data) {
								$('tr#0 td#project_td0').html(data);
							}
						});
					}
				});
			});
		</script>
		<?php
	}

	$userList = (($user->rights->timesheet->all->read) ? $doliform->select_dolusers($user,'userid_0') : $formTimes->hidden('userid_0', $user).$user->getNomUrl(1));
	if($mode == 'edittime' && ! empty($userid_selected) && $userid_selected > 0) {
		$userList = $formTimes->hidden('userid_0', $userid_selected).$user_selected->getNomUrl(1);
	}

	$formProjets = new FormProjets($db);
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
				'projets' => $formProjets->select_projects(-1, '', 'projectid_0', 16, 0, 1, 2, 0, 0, 0, '', 1)
				,'services'=>$freemode ? $formTimes->combo_sexy('', 'serviceid_0', array(str_repeat('&nbsp;', 42)), 0) : $doliform->select_produits_list('','serviceid_0','1')
				,'consultants' => $userList
				,'nbLines' => count($TligneTimesheet)
			)
			,'view'=>array(
				'mode'=>$mode
				,'righttoedit'=>($user->rights->timesheet->user->add)
				,'TimesheetYouCantIsEmpty'=>addslashes( $langs->transnoentitiesnoconv('TimesheetYouCantIsEmpty') )
				,'freemode'=>$freemode
				,'linkback'=>$linkback
				,'colspan' => count($TJours) + 5
				,'messageNothing' => $langs->trans('TimesheetNoTimeInThisTimesheet')
			)
			,'langs'=>$langs
		)
	);

	if($mode == 'edittime') {
		$formTimes->end();
	}

	if($mode == 'edittime' && ! empty($conf->absence->enabled) && empty($conf->global->TIMESHEET_RH_NO_CHECK)) {
		?>
	<script type="text/javascript">
		$(document).ready(function() {
			$('#userid_0').change(function() {
				var date_deb = '<?php echo $timesheet->get_date('date_deb', 'd/m/Y'); ?>';
				var date_fin = '<?php echo $timesheet->get_date('date_fin', 'd/m/Y'); ?>';
				var userid = parseInt($(this).val());

				if(userid > 0) {
					$.ajax({
						method: 'GET'
						, url: '<?php echo dol_buildpath('/timesheet/script/interface.php', 1); ?>'
						, data: {
							get: 'get_emploi_du_temps'
							, fk_user: userid
							, date_deb: date_deb
							, date_fin: date_fin
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


