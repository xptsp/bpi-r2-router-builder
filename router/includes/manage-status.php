<?php
require_once("subs/manage.php");

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	###################################################################################################
	# ACTION: REBOOT/POWEROFF ==> Reboot or power off the router:
	###################################################################################################
	if ($_POST['action'] == 'reboot' || $_POST['action'] == 'poweroff')
	{
		die(@exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh status ' . $_POST['action']));
	}
	###################################################################################################
	# ACTION: STATUS ==> Return information for the "Router Status" page:
	###################################################################################################
	else if ($_POST['action'] == 'status')
	{
		header('Content-type: application/json');
		$dhcp = explode(' ', trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp info')) . " 0 0");

		$year = date("Y");
		$dhcp_begin = strtotime("$dhcp[1] $dhcp[2] $year $dhcp[3]");
		if ($dhcp_begin > time())
		{
			$year = ((int) $year) - 1;
			$dhcp_begin = strtotime("$dhcp[1] $dhcp[2] $year $dhcp[3]");
		}
		$dhcp_expire = $dhcp_begin + intval($dhcp[4]);
		die(json_encode(array(
			'dhcp_server'  => $dhcp[0],
			'dhcp_begin'   => date('Y-m-d H:i:s', $dhcp_begin),
			'dhcp_expire'  => date('Y-m-d H:i:s', $dhcp_expire),
			'dhcp_refresh' => $dhcp_expire - time()
		)));
	}
	###################################################################################################
	# ACTION: NETWORK ==> Display statistics for each interface:
	###################################################################################################
	else if ($_POST['action'] == 'network')
	{
		require_once("subs/manage.php");
		$ifaces = get_network_adapters();
		$SYS = '/sys/class/net/';

		echo
		'<table class="table table-bordered">',
			'<thead>',
				'<tr>',
					'<th>Port</th>',
					'<th>Status</th>',
					'<th>TX Packets</th>',
					'<th>RX Packets</th>',
					'<th>Collisions</th>',
					'<th>TX Bytes</th>',
					'<th>RX Bytes</th>',
				'</tr>',
			'</thead>',
			'<tbody>';
		foreach ($ifaces as $name => $bridged)
		{
			if (!preg_match('/^(lo|sit.*|eth0|eth1|aux|docker.*)$/', $name))
			{
				$status = trim(@file_get_contents($SYS . $name . '/speed'));
				if ($status == '-1' || empty($status))
					$status = 'Link Down';
				else
				{
					$status .= 'M';
					$duplex = ucwords(trim(@file_get_contents($SYS . $name . '/duplex')));
					if ($duplex != 'Unknown')
						$status .= '/' . $duplex;
				}
				echo
					'<tr>',
						'<td>', $name, '</td>',
						'<td>', $status, '</td>',
						'<td><span class="float-right">', number_format((int) @file_get_contents($SYS . $name . '/statistics/tx_packets')), '</span></td>',
						'<td><span class="float-right">', number_format((int) @file_get_contents($SYS . $name . '/statistics/rx_packets')), '</span></td>',
						'<td><span class="float-right">', number_format((int) @file_get_contents($SYS . $name . '/statistics/collisions')), '</span></td>',
						'<td><span class="float-right">', number_format((int) @file_get_contents($SYS . $name . '/statistics/tx_bytes') / 1024 / 1024, 2), ' MB</span></td>',
						'<td><span class="float-right">', number_format((int) @file_get_contents($SYS . $name . '/statistics/rx_bytes') / 1024 / 1024, 2), ' MB</span></td>',
					'</tr>';
			}
		}
		die(
				'</td>' .
			'</tbody>' .
		'</table>');
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#######################################################################################################
# Gather as much information before starting the overview display as we can:
#######################################################################################################
$load = sys_getloadavg();
$br0 = parse_ifconfig('br0');
$wan = get_mac_info('wan');
$wan_if = parse_ifconfig('wan');
$dns = get_dns_servers();
$type = strpos($wan['iface'], 'dhcp') > 0 ? 'DHCP' : 'Static IP';
$power_button = file_exists("/etc/modprobe.d/power_button.conf");
$model = explode(":", @shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh status machine'));
$debian_version = @file_get_contents('/etc/debian_version');

#######################################################################################################
# Display information about the router:
#######################################################################################################
site_menu();
echo '
<div class="container-fluid">
	<div class="row">
		<div class="col-md-6">
			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title">Router Information</h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive p-0">
					<table class="table table-hover text-nowrap">
						<tr>
							<td><strong>Internal IP Address</strong></td>
							<td>', $br0['inet'], '</td>
						</tr>
						<tr>
							<td width="50%"><strong>Internal MAC Address</strong></td>
							<td>', strtoupper(trim($br0['ether'])), '</td>
						</tr>
						<tr>
							<td colspan="2"><strong><i>Operating System Information</i></strong></td>
						</tr>
						<tr>
							<td width="50%"><strong>Machine Model</strong></td>
							<td>', str_replace("Bananapi", "Banana Pi", $model[ count($model) - 1 ]), '</td>
						</tr>
						<tr>
							<td><strong>OS Version</strong></td>
							<td>Debian ', $debian_version < 11 ? 'Buster' : 'Bullseye', ' ', $debian_version, '</td>
						</tr>
						<tr>
							<td><strong>OS Kernel</strong></td>
							<td>', explode(' ', @file_get_contents('/proc/version'))[2], '</td>
						</tr>
						<tr>
							<td><strong>Web UI Version</strong></td>
							<td>v', $_SESSION['webui_version'], '</td>
						</tr>
						<tr>
							<td><strong>Wifi Regulatory Database</strong></td>
							<td>v', $_SESSION['regdb_version'], '</td>
						</tr>

					</table>
				</div>
				<!-- /.card-body -->
				<div class="card-footer">
					<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-danger center_50" data-toggle="modal" data-target="#reboot-modal" id="reboot_button">Reboot Router</button></a>
				</div>
				<!-- /.card-body -->
			</div>
			<!-- /.card -->
		</div>
		<!-- /.col -->';

#######################################################################################################
# Display information about the Internet Port ("wan" interface):
#######################################################################################################
echo '
		<div class="col-md-6">
			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title">Internet Port</h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive p-0">
					<table class="table table-hover text-nowrap">
						<tr>
							<td><strong>External IP Address</strong></td>
							<td>', isset($wan_if['inet']) ? $wan_if['inet'] : '<i>Disconnected</i>', '</td>
						</tr>
						<tr>
							<td><strong>External Subnet Mask</strong></td>
							<td>', isset($wan_if['netmask']) ? $wan_if['netmask'] : '<i>Disconnected</i>', '</td>
						</tr>
						<tr>
							<td width="50%"><strong>External MAC Address</strong></td>
							<td>', isset($wan_if['ether']) ? strtoupper($wan_if['ether']) : '<i>Disconnected</i>', '</td>
						</tr>
						<tr>
							<td><strong>Domain Name Server', isset($dns[1]) ? 's' : '', '</strong></td>
							<td>', $dns[0], isset($dns[1]) ? ', ' . $dns[1] : '', '</td>
						</tr>
						<tr>
							<td><strong>Connection</strong></td>
							<td id="connection_type">', $type, '</td>
						</tr>';
if ($type == 'DHCP')
	echo '
						<tr>
							<td><strong>External DHCP Server</strong></td>
							<td id="dhcp_server"><i>Retrieving...</i></td>
						</tr>
						<tr>
							<td><strong>DHCP Lease Began</strong></td>
							<td id="dhcp_begin"><i>Retrieving...</i></td>
						</tr>
						<tr>
							<td><strong>DHCP Lease Expires</strong></td>
							<td id="dhcp_expire"><i>Retrieving...</i></td>
						</tr>';
echo '
					</table>
				</div>
				<!-- /.card-body -->
				<div class="card-footer centered">
					<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-primary center_50" data-toggle="modal" data-target="#stats-modal" id="stats_button">Network Statistics</button></a>
				</div>
			</div>
			<!-- /.card -->
		</div>
		<!-- /.col -->';

#######################################################################################################
# Display information about the normal Wireless Network (2.4GHz)
#######################################################################################################
echo '
		<div class="col-md-6">
			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title">Wireless Network (2.4GHz)</h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive p-0">
					<table class="table table-hover text-nowrap">
						<tr>
							<td width="50%"><strong>Name (SSID)</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Region</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Channel</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Mode</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Wireless AP</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Broadcast Name</strong></td>
							<td>N/A</td>
						</tr>
					</table>
				</div>
				<!-- /.card-body -->
			</div>
			<!-- /.card -->
		</div>
		<!-- /.col -->';

#######################################################################################################
# Display information about the normal Wireless Network (5GHz)
#######################################################################################################
echo '
		<div class="col-md-6">
			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title">Wireless Network (5GHz)</h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive p-0">
					<table class="table table-hover text-nowrap">
						<tr>
							<td width="50%"><strong>Name (SSID)</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Region</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Channel</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Mode</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Wireless AP</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Broadcast Name</strong></td>
							<td>N/A</td>
						</tr>
					</table>
				</div>
				<!-- /.card-body -->
			</div>
			<!-- /.card -->
		</div>
		<!-- /.col -->';

#######################################################################################################
# Display information about the Guest Wireless Network (2.4GHz)
#######################################################################################################
echo '
		<div class="col-md-6">
			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title">Guest Network (2.4GHz)</h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive p-0">
					<table class="table table-hover text-nowrap">
						<tr>
							<td width="50%"><strong>Name (SSID)</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Wireless AP</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Broadcast Name</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Can access local network</strong></td>
							<td>N/A</td>
						</tr>
					</table>
				</div>
				<!-- /.card-body -->
			</div>
			<!-- /.card -->
		</div>
		<!-- /.col -->';

#######################################################################################################
# Display information about the Guest Wireless Network (5GHz)
#######################################################################################################
echo '
		<div class="col-md-6">
			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title">Guest Network (5GHz)</h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive p-0">
					<table class="table table-hover text-nowrap">
						<tr>
							<td width="50%"><strong>Name (SSID)</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Wireless AP</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Broadcast Name</strong></td>
							<td>N/A</td>
						</tr>
						<tr>
							<td><strong>Can access local network</strong></td>
							<td>N/A</td>
						</tr>
					</table>
				</div>
				<!-- /.card-body -->
			</div>
			<!-- /.card -->
		</div>
		<!-- /.col -->';

#######################################################################################################
# Close this overview page:
#######################################################################################################
echo '
	</div>
	<!-- /.row -->
</div>
<!-- container-fluid -->';

#######################################################################################################
# Network Statistics modal:
#######################################################################################################
echo '
<div class="modal fade" id="stats-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Network Statistics</h4>
				<span class="float-right">Refresh <input type="checkbox" id="refresh_switch" checked data-bootstrap-switch></span>
			</div>
			<div class="modal-body">
				<p id="stats_body"></p>
			</div>
			<div class="modal-footer justify-content-between">
				<a href="javascript:void(0);"><button type="button" class="btn btn-default bg-primary" id="stats_close" data-dismiss="modal">Close</button></a>
			</div>
		</div>
		<!-- /.modal-content -->
	</div>
	<!-- /.modal-dialog -->
</div>';
reboot_modal();

site_footer('Init_Stats();');
