	<span>[THidden.val;block=span;strconv=no]</span>

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
		<tr>
			<td colspan="[view.colspan;strconv=no]" align="center">
				[view.messageNothing;strconv=no] [onshow; block=tr; when [timesheet.nbLines]==0]
			</td>
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
		<a class="button" href="[view.linkback]&amp;action=edittime">[langs.transnoentities(Modify)]</a>
		[onshow;block=end]	
	</div>
	[onshow;block=end]

	[onshow;block=begin;when [view.mode]=='edittime']
	<div class="tabsAction" style="text-align:center;">
		<input type="submit" value="[langs.transnoentities(Save)]" name="save" class="button">&nbsp;&nbsp;&nbsp;&nbsp;<a class="button" href="[view.linkback]">[langs.transnoentities(Cancel)]</a>
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