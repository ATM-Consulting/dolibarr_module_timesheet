	<span>[THidden.val;block=span;strconv=no]</span>
	<table class="nobrder" style="width:100%;">
		[onshow;block=begin;when [view.mode]='changedate']
			<input type="hidden" name="action" id="action" value="[view.mode]">
		[onshow;block=end]
		<tr>
			<td> [langs.transnoentities(Period)] [view.date_deb;strconv=no] [langs.transnoentities(to)] [view.date_fin;strconv=no] [langs.transnoentities(UserFilter)] : [view.liste_user;strconv=no] <input class="button" type="submit" name="save" class="butAction" value="[langs.transnoentities(Display)]"></td>
		</tr>
	</table>
	<br><br>
	<table class="border" style="width:100%;">
		<!-- entête du tableau -->
		<thead style="background-color:#CCCCCC;">
			<tr>
				<td>[langs.transnoentities(Project)]</td>
				<td>[langs.transnoentities(TaskOrService)]</td>
				<td>[langs.transnoentities(User)]</td>
				<td>[langs.transnoentities(Total)]</td>
				<td>[joursVisu.key;block=td]<br>[joursVisu.val]</td>
				<td>.</td>
			</tr>
		</thead>
		
		<tbody>
		<!-- Contenu déjà existant -->
		<tr id="[ligneTimesheet.$;strconv=no;block=tr;sub1]" >
			<td>[ligneTimesheet_sub1.val;block=td;strconv=no]</td>
		</tr>

		[onshow;block=begin;when [view.mode]=='edittime']
			<!-- Nouvelle ligne de timesheet-->
			<tr id="0">
				<td>[timesheet.projets;strconv=no]</td>
				<td id="project_td0">[timesheet.services;strconv=no]</td>
				<td>[timesheet.consultants;strconv=no]</td>
				<td><!--  --></td>

				<td>[formjour.val;block=td;strconv=no]</td>
				<td></td>
			</tr>
		[onshow;block=end]
		</tbody>
	</table>
	
	[onshow;block=begin;when [view.mode]!='edittime']
	<div class="tabsAction" style="text-align: center;">
		[onshow;block=begin;when [view.righttoedit]==1]	
		<input type="submit" value="[langs.transnoentities(Modify)]" onclick="$('#action').val('edittime');" name="save" class="button">
		[onshow;block=end]	
	</div>
	[onshow;block=end]	
	[onshow;block=begin;when [view.mode]=='edittime']
		<div class="tabsAction" style="text-align:center;">
		<input type="submit" value="[langs.transnoentities(Save)]" name="save" class="button"> 
		&nbsp; &nbsp; <input type="button" value="[langs.transnoentities(Cancel)]" name="cancel" class="button" onclick="document.location.href=''">
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