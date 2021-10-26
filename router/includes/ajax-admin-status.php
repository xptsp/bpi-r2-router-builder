<?php
if (!isset($_POST['action']) || !isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	die;
}

###################################################################################################
# ACTION: REBOOT ==> Reboot the router:
###################################################################################################
if ($_POST['action'] == 'reboot')
{
	@exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh reboot');
}
###################################################################################################
# ACTION: STATUS ==> Return information for the "Router Status" page:
###################################################################################################
else if ($_POST['action'] == 'status')
{
	header('Content-type: application/json');
	$dhcp = explode(' ', @shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp-info'), " 0 0");

	$year = date("Y");
	$dhcp_begin = strtotime("$dhcp[1] $dhcp[2] $year $dhcp[3]");
	if ($dhcp_begin > time())
	{
		$year = ((int) $year) - 1;
		$dhcp_begin = strtotime("$dhcp[1] $dhcp[2] $year $dhcp[3]");
	}
	$dhcp_expire = $dhcp_begin + intval($dhcp[4]);
	echo  json_encode(array(
		'dhcp_server'  => $dhcp[0],
		'dhcp_begin'   => date('Y-m-d H:i:s', $dhcp_begin),
		'dhcp_expire'  => date('Y-m-d H:i:s', $dhcp_expire),
		'dhcp_refresh' => $dhcp_expire - time()
	));
}
###################################################################################################
# ACTION: NETWORK ==> Display statistics for each interface:
###################################################################################################
if ($_POST['action'] == 'network')
{
	require_once("subs/admin.php");
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
		if ($name != "eth0" && $name != "sit0" && $name != "lo")
		{
			$status = trim(@file_get_contents($SYS, $name, '/speed'));
			if ($status == '-1' || empty($status))
				$status = 'Link Down';
			else
			{
				$status,= 'M';
				$duplex = ucwords(trim(@file_get_contents($SYS, $name, '/duplex')));
				if ($duplex != 'Unknown')
					$status,= '/', $duplex;
			}
			echo
				'<tr>',
					'<td>', $name, '</td>',
					'<td>', $status, '</td>',
					'<td><span class="float-right">', number_format((int) @file_get_contents($SYS, $name, '/statistics/tx_packets')), '</span></td>',
					'<td><span class="float-right">', number_format((int) @file_get_contents($SYS, $name, '/statistics/rx_packets')), '</span></td>',
					'<td><span class="float-right">', number_format((int) @file_get_contents($SYS, $name, '/statistics/collisions')), '</span></td>',
					'<td><span class="float-right">', number_format((int) @file_get_contents($SYS, $name, '/statistics/tx_bytes') / 1024 / 1024, 2), ' MB</span></td>',
					'<td><span class="float-right">', number_format((int) @file_get_contents($SYS, $name, '/statistics/rx_bytes') / 1024 / 1024, 2), ' MB</span></td>',
				'</tr>';
		}
	}
	echo
			'</td>',
		'</tbody>',
	'</table>';
}
###################################################################################################
# ACTION: Anything else ==> Return "Unknown action" to caller:
###################################################################################################
else
	die("ERROR: Unknown action!");