<?php
require_once("subs/manage.php");
#$_GET['sid'] = $_SESSION['sid'];

#################################################################################################
# If SID is specified, retrieve the information and statistics for the router's home page:
#################################################################################################
if (isset($_GET['sid']))
{
	#################################################################################################
	# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
	#################################################################################################
	if ($_GET['sid'] != $_SESSION['sid'])
		die('RELOAD');

	##########################################################################################
	# Set dark mode setting via toggle checkbox:
	##########################################################################################
	if (isset($_GET['dark_mode']))
	{
		$options = parse_options();
		$options['dark_mode'] = $_SESSION['dark_mode'] = option("dark_mode", "/^[Y|N]$/", false);
		apply_options('options');
		die('OK');
	}

	##########################################################################################
	# Get information for the AJAX request:
	##########################################################################################
	$load = sys_getloadavg();
	$temp = number_format((float) @file_get_contents('/sys/devices/virtual/thermal/thermal_zone0/temp') / 1000, 1);

	##########################################################################################
	# Insert hardware statistics information into array:
	##########################################################################################
	$arr = array(
		'load0' => number_format((float)$load[0], 2),
		'load1' => number_format((float)$load[1], 2),
		'load2' => number_format((float)$load[2], 2),
		'temp' => $temp,
		'temp_icon' => 'fa-thermometer-' . ($temp > 70 ? 'full' : ($temp > 60 ? 'three-quarters' : ($temp > 50 ? 'half' : ($temp > 40 ? 'quarter' : 'empty')))),
		'system_uptime' => system_uptime(),
		'server_time' => date('Y-m-d H:i:s'),
		'lan_devices' => array(),
		'lan_count' => 0,
		'usb_devices' => array(),
		'usb_count' => 0,
	);

	##########################################################################################
	# Get the number of domains blocked by our adblock script:
	##########################################################################################
	if (empty($_SESSION['pihole_json']))
		$_SESSION['pihole_json'] = @json_decode( @file_get_contents( "http://pi.hole/admin/api.php?summary" ) );
	$pihole = $_SESSION['pihole_json'];

	##########################################################################################
	# Insert Pi-Hole statistics information into array:
	##########################################################################################
	if (isset($pihole->unique_clients))
		$arr['unique_clients'] = $pihole->unique_clients;
	if (isset($pihole->dns_queries_today))
		$arr['dns_queries_today'] = $pihole->dns_queries_today;
	if (isset($pihole->ads_blocked_today))
		$arr['ads_blocked_today'] = $pihole->ads_blocked_today;
	if (isset($pihole->ads_percentage_today))
		$arr['ads_percentage_today'] = $pihole->ads_percentage_today;
	if (isset($pihole->domains_being_blocked))
		$arr['domains_being_blocked'] = $pihole->domains_being_blocked;

	##########################################################################################
	# Return status of internet-facing interfaces:
	##########################################################################################
	$ifaces = array();
	$arr['status'] = '';
	foreach (glob('/etc/network/interfaces.d/*') as $file)
	{
		$iface = basename($file);
		$show = $wifi = false;
		$ifaces[$iface] = explode("\n", trim(@file_get_contents($file)));
		foreach ($ifaces[$iface] as $line)
		{
			$wifi |= preg_match("/wpa_ssid (.*)/", $line);
			$show |= preg_match("/masquerade (.*)/", $line);
		}
		if ($show || $wifi)
		{
			$if = parse_ifconfig($iface);
			if (strpos($if['brackets'], 'RUNNING') === false)
				$status = 'Disconnected';
			else
				$status = strpos(@shell_exec('ping -I ' . $iface . ' -c 1 -W 1 8.8.8.8'), '1 received') > 0 ? 'Online' : 'Offline';
			$arr['status'] .= show_interface_status($iface, $status, '/setup/wire' . ($wifi ? 'less' : 'd') . ($iface != 'wan' ? '?iface=' . $iface : ''), $wifi ? 'fa-wifi' : 'fa-ethernet');
		}
	}

	##########################################################################################
	# Parse the dnsmasq.leases file into the "devices" element of the array:
	##########################################################################################
	foreach (explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases"))) as $num => $line)
	{
		$temp = explode(" ", preg_replace("/\s+/", " ", $line));
		$arr['lan_devices'][] = array(
			'lease_expires' => $temp[0],
			'mac_address' => $temp[1],
			'ip_address' => $temp[2],
			'machine_name' => $temp[3],
		);
	}
	$arr['lan_count'] = count($arr['lan_devices']);

	##########################################################################################
	# Return status of access-point interfaces:
	##########################################################################################
	foreach ($ifaces as $iface => $config)
	{
		$show = $addr = false;
		foreach ($config as $line)
		{
			$addr = preg_match("/address ((\d+)\.(\d+)\.(\d+)\.)/", $line, $regex) ? $regex[1] : $addr;
			$show |= preg_match("/post-up systemctl start hostapd@(.*)/", $line);
		}
		if ($show)
		{
			$if = parse_ifconfig($iface);
			$status = strpos($if['brackets'], 'RUNNING') === false ? 'Down' : access_point_status($iface, $addr, $arr['lan_devices']);
			$arr['status'] .= show_interface_status($iface, $status, '/setup/wireless?iface=' . $iface, 'fa-wifi');
		}
	}

	##########################################################################################
	# Get the number of samba shares:
	##########################################################################################
	$arr['usb_count'] = 0;
	foreach (glob('/etc/samba/smb.d/*.conf') as $file)
	{
		foreach (explode("\n", trim(@file_get_contents($file))) as $line)
		{
			if (preg_match("/path=(.*)/", $line, $regex))
				$arr['usb_count']++;
		}
	}

	##########################################################################################
	# Output the resulting array:
	##########################################################################################
	header('Content-type: application/json');
	die(json_encode($arr));
}

#######################################################################################################
# Start the page:
#######################################################################################################
site_menu(true);

#######################################################################################################
# Display system temperature:
#######################################################################################################
echo '
<div class="row mb-2">
	<div class="col-sm-12">
		<h3>Statistics</h1>
	</div>
</div>
<div class="row">
	<div class="col-md-3">
		<div class="small-box bg-info" id="temp_div">
			<div class="inner">
				<p class="text-lg">Temperature</p>
				<h4><span id="temp">&nbsp;</span>&deg; C</h4>
			</div>
			<div class="icon">
				<i class="fas fa-thermometer"></i>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display system load:
#######################################################################################################
echo '
	<div class="col-md-3">
		<div class="small-box bg-info">
			<div class="inner">
				<p class="text-lg">Average Load</p>
				<h4>
					<span id="load0">&nbsp;</span>,
					<span id="load1">&nbsp;</span>,
					<span id="load2">&nbsp;</span>
				</h4>
			</div>
			<div class="icon">
				<i class="fas fa-truck-loading"></i>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display system uptime:
#######################################################################################################
echo '
	<div class="col-md-3">
		<div class="small-box bg-info">
			<div class="inner">
				<p class="text-lg">System Uptime</p>
				<h4><span id="system_uptime">&nbsp;</span></h4>
			</div>
			<div class="icon">
				<i class="fas fa-stopwatch"></i>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display system current time:
#######################################################################################################
echo '
	<div class="col-md-3">
		<div class="small-box bg-info">
			<div class="inner">
				<p class="text-lg">System Time</p>
				<h4><span id="server_time">&nbsp;</span></h4>
			</div>
			<div class="icon">
				<i class="fas fa-clock"></i>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display number of attached devices:
#######################################################################################################
echo '
	<div class="col-md-3">
		<div class="small-box bg-info">
			<div class="inner">
				<p class="text-lg">Attached Devices</p>
				<h3 id="num_of_devices">&nbsp;</h3>
			</div>
			<div class="icon">
				<i class="fas fa-laptop-house"></i>
			</div>
			<a href="/manage/attached" class="small-box-footer">
				Device List <i class="fas fa-arrow-circle-right"></i>
			</a>
		</div>
	</div>';

#######################################################################################################
# Display number of samba shares:
#######################################################################################################
$sharing = false;
echo '
	<div class="col-md-3">
		<div class="small-box bg-info">
			<div class="inner">
				<p class="text-lg">Samba Shares</p>
				<h3 id="usb-sharing">&nbsp;</span></h3>
			</div>
			<div class="icon">
				<i class="fab fa-usb"></i>
			</div>
			<a href="#" class="small-box-footer">
				Samba Settings <i class="fas fa-arrow-circle-right"></i>
			</a>
		</div>
	</div>';

#######################################################################################################
# Display number of domains blocked by our adblocking script:
#######################################################################################################
echo '
	<div class="col-md-3">
		<div class="small-box bg-info">
			<div class="inner">
				<p class="text-lg">Bluetooth Status</p>
				<h3 id="empty-text">&nbsp;</span></h3>
			</div>
			<div class="icon">
				<i class="fab fa-bluetooth"></i>
			</div>
			<a href="http://pi.hole/admin/" class="small-box-footer">
				Bluetooth Settings <i class="fas fa-arrow-circle-right"></i>
			</a>
		</div>
	</div>';

#######################################################################################################
# Display number of domains blocked by our adblocking script:
#######################################################################################################
echo '
	<div class="col-md-3">
		<div class="small-box bg-info">
			<div class="inner">
				<p class="text-lg">Domains Blocked</p>
				<h3 id="domains-blocked">&nbsp;</span></h3>
			</div>
			<div class="icon">
				<i class="fab fa-raspberry-pi"></i>
			</div>
			<a href="http://pi.hole/admin/" class="small-box-footer">
				Pi-Hole Settings <i class="fas fa-arrow-circle-right"></i>
			</a>
		</div>
	</div>';

#######################################################################################################
# Display any AP and internet-facing interface statuses:
#######################################################################################################
echo '
</div>
<div class="row mb-2">
	<div class="col-sm-12">
		<h3>Network Interfaces</h1>
	</div>
</div>
<div class="row mb-2" id="connectivity-text">
</div>';

#######################################################################################################
# Close this page, including the AJAX call to get information:
#######################################################################################################
site_footer('Init_Home();');
