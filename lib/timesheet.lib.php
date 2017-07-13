<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file		lib/mymodule.lib.php
 *	\ingroup	mymodule
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function timesheetAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("timesheet@timesheet");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/timesheet/admin/timesheet_setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/timesheet/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'timesheet');

    return $head;
}

function timesheetPrepareHead(&$timesheet,$type='timesheet') {
	global $user,$langs;

	switch ($type) {
		case 'timesheet':
			if($timesheet->rowid==0) {
				return array(
					array(dol_buildpath('/timesheet/timesheetusertimes.php',2), $langs->trans('Card'),'fiche')
				);
				
			}
			else{
				return array(
					array(dol_buildpath('/timesheet/timesheet.php?id='.$timesheet->rowid,2), $langs->trans('Card'),'fiche')
				);
				
			}
			break;
		case 'hsup':
				return array(
					array(dol_buildpath('/timesheet/timesheet_heures_sup.php',2), $langs->trans('Card'),'fiche')
				);
			break;
	}
}

function getEmploiDuTemps(&$PDOdb, $timesheet, $fk_user) {

	dol_include_once('/rh/absence/class/absence.class.php');

	$edt = new TRH_EmploiTemps;
	$edt->load_by_fkuser($PDOdb, $fk_user);

	$TJours = $timesheet->loadTJours(); // Chargement de la liste des jours de la feuille de temps

	$TEDT = array();

	foreach($TJours as $date => $jour) {
		$timestamp = dol_stringtotime($date, false);

		$duration = 0;
		$indiceJour = (int) date('N', $timestamp) - 1; // O => lundi, 1 => mardi, etc.
		$jour = $edt->TJour[$indiceJour]; // renvoie 'lundi', 'mardi', etc.

		if($edt->{$jour . 'am'} == 1) $duration += 3600 * $edt->getHeurePeriode($jour, 'am'); // $edt->getHeurePeriode() renvoie des heures, on met en secondes
		if($edt->{$jour . 'pm'} == 1) $duration += 3600 * $edt->getHeurePeriode($jour, 'pm');

		$TEDT[$date] = $duration;
	}

	return $TEDT;
}
