<?php
require_once("manage.php");

function get_network_adapters_list($get_bridged = true)
{
	$out = array('wan' => 'wan');
	foreach (glob("/sys/class/net/*") as $iface)
	{
		$base = basename($iface);
		$found = false;
		foreach (explode("\n", @file_get_contents("/etc/network/interfaces.d/" . $base)) as $line)
		{
			if ($found = preg_match('/bridge_ports\s+(.*)/', $line, $regex))
				break;
		}
		if (!$found)
			$out[$base] = $base;
	}
	return $out;
}

function device_name($mac)
{
	global $leases;
	foreach ($leases as $id => $lease)
	{
		if (isset($lease[3]) && $mac == strtoupper($lease[1]))
			return $lease[3];
	}
	return "Unknown";
}

function get_invalid_adapters($iface)
{
	global $ifaces;
	$arr = array();
	foreach ($ifaces as $tface => $bound)
	{
		if ($tface != $iface)
			$arr = array_merge($bound, $arr);
	}
	return $arr;
}

function timezone_list()
{
    $timezones = [];
    $offsets = [];
    $now = new DateTime('now', new DateTimeZone('UTC'));

    foreach (DateTimeZone::listIdentifiers() as $timezone) {
        $now->setTimezone(new DateTimeZone($timezone));
        $offsets[] = $offset = $now->getOffset();
        $timezones[$timezone] = '(' . format_GMT_offset($offset) . ') ' . format_timezone_name($timezone);
    }
    array_multisort($offsets, $timezones);
    return $timezones;
}

function format_GMT_offset($offset)
{
    $hours = intval($offset / 3600);
    $minutes = abs(intval($offset % 3600 / 60));
    return 'GMT' . ($offset ? sprintf('%+03d:%02d', $hours, $minutes) : '');
}

function format_timezone_name($name)
{
    $name = str_replace('/', ', ', $name);
    $name = str_replace('_', ' ', $name);
    $name = str_replace('St ', 'St. ', $name);
    return $name;
}

function get_os_locales()
{
	$lang = null;
	$locales = array();
	foreach (explode("\n", trim(@shell_exec("locale -v -a"))) as $line)
	{
		if (preg_match("/locale\:\s([^\s]*)\s+/", $line, $matches))
			$lang = $matches[1];
		else if (preg_match("/language \| (.*)/", $line, $matches) && $lang != "C.UTF-8")
			$locales[$lang] = $matches[1];
	}
	return $locales;
}

################################################################################################
# Function that returns which image to use to indicate wifi network signal strength:
################################################################################################
function network_signal_strength($signal)
{
	#############################################################################################
	# Signal strengths as shown by https://cellboosteronline.com/measure-signal-strength/:
	# 	4: -60 or greater = Excellent
	#   3: -60 to -75     = Above Average
	#   2: -76 to -90     = Average
	#   1: -91 to -100    = Fair
	#   0: -100 to -110   = Weak
	#   N: Less than -110 = No Signal
	#############################################################################################
	return $signal > -60 ? '4' : ($signal > -75 ? '3' : ($signal > -90 ? '2' : ($signal > -100 ? '1' : ($signal > -110 ? '0' : 'N'))));
}

################################################################################################
# Function that returns information about the specified physical interface:
################################################################################################
function get_wifi_capabilities($iface)
{
	################################################################################################
	# Determine which physical wireless interface the specified interface is on:
	################################################################################################
	$phys = $num = -1;
	foreach (explode("\n", trim(@shell_exec("iw dev | egrep 'phy|Interface'"))) as $line)
	{
		if (preg_match("/^phy\#(\d+)/", trim($line), $regex))
			$num = $regex[1];
		else if (preg_match("/Interface ([\w\d\_]+)/", trim($line), $regex) && $iface == $regex[1])
			$phys = $num;
	}
	if ($phys == -1)
		return array();

	################################################################################################
	# Parse the output of "iw list" to determine the capabilities of the physical wireless device:
	################################################################################################
	$found = false;
	$info = array();
	foreach (explode("\n", trim(@shell_exec("iw list"))) as $line)
	{
		if (!$found)
			$found = preg_match('/Wiphy phy' . $phys . '/', trim($line));
		else
		{
			if (preg_match('/Wiphy phy(\d+)/', $line))
			{
				$found = false;
				break;
			}
			else if (preg_match("/Band (\d+)/", $line, $regex))
			{
				$info['band'][$regex[1]] = array();
				$band = &$info['band'][$regex[1]];
			}
			else if (preg_match("/Frequencies/", $line))
				$current = &$band;
			else if (preg_match('/\*\s+(\d+) MHz \[(\d+)\]\s+?\(([\d\.]+ dBm)\)(.*)/', $line, $regex) && trim($regex[4]) == '')
				$current['channels'][ $regex[2] ] = 'Channel ' . $regex[2] . ' (' . number_format($regex[1] / 1000, 3) . ' GHz)';
			else if (preg_match("/Supported interface modes/", $line))
				$info['supported'] = array();
			else if (preg_match('/\* (IBSS|managed|AP\/VLAN|AP|monitor|P2P-client|P2P-GO)$/', $line, $regex))
				$info['supported'][$regex[1]] = true;
			else if (preg_match("/Bitrates \(non-HT\)/", $line))
				$band['bitrates'] = array();
			else if (preg_match("/\* ([\d\.]+) Mbps/", $line, $regex))
				$band['bitrates'][ $regex[1] ] = $regex[1] . ' Mbps';
		}
	}
	return $info;
}
