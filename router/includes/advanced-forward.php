<?php
$_POST['action'] = 'list';
$_POST['sid'] = $_SESSION['sid'];

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

	#################################################################################################
	# ACTION: LIST => List current ports being forwarded through the router:
	#################################################################################################
	if ($_POST['action'] == 'list')
	{
		$rules = array();
		foreach (explode("\n", trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh forward list"))) as $id => $line)
		{
			$rules[$id]['port']  = preg_match('/\--dport (\d+)/', $line, $regex) ? $regex[1] : 'ERROR';
			$rules[$id]['to']    = preg_match('/\--to-destination (\d+\.\d+\.\d+\.\d+\:\d+)/', $line, $regex) ? $regex[1] : 'ERROR';
			$rules[$id]['proto'] = preg_match('/\-p (tcp|udp)/', $line, $regex) ? $regex[1] : 'both';
		}
		echo '<pre>'; print_r($rules); exit;
		die();
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# Output the UPnP Settings page:
#################################################################################################
site_menu();
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Port Forwarding</h3>
	</div>
	<div class="card-body">
		<h5>
			<a href="javascript:void(0);"><button type="button" id="forward_refresh" class="btn btn-sm btn-primary float-right">Refresh</button></a>
			Current Port Forwards
		</h5>
		<div class="table-responsive p-0">
			<table class="table table-hover text-nowrap table-sm table-striped table-bordered">
				<thead class="bg-primary">
					<td width="10%"><center>ID</center></td>
					<td width="10%"><center>Protocol</center></td>
					<td width="10%"><center>Ext. Port</center></td>
					<td width="10%"><center>IP Address</center></td>
					<td width="10%"><center>Int. Port</center></td>
					<td width="50%">Description</td>
				</thead>
				<tbody id="forward_table">
					<tr><td colspan="7"><center>Loading...</center></td></tr>
				</tbody>
			</table>
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="forward_submit">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while the UPnP settings are managed....', true);
site_footer('Init_PortForward();');
