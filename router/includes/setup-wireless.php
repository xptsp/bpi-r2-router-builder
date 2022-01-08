<?php
require_once("subs/setup.php");
require_once("subs/dhcp.php");

#$_POST['action'] = 'scan';
#$_POST['sid'] = $_SESSION['sid'];
#$_POST['iface'] = 'mt7615_24g';
#$_POST['test'] = 'N';
#$_POST['hidden'] = 'N';

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
	$iface   = option_allowed('iface', explode("\n", trim(@shell_exec("iw dev | grep Interface | awk '{print $2}'"))) );
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
		$ip_addr = option_ip('ip_addr');
		$ip_mask = option_ip('ip_mask');
		$ip_gate = option_ip('ip_gate');
	}
	if ($action == 'client_dhcp' || $action == 'client_static')
	{
		$wpa_ssid = option('wpa_ssid', '/[\w\d\s\_\-]+/');
		$wpa_psk = option('wpa_psk', '/([\w\d\s\_\-]{8,63}|)/');
	}
	if ($action == 'ap')
	{
		$ap_ssid = option('ap_ssid', '/[\w\d\s\_\-]+/');
		$ap_psk = option('ap_psk', '/([\w\d\s\_\-]{8,63}|)/');
	}
	$firewalled = option("firewalled", "/^(Y|N)$/");

	#################################################################################################
	# Shut down the wireless interface right now, before modifying the configuration:
	#################################################################################################
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface ifdown " . $iface);

	if ($action == "ap")
	{
		$text  = 'interface=' . $iface . "\n";
		$text .= 'driver=nl80211' . "\n";
		$text .= 'ssid=' . $ap_ssid . "\n";
/*		$text .= 'hw_mode=' . $hw_mode . "\n";
		$text .= 'channel=' . $channel . "\n";
		$text 
#macaddr_acl=0
auth_algs=1
#ignore_broadcast_ssid=0
wpa=2
wmm_enabled=1
wpa_passphrase=SaltyCobra81
wpa_key_mgmt=WPA-PSK
wpa_pairwise=TKIP
rsn_pairwise=CCMP
*/
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
	if ($firewalled && $action != "disabled")
		$text .= '    firewall yes' . "\n";
	if ($action == "ap")
	{
		$text .= '    post-up systemctl start hostapd@' . $iface . "\n";
		$text .= '    pre-down systemctl stop hostapd@' . $fiace . "\n";
	}

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
	if ($_POST['action'] == 'disabled' || $_POST['action'] == 'client_static' || $_POST['action'] == 'client_dhcp' || $_POST['use_dhcp'] == "N")
		$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp del " . $_POST['iface']);
	else
		$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp set " . $_POST['iface'] . " " . $ip_addr . " " . $dhcp_start . " " . $dhcp_end . ' ' . $dhcp_lease);
	if ($tmp != "")
		die($tmp);

	#################################################################################################
	# Start the wireless interface and restart pihole-FTL:
	#################################################################################################
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface ifup " . $iface);
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh pihole restartdns");
	die("OK");
}

########################################################################################################
# Determine what wireless interfaces exist on the system, then remove the AP0 interface if client-mode
#  is specified for the R2's onboard wifi:
########################################################################################################
$ifaces = array();
$options = parse_options();
#echo '<pre>'; print_r($options); exit;
foreach (explode("\n", @trim(@shell_exec("iw dev | grep Interface | awk '{print $2}' | sort"))) as $tface)
	$ifaces[] = $tface;
#echo '<pre>'; print_r($ifaces); exit;
$iface = isset($_GET['iface']) ? $_GET['iface'] : $ifaces[0];
#echo $iface; exit;
$adapters = explode("\n", trim(@shell_exec("iw dev | grep Interface | awk '{print $2}'")));
#echo '<pre>'; print_r($adapters); exit();
$netcfg = get_mac_info($iface);
#echo '<pre>'; print_r($netcfg); exit;
$wpa_ssid = preg_match('/wpa_ssid\s+\"(.+)\"/', isset($netcfg['wpa_ssid']) ? $netcfg['wpa_ssid'] : '', $regex) ? $regex[1] : '';
#echo '<pre>'; print_r($wpa_ssid); exit;
$wpa_psk = preg_match('/wpa_psk\s+\"(.+)\"/', isset($netcfg['wpa_psk']) ? $netcfg['wpa_psk'] : '', $regex) ? $regex[1] : '';
#echo '<pre>'; print_r($wpa_psk); exit;
$dhcp = explode(",", explode("=", trim(@shell_exec("cat /etc/dnsmasq.d/" . $iface . ".conf | grep dhcp-range=")) . '=')[1]);
#echo '<pre>'; print_r($dhcp); exit();
$use_dhcp = isset($dhcp[1]);
#echo (int) $use_dhcp; exit;
$ifcfg = parse_ifconfig($iface);
#echo '<pre>'; print_r($ifcfg); echo '</pre>'; exit();
$wifi = get_wifi_capabilities($iface);
#echo '<pre>'; echo '$iface = ' . $iface . "\n"; print_r($wifi); echo '</pre>'; exit();
$netcfg['op_mode'] = 'ap';

########################################################################################################
# Main code for the page:
########################################################################################################
site_menu();
echo '
<div class="card card-primary">
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
echo '
		<div id="client_mode_div"', ($netcfg['op_mode'] == 'dhcp' && isset($netcfg['wpa_ssid'])) || ($netcfg['op_mode'] == 'static' && isset($netcfg['wpa_ssid'])) ? '' : ' class="hidden"', '>
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
						<input id="ap_ssid" type="text" class="form-control" placeholder="Required" value="', $wpa_ssid, '">
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
						<input id="ap_psk" type="password" class="form-control" value="', $wpa_psk, '">
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_mask">Channel:</label>
				</div>
				<div class="col-6">
					<select id="op_mode" class="form-control">';
foreach ($wifi['band'] as $band => $info)
{
	if (count($wifi['band']) > 1)
		echo '
						<optgroup label="', isset($info['channels'][1]) ? '2.4 GHz' : (isset($info['channels'][36]) ? '5 GHz' : '?'), '">';
	foreach ($info['channels'] as $channel => $text)
		echo '
						<option value="', $channel, '">', $text, '</option>';
	if (count($wifi['band']) > 1)
		echo '
						</optgroup>';
}
echo '
					</select>
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
		<div id="static_ip_div"', ($netcfg['op_mode'] == 'static' && isset($netcfg['wpa_ssid'])) ? '' : ' class="hidden"', '>
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
dhcp_reservations_settings();

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
