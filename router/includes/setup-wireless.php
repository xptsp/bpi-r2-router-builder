<?php
require_once("subs/admin.php");
require_once("subs/setup.php");
require_once("subs/dhcp.php");

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
	$action = $_POST['action'] = option_allowed('action', get_dhcp_actions(array('disabled', 'client_dhcp', 'client_static', 'ap', 'encode')));
	do_dhcp_actions();

	#################################################################################################
	# Encode the specified credentials:
	#################################################################################################
	if ($action == 'encode')
	{
		$wpa_ssid = option('wpa_ssid', '/[\w\d\s\_\-]+/');
		$wpa_psk = option('wpa_psk', '/[\w\d\s\_\-]{8,63}/');
		foreach (explode("\n", trim(@shell_exec('wpa_passphrase "' . $wpa_ssid . '" "' . $wpa_psk . '"'))) as $line)
		{
			$line = explode("=", trim($line . '='));
			if ($line[0] == "psk")
				die($line[1]);
		}
		die("ERROR");
	}

	#################################################################################################
	# Validate the input sent to this script (we paranoid... for the right reasons, of course...):
	#################################################################################################
	$iface   = option('iface', '/^(' . implode("|", explode("\n", trim(@shell_exec("iw dev | grep Interface | awk '{print $2}'")))) . ')$/');
	if ($action == 'client_static' || $action == 'ap')
	{
		$ip_addr = option_ip('ip_addr');
		$ip_mask = option_ip('ip_mask');
		$ip_gate = option_ip('ip_gate');
		$wpa_ssid = option('wpa_ssid', '/[\w\d\s\_\-]+/');
		$wpa_psk = option('wpa_psk', '/[\w\d\s\_\-]{8,63}/');
	}
	$firewalled = option("firewalled", "/^(Y|N)$/");

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
	if ($firewalled)
		$text .= '    firewall yes' . "\n";
		
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
	# Restarting wireless interface and pihole-FTL:
	#################################################################################################
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface restart " . $iface);
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
{
	$include = $tface != "mt6625_0" && $tface != "ap0";
	$include |= ($tface == 'mt6625_0' && isset($options['onboard_wifi']) && $options['onboard_wifi'] == '1');
	$include |= ($tface == 'ap0' && isset($options['onboard_wifi']) && $options['onboard_wifi'] == 'A');
	if ($include)
		$ifaces[] = $tface;
}
$iface = isset($_GET['iface']) ? $_GET['iface'] : $ifaces[0];
#echo '<pre>'; print_r($ifaces); exit;
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
	<div class="card-body">';

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
if ($iface != 'ap0')
	echo '
					<option value="client_dhcp"', $netcfg['op_mode'] == 'dhcp' && isset($netcfg['wpa_ssid']) ? ' selected="selected"' : '', '>Client Mode - Automatic Configuration (DHCP)</option>
					<option value="client_static"', $netcfg['op_mode'] == 'static' && isset($netcfg['wpa_ssid']) ? ' selected="selected"' : '', '>Client Mode - Static IP Address</option>';
if ($iface == 'ap0' || ($iface != "ap0" && $iface != "mt6625_0"))
	echo '
					<option value="ap"' . ($netcfg['op_mode'] == 'static' && !isset($netcfg['wpa_ssid'])  ? ' selected="selected"' : '') . '>Access Point</option>';
echo '
				</select>
			</div>
		</div>';

###################################################################################################
# Wifi SSID, password and firewalled setting:
###################################################################################################
echo '
		<div id="client_mode_div"', ($netcfg['op_mode'] == 'dhcp' && isset($netcfg['wpa_ssid'])) || ($netcfg['op_mode'] == 'static' && !isset($netcfg['wpa_ssid'])) ? '' : ' class="hidden"', '>
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
						<input id="wpa_ssid" type="text" class="form-control" value="', $wpa_ssid, '">
						<div class="input-group-prepend" id="wifi_scan_div">
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
						<div class="input-group-prepend" id="wpa_toggle">
							<span class="input-group-text"><a href="javascript:void(0);"><i class="fas fa-eye"></i></a></span>
						</div>
						<input type="password" class="form-control" id="wpa_psk" name="wpa_psk" placeholder="Required" value="', $wpa_psk, '">
						<div class="input-group-prepend" id="wifi_encode_div">
							<a href="javascript:void(0);"><button type="button" class="btn btn-primary" id="wifi_encode">Encode</button></a>
						</div>
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
# Interface IP Address section
###################################################################################################
$subnet = isset($ifcfg['inet']) ? $ifcfg['inet'] : '';
if (empty($subnet))
	$subnet = "192.168." . strval( (int) trim(@shell_exec("iw dev " . $iface . " info | grep ifindex | awk '{print \$NF}'")) + 10 ) . ".1";
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
dhcp_reservations_modals();
apply_changes_modal('Please wait while the wireless interface is being configured....', true);
reboot_modal();
site_footer('Init_Wireless("' . $iface . '");');
