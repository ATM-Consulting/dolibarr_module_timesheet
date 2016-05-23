<?php
	require('../config.php');
?>

function get_ndfp(fk_user, fk_task, fk_timesheet, date) {

	$.get('<?php echo dol_buildpath('/timesheet/script/interface.php',2); ?>?get=get_ndfp&fk_user='+fk_user+'&fk_task='+fk_task+'&fk_timesheet='+fk_timesheet,function(ndfp){
		ndfp=$.parseJSON(ndfp);
		console.log(ndfp);
		
		if(ndfp.id==null) alert('<?php echo addslashes($langs->transnoentities('CantLoadNDFcheckrights')) ?>'); 
		else {
			pop_ndfp(ndfp, date);
			$('#saisie').show().dialog({
				modal:true
				,minWidth:1250
				,minHeight:200
				,title:'Note de frais'
			});
			
			
		}
	});
	
}

function pop_ndfp(ndfp, date){
		
	$("#saisie").load('<?php echo dol_buildpath('/ndfp/ndfp.php',2) ?>?id='+ndfp.id+' form[id=addexpense]',function() {
			
		$('#ee,#es').val(date);

		dpChangeDay('ee','<?php echo $langs->trans("FormatDateShortJavaInput") ?>');
		dpChangeDay('es','<?php echo $langs->trans("FormatDateShortJavaInput") ?>');

		jQuery.getScript("<?php echo dol_buildpath('/ndfp/js/functions.js.php?rowid=0',2); ?>&id="+ndfp.id+"&fk_cat="+ndfp.fk_cat);


		$('form[id=addexpense]').submit(function() {

			$.post( $(this).attr('action')
				, $(this).serialize()
				
			) .done(function(data) {
				/*
				 * Récupération de l'erreur de sauvegarde du temps
				 */
				
				$.jnotify('<?php echo $langs->trans('ExpAdded') ?>', "ok");
				
				
			});
			
			$("#saisie").dialog('close');

			return false;
		
		});
	});
}
