<?php
if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}

#################################################################################################
# Validate the input sent to this script (we paranoid... for the right reasons, of course...):
#################################################################################################
$hostname = isset($_POST['hostname']) ? $_POST['hostname'] : '';
if (!preg_match("/^([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)$/", $hostname))
	die("[HOSTNAME] ERROR: " . $_POST['hostname'] . " is not a valid hostname");

# Our local CloudFlare DoH "servers" use "127.0.0.1#" and a port number.  Validate each part seperately:
$parts = explode("#", isset($_POST['dns1']) ? $_POST['dns1'] : '');
if (!filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	die('[DNS1] ERROR: "' . $parts[0] . '" is an invalid IPv4 address!');
if (isset($parts[1]) && (!is_numeric($parts[1]) || $parts[1] < 5051 || $parts[1] > 5053))
	die('[DNS1] ERROR: "' . $parts[1] . '" is an invalid port number!');

$_POST['dns2'] = isset($_POST['dns2']) ? $_POST['dns2'] : '';
if (!filter_var($_POST['dns2'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	die('[DNS2] ERROR: "' . $_POST['dns2'] . '" is an invalid IPv4 address!');

$_POST['mac'] = isset($_POST['mac']) ? $_POST['mac'] : '';
if (!filter_var($_POST['mac'], FILTER_VALIDATE_MAC))
	die('[MAC] ERROR: "' . $_POST['mac'] . '" is an invalid MAC address!');

#echo '<pre>'; print_r($_POST); echo '</pre>'; exit;

#################################################################################################
# Change the DNS servers by calling the router-helper script:
#################################################################################################
@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh hostname " . $_POST["hostname"]);
@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dns " . $_POST['dns1'] . " " . $_POST['dns2']);
@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh mac " . $_POST['mac']);
echo "OK";
