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
	# Return status of access-point interfaces:
	##########################################################################################
	foreach (glob('/etc/network/interfaces.d/*') as $file)
	{
		$iface = basename($file);
		$show = false;
		foreach ($ifaces[$iface] as $line)
			$show |= preg_match("/accesspoint (.*)/", $line);
/*
		if ($show)
		{
			$if = parse_ifconfig($iface);
			if (strpos($if['brackets'], 'RUNNING') === false)
				$status = 'Disconnected';
			else
				$status = strpos(@shell_exec('ping -I ' . $iface . ' -c 1 -W 1 8.8.8.8'), '1 received') > 0 ? 'Online' : 'Offline';
			$arr['status'] .= show_interface_status($iface, $status, '/setup/wire' . ($wifi ? 'less' : 'd') . ($iface != 'wan' ? '?iface=' . $iface : ''), $wifi ? 'fa-wifi' : 'fa-ethernet');
		}
*/
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
	# Get the number of mounted USB devices:
	##########################################################################################
	foreach (glob('/etc/samba/smb.d/*.conf') as $file)
	{
		foreach (explode("\n", trim(@file_get_contents($file))) as $line)
		{
			if (preg_match("/path=(\/media\/.*)/", $line, $regex))
				$arr['usb_devices'][basename($file)]['path'] = $regex[1];
			if (preg_match("/\#mount_dev=(.*)/", $line, $regex))
				$arr['usb_devices'][basename($file)]['mount_dev'] = $regex[1];
		}
	}
	$arr['usb_count'] = count($arr['usb_devices']);

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
echo '
<div class="row">';

#######################################################################################################
# Display number of attached devices:
#######################################################################################################
echo '
	<div class="col-md-4">
		<div class="small-box bg-indigo">
			<div class="overlay dark" id="devices-spinner">
				<i class="fas fa-2x fa-sync-alt fa-spin"></i>
			</div>
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
# Display USB drive sharing:
#######################################################################################################
$sharing = false;
echo '
	<div class="col-md-4">
		<div class="small-box bg-orange">
			<div class="inner">
				<p class="text-lg">USB Drive Sharing</p>
				<h3 id="usb-sharing">Disabled</span></h3>
			</div>
			<div class="icon">
				<i class="fab fa-usb"></i>
			</div>
			<a href="#" class="small-box-footer">
				USB Sharing Settings <i class="fas fa-arrow-circle-right"></i>
			</a>
		</div>
	</div>';

#######################################################################################################
# Display number of domains blocked by our adblocking script:
#######################################################################################################
echo '
	<div class="col-md-4">
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
<div class="row mb-2" id="connectivity-text">
</div>';

#######################################################################################################
# Display system overview
#######################################################################################################
echo '
<div class="row mb-2">
	<div class="col-sm-12">
		<h3>Hardware Stats</h1>
	</div>
</div>
<div class="row">
	<div class="col-md-3">
		<div class="card card-primary">
			<div class="card-header">
				<h3 class="card-title">
					<i class="fas ', $icon, '"></i>
					Temperature:
				</h3>
			</div>
			<div class="card-body centered text-lg">
				<span id="temp"></span>&deg; C
			</div>
			<div class="ribbon-wrapper ribbon-lg invisible" id="temp-danger">
				<div class="ribbon bg-danger text-lg">Danger!</div>
			</div>
		</div>
	</div>
	<div class="col-md-3">
		<div class="card card-primary">
			<div class="card-header">
				<h3 class="card-title">
					<i class="fas fa-truck-loading"></i>
					Average Load:
				</h3>
			</div>
			<div class="card-body centered text-lg">
					<span id="load0"></span>,
					<span id="load1"></span>,
					<span id="load2"></span>
			</div>
		</div>
	</div>
	<div class="col-md-3">
		<div class="card card-primary">
			<div class="card-header">
				<h3 class="card-title">
					<i class="fas fa-stopwatch"></i>
					System Uptime:
				</h3>
			</div>
			<div class="card-body centered text-lg" id="system_uptime">&nbsp;</div>
		</div>
	</div>
	<div class="col-md-3">
		<div class="card card-primary">
			<div class="card-header">
				<h3 class="card-title">
					<i class="fas fa-clock"></i>
					System Time:
				</h3>
			</div>
			<div class="card-body centered text-lg" id="server_time">&nbsp;</div>
		</div>
	</div>
</div>';

#######################################################################################################
# Close this page, including the AJAX call to get information:
#######################################################################################################
site_footer('Init_Home();');
