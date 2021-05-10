<?php
if (!isset($_GET['sid']) or $_GET['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}
require_once("subs-detailed.php");
$_SESSIONS['ifaces'] = get_network_adapters();
$SYS = '/sys/class/net/';

echo '
<table class="table table-bordered">
	<thead>
		<tr>
			<th>Port</th>
			<th>Status</th>
			<th>TX Packets</th>
			<th>RX Packets</th>
			<th>Collisions</th>
			<th>TX Bytes</th>
			<th>RX Bytes</th>
		</tr>
	</thead>
	<tbody>';
foreach ($_SESSIONS['ifaces'] as $name => $bridged)
{
	if ($name != "eth0" and $name != "sit0" and $name != "lo")
	{
		$status = trim(@file_get_contents($SYS . $name . '/speed'));
		if ($status == '-1' or empty($status))
			$status = 'Link Down';
		else
		{
			$status .= 'M';
			$duplex = ucwords(trim(@file_get_contents($SYS . $name . '/duplex')));
			if ($duplex != 'Unknown')
				$status .= '/' . $duplex;
		}
		echo '
			<tr>
				<td>', $name, '</td>
				<td>', $status, '</td>
				<td><span class="float-right">', number_format((int) @file_get_contents($SYS . $name . '/statistics/tx_packets')), '</span></td>
				<td><span class="float-right">', number_format((int) @file_get_contents($SYS . $name . '/statistics/rx_packets')), '</span></td>
				<td><span class="float-right">', number_format((int) @file_get_contents($SYS . $name . '/statistics/collisions')), '</span></td>
				<td><span class="float-right">', number_format((int) @file_get_contents($SYS . $name . '/statistics/tx_bytes') / 1024 / 1024, 2), ' MB</span></td>
				<td><span class="float-right">', number_format((int) @file_get_contents($SYS . $name . '/statistics/rx_bytes') / 1024 / 1024, 2), ' MB</span></td>
			</tr>';
	}
}
echo '
		</td>
	</tbody>
</table>';