<?php
if (!isset($_GET['sid']) || $_GET['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	die;
}
header('Content-type: application/json');
$dhcp = explode(' ', @shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp-info') . " 0 0");

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
