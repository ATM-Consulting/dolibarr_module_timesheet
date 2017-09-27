<?php
	
	require('../config.php');

	$PDOdb = new TPDOdb;

	$get = GETPOST('get');
	$put = GETPOST('put');

	switch($get) {
		case 'get_ndfp':
			print _get_ndfp($PDOdb,$_REQUEST['fk_user'],$_REQUEST['fk_task'],$_REQUEST['fk_timesheet']);
			break;
		case 'get_line_ndfp':
			print _get_line_ndfp($PDOdb,$_REQUEST['fk_ndfp']);
			break;
		case 'get_emploi_du_temps':
			print __out(_get_emploi_du_temps($PDOdb, $_REQUEST['fk_timesheet'], $_REQUEST['fk_user'], $_REQUEST['date_deb'], $_REQUEST['date_fin']), 'json');
			break;
	}
	
	switch ($put) {
		case 'value':
			
			break;

	}
	
	function _get_ndfp(&$PDOdb,$fk_user,$fk_task,$fk_timesheet){
		global $db;

		dol_include_once('/ndfp/class/ndfp.class.php');
		dol_include_once('/projet/class/project.class.php');
		dol_include_once('/projet/class/task.class.php');
		dol_include_once('/core/class/html.form.class.php');
		
		if($fk_timesheet > 0){ //on est sur une vrai feuille de temps
			$timesheet = new TTimesheet;
			$timesheet->load($PDOdb,$fk_timesheet);
			
			$fk_soc = $timesheet->societe->id;
		}
		else{ //On est sur de la saisie à la volé hebdomadaire donc pas dez timesheet

			$task = new Task($db);
			$task->fetch($fk_task);
			
			$projet = new Project($db);
			$projet->fetch($task->fk_project);
			$fk_soc = (int)$projet->socid;
		}
		
		$sql = "SELECT n.rowid as 'rowid'
					FROM ".MAIN_DB_PREFIX."ndfp as n
					WHERE n.fk_user = ".$fk_user." AND n.fk_soc = ".$fk_soc." AND n.statut = 0";
		
		$PDOdb->Execute($sql);
		
		if($PDOdb->Get_line()){
			$fk_ndfp = $PDOdb->Get_field('rowid');
		}
		else{
			if($fk_timesheet > 0){
				$fk_ndfp = _createNdfp($PDOdb,$fk_soc,$fk_user,$timesheet->date_deb,$timesheet->date_fin);
			}
			else {
				$fk_ndfp = _createNdfp($PDOdb,$fk_soc,$fk_user);
			}
		}
		
		$ndfp=new Ndfp($db);
		$ndfp->fetch($fk_ndfp);
				
		return json_encode(array(
				'id'=>$ndfp->id
				,'fk_cat'=>$ndfp->fk_cat
		));
		
		
	}
	
	function _get_line_ndfp(&$PDOdb,$fk_ndfp){
		global $db, $conf, $user, $langs;
		
		dol_include_once('/ndfp/class/ndfp.class.php');
		
		$ndfp = new Ndfp($db);
		$ndfp->fetch($fk_ndfp);
		
		$numLines = sizeof($ndfp->lines);
		$action = 'view';
		
		//En tête du tableau
		$chaine = '
		<table id="tablelines" class="noborder" width="100%">
		    <tr class="liste_titre nodrag nodrop">
		        <td>'.$langs->trans('Type').'</td>
		        <td align="right" width="90">'.$langs->trans('DateStart').'</td>
		        <td align="right" width="90">'.$langs->trans('DateEnd').'</td>
				<td align="right" width="70">'.$langs->trans('ExternalReference').'</td>
				<td align="right" width="50">'.$langs->trans('Qty').'</td>
				<td align="right" width="70">'.$langs->trans('TVA').'</td>
				<td align="right" width="70">'.$langs->trans('Total_HT').' ('.$langs->trans('Currency'.$ndfp->cur_iso).'</td>
				<td align="right" width="70">'.$langs->trans('Total_TTC').' ('.$langs->trans('Currency'.$ndfp->cur_iso).'</td>
				<td width="50">&nbsp;</td>
			</tr>';

		//Lignes du tableau
		for($i = 0; $i < $numLines; $i++){
		    $line = $ndfp->lines[$i];

		    if ($action == 'editline' && $lineid == $line->rowid){
				
				$chaine .='
			    <form action="'.$_SERVER["PHP_SELF"].'?id='.$ndfp->id.'" method="POST">
			    <input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />
			    <input type="hidden" name="action" value="updateline" />
			    <input type="hidden" name="id" value="'.$ndfp->id.'" />
			    <input type="hidden" name="lineid" value="'.$line->rowid.'" />';

		    }
				$chaine .='
				<tr class="'.($i%2==0 ? 'impair' : 'pair').'">';
				
				if ($action == 'editline' && $lineid == $line->rowid){
					
					$chaine .='
					<td colspan="3">           
		                <select id="fk_exp" name="fk_exp">';
		                   for ($k=0; $k<sizeof($predefined_expenses); $k++){
		                   	
		                    	if ($predefined_expenses[$k]->rowid == $line->fk_exp && $predefined_expenses[$k]->code == 'EX_OTH')
		                    	{
		                    		$otherExpense = true;
		                    	}
								
								$chaine .='
		                        <option value="'.$predefined_expenses[$k]->rowid.'" '.($predefined_expenses[$k]->rowid==$line->fk_exp ? 'selected="selected"' : '').' >
		                            '.$langs->trans($predefined_expenses[$k]->label).'
		                        </option>';
		                    }
						$chaine .='
		                </select>
						'.$langs->trans("DateStart").' : '.$html->select_date($ndfp->dates, 'es', 0, 0, 0,"addexpense").'&nbsp;
						'.$langs->trans("DateEnd").' : '.$html->select_date($ndfp->datee, 'ee', 0, 0, 0,"addexpense").'
						<br />			
						'.$langs->trans("Comment").' : <input type="text" size="65" id="comment" name="comment" value="'.$line->comment.'" />
					</td>';
				}
				else
				{
					$chaine .=' 
					<td>	
						'.$langs->trans($line->label).' <em>'.($line->comment ? ' - '. $line->comment : '').'</em>
					</td>
					<td width="90" align="right" nowrap="nowrap">
						'.dol_print_date($line->dated, '%d/%m/%Y').'
					</td>
					<td width="90" align="right" nowrap="nowrap">
						'.dol_print_date($line->datef, '%d/%m/%Y').'
					</td>';			
				}
				
				$chaine .='
			        <td width="70" align="right" nowrap="nowrap">';

			            if ($action == 'editline' && $lineid == $line->rowid){
			                $chaine .='<input type="text" size="10" id="ref_ext" name="ref_ext" value="'.$line->ref_ext.'" />';
			            }
			            else{
			                $chaine .= ($line->ref_ext ? $line->ref_ext : '');
			            }
					
				$chaine .='	
			        </td>		
			        <td align="right" nowrap="nowrap">';
			           if ($action == 'editline' && $lineid == $line->rowid){
			               $chaine .=' <input type="text" size="8" id="qty" name="qty" value="<?php echo $line->qty; ?>" />';
			           }else{ $chaine .=$line->qty; }
			        $chaine .='	
			        </td>
			        <td align="right" nowrap="nowrap">';
			           if ($action == 'editline' && $lineid == $line->rowid){
			                 $chaine .= $ndfpHtml->select_tva($line->fk_tva, 'fk_tva');
			           }else{ $chaine .= $line->taux.'%'; }
			        $chaine .='	
			        </td>
			        <td align="right" nowrap="nowrap">';
			            if ($action == 'editline' && $lineid == $line->rowid){
			               $chaine .=' <input type="text" size="8" id="total_ht" name="total_ht" value="'.price($line->total_ht).'" '.($otherExpense ? '' : 'disabled="disabled"').'/>';
			           	}else{ $chaine .= price($line->total_ht); }
			        $chaine .='	
			        </td>
			        <td align="right" nowrap="nowrap">';
			            if ($action == 'editline' && $lineid == $line->rowid){
			                 $chaine .='<input type="text" size="8" id="total_ttc" name="total_ttc" value="'.price($line->total_ttc).'" />';
			            }else{  $chaine .= price($line->total_ttc); }
					$chaine .='
			        </td>';

			        if ($action == 'editline' && $lineid == $line->rowid){
			        	$chaine .='
				        <td align="right">
				            <input type="submit" class="button" name="save" value="'.$langs->trans("Save").'" />&nbsp;<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'" />
				        </td>';
			        }
			        else{
			            if ($ndfp->statut == 0) {
			            	$chaine .='
				            <td align="right">
				                <a href="'.$_SERVER["PHP_SELF"].'?id='.$ndfp->id.'&amp;action=editline&amp;lineid='.$line->rowid.'">'.
				                    img_edit().'
				                </a>
				                <a href="'.$_SERVER["PHP_SELF"].'?id='.$ndfp->id.'&amp;action=ask_deleteline&amp;lineid='.$line->rowid.'">'.
				                    img_delete().'
				                </a>
				            </td>';
			        }
			        else{
			        	$chaine .='
			        	<td>&nbsp;</td>';
			        } 
				}
			$chaine .='
		    </tr>';

			if ($action == 'editline' && $lineid == $line->rowid){
				$chaine .=' </form>';
			}
		}
		$chaine .='</table>';
		
		return $chaine;
	}

	function _createNdfp(&$PDOdb,$fk_soc,$fk_user,$date_deb="",$date_fin=""){
		global $db,$conf,$user;
		
		if(empty($date_deb)) $date_deb = dol_now();
		if(empty($date_fin)) $date_fin = dol_now();
		
		$userNdfp = new User($db);
		$userNdfp->fetch($fk_user);

		$ndfp = new Ndfp($db);
		$html = new Form($db);

		$fk_user = $fk_user;
        $fk_soc = $fk_soc;
        $fk_cat = 6; //TODO Mis en dur pour le moment = 5 CV, prévoir un paramétrage fiche user
        $fk_project = 0; //0 puisqu'une note de frais peux être lié à plusieurs projets d'un client

		$previous_exp = 0; //Aucune idée du pk = 0
        $model = 'calamar'; //Calamar par défaut
        $currency = $conf->currency;
        $description = '';
        $note_public = '';
        $note = '';

        $result = $ndfp->check_user_rights($user, 'create');
        if ($result < 0)
        {
            return -1;
        }

        $start_date = $date_deb;
        $end_date = $date_fin;

        $ndfp->ref            = '(PROV)';
        $ndfp->cur_iso        = $currency;

        $ndfp->entity         = $conf->entity;
        $ndfp->dates          = $start_date;
        $ndfp->datee          = $end_date;

        $ndfp->fk_user        = $fk_user;
        $ndfp->fk_soc         = $fk_soc;
        $ndfp->fk_project     = $fk_project;

        $ndfp->fk_cat        	= $fk_cat;

        $ndfp->total_tva      	= 0;
        $ndfp->total_ht     	= 0;
        $ndfp->total_ttc      	= 0;
		$ndfp->previous_exp 	= $previous_exp;

        $ndfp->description      = $description;
        $ndfp->comment_user	    = $note_public;
        $ndfp->comment_admin    = $note;

        $ndfp->model_pdf        = $model;
        $ndfp->statut      		= 0;

		$idNdfp = $ndfp->create($user);
		
		return $idNdfp;
	}


	function _get_emploi_du_temps(&$PDOdb, $fk_timesheet, $fk_user, $date_deb = '', $date_fin = '') {

		$timesheet = new TTimesheet;
		if(! empty($fk_timesheet)) {
			$timesheet->load($PDOdb, $fk_timesheet);
		} else {
			$timesheet->set_date('date_deb', $date_deb);
			$timesheet->set_date('date_fin', $date_fin);
		}

		$TEDTforJSON = array();
		$TEDT = getEmploiDuTemps($PDOdb, $timesheet, $fk_user);

		if(! empty($TEDT)) {
			foreach($TEDT as $date => $time) {
				$TEDTforJSON[] = array( // $TEDT est transformé en objet JSON et pas en tableau sans ce refactoring
					'date' => $date
					, 'time' => dol_print_date($time, '%H:%M', 'gmt')
				);
			}
		}

		return $TEDTforJSON;
	}

