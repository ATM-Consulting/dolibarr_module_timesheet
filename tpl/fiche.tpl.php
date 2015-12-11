			<script type="text/javascript" src="[fiche.link]"></script>
	
			<table width="100%" class="border">
				[onshow;block=begin; when [timesheet.id]!=0 ]
				<tr><td >Id.</td><td>[timesheet.id;strconv=no]</td></tr>
				[onshow;block=end]
				<tr><td width="20%">[langs.transnoentities(Company)]</td><td>[timesheet.societe;strconv=no]</td></tr>
				<tr><td>[langs.transnoentities(Project)]</td><td id="timesheet-project-list">[timesheet.project;strconv=no]</td></tr>
				<tr><td>[langs.transnoentities(Status)]</td><td>[timesheet.status;strconv=no]</td></tr>
				<tr><td>[langs.transnoentities(DateStart)]</td><td>[timesheet.date_deb;strconv=no]</td></tr>
				<tr><td>[langs.transnoentities(DateEnd)]</td><td>[timesheet.date_fin;strconv=no]</td></tr>
				<tr><td>[langs.transnoentities(BillLabel)]</td><td>[timesheet.libelleFactureLigne;strconv=no]</td></tr>

			</table>

		
		[onshow;block=begin;when [fiche.mode]!='edit']
			[onshow;block=begin;when [fiche.mode]!='new']
				<div class="tabsAction">
					[onshow;block=begin;when [fiche.mode]!='edittime']
						[onshow;block=begin;when [fiche.statusval]!=2]
						[onshow;block=begin;when [fiche.righttodelete]==1]
						
							<input type="button" id="action-delete" value="[langs.transnoentities(Delete)]" name="cancel" class="butActionDelete" onclick="if(confirm('[langs.transnoentities(DeleteConfirmation)]')) document.location.href='?action=delete&id=[timesheet.id]'">
						[onshow;block=end]
						[onshow;block=end]
						[onshow;block=begin;when [fiche.righttomodify]==1]
						[onshow;block=begin;when [fiche.statusval]!=2]
							&nbsp; &nbsp; <a href="?id=[timesheet.id]&action=edit" class="butAction">[langs.transnoentities(Modify)]</a>
						[onshow;block=end]	
						[onshow;block=end]	
						[onshow;block=begin;when [fiche.righttoapprove]==1]
							[onshow;block=begin;when [fiche.statusval]==0]
								&nbsp; &nbsp; <a href="?id=[timesheet.id]&action=approve" class="butAction">[langs.transnoentities(Approve)]</a>
							[onshow;block=end]
							[onshow;block=begin;when [fiche.statusval]==1]
								<!-- &nbsp; &nbsp; <a href="?id=[timesheet.id]&action=facturer" class="butAction">Facturer</a> -->
							[onshow;block=end]
						[onshow;block=end]
						[onshow;block=begin;when [fiche.righttoprint]==1]
						 &nbsp; &nbsp; <a href="?id=[timesheet.id]&action=print" class="butAction">[langs.transnoentities(Print)]</a> 
						[onshow;block=end]
						&nbsp; &nbsp; <input id="action-retour-liste" class="butAction" type="button" onclick="document.location.href='timesheet.php'" name="retour-liste" value="[langs.transnoentities(Back)]">
					[onshow;block=end]
				</div>
			[onshow;block=end]
		[onshow;block=end]
		[onshow;block=begin;when [fiche.mode]!='view']
			[onshow;block=begin;when [fiche.mode]!='edittime']
			<p align="center">
					<input type="submit" value="[langs.transnoentities(Save)]" name="save" class="button">
					&nbsp; &nbsp; <input type="button" value="[langs.transnoentities(Cancel)]" name="cancel" class="button" onclick="document.location.href='?id=[timesheet.id]'">
			</p>
			[onshow;block=end]
		[onshow;block=end]
		
		<style type="text/css">
			div.bodyline {
				z-index:1050;
			}
		</style>
		
		<div id="saisie" style="display:none;">
			<div id="viewlines"></div>
			<div id="adlines"></div>
		</div>

