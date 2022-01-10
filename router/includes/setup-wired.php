<?php
require_once("subs/manage.php");
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
	# Validate the input sent to this script (we paranoid... for the right reasons, of course...):
	#################################################################################################
	$action = $_POST['action'] = option_allowed('action', get_dhcp_actions(array('dhcp', 'static', 'bridged')));
	do_dhcp_actions();
	$iface   = option('iface', '/^(' . implode("|", array_keys(get_network_adapters())) . ')$/');
	$ip_addr = option_ip('ip_addr');
	$ip_mask = option_ip('ip_mask');
	$reboot  = option('reboot', "/^(true|false)$/");
	$firewalled = option("firewalled");
	$no_internet = option("no_net");

	#################################################################################################
	# If using DHCP on this interface, make sure addresses are valid:
	#################################################################################################
	if (!empty($_POST['use_dhcp']))
	{
		#################################################################################################
		# Make sure the IP address is held within the DHCP address range:
		#################################################################################################
		$mask    = '/^(' . implode('\.', explode(".", substr($ip_addr, 0, strrpos($ip_addr, '.')))) . '\.\d+)$/';
		$dhcp_start = option('dhcp_start', $mask);
		$dhcp_end   = option('dhcp_end', $mask);

		#################################################################################################
		# Make sure the client lease time is valid:
		#################################################################################################
		$dhcp_lease = option('dhcp_lease', '/^(infinite|(\d+)(m|h|d|w|))$/');
		if ($dhcp_lease != "infinite")
		{
			preg_match("/(\d+)(m|h|d|w|)/", $dhcp_lease, $parts);
			#echo '<pre>'; print_r($parts); exit;
			if (($parts[2] == '' && (int) $parts[1] < 120) || ($parts[2] == 'm' && (int) $parts[1] < 2) || ((int) $parts[1] < 1))
				die('ERROR: Invalid DHCP lease time!');
		}
	}

	#################################################################################################
	# Create the network configuration for each of the bound network adapters:
	#################################################################################################
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface delete " . $iface);
	$_POST['bridge'] = isset($_POST['bridge']) ? $_POST['bridge'] : '';
	$bridged = array_diff( explode(" ", trim($_POST['bridge'])), array("undefined") );
	if (empty($bridged))
		die("[BRIDGE] ERROR: No interfaces specified in bridge configuration!");
	$text =  'auto {iface}' . "\n";
	$text .= 'iface {iface} inet manual' . "\n";
	if (count($bridged) > 1)
	{
		foreach ($bridged as $adapter)
		{
			$handle = fopen("/tmp/" . $adapter, "w");
			fwrite($handle, str_replace('{iface}', $adapter, trim($text)) . "\n");
			fclose($handle);
			$tmp = trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface move " . $adapter));
			if ($tmp != "")
				die($tmp);
		}
		if (substr($iface, 0, 2) != "br")
			$iface = 'br' . strval( intval(str_replace("/etc/network/interfaces.d/br", "", trim(@shell_exec("ls /etc/network/interfaces.d/br* | sort | tail -1")))) + 1 );
	}
	else if (substr($iface, 0, 2) == "br")
		$iface = $bridged[0];

	#################################################################################################
	# Decide what the interface configuration text will look like:
	#################################################################################################
	$text =  'auto ' . $iface . "\n";
	$text .= 'iface ' . $iface . ' inet ' . ($_POST['action'] == 'dhcp' ? 'dhcp' : 'static') . "\n";
	if ($_POST['action'] != 'dhcp')
	{
		$text .= '    address ' . $ip_addr . "\n";
		$text .= '    netmask ' . $ip_mask . "\n";
		if (!empty($_POST['gateway']) && $_POST['gateway'] != "0.0.0.0")
			$text .= '    gateway ' . $_POST['ip_gate'] . "\n";
	}
	if ($_POST['action'] == 'bridged' && count($bridged) > 1)
	{
		$text .= '    bridge_ports ' . implode(" ", $bridged) . "\n";
		$text .= '    bridge_fd 5' . "\n";
		$text .= '    bridge_stp no' . "\n";
	}
	else if ($_POST['action'] != 'bridged')
		$text .= '    masquerade yes' . "\n";
	if ($firewalled == "Y")
		$text .= '    firewall yes' . "\n";
	if ($no_internet == "Y")
		$text .= '    no_internet yes' . "\n";

	#################################################################################################
	# Output the network adapter configuration to the "/tmp" directory:
	#################################################################################################
	#echo '<pre>'; echo $text; exit;
	$handle = fopen("/tmp/" . $iface, "w");
	fwrite($handle, trim($text) . "\n");
	fclose($handle);
	$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface move " . $iface);
	if ($tmp != "")
		die($tmp);

	#################################################################################################
	# Output the DNSMASQ configuration file related to the network adapter:
	#################################################################################################
	if ($_POST['action'] == 'dhcp' || $_POST['use_dhcp'] == "N")
		$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp del " . $_POST['iface']);
	else
		$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp set " . $_POST['iface'] . " " . $ip_addr . " " . $dhcp_start . " " . $dhcp_end . ' ' . $dhcp_lease);
	if ($tmp != "")
		die($tmp);

	#################################################################################################
	# Restarting networking service:
	#################################################################################################
	if ($reboot == "false")
	{
		@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh systemctl restart networking");
		@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh pihole restartdns");
		die("OK");
	}
	else
		die("REBOOT");
}

###################################################################################################
# Assemble a list of all of the network adapters available on the system:
###################################################################################################
$ifaces = get_network_adapters();
#echo '<pre>'; print_r($ifaces); exit();
$adapters = get_network_adapters_list();
#echo '<pre>'; print_r($adapters); exit();
$iface = isset($_GET['iface']) ? $_GET['iface'] : 'wan';
#echo $iface; exit();
$exclude_regex = '/^(' . implode('|',array_merge(explode("\n", @trim(@shell_exec("iw dev | grep Interface | awk '{print $2}'"))), array("docker.+", "lo", "sit.+", "eth0", "eth1", "aux"))) . ')$/';
#echo $exclude_regex; exit;
$invalid = get_invalid_adapters($iface);
#echo '<pre>'; print_r($invalid); exit();

###################################################################################################
# Assemble some information about the adapter:
###################################################################################################
$ifcfg = parse_ifconfig($iface);
#echo '<pre>'; print_r($ifcfg); echo '</pre>'; #exit();
$dhcp = explode(",", explode("=", trim(@shell_exec("cat /etc/dnsmasq.d/" . $iface . ".conf | grep dhcp-range=")) . '=')[1]);
#echo '<pre>'; print_r($dhcp); exit();
$use_dhcp = isset($dhcp[1]);
#echo (int) $use_dhcp; exit;
$netcfg = get_mac_info($iface);
#echo '<pre>'; print_r($netcfg); exit();
$no_internet = isset($netcfg['no_internet']);
#echo '<pre>'; print_r($no_internet); exit;

###################################################################################################
# Tabbed interface with list of wired adapters:
###################################################################################################
site_menu();
echo '
<div id="alert-div" style="display:none">
	<div class="alert alert-warning">
		<h5><i class="icon fas fa-info"></i> Alert!</h5>
		Router must be restarted for the DHCP reservation changes to take effect.
	</div>
</div>
<div class="card card-primary">
    <div class="card-header p-0 pt-1">
		<ul class="nav nav-tabs">';
$init_list = array();
$URL = explode("?", $_SERVER['REQUEST_URI'])[0];
foreach ($ifaces as $tface => $details)
{
	if (!preg_match($exclude_regex, $tface))
	{
		echo '
			<li class="nav-item">
				<a class="ifaces nav-link', $iface == $tface ? ' active' : '', '" href="', $URL, $tface == "wan" ? '' : '?iface=' . $tface, '">', $tface, '</a>
			</li>';
	}
}
echo '
		</ul>
	</div>
	<div class="card-body">';

###################################################################################################
# List modes of operation for the interface:
###################################################################################################
$tmp = array();
foreach ($adapters as $tface)
{
	if (!preg_match($exclude_regex, $tface) && !in_array($tface, $invalid))
		$tmp[] = $tface;
}
#echo '<pre>'; print_r($tmp); exit;
echo '
		<div class="row">
			<div class="col-6">
				<label for="iface_mode">Mode of Operation:</label>
			</div>
			<div class="col-6">
				<select id="op_mode" class="form-control"', $netcfg['op_mode'] == 'bridged' ? ' disabled="disabled"' : '', '>
					<option value="dhcp"', $netcfg['op_mode'] == 'dhcp' ? ' selected="selected"' : '', '>Automatic Configuration - DHCP</option>
					<option value="static"', $netcfg['op_mode'] == 'static' ? ' selected="selected"' : '', '>Static IP Address</option>';
if (count($tmp) > 1)
	echo '
					<option value="bridged"', $netcfg['op_mode'] == 'bridged' ? ' selected="selected"' : '', '>Bridged Interfaces</option>';
echo '
				</select>
			</div>
		</div>';
if (count($tmp) == 1)
	echo '
		<div class="row" style="margin-top: 5px">
			<div class="col-12">
				<div class="icheck-primary">
					<input type="checkbox" id="firewalled"', isset($netcfg['firewall']) ? ' checked="checked"' : '', '>
					<label for="firewalled">Firewall Interface from Internet</label>
				</div>
			</div>
		</div>';
echo '
		<div class="row" style="margin-top: 5px">
			<div class="col-12">
				<div class="icheck-primary">
					<input type="checkbox" id="if_no_net"', isset($netcfg['no_internet']) ? ' checked="checked"' : '', '>
					<label for="if_no_net">No Internet Access from interface ', $iface, '</label>
				</div>
			</div>
		</div>
		<hr style="border-width: 2px" />
		<div id="static_ip_div"', $netcfg['op_mode'] == 'dhcp' ? ' class="hidden"' : '', '>';

###################################################################################################
# List interfaces bridged under the specified interface ONLY when more than 1 interfaces bridged:
###################################################################################################
if (count($tmp) > 1)
{
	echo '
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="', $iface . '_bound">Bridged Interfaces:
				</div>
				<div class="col-6">
					<input id="iface" type="hidden" value="', $iface, '" />
					<ul class="pagination pagination-sm" style="margin-bottom: 0px">';
	foreach ($tmp as $tface)
	{
		echo '
						<li class="bridge page-item', $tface == $iface || in_array($tface, $ifaces[$iface]) ? ' active' : '', '">
							<div class="page-link">', $tface, '</div>
						</li>';
	}
	echo '
					</ul>
				</div>
			</div>';
}

###################################################################################################
# Internet IP Address section
###################################################################################################
$subnet = isset($ifcfg['inet']) ? $ifcfg['inet'] : '';
echo '
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
						<input id="ip_mask" type="text" class="ip_address form-control" value="', isset($ifcfg['netmask']) ? $ifcfg['netmask'] : '', '" data-inputmask="\'alias\': \'ip\'" data-mask>
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
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" id="apply_reboot" class="btn btn-success float-right hidden" data-toggle="modal" data-target="#reboot-modal" id="reboot_button">Apply and Reboot</button></a>
		<a href="javascript:void(0);"><button type="button" id="apply_changes" class="btn btn-success float-right">Apply Changes</button></a>
		<a id="add_reservation_href" href="javascript:void(0);"', !$use_dhcp || $netcfg['op_mode'] == 'dhcp' ? ' class="hidden"' : '', '><button type="button" id="add_reservation" class="dhcp_div btn btn-primary"><i class="fas fa-plus"></i>&nbsp;&nbsp;Add</button></a>
	</div>';

###################################################################################################
# Close page
###################################################################################################
dhcp_reservations_modals();
apply_changes_modal('Please wait while the networking service is restarted....', true);
reboot_modal();
site_footer('Init_Wired("' . $iface . '");');
