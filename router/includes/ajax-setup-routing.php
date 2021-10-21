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
	foreach (explode("\n", trim(@shell_exec("route -n | grep -v Kernel | grep -v Destination"))) as $line)
	{
		$arr = explode(" ", preg_replace('/\s+/', ' ', $line));
		if (empty($routes[$arr[7]]))
			$routes[$arr[7]] = trim(@file_get_contents("/etc/network/if-up.d/" . $arr[7] . "-route"));
		$found = strpos($routes[$arr[7]], ip_range_cmd($arr[0], $arr[2], $arr[1], $arr[7], $arr[4]));
		$out[] = array($arr[0], $arr[2], $arr[1], $arr[4], $arr[7], $found ? $delete : '');
	}

	foreach ($out as $arr)
		echo "<tr><td>" . implode("</td><td>", $arr) . "</td></tr>";
}
###################################################################################################
# ACTION: Anything else ==> Return error message
###################################################################################################
else
	echo "ERROR: Unknown action";
