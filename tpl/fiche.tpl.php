
		<div class="fiche"> <!-- begin div class="fiche" -->

			<table width="100%" class="border">
				<tr><td width="10%">Identifiant</td><td>[timesheet.id;strconv=no]</td></tr>
				<tr><td>Société</td><td>[timesheet.societe;strconv=no]</td></tr>
				<tr><td>Project</td><td>[timesheet.project;strconv=no]</td></tr>
				<tr><td>Status</td><td>[timesheet.status;strconv=no]</td></tr>
				<tr><td>Date début période</td><td>[timesheet.date_deb;strconv=no]</td></tr>
				<tr><td>Date fin période</td><td>[timesheet.date_fin;strconv=no]</td></tr>

			</table>

		</div>
		
		[onshow;block=begin;when [fiche.mode]!='edit']
			[onshow;block=begin;when [fiche.mode]!='new']
				[onshow;block=begin;when [fiche.mode]!='edittime']
				<div class="tabsAction">
						[onshow;block=begin;when [fiche.statusval]!=2]
							<input type="button" id="action-delete" value="Supprimer" name="cancel" class="butActionDelete" onclick="if(confirm('Supprimer cette feuille de temps?')) document.location.href='?action=delete&id=[timesheet.id]'">
						[onshow;block=end]
							&nbsp; &nbsp; <a href="?id=[timesheet.id]&action=edit" class="butAction">Modifier</a>
						[onshow;block=begin;when [fiche.statusval]==1]
							&nbsp; &nbsp; <a href="?id=[timesheet.id]&action=facturer" class="butAction">Facturer</a>
						[onshow;block=end]
						&nbsp; &nbsp; <input id="action-retour-liste" class="butAction" type="button" onclick="document.location.href='liste.php'" name="retour-liste" value="Retour">
				</div>
				[onshow;block=end]
			[onshow;block=end]
		[onshow;block=end]
		[onshow;block=begin;when [fiche.mode]!='view']
			[onshow;block=begin;when [fiche.mode]!='edittime']
			<p align="center">
					<input type="submit" value="Enregistrer" name="save" class="button">
					&nbsp; &nbsp; <input type="button" value="Annuler" name="cancel" class="button" onclick="document.location.href='?id=[timesheet.id]'">
			</p>
			[onshow;block=end]
		[onshow;block=end]

