<?php
require_once("subs/manage.php");
#$_GET['sid'] = $_SESSION['sid'];

#################################################################################################
# If SID is specified, retrieve the information and statistics for the router's home page:
#################################################################################################
if (isset($_GET['sid']))
{
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

	##########################################################################################
	# Insert hardware statistics information into array:
	##########################################################################################
	$arr = array(
		'load0' => number_format((float)$load[0], 2),
		'load1' => number_format((float)$load[1], 2),
		'load2' => number_format((float)$load[2], 2),
		'temp' => number_format((float) @file_get_contents('/sys/devices/virtual/thermal/thermal_zone0/temp') / 1000, 1),
		'system_uptime' => system_uptime(),
		'server_time' => date('H:i:s'),
		'server_date' => date('Y-m-d'),
		'lan_count' => 0,
		'usb_count' => 0,
	);

	##########################################################################################
	# Get root disk usage:
	##########################################################################################
	$arr['root_usage'] = $_SESSION['root_usage_ext'];

	##########################################################################################
	# Get root usage, memory usage, swap usage, and number of local users:
	##########################################################################################
	$data = trim(@shell_exec("/etc/update-motd.d/10-sysinfo"));
	#echo '<pre>'; print_r($data); exit;
	if (preg_match("/Memory usage:\t([^\t\n\%]*)/", $data, $regex))
		$arr['mem_usage'] = $regex[1];
	if (preg_match("/Swap usage:\t([^\t\n\%]*)/", $data, $regex))
		$arr['swap_usage'] = $regex[1];
	if (preg_match("/Local Users:\t([^\t\n]*)/", $data, $regex))
		$arr['local_users'] = $regex[1];
	if (preg_match("/Processes:\t([^\t\n]*)/", $data, $regex))
		$arr['processes'] = $regex[1];
	if (preg_match("/Usage on \/:\t([^\t\n]*) (\([^\)]*\))/", $data, $regex))
	{
		$arr['root_usage'] = $regex[1];
		$arr['root_space'] = $regex[2];
	}

	##########################################################################################
	# Get the number of domains blocked by our adblock script:
	##########################################################################################
	if (empty($_SESSION['pihole_addr']))
		$_SESSION['pihole_addr'] = trim(@shell_exec("ifconfig br0:1 | grep inet | awk '{print $2}'"));
	$pihole = @json_decode( @file_get_contents( "http://" . $_SESSION['pihole_addr'] . "/admin/api.php?summary" ) );

	##########################################################################################
	# Insert Pi-Hole statistics information into array:
	##########################################################################################
	$arr['dns_queries_today'] = isset($pihole->dns_queries_today) ? $pihole->dns_queries_today : 0;
	$arr['ads_blocked_today'] = isset($pihole->ads_blocked_today) ? $pihole->ads_blocked_today : 0;
	$arr['ads_percentage_today'] = isset($pihole->ads_percentage_today) ? $pihole->ads_percentage_today : 0;
	$arr['domains_being_blocked'] = isset($pihole->domains_being_blocked) ? $pihole->domains_being_blocked : 0;

	##########################################################################################
	# Parse the dnsmasq.leases file into the "devices" element of the array:
	##########################################################################################
	$ifaces = $tmp = $leases = array();
	foreach (explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases"))) as $num => $line)
	{
		$temp = explode(" ", preg_replace("/\s+/", " ", $line));
		$leases[] = array(
			'lease_expires' => $temp[0],
			'mac_address' => $temp[1],
			'ip_address' => $temp[2],
			'machine_name' => $temp[3],
		);
	}
	$arr['lan_count'] = number_format(count($leases));

	##########################################################################################
	# Return status of internet-facing interfaces:
	##########################################################################################
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
			if (!file_exists("/sys/class/net/" . $iface . "/operstate"))
				$status = 'Disconnected';
			else if (trim(file_get_contents("/sys/class/net/" . $iface . "/operstate")) == "down")
				$status = 'Down';
			else
				$status = strpos(@shell_exec('ping -I ' . $iface . ' -c 1 -W 1 8.8.8.8'), '1 received') > 0 ? 'Online' : 'Offline';
			$arr['status'] .= show_interface_status($iface, $status, '/setup/wire' . ($wifi ? 'less' : 'd') . '?iface=' . $iface, $wifi ? 'fa-wifi' : 'fa-ethernet');
		}
	}

	##########################################################################################
	# Return status of access-point interfaces:
	##########################################################################################
	foreach ($ifaces as $iface => $config)
	{
		$show = $addr = false;
		foreach ($config as $line)
		{
			$addr = preg_match("/address ((\d+)\.(\d+)\.(\d+)\.)/", $line, $regex) ? $regex[1] : $addr;
		}
		if (trim(@shell_exec("systemctl is-active hostapd@" . $iface)) == "active")
		{
			$if = parse_ifconfig($iface);
			$status = strpos($if['brackets'], 'RUNNING') === false ? 'Down' : access_point_status($iface, $addr, $leases);
			$arr['status'] .= show_interface_status($iface, $status, '/setup/wireless?iface=' . $iface, 'fa-wifi');
		}
	}

	##########################################################################################
	# Get the number of samba shares:
	##########################################################################################
	$arr['usb_count'] = -1;
	foreach (explode("\n", trim(@file_get_contents("/etc/samba/smb.conf"))) as $line)
	{
		if ($line == ';   write list = root, @lpadmin' || ($arr['usb_count'] > -1 && preg_match("/^path=(.*)/", $line, $regex)))
			$arr['usb_count']++;
	}
	$arr['usb_count'] = number_format(max(0, $arr['usb_count']));

	##########################################################################################
	# Output the resulting array:
	##########################################################################################
	header('Content-type: application/json');
	die(json_encode($arr));
}

#######################################################################################################
# Start the page:
#######################################################################################################
unset($_SESSION['last_check']);
site_menu('<span class="float-right">Refresh <input type="checkbox" id="refresh_switch" checked="checked" data-bootstrap-switch></span>');
echo '
<div class="row mb-2">
	<div class="col-sm-12">
		<h3>Statistics</h1>
	</div>
</div>';

#######################################################################################################
# Display system temperature:
#######################################################################################################
echo '
<div class="row">
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fas fa-thermometer"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Temperature</span>
				<span class="info-box-number"><span id="temp">&nbsp;</span><small>&deg; C</small></h4>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display memory usage:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fas fa-memory"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Memory Usage:</span>
				<span class="info-box-number"><span id="mem_usage">&nbsp;</span><small>%</small></h4>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display root usage:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="far fa-hdd"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Root Usage:</span>
				<span class="info-box-number"><span id="root_percent">&nbsp;</span> <small id="root_used">&nbsp;</small></h4>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display number of local users:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Local Users:</span>
				<span class="info-box-number"><span id="local_users">&nbsp;</span></h4>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display system uptime:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fas fa-stopwatch"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">System Uptime:</span>
				<span class="info-box-number"><span id="system_uptime">&nbsp;</span></span>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display system load:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fas fa-truck-loading"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Average Load:</span>
				<span class="info-box-number">
					<span id="load0">&nbsp;</span>,
					<span id="load1">&nbsp;</span>,
					<span id="load2">&nbsp;</span>
				</span>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display swap usage:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fas fa-exchange-alt"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Swap Usage:</span>
				<span class="info-box-number"><span id="swap_usage">&nbsp;</span><small>%</small></span>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display swap usage:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fas fa-microchip"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Processes:</span>
				<span class="info-box-number"><span id="processes">&nbsp;</span></span>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display current server date:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="far fa-calendar-alt"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Server Date:</span>
				<span class="info-box-number"><span id="server_date">&nbsp;</span></span>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display current server time:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fas fa-clock"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Server Time:</span>
				<span class="info-box-number"><span id="server_time">&nbsp;</span></span>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display number of samba shares:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fab fa-usb"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Samba Shares:</span>
				<span class="info-box-number"><span id="usb-sharing">&nbsp;</span></span>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display number of attached devices:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fas fa-laptop-house"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Attached Devices:</span>
				<span class="info-box-number"><span id="num_of_devices">&nbsp;</span></span>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display number of domains blocked by our adblocking script:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fab fa-raspberry-pi"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Domains Blocked:</span>
				<span class="info-box-number"><span id="domains-blocked">&nbsp;</span></span>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display number of DNS queries today:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fab fa-raspberry-pi"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">DNS Queries Today:</span>
				<span class="info-box-number"><span id="dns_queries_today">&nbsp;</span></span>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display number of ads blocked today:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fab fa-raspberry-pi"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Ads Blocked Today:</span>
				<span class="info-box-number"><span id="ads_blocked_today">&nbsp;</span></span>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display number of ads blocked today:
#######################################################################################################
echo '
	<div class="col-12 col-sm-6 col-md-3">
		<div class="info-box">
			<span class="info-box-icon bg-info elevation-1"><i class="fab fa-raspberry-pi"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Ads Percentage:</span>
				<span class="info-box-number"><span id="ads_percentage_today">&nbsp;</span>%</span>
			</div>
		</div>
	</div>';

#######################################################################################################
# Display any AP and internet-facing interface statuses:
#######################################################################################################
echo '
</div>
<div class="row">
	<div class="col-sm-12">
		<h3>Network Interfaces</h3>
	</div>
</div>
<div class="row" id="connectivity-text">
</div>';

#######################################################################################################
# Close this page, including the AJAX call to get information:
#######################################################################################################
site_footer('Init_Home();');
