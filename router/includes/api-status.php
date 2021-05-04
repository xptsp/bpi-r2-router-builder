<?php
$debug = false;

##########################################################################################
# Split each line of the results of the "arp" command into array elements:
##########################################################################################
$result = explode("\n", trim(@shell_exec('arp | grep -v -e "^Address"')));
$arr = array(
	'is_online' => strpos(@shell_exec('ping -c 1 -W 5 8.8.8.8'), "1 received") > 0,
	'count' => count($result),
	'internal' => 0,
	'external' => 0,
);
foreach ($result as $line)
{
	$temp = explode(" ", preg_replace("/\s+/", " ", $line));
	$arr['devices'][] = array(
		'ip' => $temp[0],
		'type' => $temp[1],
		'mac' => $temp[2],
		#'unknown' => $temp[3],
		'iface' => $temp[4],
	);
	$arr[$temp[4]][] = &$arr['devices'][count($arr['devices']) - 1];
}

##########################################################################################
# Split each line of the dnsmasq.leases file and place into appropriate element:
##########################################################################################
foreach (explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases"))) as $num => $line)
{
	$temp = explode(" ", preg_replace("/\s+/", " ", $line));
	if ($debug) { echo '<h3>$temp[', $num , ']</h3><pre>'; print_r($temp); echo "</pre>"; }
	foreach ($arr['devices'] as $id => $device)
	{
		if ($device['mac'] == $temp[1])
		{
			$arr['devices'][$id]['name'] = $temp[3];
			$arr['devices'][$id]['ends'] = intval($temp[0]);
		}
	}
}

##########################################################################################
# Debug message
##########################################################################################
$arr['internal'] = $arr['count'] - ($arr['external'] = count($arr['wan']));
if ($debug) { echo '<h3>$arr</h3><pre>'; print_r($arr); echo "</pre>"; }

##########################################################################################
# Output the resulting array:
##########################################################################################
echo json_encode($arr);
die();
