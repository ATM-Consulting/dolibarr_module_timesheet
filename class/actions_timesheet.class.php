<?php
class ActionsTimesheet
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */
    function doActions($parameters, &$object, &$action, $hookmanager) {
    	global $langs,$user;
		
		if (in_array('invoicecard',explode(':',$parameters['context']))) 
        {
        	if($action==='addtimesheet' && $user->rights->timesheet->user->approve) {
				$fk_timesheet = (int)GETPOST('fk_timesheet');

				if($fk_timesheet>0){
	
					define('INC_FROM_DOLIBARR', true);
					dol_include_once('/timesheet/config.php');
					
					$PDOdb = new TPDOdb;
	
					$timesheet = new TTimesheet;
					$timesheet->load($PDOdb,$fk_timesheet);
	
					$facture = &$object;
					
					list($pu_ht,$description,$tx_tva) = $timesheet->_makeFactureLigne($PDOdb, $facture);
					
					$idline = $facture->addline($timesheet->libelleFactureLigne." : <br>".$description, $pu_ht, 1, $tx_tva,0,0,0,0,'','',0,0,0,'HT',0,1);
					
					$timesheet->fk_facture = $facture->id;
					$timesheet->fk_facture_ligne = $idline;
					
					$timesheet->status = 2;
					
					$timesheet->save($PDOdb);
					
					setEventMessage($langs->trans('TimeSheetToInvoice'));
	
				}
				
				
			}
		}
		
    }
      
    function formObjectOptions($parameters, &$object, &$action, $hookmanager) 
    {  
      	global $langs,$db;
		
		if (in_array('ordercard',explode(':',$parameters['context']))) 
        {
        	
		}
		
		return 0;
	}
     
    function formEditProductOptions($parameters, &$object, &$action, $hookmanager) 
    {
		
    	if (in_array('invoicecard',explode(':',$parameters['context'])))
        {
        	
        }
		
        return 0;
    }

	function formAddObjectLine ($parameters, &$object, &$action, $hookmanager) {
		
		global $db,$langs,$user;

		if (in_array('invoicecard',explode(':',$parameters['context']))) 
        {
        	
			if($user->rights->timesheet->user->bill) {
        	//Charger les liste des projets de type feuille de temps pas encore facturé
        	
	        	$langs->load('timesheet@timesheet');
	        	
	        	$sql = "SELECT p.rowid, p.ref, p.title,t.status,t.rowid as 'idTS'
	        			FROM ".MAIN_DB_PREFIX."projet as p
	        				LEFT JOIN ".MAIN_DB_PREFIX."timesheet as t ON (t.fk_project = p.rowid)
	        			WHERE t.status=1 AND t.rowid NOT IN (SELECT rowid
	        								  FROM ".MAIN_DB_PREFIX."timesheet
	        								  WHERE fk_facture>0)";
	
				dol_include_once('/core/class/html.form.core.php');
				$form = new Form($db);
				
				$TTimeSheet = array('0'=>$langs->trans('TimeSheetSelOne'));
				
				$resql = $db->query($sql);
				if($resql){
					while ($res = $db->fetch_object($resql)) {
						$TTimeSheet[$res->idTS] = $res->ref." - ".$res->title/*.' + '.$res->status.' '.$res->idTS*/;
					}
				}
				$select = ' '.$langs->trans('TimeSheetSelectOne').' : ';
				$select .=$form->selectarray('fk_timesheet', $TTimeSheet);
				
				?>
				<tr class="liste_titre nodrag nodrop">
					<td colspan="9">Ajouter une ligne de feuille de temps</td>
					<td></td>
				</tr>
				<tr class="pair">
					<td colspan="7"><?php echo $select; ?></td>
					<td valign="middle" align="center">
						<input id="addline_timesheet" class="button" type="button" name="addline_timesheet" value="Ajouter">
					</td>
				</tr>
				<script type="text/javascript">
					$(document).ready(function(){
						$('#fk_timesheet').change(function(){
							/*$('#select_type option[value=1]').attr('selected','selected');
							$('input[name=price_ht]').val('1');
							$('#dp_desc').text('Temps de réalisation');
							CKEDITOR.instances.dp_desc.setData('Temps de réalisation');*/
						});
						
						
						$('#addline_timesheet').click(function() {
							
							$('#addproduct input[name=action]').val('addtimesheet');
							
							$('#addproduct').submit();
							
						});
						
					});
				</script>
				
				<?php				
			}

        }

		return 0;
	}

	function printObjectLine ($parameters, &$object, &$action, $hookmanager){
		

		return 0;
	}
}