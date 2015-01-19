<?php
/* Copyright (C) 2007-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2012-2014 ATM Consulting <contact@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *   	\file       dev/skeletons/skeleton_page.php
 *		\ingroup    mymodule othermodule1 othermodule2
 *		\brief      This file is an example of a php page
 *		\version    $Id: skeleton_page.php,v 1.19 2011/07/31 22:21:57 eldy Exp $
 *		\author		Put author name here
 *		\remarks	Put here some comments
 */
// Change this following line to use the correct relative path (../, ../../, etc)
include '../config.php';
// Change this following line to use the correct relative path from htdocs (do not remove DOL_DOCUMENT_ROOT)

dol_include_once('/core/lib/admin.lib.php');

// Protection if external user
if ($user->societe_id > 0)
{
	accessforbidden();
}


$action=__get('action','');

if($action=='save') {
	
	foreach($_REQUEST['TParam'] as $name=>$param) {
		dolibarr_set_const($db, $name, $param, 'chaine', 0, '', $conf->entity);
		
	}
	
	setEventMessage("Configuration enregistrée");
}


/***************************************************
* PAGE
*
* Put here all code to build page
****************************************************/



llxHeader('',$langs->trans('TimeSheetConfiguration'),'');

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans('TimeSheetConfiguration'),$linkback,'setup');

$form=new TFormCore;

showParameters($form);

function showParameters(&$form) {
	global $db,$conf,$langs;
	
	$html=new Form($db);
	$TConst=array(
		'RH_JOURS_NON_TRAVAILLE'
	);
	
	?><form action="<?php echo $_SERVER['PHP_SELF'] ?>" name="load-<?php echo $typeDoc ?>" method="POST" enctype="multipart/form-data">
		<input type="hidden" name="action" value="save" />
	<table width="100%" class="noborder" style="background-color: #fff;">
		<tr class="liste_titre">
			<td colspan="2"><?php echo $langs->trans('Parameters') ?></td>
		</tr>
		
		<tr>
			<td><?php echo $langs->trans('timesheetTIMESHEET_WORKING_HOUR_PER_DAY') ?></td><td><?php echo $form->texte('', 'TParam[TIMESHEET_WORKING_HOUR_PER_DAY]', $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY,3,3).$langs->trans('days')  ?></td>				
		</tr>
		<tr>
			<td><?php echo $langs->trans('timesheetTIMESHEET_RH_NO_CHECK') ?></td><td><?php echo $form->combo('', 'TParam[TIMESHEET_RH_NO_CHECK]',array(0=>'Non',1=>'Oui'), $conf->global->TIMESHEET_RH_NO_CHECK)  ?></td>				
		</tr>
		<tr>
			<td><?php echo $langs->trans('timesheetTIMESHEET_CREATE_TASK_DOUBLE') ?></td><td><?php echo $form->combo('', 'TParam[TIMESHEET_CREATE_TASK_DOUBLE]',array(0=>'Non',1=>'Oui'), $conf->global->TIMESHEET_CREATE_TASK_DOUBLE)  ?></td>				
		</tr>
		<tr>
			<td><?php echo $langs->trans('timesheetTIMESHEET_USE_SERVICES') ?></td><td><?php echo $form->combo('', 'TParam[TIMESHEET_USE_SERVICES]',array(0=>'Non',1=>'Oui'), $conf->global->TIMESHEET_USE_SERVICES)  ?></td>				
		</tr>
		
		<?php foreach($TConst as $key) { ?>
			<tr>
				<td><?php echo $langs->trans($key) ?></td><td><?php echo $form->texte('', 'TParam['.$key.']', $conf->global->$key,50,255)  ?></td>				
			</tr>
		<?php } ?>
		
	</table>
	<p align="right">
		
		<input type="submit" name="bt_save" value="<?php echo $langs->trans('Save') ?>" /> 
		
	</p>
	
	</form>
	
	
	<br /><br />
	<?php
}
?>

<table width="100%" class="noborder">
	<tr class="liste_titre">
		<td>A propos</td>
		<td align="center">&nbsp;</td>
		</tr>
		<tr class="impair">
			<td valign="top">Module développé par </td>
			<td align="center">
				<a href="http://www.atm-consulting.fr/" target="_blank">ATM Consulting</a>
			</td>
		</td>
	</tr>
</table>
<?php

// Put here content of your page
// ...

/***************************************************
* LINKED OBJECT BLOCK
*
* Put here code to view linked object
****************************************************/
//$somethingshown=$asset->showLinkedObjectBlock();

// End of page
$db->close();
llxFooter('$Date: 2011/07/31 22:21:57 $ - $Revision: 1.19 $');
