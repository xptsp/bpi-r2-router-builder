<?php
require_once("admin.php");

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
