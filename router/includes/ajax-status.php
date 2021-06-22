<?php
if (!isset($_GET['sid']) || $_GET['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	die;
}
header('Content-type: application/json');
$dhcp = explode(' ', @shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp-info') . " 0 0");
echo  json_encode(array(
	'pihole_state' => strpos(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh pihole status'), 'enabled') ? 'Enabled' : 'Disabled',
	'dhcp_server'  => $dhcp[0],
	'dhcp_begin'   => date('Y-m-d H:i:s', intval($dhcp[1])),
	'dhcp_expire' => date('Y-m-d H:i:s', intval($dhcp[1]) + intval($dhcp[2])),
));
