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
		
		global $db;

		if (in_array('invoicecard',explode(':',$parameters['context']))) 
        {
        	//Charger les liste des projets de type feuille de temps pas encore facturé
        	
        	$sql = "SELECT p.rowid, p.ref, p.title
        			FROM ".MAIN_DB_PREFIX."projet as p
        				INNER JOIN ".MAIN_DB_PREFIX."projet_extrafields as pe ON (pe.fk_object = p.rowid)
        			WHERE p.rowid NOT IN (SELECT fk_source 
        								  FROM ".MAIN_DB_PREFIX."element_element
        								  WHERE sourcetype = 'timesheet' AND targettype = 'facture')";
										  
			dol_include_once('/core/class/html.form.core.php');
			$form = new Form($db);
			
			$TIdProjet = array('0'=>'');
			
			$resql = $db->query($sql);
			while ($res = $db->fetch_object($resql)) {
				$TIdProjet[$res->rowid] = $res->ref." - ".$res->title;
			}
			$select = " ou Sélectionnez une feuille de temps : ";
			$select .= addslashes(str_replace("\n",'',$form->selectarray('fk_timesheet', $TIdProjet)));
			?>
			<script type="text/javascript">
				$(document).ready(function(){
					$('input[name=qty]').parent().parent().children().eq(0).children().eq(0).append("<?php echo $select; ?>");
				})
			</script>
			<?php
        }

		return 0;
	}

	function printObjectLine ($parameters, &$object, &$action, $hookmanager){
		

		return 0;
	}
}