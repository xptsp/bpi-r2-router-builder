<?php

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
		$str = '';
		foreach (explode("\n", trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh forward list"))) as $id => $line)
		{
			$ext_port = preg_match('/--(dport|dports) (\d+\:\d+|\d+)/', $line, $regex) ? $regex[2] : 'ERROR';
			$ip_addr  = preg_match('/--to-destination (\d+\.\d+\.\d+\.\d+\:\d+|\d+\.\d+\.\d+\.\d+)/', $line, $regex) ? $regex[1] : 'ERROR';
			$int_port = preg_match('/\:(\d+)/', $ip_addr, $regex) ? $regex[1] : $ext_port;
			$proto    = preg_match('/-p (tcp|udp)/', $line, $regex) ? $regex[1] : 'both';
			$comment  = preg_match('/--comment (".*?"|\'.*?\'|[^\s+])/', $line, $regex) ? str_replace('"', '', str_replace("\'", "", $regex[1])) : '';
			$enabled  = preg_match('/-j ([^\s]+)/', $line, $regex);
			$str .=
				'<tr>' .
					'<td><center>' . ($enabled ? 'Y' : 'N') . '</center></td>' .
					'<td><center>' . $proto . '</center></td>' .
					'<td><span class="float-right" style="margin-right: 10px">' . $ext_port . '</span></td>' .
					'<td>' . explode(":", $ip_addr)[0] . '</td>' .
					'<td><span class="float-right" style="margin-right: 10px">' . $int_port . '</span></td>' .
					'<td>' . $comment . '</td>' .
				'</tr>';
		}
		die( !empty($str) ? $str : '<tr><td colspan="7"><center>No Ports Forwarded</center></td></tr>' );
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
					<td width="10%"><center>Enabled</center></td>
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
		<a id="add_reservation_href" href="javascript:void(0);"', '><button type="button" id="add_forward" class="btn btn-success float-right"><i class="fas fa-plus"></i>&nbsp;&nbsp;Add Port Forward</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while the UPnP settings are managed....', true);
site_footer('Init_PortForward();');
