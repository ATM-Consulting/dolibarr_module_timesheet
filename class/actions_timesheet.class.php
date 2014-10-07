<?php
class ActionsTimesheet
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */
      
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
		
		global $db,$langs;

		if (in_array('invoicecard',explode(':',$parameters['context']))) 
        {
        	//Charger les liste des projets de type feuille de temps pas encore facturé
        	
        	$langs->load('timesheet@timesheet');
        	
        	$sql = "SELECT p.rowid, p.ref, p.title
        			FROM ".MAIN_DB_PREFIX."projet as p
        				INNER JOIN ".MAIN_DB_PREFIX."projet_extrafields as pe ON (pe.fk_object = p.rowid)
        				LEFT JOIN ".MAIN_DB_PREFIX."timesheet as t ON (t.fk_project = p.rowid)
        			WHERE t.rowid NOT IN (SELECT rowid
        								  FROM ".MAIN_DB_PREFIX."timesheet
        								  WHERE fk_facture>0)";

			dol_include_once('/core/class/html.form.core.php');
			$form = new Form($db);
			
			$TIdProjet = array('0'=>$langs->trans('TimeSheetSelOne'));
			
			$resql = $db->query($sql);
			if($resql){
				while ($res = $db->fetch_object($resql)) {
					$TIdProjet[$res->rowid] = $res->ref." - ".$res->title;
				}
			}
			$select = ' '.$langs->trans('TimeSheetSelectOne').' : ';
			$select .=$form->selectarray('fk_timesheet', $TIdProjet);
			
			?>
			<script type="text/javascript">
				$(document).ready(function(){
					$('#fk_timesheet').change(function(){
						$('#select_type option[value=1]').attr('selected','selected');
						$('input[name=price_ht]').val('1');
						$('#dp_desc').text('Temps de réalisation');
						CKEDITOR.instances.dp_desc.setData('Temps de réalisation');
					});
				});
			</script>
			<tr class="liste_titre nodrag nodrop">
				<td colspan="9">Ajouter une ligne de feuille de temps</td>
				<td></td>
			</tr>
			<tr class="pair">
				<td colspan="7"><?php echo $select; ?></td>
				<td valign="middle" align="center">
					<input id="addline_timesheet" class="button" type="submit" name="addline_timesheet" value="Ajouter">
				</td>
			</tr>
			<?php
        }

		return 0;
	}

	function printObjectLine ($parameters, &$object, &$action, $hookmanager){
		

		return 0;
	}
}