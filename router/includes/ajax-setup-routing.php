<?php
if (empty($_POST['action']) || !isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}

###################################################################################################
# Supporting function:
###################################################################################################
function ip_range_cmd($dest_addr, $mask_addr, $gate_addr, $dev, $metric)
{
	$mask = $mask_addr == "0.0.0.0" ? 0 : 32-log(( ip2long($mask_addr) ^ ip2long('255.255.255.255') ) + 1, 2);
	return "ip route add " . $dest_addr . "/" . $mask . " via " . $gate_addr . " dev " . $dev . " metric " . $metric;
}

###################################################################################################
# ACTION: SHOW ==> Show the current routing table.  Add delete icons to any custom lines we find.
###################################################################################################
if ($_POST['action'] == 'show')
{
	$routes = $out = array();
	$delete = '<center><a href="javascript:void(0);"><i class="far fa-trash-alt"></i></a></center>';
	foreach (explode("\n", trim(@shell_exec("route | grep -v Kernel | grep -v Destination"))) as $line)
	{
		$a = explode(" ", preg_replace('/\s+/', ' ', $line));
		if (empty($routes[$a[7]]))
			$routes[$a[7]] = trim(@file_get_contents("/etc/network/if-up.d/" . $a[7] . "-route"));
		echo 
			'<tr>',
				'<td class="dest_addr">', $a[0], '</td>',
				'<td class="mask_addr">', $a[2], '</td>',
				'<td class="gate_addr">', $a[1], '</td>',
				'<td class="metric">', $a[4], '</td>',
				'<td class="iface">', $a[7], '</td>',
				'<td>', strpos($routes[$a[7]], ip_range_cmd($a[0], $a[2], $a[1], $a[7], $a[4])) ? $delete : '', '</td>',
			'</tr>';
	}
}
###################################################################################################
# ACTION: Anything else ==> Return error message
###################################################################################################
else
	echo "ERROR: Unknown action";