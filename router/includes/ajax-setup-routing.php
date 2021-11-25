<?php
if (!isset($_POST['action']) || !isset($_POST['sid']))
	require_once("404.php");
if ($_POST['sid'] != $_SESSION['sid'])
	die('RELOAD');

###################################################################################################
# Supporting functions:
###################################################################################################
function ip_range_cmd($dest_addr, $mask_addr, $gate_addr, $dev, $metric)
{
	$mask = $mask_addr == "0.0.0.0" ? 0 : 32-log(( ip2long($mask_addr) ^ ip2long('255.255.255.255') ) + 1, 2);
	return "ip route add " . $dest_addr . "/" . $mask . " via " . $gate_addr . " dev " . $dev . " metric " . $metric;
}

function validate_params()
{
	$_POST['dest_addr'] = option_ip('dest_addr');
	$_POST['mask_addr'] = option_ip('mask_addr');
	$_POST['gate_addr'] = option_ip('gate_addr');
	$_POST['metric'] = option_range('metric', 0, 9999);

	$_POST['iface'] = isset($_POST['iface']) ? $_POST['iface'] : '';
	if (empty($_POST['iface']) || !file_exists("/sys/class/net/" . $_POST['iface']))
		die('[IFACE] ERROR: "' . $_POST['iface'] . '" is not a valid network interface!');

	return ip_range_cmd($_POST['dest_addr'], $_POST['mask_addr'], $_POST['gate_addr'], $_POST['iface'], $_POST['metric']);
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
# ACTION: DELETE ==> Remove specified ip routing from the system configuration:
###################################################################################################
else if ($_POST['action'] == 'delete')
{
	$out = validate_params();
	if (!file_exists('/etc/network/if-up.d/' . $_POST['iface'] . '-route'))
		die('ERROR: Post-up script does not exist for interface "' . $_POST['iface'] . '"!');
	@shell_exec('cat /etc/network/if-up.d/' . $_POST['iface'] . '-route | grep -v "' . $out . '" > /tmp/' . $_POST['iface'] . '-route');
	@shell_exec('/opt/bpi-r2-router-builder/router-helper.sh route move ' . $_POST['iface'] . '-route');
	echo @shell_exec('/opt/bpi-r2-router-builder/router-helper.sh route ' . str_replace("ip route add", "del", $out));
}
###################################################################################################
# ACTION: ADD ==> Add specified ip routing to the system configuration:
###################################################################################################
else if ($_POST['action'] == 'add')
{
	$out = validate_params();
	if (!file_exists('/etc/network/if-up.d/' . $_POST['iface'] . '-route'))
		$text = 'if [[ "${IFACE}" == "' . $_POST['iface'] . '" ]]; then' . "\n\t" . $out . "\nfi";
	else
	{
		$text = @file_get_contents('/etc/network/if-up.d/' . $_POST['iface'] . '-route');
		$text = str_replace("fi", "\t" . $out . "\nfi", $text);
	}
	$handle = fopen("/tmp/" . $_POST['iface'] . "-route", "w");
	fwrite($handle, $text);
	fclose($handle);
	@shell_exec('/opt/bpi-r2-router-builder/router-helper.sh route move ' . $_POST['iface'] . '-route');
	echo @shell_exec('/opt/bpi-r2-router-builder/router-helper.sh route ' . str_replace("ip route ", "", $out));
}
###################################################################################################
# ACTION: Anything else ==> Return error message
###################################################################################################
else
	echo "ERROR: Unknown action";
