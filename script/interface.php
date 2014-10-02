<?php
	
	require('../config.php');

	$PDOdb = new TPDOdb;

	$get = GETPOST('get');
	$put = GETPOST('put');

	switch($get) {
		case 'get_ndfp':
			print _get_ndfp($PDOdb,$_REQUEST['fk_user'],$_REQUEST['fk_task'],$_REQUEST['fk_timesheet']);
			break;
	}
	
	switch ($put) {
		case 'value':
			
			break;

	}
	
	
	function _get_ndfp(&$PDOdb,$fk_user,$fk_task,$fk_timesheet){

		dol_include_once('/ndfp/class/ndfp.class.php');
		dol_include_once('/core/class/html.form.class.php');
		
		$sql = "SELECT n.rowid as 'rowid'
				FROM ".MAIN_DB_PREFIX."ndfp as n
					LEFT JOIN ".MAIN_DB_PREFIX."ndfp_det as nd On (nb.fk_ndfp = n.rowid)
					LEFT JOIN ".MAIN_DB_PREFIX."task as t ON (t.rowid = nd.fk_task)
				WHERE t.rowid = ".$fk_task." AND n.fk_user = ".$fk_user;

		$PDOdb->Execute($sql);

		if($PDOdb->Get_line()){
			return $PDOdb->Get_field('rowid');
		}
		else{
			
			$idNdfp = _createNdfp($PDOdb,$fk_user,$fk_timesheet);

			return $idNdfp;
		}
	}

	function _createNdfp(&$PDOdb,$fk_user,$fk_timesheet){
		global $db,$conf,$user;

		$userNdfp = new User($db);
		$userNdfp->fetch($fk_user);

		$timesheet = new TTimesheet;
		$timesheet->load($PDOdb,$fk_timesheet);
		
		$ndfp = new Ndfp($db);
		$html = new Form($db);

		$fk_user = $fk_user;
        $fk_soc = $timesheet->societe->id;
        $fk_cat = 22; //Mis en dure pour le moment = "not applicable"
        $fk_project = 0; //0 puisqu'une note de frais peux être lié à plusieurs projets d'un client

		$previous_exp = 0; //Aucune idée du pk = 0
        $model = 'calamar'; //Calamar par défaut
        $currency = $conf->currency;
        $description = '';
        $note_public = '';
        $note = '';

        $result = $ndfp->check_user_rights($user, 'create');
        if ($result < 0)
        {
            return -1;
        }

        $start_date = $timesheet->date_deb;
        $end_date = $timesheet->date_fin;

        $ndfp->ref            = '(PROV)';
        $ndfp->cur_iso        = $currency;

        $ndfp->entity         = $conf->entity;
        $ndfp->dates          = $start_date;
        $ndfp->datee          = $end_date;

        $ndfp->fk_user        = $fk_user;
        $ndfp->fk_soc         = $fk_soc;
        $ndfp->fk_project     = $fk_project;

        $ndfp->fk_cat        	= $fk_cat;

        $ndfp->total_tva      	= 0;
        $ndfp->total_ht     	= 0;
        $ndfp->total_ttc      	= 0;
		$ndfp->previous_exp 	= $previous_exp;
		
        $ndfp->description      = $description;
        $ndfp->comment_user	    = $note_public;
        $ndfp->comment_admin    = $note;

        $ndfp->model_pdf        = $model;
        $ndfp->statut      		= 0;
		
		$idNdfp = $ndfp->create($user);
		
		return $idNdfp;
	}
