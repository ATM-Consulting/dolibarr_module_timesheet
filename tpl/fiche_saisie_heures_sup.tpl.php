[onshow;block=begin;when [view.mode]!='new']

	<script type="text/javascript">
		$(document).ready(function(){
			[onshow;block=begin;when [view.tous]='false']
				$('#userid').prepend('<option value="0">Tous</option>');
			[onshow;block=end]
			[onshow;block=begin;when [view.tous]='true']
				$('#userid').prepend('<option value="0" selected="selected">Tous</option>');
			[onshow;block=end]
		});
	</script>

	<span>[THidden.val;block=span;strconv=no]</span>
	<table class="nobrder" style="width:100%;">
		<tr>
			<td>Période du [view.date_deb;strconv=no] au [view.date_fin;strconv=no] Filtrage utilisateur : [view.liste_user;strconv=no] <input class="button" type="submit" name="save" class="butAction" value="Visualiser"></td>
		</tr>
	</table>
	<br><br>
	<table class="border" style="width:100%;">
		<!-- entête du tableau -->
		<thead style="background-color:#CCCCCC;">
			<tr>
				<td>Consultant</td>
				<td>Total</td>
				<td>Total heures souhaité</td>
				<td>Total heures supplémentaires</td>
				<td>Heures rémunérées</td>
				<td>Heures rattrapées</td>
			</tr>
		</thead>
		
		<tbody>
		<!-- Contenu déjà existant -->
		<tr id="[ligneTimesheet.$;strconv=no;block=tr;sub1]" >
			<td>[ligneTimesheet_sub1.val;block=td;strconv=no]</td>
		</tr>
		</tbody>
	</table>
	
	[onshow;block=begin;when [view.mode]!='edittime']
	<div class="tabsAction" style="text-align: center;">
		[onshow;block=begin;when [view.righttoedit]==1]	
		<a href="?id=[timesheet.id]&action=edittime" class="butAction">Ventiler heures</a>
		[onshow;block=end]	
	</div>
	[onshow;block=end]	
	[onshow;block=begin;when [view.mode]=='edittime']
		<div class="tabsAction" style="text-align:center;">
		<input type="submit" value="Ventiler heures" name="save" class="butAction"> 
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