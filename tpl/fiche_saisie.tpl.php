[onshow;block=begin;when [view.mode]!='new']
	<span>[THidden.val;block=span;strconv=no]</span>

	<table class="border" style="width:100%;">
		<!-- entête du tableau -->
		<thead style="background-color:#CCCCCC;">
			<tr>
				[onshow;block=begin;when [view.freemode]='1']
					<td colspan="2">[langs.transnoentities(Task)]</td>
				[onshow;block=end]
				[onshow;block=begin;when [view.freemode]!='1']
					<td>[langs.transnoentities(Service)]</td>
				[onshow;block=end]
				<td>[langs.transnoentities(User)]</td>
				<td>[langs.transnoentities(Comment)]</td>
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
			<tr id="[timesheet.rowid;strconv=no]">
				<td [onshow;block=begin;when [view.freemode]='1'] colspan="2" [onshow;block=end]>[timesheet.services;strconv=no]</td>
				<td>[timesheet.consultants;strconv=no]</td>
				<td>[timesheet.commentaireNewLine;strconv=no]</td>
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
		<a href="?id=[timesheet.id]&action=edittime" class="butAction">[langs.transnoentities(Modify)]</a>
		[onshow;block=end]	
	</div>
	[onshow;block=end]	
	[onshow;block=begin;when [view.mode]=='edittime']
		<div class="tabsAction" style="text-align:center;">
		<input type="submit" value="[langs.transnoentities(Save)]" name="save" class="button"> 
		&nbsp; &nbsp; <input type="button" value="[langs.transnoentities(Cancel)]" name="cancel" class="button" onclick="document.location.href='?id=[timesheet.id]'">
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
	
	
[onshow;block=end]