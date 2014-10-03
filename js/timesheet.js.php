<?php
	require('../config.php');
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
		,minWidth:1250
		,minHeight:200
		,title:'Note de frais'
	});
}
