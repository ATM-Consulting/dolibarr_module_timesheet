<?php
// gère le popin NDF entre autre
	require('../config.php');
	dol_include_once("/ndfp/class/ndfp.class.php");
	
	$langs->load('ndfp@ndfp');
	$langs->load('main');
	
	
	$fk_cat = GETPOST('fk_cat');
	$id = GETPOST('id', 'int');
	$ref = GETPOST('ref', 'alpha');
	
	$ndfp = new Ndfp($db);

	$previous_exp = 0;
	if ($id > 0 || !empty($ref))
	{
	    $result = $ndfp->fetch($id, $ref);
	
	    $previous_exp = ($result > 0 ? $ndfp->previous_exp : 0);
	    
	    foreach ($ndfp->lines as $line)
	    {
	    	if ($line->code == 'EX_KME')
	    	{
	    		$previous_exp = $previous_exp + $line->qty;
	    	}
	    }
		
	}
	
	
	$indexes = array();
	$rates = array();
	$kmExpId = 0;
	$coefs = array();
	$offsets = array();
	$ranges = array();
	
	$sql  = " SELECT e.rowid, e.code, e.fk_tva, t.taux";
	$sql .= " FROM ".MAIN_DB_PREFIX."c_exp e";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_tva t ON t.rowid = e.fk_tva";
	$sql .= " WHERE e.active = 1";
	$sql .= " ORDER BY e.rowid DESC";
	
	$result = $db->query($sql);
	
	
	if ($result)
	{
	    $num = $db->num_rows($result);
	
	    if ($num)
	    {
	        for ($i = 0; $i < $num; $i++)
	        {
	            $obj = $db->fetch_object($result);
	
	            $indexes[$i] = $obj->fk_tva;
	            //$rates[$i] = (1 - $obj->taux/100);
	
	            if ($obj->code == 'EX_KME'){
	                $kmExpId = $obj->rowid;
	            }
	            
	            if ($obj->code == 'EX_OTH'){
	                $othExpId = $obj->rowid;
	            }            
	        }
	    }
	    $db->free($result);
	}
	
	$sql  = " SELECT r.range, t.offset, t.coef";
	$sql .= " FROM ".MAIN_DB_PREFIX."c_exp_tax t";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_exp_tax_range r ON r.rowid = t.fk_range";
	$sql .= " WHERE r.active = 1 AND t.fk_cat = ".$fk_cat;
	$sql .= " ORDER BY r.range ASC";
	
	$result = $db->query($sql);
	
	
	if ($result)
	{
	    $num = $db->num_rows($result);
	
	    if ($num)
	    {
	        for ($i = 0; $i < $num; $i++)
	        {
	            $obj = $db->fetch_object($result);
	
	            $coefs[$i] = $obj->coef;
	            $offsets[$i] = $obj->offset;
	            $ranges[$i] = $obj->range;
	        }
	    }
	    $db->free($result);
	}
	
	$coef = 0;
	$offset = 0;
	if ($previous_exp)
	{
	       for ($i = 0; $i < sizeof($ranges); $i++){
	
	            if ($i < (sizeof($ranges)-1)){
	                if ($previous_exp > $ranges[$i] && $previous_exp < $ranges[$i+1]){
	                    $coef = $coefs[$i];
	                    $offset = $offsets[$i];
	                }
	            }
	
	            if ($i == (sizeof($ranges) - 1)){
	                if ($previous_exp > $ranges[$i]){
	                    $coef = $coefs[$i];
	                    $offset = $offsets[$i];
	                }
	            }
	       }
	       
	       
	}
	
	
	$previous_fees = $offset + $coef * $previous_exp;
	
	// Load TVA, use id instead of value
	$sql  = "SELECT DISTINCT t.taux, t.rowid, t.recuperableonly";
	$sql.= " FROM ".MAIN_DB_PREFIX."c_tva as t, ".MAIN_DB_PREFIX."c_pays as p";
	$sql.= " WHERE t.fk_pays = p.rowid";
	$sql.= " AND t.active = 1";
	$sql.= " AND p.code IN ('".$mysoc->country_code."')";
	$sql.= " ORDER BY t.taux ASC, t.recuperableonly ASC";
	
	$result = $db->query($sql);
	if ($result)
	{
	    $num = $db->num_rows($result);
	    if ($num)
	    {
	        for ($i = 0; $i < $num; $i++)
	        {
	            $obj = $db->fetch_object($result);
	
	            $rates[$i] = price2num(1 + $obj->taux/100);
	        }
	    }
	
	    $db->free($result);
	
	}
	
	$db->close();
?>
function get_ndfp(fk_user, fk_task, fk_timesheet) {
	
	$.get('<?php echo dol_buildpath('/timesheet/script/interface.php',2); ?>?get=get_ndfp&fk_user='+fk_user+'&fk_task='+fk_task+'&fk_timesheet='+fk_timesheet,function(fk_ndfp){
		pop_ndfp(fk_ndfp);
	});
	
}

function pop_ndfp(fk_ndfp){
	$("#saisie").load('<?php echo dol_buildpath('/ndfp/ndfp.php',2) ?>?id='+fk_ndfp+' table[id=tablelines]',function() {
		
		$('#saisie form[id=addexpense]').submit(function() {
			
			$.post( $(this).attr('action')
				, {
					token : $(this).find('input[name=token]').val()
					,action : 'addtimespent'
					,id : $(this).find('input[name=id]').val()
					
					,fk_exp : $(this).find('input[name=fk_exp]').val()
					,es : $(this).find('input[name=es]').val()
					,esday : $(this).find('input[name=esday]').val()
					,esmonth : $(this).find('input[name=esmonth]').val()
					,esyear : $(this).find('input[name=esyear]').val()
					,ee : $(this).find('input[name=ee]').val()
					,eeday : $(this).find('input[name=eeday]').val()
					,eemonth : $(this).find('input[name=eemonth]').val()
					,eeyear : $(this).find('input[name=eeyear]').val()
					,comment : $(this).find('input[name=comment]').val()
					,ref_ext : $(this).find('input[name=ref_ext]').val()
					,qty : $(this).find('input[name=qty]').val()
					,fk_tva : $(this).find('input[name=fk_tva]').val()
					,total_ttc : $(this).find('input[name=total_ttc]').val()
					,addline : $(this).find('input[name=addline]').val()
				}
				
			) .done(function(data) {
				/*
				 * Récupération de l'erreur de sauvegarde du temps
				 */
				jStart = data.indexOf("$.jnotify(");
				
				if(jStart>0) {
					jStart=jStart+11;
					
					jEnd = data.indexOf('"error"', jStart) - 10; 
					message = data.substr(jStart,  jEnd - jStart).replace(/\\'/g,'\'');
					$.jnotify(message, "error");
				}
				else {
					$.jnotify('<?php echo $langs->trans('ExpAdded') ?>', "ok");
				}
				
			});
			
			$("#saisie").dialog('close');

			return false;
		
		});
	})
	.dialog({
		modal:true
		,minWidth:800
		,minHeight:200
		,title:'Note de frais'
	});
}

$(document).ready(function() {

   var kmExpId = <?php echo $kmExpId; ?>;
   var othExpId = <?php echo $othExpId; ?>;
   var tvaIds = new Array (<?php echo implode(",", $indexes); ?>);

   			
	$('#qty').bind('keyup set change', null, function(){	
		
		var value = $(this).val();
		var expSelectedId = $("#fk_exp").val();

		if (expSelectedId == kmExpId)
		{
			// Update travel fees
	
			var qtyvalue = getValue("qty");

			var val = new Number(0);
			if (isNumber(qtyvalue))
			{
				val = new Number(qtyvalue);
			}
			val = val.toFixed(0);
			
			computeTTC(val);
			updateHT();
		}
   		
		return;
	});

	$('#total_ttc').bind('keyup set change', null, function(){	
		
		var expSelectedId = $("#fk_exp").val();

		if (expSelectedId != othExpId)
		{
			// Update travel fees
			updateHT();
		}
   		
		return;
	});
	
	$('#fk_exp').bind('change', null, function(){	
		
		var value = $(this).val();
		var index = $(this).prop("selectedIndex");
		
	   var selTvaId = tvaIds[index];

	
		$("#fk_tva option").each(function(){
			if ($(this).val() ==  selTvaId)
			{
				$(this).prop('selected', true);
			}
		});

		
	   if (value == kmExpId)
	   {	   
			// Disable TTC field
			$("#total_ttc").prop('disabled', true); 
		}
		else
		{
			$("#total_ttc").prop('disabled', false); 
		}

		if (value == othExpId)
		{	   
			// Enable HT field
			$("#total_ht").prop('disabled', false); 
		}
		else
		{
			$("#total_ht").prop('disabled', true); 
		}
		
		$("#qty").val('0');  
		$("#total_ht").val('0,00');
		$("#total_ttc").val('0,00');
		   		
		return;
	});	
	
	
	$('#fk_tva').bind('change', null, function(){	
		
		var value = $('#fk_exp').val();

		if (value != othExpId)
		{	   
			updateHT();
		}
		   		
		return;
	});		
});


function pick(arg, def) {
   return (typeof arg == 'undefined' ? def : arg);
}

function isNumber(n) {
  return !isNaN(parseFloat(n)) && isFinite(n);
}

function changeStateTTC(){
   var kmExpId = <?php echo $kmExpId; ?>;
   var othExpId = <?php echo $othExpId; ?>;
   var ttc = document.getElementById("total_ttc");
   var ht = document.getElementById("total_ht");
   var expList = document.getElementById("fk_exp");
   var qty = document.getElementById("qty");

   if (expList.options[expList.selectedIndex].value == othExpId){
        ht.disabled = false;
   }else{
       ht.disabled = true;
   }
   
   if (expList.options[expList.selectedIndex].value == kmExpId){
        ttc.disabled = true;
   }else{
       ttc.disabled = false;
   }

   ttc.value = 0;
   qty.value = 0;

   computeHT();
}


function updateHT(){
   var tvaRates = new Array (<?php echo implode(",", $rates); ?>);
   var tvaList = document.getElementById("fk_tva");

   var ttc = getValue("total_ttc");

   var ttcvalue = new Number(ttc);

   var selTvaRate = tvaRates[tvaList.selectedIndex];
   var htvalue = new Number(ttcvalue / selTvaRate);
   
   setValue("total_ht", htvalue);
}

function updateTTC(){
   var tvaRates = new Array (<?php echo implode(",", $rates); ?>);
   var tvaList = document.getElementById("fk_tva");

   var expList = document.getElementById("fk_exp");
   var htvalue = getValue("total_ht");

   var selTvaRate = tvaRates[tvaList.selectedIndex];

	var ttcvalue = new Number(htvalue * selTvaRate);
	setValue("total_ttc", ttcvalue);

}

function getValue(tag)
{
	var value = document.getElementById(tag).value;
	if (value == '')
	{
		value = new String("0");
	}	
	
	value = value.replace(',', '.');
	value = value.replace(' ', '');	
	return value;
}

function setValue(tag, val)
{
	var node = document.getElementById(tag);

	val = val.toFixed(2);
	//val = val.replace('.', ',');
	
	val = number_format(val, 2, ',', ' ');	
	node.value = val;	
}


function computeTTC(qtyvalue){

	var km_offset = new Number(<?php echo $previous_exp; ?>);
	var total_previous = new Number(<?php echo $previous_fees; ?>);
	var kmExpId = <?php echo $kmExpId; ?>;
	var coefs = new Array (<?php echo implode(",", $coefs); ?>);
	var offsets = new Array (<?php echo implode(",", $offsets); ?>);
	var ranges = new Array (<?php echo implode(",", $ranges); ?>);


	var kms = new Number(qtyvalue);

	kms = kms + km_offset;
	var i;
	var coef = 0;
	var offset = 0;
	var total = 0;

	for (i=0; i < ranges.length; i++){

		if (i < (ranges.length-1)){
			if (kms > ranges[i] && kms < ranges[i+1]){
				coef = coefs[i];
				offset = offsets[i];
			}
		}

		if (i == (ranges.length - 1)){
			if (kms > ranges[i]){
			coef = coefs[i];
			offset = offsets[i];
			}
		}
	}

	total =  offset + kms * coef;
	total = total - total_previous;
	
	
	setValue("total_ttc", total);
      
}

function number_format (number, decimals, dec_point, thousands_sep) {
  // http://kevin.vanzonneveld.net
  // +   original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
  // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // +     bugfix by: Michael White (http://getsprink.com)
  // +     bugfix by: Benjamin Lupton
  // +     bugfix by: Allan Jensen (http://www.winternet.no)
  // +    revised by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
  // +     bugfix by: Howard Yeend
  // +    revised by: Luke Smith (http://lucassmith.name)
  // +     bugfix by: Diogo Resende
  // +     bugfix by: Rival
  // +      input by: Kheang Hok Chin (http://www.distantia.ca/)
  // +   improved by: davook
  // +   improved by: Brett Zamir (http://brett-zamir.me)
  // +      input by: Jay Klehr
  // +   improved by: Brett Zamir (http://brett-zamir.me)
  // +      input by: Amir Habibi (http://www.residence-mixte.com/)
  // +     bugfix by: Brett Zamir (http://brett-zamir.me)
  // +   improved by: Theriault
  // +      input by: Amirouche
  // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // *     example 1: number_format(1234.56);
  // *     returns 1: '1,235'
  // *     example 2: number_format(1234.56, 2, ',', ' ');
  // *     returns 2: '1 234,56'
  // *     example 3: number_format(1234.5678, 2, '.', '');
  // *     returns 3: '1234.57'
  // *     example 4: number_format(67, 2, ',', '.');
  // *     returns 4: '67,00'
  // *     example 5: number_format(1000);
  // *     returns 5: '1,000'
  // *     example 6: number_format(67.311, 2);
  // *     returns 6: '67.31'
  // *     example 7: number_format(1000.55, 1);
  // *     returns 7: '1,000.6'
  // *     example 8: number_format(67000, 5, ',', '.');
  // *     returns 8: '67.000,00000'
  // *     example 9: number_format(0.9, 0);
  // *     returns 9: '1'
  // *    example 10: number_format('1.20', 2);
  // *    returns 10: '1.20'
  // *    example 11: number_format('1.20', 4);
  // *    returns 11: '1.2000'
  // *    example 12: number_format('1.2000', 3);
  // *    returns 12: '1.200'
  // *    example 13: number_format('1 000,50', 2, '.', ' ');
  // *    returns 13: '100 050.00'
  // Strip all characters but numerical ones.
  number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
  var n = !isFinite(+number) ? 0 : +number,
    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
    dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
    s = '',
    toFixedFix = function (n, prec) {
      var k = Math.pow(10, prec);
      return '' + Math.round(n * k) / k;
    };
  // Fix for IE parseFloat(0.55).toFixed(0) = 0;
  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
  if (s[0].length > 3) {
    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
  }
  if ((s[1] || '').length < prec) {
    s[1] = s[1] || '';
    s[1] += new Array(prec - s[1].length + 1).join('0');
  }
  return s.join(dec);
}
