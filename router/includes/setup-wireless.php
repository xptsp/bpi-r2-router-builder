<?php
require_once("subs/setup.php");
require_once("subs/dhcp.php");

#$_POST['action'] = 'scan';
#$_POST['sid'] = $_SESSION['sid'];
#$_POST['iface'] = 'mt7615_24g';
#$_POST['test'] = 'N';
#$_POST['hidden'] = 'N';

#################################################################################################
# Gather up the information we need to start this page:
#################################################################################################
$ifaces = explode("\n", @trim(@shell_exec("iw dev | grep Interface | awk '{print $2}' | sort")));
#echo '<pre>'; print_r($ifaces); exit;
$iface = isset($_GET['iface']) ? $_GET['iface'] : $ifaces[0];
#echo $iface; exit;
$options = parse_options();
#echo '<pre>'; print_r($options); exit;
$wifi = get_wifi_capabilities($iface);
#echo '<pre>'; print_r($wifi); echo '</pre>'; exit();

#################################################################################################
# If we are not doing the submission action, then skip this entire block of code:
#################################################################################################
if (isset($_POST['action']))
{
	#################################################################################################
	# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
	#################################################################################################
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

	#################################################################################################
	# Validate the actions, then do any DHCP actions as requested by the caller:
	#################################################################################################
	$action = $_POST['action'] = option_allowed('action', get_dhcp_actions(array('disabled', 'client_dhcp', 'client_static', 'ap', 'scan')));
	do_dhcp_actions();

	#################################################################################################
	# Scan for Wireless Networks using the interface:
	#################################################################################################
	$iface   = option_allowed('iface', $ifaces);
	if ($action == 'scan')
	{
		$networks = array();
		$number = 0;
		$cmd = '/opt/bpi-r2-router-builder/helpers/router-helper.sh iface ' . (option("test") == "N" ? 'scan ' . $iface : 'scan-test');
		#echo '<pre>'; print_r(explode("\n", trim(@shell_exec($cmd)))); exit;
		foreach (explode("\n", trim(@shell_exec($cmd))) as $id => $line)
		{
			if (preg_match("/^BSS ([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/", $line))
				$number++;
			else if (preg_match('/SSID: (.*)/', $line, $regex))
				$networks[ $number ]['ssid'] = trim($regex[1]);
			else if (preg_match('/DS Parameter set\: channel (\d+)/', $line, $regex))
				$networks[ $number ]['channel'] = $regex[1];
			else if (preg_match('/signal: (-?[0-9\.]+ dBm)/', $line, $regex))
				$networks[ $number ]['signal'] = $regex[1];
			else if (preg_match('/freq: ([\d+\.]+)/', $line, $regex))
				$networks[ $number ]['freq'] = ((int) $regex[1]) / 1000;
		}
		#echo '<pre>'; print_r($networks); exit;
		$hidden = option("hidden") == "Y";
		echo
		'<table class="table table-striped table-sm">',
			'<thead>',
				'<tr>',
					'<th>SSID</th>',
					'<th width="15%"><center>Channel</center></th>',
					'<th width="15%"><center>Frequency</center></th>',
					'<th width="15%"><center>Signal<br />Strength</center></th>',
					'<th>&nbsp;</th>',
				'</tr>',
			'</thead>',
			'<tbody>';
		foreach ($networks as $network)
		{
			if ($hidden || !empty($network['ssid']))
				echo
				'<tr>',
					'<td class="network_name">', empty($network['ssid']) ? '<i>(No SSID broadcast)</i>' : $network['ssid'], '</td>',
					'<td><center>', $network['channel'], '</center></center></td>',
					'<td><center>', number_format($network['freq'], 3), ' GHz</center></center></td>',
					'<td><center><img src="/img/wifi_', network_signal_strength($network['signal']), '.png" width="24" height="24" title="Signal Strength: ', $network['signal'], '" /></center></td>',
					'<td><a href="javascript:void(0);"><button type="button" class="use_network btn btn-sm bg-primary float-right">Use</button></a></td>',
				 '</tr>';
		}
		echo
			'</tbody>',
		'</table>';
		die();
	}

	#################################################################################################
	# Validate the input sent to this script (we paranoid... for the right reasons, of course...):
	#################################################################################################
	if ($action == 'client_static' || $action == 'ap')
	{
		$ip_addr    = option_ip('ip_addr');
		$ip_mask    = option_ip('ip_mask');
		$ip_gate    = option_ip('ip_gate');
	}
	if ($action == 'client_dhcp' || $action == 'client_static')
	{
		$wpa_ssid   = option('wpa_ssid', '/[\w\d\s\_\-]+/');
		$wpa_psk    = option('wpa_psk', '/([\w\d\s\_\-]{8,63}|)/');
		$firewalled = option("firewalled");
	}
	if ($action == 'ap')
	{
		$dhcp_start = option_ip("dhcp_start");
		$dhcp_end   = option_ip("dhcp_end");
		$dhcp_lease = option("dhcp_lease", "/(infinite|(\d+)[m|h|d|w|])/");
		$ap_ssid    = option('ap_ssid', '/[\w\d\s\_\-]+/');
		$ap_psk     = option('ap_psk', '/([\w\d\s\_\-]{8,63}|)/');
		$ap_band    = option_allowed('ap_band', array_keys($wifi['band']));
		$ap_channel = option_allowed('ap_channel', array_merge(array(0), array_keys($wifi['band'][$ap_band]['channels'])));
		$ap_hide    = option('ap_hide') == "Y" ? 1 : 0;
		if ($wifi['band'][$ap_band]['channels']['first'] < 36)
		{
			$ap_mode = option_allowed('ap_mode', array('b', 'g', 'n'));
			$n_mode  = $ap_mode == 'n';
			$ac_mode = false;
			$ap_mode = $ap_mode == 'n' ? 'g' : $ap_mode;
		}
		else
		{
			$ap_mode = option_allowed('ap_mode', array('a', 'n', 'ac'));
			$ac_mode = $ap_mode == 'ac';
			$n_mode  = $ap_mode == 'n' || $ap_mode == 'ac';
			$ap_mode = 'a';
		}
	}

	#################################################################################################
	# Shut down the wireless interface right now, before modifying the configuration:
	#################################################################################################
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface ifdown " . $iface);

	#################################################################################################
	# Validate AP options, then configure the hostapd configuration file for the interface:
	#################################################################################################
	if ($action == "ap")
	{
		$text  = 'interface=' . $iface . "\n";				# Interface we are using for this access point
		$text .= 'driver=nl80211' . "\n";					# Driver used (usually "nl80211")
		$text .= 'ssid=' . $ap_ssid . "\n";					# Name of SSID we are creating
		$text .= 'ignore_broadcast_ssid=' . $ap_hide . "\n";# Setting to "1" hides the SSID.
		$text .= 'hw_mode=' . $ap_mode . "\n";				# "b" and "g" mean 2.4GHz.  "a" means 5GHz.
		$text .= 'channel=' . $ap_channel . "\n";			# Valid 2.4GHz channels range from 1 to 13.  Valid 5GHz range from 136 to 173.

		# Set to 1 to limit the frequencies used to those allowed in the country specified.
		$text .= 'ieee80211d=1' . "\n";
		$text .= 'country_code=' . $options['wifi_country'] . "\n";

		# Enable 802.11n/ac support as necessary:
		if ($n_mode)
		{
			$text .= 'ieee80211n=1' . "\n";					# Set to 1 for 802.11n support
			$text .= 'wmm_enabled=1' . "\n";				# QoS support, also required for full speed on 802.11n/ac/ax
		}
		if ($ac_mode)
			$text .= 'ieee80211ac=1' . "\n";				# Set to 1 for 802.11ac support (5GHz only)

		# Enable passphrase support if requested:
		if (!empty($ap_psk))
		{
			$text .= 'auth_algs=1' . "\n";					# Set to "1" for WPA/WPA2, "2" for WEP, or "3" for both WPA/WPA2 and WEP
			$text .= 'wpa=2' . "\n";						# 1=WPA, 2=WPA2, 3=both WPA and WPA2
			$text .= 'wpa_passphrase=' . $ap_psk . "\n";	# Passphrase for our access point
			$text .= 'wpa_key_mgmt=WPA-PSK' . "\n";
			$text .= 'wpa_pairwise=TKIP' . "\n";
			$text .= 'rsn_pairwise=CCMP' . "\n";
		}

		# Write the hostapd configuration file to disk:
		#echo '<pre>'; echo $text; exit;
		$handle = fopen("/tmp/" . $iface, "w");
		fwrite($handle, trim($text) . "\n");
		fclose($handle);
		@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface hostapd " . $iface);
	}

	#################################################################################################
	# Decide what the interface configuration text will look like:
	#################################################################################################
	$text  = 'auto ' . $iface . "\n";
	$text .= 'iface ' . $iface . ' inet ' . ($action == "disabled" ? 'manual' : ($action == 'client_static' || $action == 'ap' ? 'static' : 'dhcp')) . "\n";
	if ($action != "disabled" && $action != 'client_dhcp')
	{
		$text .= '    address ' . $ip_addr . "\n";
		$text .= '    netmask ' . $ip_mask . "\n";
		if (!empty($ip_gate) && $ip_gate != "0.0.0.0")
			$text .= '    gateway ' . $ip_gate . "\n";
	}
	if ($action == "client_dhcp" || $action == "client_static")
	{
		$text .= '    wpa_ssid "' . $wpa_ssid . '"' . "\n";
		if (!empty($wpa_psk))
			$text .= '    wpa_psk "' . $wpa_psk . '"' . "\n";
		$text .= '    masquerade yes' . "\n";
	}
	if (!empty($firewalled) && $action != "disabled")
		$text .= '    firewall yes' . "\n";
	if ($action == "ap")
		$text .= '    nohook wpa_supplicant' . "\n";

	#################################################################################################
	# Output the network adapter configuration to the "/tmp" directory:
	#################################################################################################
	#echo '<pre>'; echo $text; exit;
	$handle = fopen("/tmp/" . $iface, "w");
	fwrite($handle, trim($text) . "\n");
	fclose($handle);
	$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface move " . $iface);

	#################################################################################################
	# Output the DNSMASQ configuration file related to the network adapter:
	#################################################################################################
	if ($_POST['action'] == 'disabled' || $_POST['action'] == 'client_static' || $_POST['action'] == 'client_dhcp')
		$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp del " . $_POST['iface']);
	else
		$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp set " . $_POST['iface'] . " " . $ip_addr . " " . $dhcp_start . " " . $dhcp_end . ' ' . $dhcp_lease);
	if (!empty($tmp))
		die($tmp);

	#################################################################################################
	# Start the wireless interface and restart pihole-FTL:
	#################################################################################################
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface ifup " . $iface);
	if ($action == 'ap')
		@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh systemctl enable --now hostapd@" . $iface);
	else
		@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh systemctl disable --now hostapd@" . $iface);
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh pihole restartdns");
	die("OK");
}

########################################################################################################
# Determine what wireless interfaces exist on the system, then remove the AP0 interface if client-mode
#  is specified for the R2's onboard wifi:
########################################################################################################
$adapters = explode("\n", trim(@shell_exec("iw dev | grep Interface | awk '{print $2}'")));
#echo '<pre>'; print_r($adapters); exit();
$netcfg = get_mac_info($iface);
#echo '<pre>'; print_r($netcfg); exit;
$dhcp = parse_options('/etc/dnsmasq.d/' . $iface . '.conf');
$dhcp = explode(",", (isset($dhcp['dhcp-range']) ? $dhcp['dhcp-range'] : ''));
#echo '<pre>'; print_r($dhcp); exit();
$use_dhcp = !empty($dhcp[1]);
#echo (int) $use_dhcp; exit;
$ifcfg = parse_ifconfig($iface);
#echo '<pre>'; print_r($ifcfg); echo '</pre>'; exit();

########################################################################################################
# Main code for the page:
########################################################################################################
site_menu();
echo '
<div class="card card-primary">
    <div class="card-header p-0 pt-1">
		<ul class="nav nav-tabs">';
$init_list = array();
$URL = explode("?", $_SERVER['REQUEST_URI'])[0];
foreach ($ifaces as $tface)
{
	echo '
			<li class="nav-item">
				<a class="ifaces nav-link', $iface == $tface ? ' active' : '', '" href="', $URL, $tface == $ifaces[0] ? '' : '?iface=' . $tface, '">', $tface, '</a>
			</li>';
}
echo '
		</ul>
	</div>
	<div class="card-body">
		<input type="hidden" id="scan-test" value="', isset($_GET['test']) ? 'Y' : 'N', '">';

###################################################################################################
# List modes of operation for the interface:
###################################################################################################
echo '
		<div class="row">
			<div class="col-6">
				<label for="iface_mode">Mode of Operation:</label>
			</div>
			<div class="col-6">
				<select id="op_mode" class="form-control">
					<option value="disabled"', $netcfg['op_mode'] == 'manual' ? ' selected="selected"' : '', '>Not Configured</option>';
if (isset($wifi['supported']['AP']))
	echo '
					<option value="ap"' . ($netcfg['op_mode'] == 'static' && !isset($netcfg['wpa_ssid'])  ? ' selected="selected"' : '') . '>Access Point</option>';
if (isset($wifi['supported']['managed']))
	echo '
					<option value="client_dhcp"', $netcfg['op_mode'] == 'dhcp' && isset($netcfg['wpa_ssid']) ? ' selected="selected"' : '', '>Client Mode - Automatic Configuration (DHCP)</option>
					<option value="client_static"', $netcfg['op_mode'] == 'static' && isset($netcfg['wpa_ssid']) ? ' selected="selected"' : '', '>Client Mode - Static IP Address</option>';
echo '
				</select>
			</div>
		</div>';

###################################################################################################
# Client SSID, password and firewalled setting:
###################################################################################################
$wpa_ssid = preg_match('/wpa_ssid\s+\"(.+)\"/', isset($netcfg['wpa_ssid']) ? $netcfg['wpa_ssid'] : '', $regex) ? $regex[1] : '';
#echo '<pre>'; print_r($wpa_ssid); exit;
$wpa_psk = preg_match('/wpa_psk\s+\"(.+)\"/', isset($netcfg['wpa_psk']) ? $netcfg['wpa_psk'] : '', $regex) ? $regex[1] : '';
#echo '<pre>'; print_r($wpa_psk); exit;
echo '
		<div id="client_mode_div"', ($netcfg['op_mode'] == 'dhcp' && !empty($wpa_ssid)) || ($netcfg['op_mode'] == 'static' && !empty($wpa_ssid)) ? '' : ' class="hidden"', '>
			<hr style="border-width: 2px" />
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_addr">Network Name (SSID):</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="wpa_ssid" type="text" class="form-control" placeholder="Required" value="', $wpa_ssid, '">
						<div class="input-group-prepend">
							<a href="javascript:void(0);"><button type="button" class="btn btn-primary" id="wifi_scan">Scan</button></a>
						</div>
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_mask">Passphrase:</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend wpa_toggle">
							<span class="input-group-text"><i class="fas fa-eye"></i></span>
						</div>
						<input type="password" class="form-control" id="wpa_psk" name="wpa_psk" value="', $wpa_psk, '">
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-12">
					<div class="icheck-primary">
						<input type="checkbox" id="firewalled"', isset($netcfg['firewall']) ? ' checked="checked"' : '', '>
						<label for="firewalled">Firewall Interface from Internet</label>
					</div>
				</div>
			</div>
		</div>';

###################################################################################################
# Access Point SSID, password and firewalled setting:
###################################################################################################
$host = parse_options('/etc/hostapd/' . $iface . '.conf');
#echo '<pre>'; print_r($host); echo '</pre>'; exit();
$hw_mode = isset($host['hw_mode']) ? $host['hw_mode'] : '';
#echo '<pre>'; print_r($hw_mode); exit;
$five_ghz = $hw_mode == "a";
#echo '<pre>'; print_r($five_ghz); exit;
$channel = isset($host['channel']) ? $host['channel'] : 0;
#echo '<pre>'; print_r($channel); exit;
$hw_mode = isset($host['hw_mode']) ? $host['hw_mode'] : '';
#echo '<pre>'; print_r($channel); exit;
$n_mode = isset($host['ieee80211n']) ? ($host['ieee80211n'] == 1) : false;
#echo '<pre>'; print_r($hw_mode); exit;
$ac_mode = isset($host['ieee80211ac']) ? ($host['ieee80211ac'] == 1) : false;
#echo '<pre>'; print_r($ac_mode); exit;
$no_broadcast = isset($host['ignore_broadcast_ssid']) ? $host['ignore_broadcast_ssid'] : 0;
#echo '<pre>'; print_r($no_broadcast); exit;
echo '
		<div id="ap_mode_div"', $netcfg['op_mode'] == 'ap' ? '' : ' class="hidden"', '>
			<hr style="border-width: 2px" />
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_addr">Network Name (SSID):</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="ap_ssid" type="text" class="form-control" placeholder="Required" value="', isset($host['ssid']) ? $host['ssid'] : '', '">
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_mask">Passphrase:</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend wpa_toggle">
							<span class="input-group-text"><i class="fas fa-eye"></i></span>
						</div>
						<input id="ap_psk" type="password" class="form-control" value="', isset($host['wpa_passphrase']) ? $host['wpa_passphrase'] : '', '">
					</div>
				</div>
			</div>
			<div class="row', count($wifi['band']) == 1 ? ' hidden' : '', '" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_mask">Wireless Band:</label>
				</div>
				<div class="col-6">
					<select id="ap_band" class="form-control">';
$band_used = false;
foreach ($wifi['band'] as $band => $info)
{
	$band_used = in_array($channel, array_keys($info['channels'])) ? $band : $band_used;
	echo '
						<option value="', $band, '" ', (count($wifi['band']) == 1 || ($five_ghz && $channel >= 36)) ? ' selected="selected"' : '', '>', $info['channels']['first'] >= 36 ? '5 GHz' : '2.4 GHz', '</option>';
}
echo '
					</select>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_mask">Wireless Channel:</label>
				</div>
				<div class="col-6">';
foreach ($wifi['band'] as $band => $info)
{
	echo '
					<select class="form-control bands band_', $band, $band_used == $band ? '' : ' hidden', '" id="ap_channel_', $band, '">
						<option value="0"', $channel == 0 ? ' selected="selected"' : '', '>Auto-Configure</option>';
	foreach ($info['channels'] as $ichannel => $text)
		if ($ichannel != 'first')
			echo '
						<option value="', $ichannel, '"', $ichannel == $channel ? ' selected="selected"' : '', '>', $text, '</option>';
	echo '
					</select>';
}
echo '
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_mask">Wifi Network Mode:</label>
				</div>
				<div class="col-6">';
foreach ($wifi['band'] as $band => $info)
{
	if ($info['channels']['first'] < 36)
		echo '
					<select class="form-control bands band_', $band, $band_used == $band ? '' : ' hidden', '" id="ap_mode_', $band, '">
						<option value="n"', ($hw_mode == 'g' and  $n_mode) ? ' selected="selected"' : '', '>Wireless-N mode</option>
						<option value="g"', ($hw_mode == 'g' and !$n_mode) ? ' selected="selected"' : '', '>Wireless-G mode</option>
						<option value="b"', ($hw_mode == 'b' and !$n_mode) ? ' selected="selected"' : '', '>Wireless-B mode</option>
					</select>';
	else
		echo '
					<select class="form-control bands band_', $band, $band_used == $band ? '' : ' hidden', '" id="ap_mode_', $band, '">
						<option value="ac"', ($hw_mode == 'g' and  $n_mode &&  $ac_mode) ? ' selected="selected"' : '', '>Wireless-AC mode</option>
						<option value="n"',  ($hw_mode == 'a' and  $n_mode && !$ac_mode) ? ' selected="selected"' : '', '>Wireless-N mode</option>
						<option value="a"',  ($hw_mode == 'a' and !$n_mode) ? ' selected="selected"' : '', '>Wireless-A mode</option>
					<select>';
}
echo '
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-12">
					<div class="icheck-primary">
						<input type="checkbox" id="ap_hide"', $no_broadcast ? ' checked="checked"' : '', '>
						<label for="ap_hide">Hide Network SSID</label>
					</div>
				</div>
			</div>
		</div>';

###################################################################################################
# Interface IP Address section
###################################################################################################
$subnet = isset($ifcfg['inet']) ? $ifcfg['inet'] : '';
$default = "192.168." . strval( (int) trim(@shell_exec("iw dev " . $iface . " info | grep ifindex | awk '{print \$NF}'")) + 10 ) . ".1";
$subnet = empty($subnet) ? $default : $subnet;
echo '
		<div id="static_ip_div"', ($netcfg['op_mode'] == 'ap' || ($netcfg['op_mode'] == 'static' && isset($netcfg['wpa_ssid']))) ? '' : ' class="hidden"', '>
			<hr style="border-width: 2px" />
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_addr">IP Address:</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="ip_addr" type="text" class="ip_address form-control" value="', $subnet, '" data-inputmask="\'alias\': \'ip\'" data-mask>
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_mask">IP Subnet Mask:</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="ip_mask" type="text" class="ip_address form-control" value="', isset($ifcfg['netmask']) ? $ifcfg['netmask'] : '255.255.255.0', '" data-inputmask="\'alias\': \'ip\'" data-mask>
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_gate">IP Gateway Address:</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="ip_gate" type="text" class="ip_address form-control" value="', isset($netcfg['gateway']) ? $netcfg['gateway'] : '0.0.0.0', '" data-inputmask="\'alias\': \'ip\'" data-mask>
					</div>
				</div>
			</div>';

###################################################################################################
# DHCP Settings and IP Range, plus IP Address Reservation section
###################################################################################################
dhcp_reservations_settings(true);

###################################################################################################
# Page footer
###################################################################################################
echo '
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" id="apply_reboot" class="btn btn-success float-right hidden" data-toggle="modal" data-target="#reboot-modal" id="reboot_button">Apply and Reboot</button></a>
		<a href="javascript:void(0);"><button type="button" id="apply_changes" class="btn btn-success float-right">Apply Changes</button></a>
		<a id="add_reservation_href" href="javascript:void(0);"', !$use_dhcp || $netcfg['op_mode'] == 'dhcp' || isset($netcfg['wpa_ssid']) ? ' class="hidden"' : '', '><button type="button" id="add_reservation" class="dhcp_div btn btn-primary"><i class="fas fa-plus"></i>&nbsp;&nbsp;Add</button></a>
	</div>
	<!-- /.card-body -->
</div>';

#######################################################################################################
# Scan Wireless Network modal:
#######################################################################################################
echo '
<div class="modal fade" id="scan-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Wireless Networks Found</h4>
				<div class="icheck-primary">
					<input type="checkbox" id="show_hidden">
					<label for="show_hidden">Show Hidden</label>
				</div>
			</div>
			<div class="modal-body">
				<p id="scan_data"></p>
			</div>
			<div class="modal-footer justify-content-between">
				<a href="javascript:void(0);"><button type="button" class="btn btn-default bg-primary" id="scan_close" data-dismiss="modal">Close</button></a>
				<a href="javascript:void(0);"><button type="button" class="btn btn-default bg-primary float-right" id="scan_refresh">Refresh</button></a>
			</div>
		</div>
		<!-- /.modal-content -->
	</div>
	<!-- /.modal-dialog -->
</div>';

#######################################################################################################
# Close the page:
#######################################################################################################
dhcp_reservations_modals();
apply_changes_modal('Please wait while the wireless interface is being configured....', true);
reboot_modal();
site_footer('Init_Wireless("' . $iface . '");');
