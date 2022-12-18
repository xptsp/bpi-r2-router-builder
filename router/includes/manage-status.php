<?php
require_once("subs/manage.php");

# Set this variable to "true" to hide internet WAN addresses & wifi access point names:
$override_blur = false;

#######################################################################################################
# Function that shows information about the interface requested:
#######################################################################################################
function Show_Interface($iface, $config, $ip_sensitive = false, $wireless = false)
{
	global $init_str, $override_blur;
	$blur_ip = ($ip_sensitive && $override_blur) ? ' style="filter: blur(4px);"' : '';
	$blur_wifi = ($wireless && $override_blur) ? ' style="filter: blur(4px);"' : '';

	# Gather information about the interface in question:
	$ifconfig = parse_ifconfig($iface);
	$type = strpos($config['iface'], 'dhcp') > 0 ? 'DHCP' : 'Static';
	$gateway = trim(@shell_exec("route -n | grep " . $iface . " | head -1 | awk '{print $2}'"));
	$gateway = !empty($gateway) ? ($gateway == '0.0.0.0' ? 'Default <i>(' . $gateway . ')</i>' : $gateway) : '<i>Disconnected</i>';
	$bridged = explode(" ", isset($config["bridge_ports"]) ? $config["bridge_ports"] : '');
	array_shift($bridged);

	# Gather information about the hostapd configuration:
	$iface_up = trim(@shell_exec("ifconfig " . $iface . " | head -1 | grep UP"));
	if (!isset($config['wpa_ssid']) && file_exists("/etc/hostapd/" . $iface . ".conf"))
	{
		$apd = parse_options("/etc/hostapd/" . $iface . ".conf");
		if (trim(@shell_exec("systemctl is-active hostapd@" . $iface)) != 'active')
			return;
		$iface_type = (isset($apd['ignore_broadcast_ssid']) && $apd['ignore_broadcast_ssid'] == '1' ? 'Hidden ' : '');
		$iface_type .= ($apd['hw_mode'] == 'a' ? '5 GHz' : ($apd['hw_mode'] == 'ad' ? '60 GHz' : '2.4 GHz')) . ' Access Point';
	}
	else
		$iface_type = isset($config['bridge_ports']) ? 'Bridge Interface' : (isset($config['masquerade']) ? 'Internet Interface' : (isset($config['wpa_ssid']) ? 'Wireless Client' : 'Wired Interface'));

	# Start outputting information about the interface:
	echo '
		<div class="col-md-6">
			<div class="card card-', $iface_up ? 'primary' : 'danger', '">
				<div class="card-header">
					<h3 class="card-title">', $iface_type, ': <strong>', $iface, '</strong></h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive p-0">
					<table class="table table-hover text-nowrap">';
	if (isset($config['wpa_ssid']) || isset($apd['ssid']))
		echo '
						<tr>
							<td><strong>Wireless SSID:</strong></td>
							<td><span', $blur_wifi, '>', isset($config['wpa_ssid']) ? $config['wpa_ssid'] : $apd['ssid'], '</span></td>
						</tr>';
	echo '
						<tr>
							<td><strong>', $type, ' IP Address:</strong></td>
							<td><span', $blur_ip, '>', isset($ifconfig['inet']) ? $ifconfig['inet'] : '<i>Disconnected</i>', '<span></td>
						</tr>
						<tr>
							<td width="50%"><strong>Network Subnet:</strong></td>
							<td><span', $blur_ip, '>', isset($ifconfig['netmask']) ? $ifconfig['netmask'] : '<i>Disconnected</i>', '</span></td>
						</tr>
						<tr>
							<td><strong>', $type == 'Static' ? 'Gateway' : 'DHCP', ' IP Address:</strong></td>
							<td><span', $blur_ip, $type == 'DHCP' ? ' id="' . $iface . '_dhcp_server"><i>Retrieving...</i>' : '>' . $gateway, '</td>
						</tr>';
	if ($type == 'DHCP')
	{
		$init_str[] = "\n\t" . 'Stats_Fetch("' . $iface . '");';
		echo '
						<tr>
							<td><strong>DHCP Lease Began:</strong></td>
							<td id="', $iface, '_dhcp_begin"><i>Retrieving...</i></td>
						</tr>
						<tr>
							<td><strong>DHCP Lease Expires:</strong></td>
							<td id="', $iface, '_dhcp_expire"><i>Retrieving...</i></td>
						</tr>';
	}
	if (count($bridged) > 0)
		echo '
						<tr>
							<td width="50%"><strong>Bridged Interfaces:</strong></td>
							<td>', implode(', ', $bridged), '</td>
						</tr>';
	echo '
						<tr>
							<td width="50%"><strong>MAC Address:</strong></td>
							<td>', isset($ifconfig['ether']) ? strtoupper($ifconfig['ether']) : '<i>Disconnected</i>', '</td>
						</tr>
					</table>
				</div>
				<!-- /.card-body -->
			</div>
			<!-- /.card -->
		</div>
		<!-- /.col -->';
}

#######################################################################################################
# Gather the list of interfaces we care about:
#######################################################################################################
$other = $wireless = array();
foreach (explode("\n", @trim(@shell_exec("iw dev | grep Interface | awk '{print $2}' | sort"))) as $iface)
{
	$config = get_mac_info($iface);
	if (isset($config['wpa_ssid']) || file_exists("/etc/hostapd/" . $iface . ".conf"))
		$wireless[$iface] = get_mac_info($iface);
}
#echo '<pre>'; print_r($wireless); exit;
foreach (glob("/etc/network/interfaces.d/*") as $file)
{
	if (!isset($wireless[ $iface = basename($file) ]))
	{
		$config = get_mac_info($iface);
		if (isset($config['address']) || isset($config['masquerade']))
			$other[$iface] = $config;
	}
}
#echo '<pre>'; print_r($other); exit;

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
		die(@exec('router-helper status ' . $_POST['action']));
	}
	###################################################################################################
	# ACTION: STATUS ==> Return information for the "Router Status" page:
	###################################################################################################
	else if ($_POST['action'] == 'status' || $_POST['action'] == 'refresh')
	{
		$iface = option_allowed("misc", array_keys( array_merge($wireless, $other) ));
		if ($_POST['action'] == 'refresh' || (isset($_SESSION['dhcp']) && $_SESSION['dhcp']['int_dhcp_end'] < time()))
			unset($_SESSION[$iface . '_dhcp']);
		if (!isset($_SESSION[$iface . '_dhcp']))
		{
			$dhcp = explode(' ', trim(@shell_exec('router-helper dhcp info ' . $iface)) . " 0 0");

			$year = date("Y");
			$dhcp_begin = strtotime("$dhcp[1] $dhcp[2] $year $dhcp[3]");
			if ($dhcp_begin > time())
			{
				$year = ((int) $year) - 1;
				$dhcp_begin = strtotime("$dhcp[1] $dhcp[2] $year $dhcp[3]");
			}
			$dhcp_expire = $dhcp_begin + intval($dhcp[4]);

			$_SESSION[$iface . '_dhcp'] = array(
				'dhcp_server'  => $dhcp[0],
				'dhcp_begin'   => date('Y-m-d H:i:s', $dhcp_begin),
				'dhcp_expire'  => date('Y-m-d H:i:s', $dhcp_expire),
				'dhcp_refresh' => $dhcp_expire - time(),
				'int_dhcp_beg' => $dhcp_begin,
				'int_dhcp_end' => $dhcp_expire,
			);
		}
		header('Content-type: application/json');
		die(json_encode($_SESSION[$iface . '_dhcp']));
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
					'<th>Packets Sent</th>',
					'<th>Packets Recv</th>',
					'<th>Bytes Sent</th>',
					'<th>Bytes Recv</th>',
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
$dns = get_dns_servers();
if (!isset($_SESSION['machine_name']))
	$_SESSION['machine_name'] = str_replace("Bananapi", "Banana Pi", trim(@shell_exec('router-helper status machine')));
$init_str = array();

#######################################################################################################
# Display information about the OS:
#######################################################################################################
site_menu();
echo '
<div class="container-fluid">
	<h3>System Overview</h3>
	<div class="row">
		<div class="col-md-6">
			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title">Operating System</h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive p-0">
					<table class="table table-hover text-nowrap">
						<tr>
							<td width="50%"><strong>Machine Model:</strong></td>
							<td>', !empty($_SESSION['machine_name']) ? $_SESSION['machine_name'] : 'Unknown', '</td>
						</tr>
						<tr>
							<td width="50%"><strong>OS Version:</strong></td>
							<td>Debian ', ucwords(parse_options("/etc/os-release")['VERSION_CODENAME']), ' ', @trim(@file_get_contents("/etc/debian_version")), '</td>
						</tr>
						<tr>
							<td width="50%"><strong>OS Kernel:</strong></td>
							<td>', explode(' ', @file_get_contents('/proc/version'))[2], '</td>
						</tr>
						<tr>
							<td width="50%"><strong>DNS Addresses:</strong></td>
							<td>', $dns[0], isset($dns[1]) ? ', ' . $dns[1] : '', '</td>
						</tr>
					</table>
				</div>
				<!-- /.card-body -->
				<div class="card-footer">
					<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-danger center_50" data-toggle="modal" data-target="#reboot-modal" id="reboot_button">Reboot Router</button></a>
				</div>
			</div>
			<!-- /.card -->
		</div>
		<!-- /.col -->';

#######################################################################################################
# Display information about the integrated Git repositories:
#######################################################################################################
foreach (array("webui", "multicast-relay") as $repo)
{
	if (!isset($_SESSION[$repo . '_version']))
	{
		$time = trim(@shell_exec('router-helper git current ' . $repo));
		$_SESSION[$repo . '_version'] = ($time == (int) $time ? date('Y.md.Hi', (int) $time) : "Invalid Data");
	}
}
echo '
		<div class="col-md-6">
			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title">Git Repositories</h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive p-0">
					<table class="table table-hover text-nowrap">
						<tr>
							<td width="50%"><strong>WebUI Version:</strong></td>
							<td>v', $_SESSION['webui_version'], '</td>
						</tr>
						<tr>
							<td width="50%"><strong>Multicast Relay:</strong></td>
							<td>v', $_SESSION['multicast-relay_version'], '</td>
						</tr>
					</table>
				</div>
				<!-- /.card-body -->
				<div class="card-footer centered">
					<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-primary center_50" data-toggle="modal" data-target="#stats-modal" id="stats_button">Network Statistics</button></a>
				</div>
			</div>
			<!-- /.card -->
		</div>
		<!-- /.col -->
	</div>';

#######################################################################################################
# Display information about the wired and bridge interfaces:
#######################################################################################################
echo '
	<h3>Wired Interfaces</h3>
	<div class="row">';
foreach ($other as $iface => $config)
	Show_Interface($iface, $config, $iface == 'wan');

#######################################################################################################
# Display information about the Wireless Networks:
#######################################################################################################
echo '
	</div>
	<h3>Active Wireless Interfaces</h3>
	<div class="row">';
foreach ($wireless as $iface => $config)
	Show_Interface($iface, $config, false, $iface != 'ap0');

/*
{
	$wifi_mode = isset($apd['hw_mode']) ? $apd['hw_mode'] : '';
	echo '
		<div class="col-md-6">
			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title">Wireless Network: <strong>', $iface, '</strong></h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive p-0">
					<table class="table table-hover text-nowrap">
						<tr>
							<td><strong>Wireless Mode:</strong></td>
							<td>', $wifi_mode, '</td>
						</tr>
						<tr>
							<td width="50%"><strong>Wireless SSID:</strong></td>
							<td>', isset($apd['ssid']) ? $apd['ssid'] : '', '</td>
						</tr>
						<tr>
							<td><strong>Wireless Channel:</strong></td>
							<td>', !empty($apd['channel']) ? $apd['channel'] : 'Auto-Select', '</td>
						</tr>
						<tr>
							<td><strong>Broadcast Status:</strong></td>
							<td>', isset($apd['ignore_broadcast_ssid']) && $apd['ignore_broadcast_ssid'] == '1' ? 'Hidden' : 'Visible', '</td>
						</tr>
					</table>
				</div>
				<!-- /.card-body -->
			</div>
			<!-- /.card -->
		</div>
		<!-- /.col -->';
}
*/

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

site_footer('Init_Stats();' . implode("\n\t", $init_str));
