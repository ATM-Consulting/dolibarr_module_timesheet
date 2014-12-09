[onshow;block=begin;when [view.mode]!='new']

	<script type="text/javascript">
		$(document).ready(function(){
			$('#userid').prepend('<option value="0">Tous</option>');
			
			$('#userid').val([view.userid_selected]);
			
		});
	</script>

	<span>[THidden.val;block=span;strconv=no]</span>
	<table class="nobrder" style="width:100%;">
		[onshow;block=begin;when [view.mode]='changedate']
			<input type="hidden" name="action" id="action" value="[view.mode]">
		[onshow;block=end]
		<tr>
			<td> du [view.date_deb;strconv=no] au [view.date_fin;strconv=no] Filtrage utilisateur : [view.liste_user;strconv=no] <input class="button" type="submit" name="save" class="butAction" value="Visualiser"></td>
		</tr>
	</table>
	<br><br>
	<table class="border" style="width:100%;">
		<!-- entête du tableau -->
		<thead style="background-color:#CCCCCC;">
			<tr>
				<td>Projet</td>
				<td>Tâche/Service</td>
				<td>Consultant</td>
				<td>Total</td>
				<td>[joursVisu.key;block=td]<br>[joursVisu.val]</td>
				<td>Actions</td>
			</tr>
		</thead>
		
		<tbody>
		<!-- Contenu déjà existant -->
		<tr id="[ligneTimesheet.$;strconv=no;block=tr;sub1]" >
			<td>[ligneTimesheet_sub1.val;block=td;strconv=no]</td>
		</tr>
		<!--
		[onshow;block=begin;when [view.mode]=='edittime']
			<tr id="[timesheet.rowid;strconv=no]">
				<td>[timesheet.services;strconv=no]</td>
				<td>[timesheet.consultants;strconv=no]</td>
				<td>[timesheet.commentaireNewLine;strconv=no]</td>
				<td>&nbsp;</td>
				
				<td>[formjour.val;block=td;strconv=no]</td>
				<td></td>
			</tr>
		[onshow;block=end]-->
		</tbody>
	</table>
	
	[onshow;block=begin;when [view.mode]!='edittime']
	<div class="tabsAction" style="text-align: center;">
		[onshow;block=begin;when [view.righttoedit]==1]	
		<input type="submit" value="Modifier les temps" onclick="$('#action').val('edittime');" name="save" class="button">
		[onshow;block=end]	
	</div>
	[onshow;block=end]	
	[onshow;block=begin;when [view.mode]=='edittime']
		<div class="tabsAction" style="text-align:center;">
		<input type="submit" value="Enregistrer" name="save" class="button"> 
		&nbsp; &nbsp; <input type="button" value="Annuler" name="cancel" class="button" onclick="document.location.href='?id=[timesheet.id]'">
	</div>
	[onshow;block=end]
	</div>
	
	<div style="clear:both"></div>
	<script type="text/javascript">
		$(document).ready(function() {
		
			$('#formtime').submit(function() {
				
				if($('#serviceid_0').val()>0) {
					var is_empty=true;
					$('[id^=temps_0]').each(function(i) {
						if($(this).val()!='')is_empty=false;
					});
					
					if(is_empty){
						alert('[view.TimesheetYouCantIsEmpty;strconv=no]');
						return false;
					}
					else{
						null; // ok
					}
				}
			});
				
		});
	
	</script>
	
	<style type="text/css">
		div.bodyline {
			z-index:1050;
		}
	</style>
	
	<div id="saisie" style="display:none;">
		<div id="viewlines"></div>
		<div id="adlines"></div>
	</div>
	
[onshow;block=end]