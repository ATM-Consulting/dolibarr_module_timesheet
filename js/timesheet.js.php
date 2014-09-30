<?php
// gère le popin NDF entre autre
	require('../config.php');

?>
function addNdf(fk_user, fk_task) {
	
	$.get('<?php echo dol_buildpath('/timesheet/script/interface.php') ?>?get=new_ndfp_line&fk_user='+fk_user+'&fk_task='+fk_task,function(data){
		
	});
	
}
