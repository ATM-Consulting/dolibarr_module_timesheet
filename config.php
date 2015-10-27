<?php

	require(__DIR__.'/config.default.php');

	$langs->load('timesheet@timesheet');
	
	dol_include_once('/timesheet/class/timesheet.class.php');
	dol_include_once('/projet/class/project.class.php');
	dol_include_once('/projet/class/task.class.php');
	dol_include_once('/product/class/product.class.php');
	dol_include_once('/compta/facture/class/facture.class.php');
	dol_include_once('/societe/class/societe.class.php');
	dol_include_once('/timesheet/lib/timesheet.lib.php');
	dol_include_once('/core/class/html.formprojet.class.php');
	dol_include_once('/core/lib/date.lib.php');
