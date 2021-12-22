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
	# Validate the input sent to this script (we paranoid... for the right reasons, of course...):
	#################################################################################################
	$action  = option('action', '/^(dhcp|static|bridged|reservations|clients|remove|check|add)$/');
	dhcp_actions();
	$iface   = option('iface', '/^(' . implode("|", array_keys(get_network_adapters())) . ')$/');
	$ip_addr = option_ip('ip_addr');
	$ip_mask = option_ip('ip_mask');
	$reboot  = option('reboot', "/^(true|false)$/");

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
	$text = '
allow-hotplug {iface}
auto {iface}
iface {iface} inet manual';
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
	# Output the network adapter configuration to the "/tmp" directory:
	#################################################################################################
	$text = '
auto ' . $iface . '
iface ' . $iface . ' inet ' . ($_POST['action'] == 'dhcp' ? 'dhcp' : 'static') . ($_POST['action'] != 'dhcp' ? '
    address ' . $ip_addr . '
    netmask ' . $ip_mask . (!empty($_POST['gateway']) && $_POST['gateway'] != "0.0.0.0" ? '
    gateway ' . $_POST['ip_gate'] : '') : '') . ($_POST['action'] == 'bridged' && count($bridged) > 1 ? '
    bridge_ports ' . implode(" ", $bridged) . '
    bridge_fd 5
    bridge_stp no' : '') . (in_array($iface, array('wan', 'br0')) ? '
    post-up echo ' . ($iface == 'wan' ? '6' : '8') . ' > /sys/class/net/wan/queues/rx-0/rps_cpus' : '');
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
		</div>
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
			</div>
			<hr style="border-width: 2px" />';

###################################################################################################
# DHCP Settings and IP Range Section
###################################################################################################
$leases = explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases")));
foreach ($leases as $id => $lease)
	$leases[$id] = explode(' ', $lease);
#echo '<pre>'; print_r($leases); exit();

$lease_time = isset($dhcp[4]) ? $dhcp[4] : '48h';
$lease_units = substr($lease_time, strlen($lease_time) - 1, 1);
$subnet = substr($subnet, 0, strrpos($subnet, '.') + 1);
echo '
			<div class="icheck-primary">
				<input type="checkbox" id="use_dhcp"', $use_dhcp ? ' checked="checked"' : '', '>
				<label for="use_dhcp">Use DHCP on interface ', $iface, '</label>
			</div>
			<div class="dhcp_div ', !$use_dhcp ? ' hidden' : '', '">
				<div class="row">
					<div class="col-6">
						<label for="dhcp_start">Starting IP Address:</label>
					</div>
					<div class="col-6">
						<div class="input-group">
							<div class="input-group-prepend">
								<span class="input-group-text"><i class="fas fa-laptop"></i></span>
							</div>
							<input id="dhcp_start" type="text" class="dhcp ip_address form-control" value="', isset($dhcp[1]) ? $dhcp[1] : $subnet, '" data-inputmask="\'alias\': \'ip\'" data-mask', !$use_dhcp ? ' disabled="disabled"' : '', '>
						</div>
					</div>
				</div>
				<div class="row" style="margin-top: 5px">
					<div class="col-6">
						<label for="dhcp_end">Ending IP Address:</label>
					</div>
					<div class="col-6">
						<div class="input-group">
							<div class="input-group-prepend">
								<span class="input-group-text"><i class="fas fa-laptop"></i></span>
							</div>
							<input id="dhcp_end" type="text" class="dhcp ip_address form-control" value="', isset($dhcp[2]) ? $dhcp[2] : $subnet, '" data-inputmask="\'alias\': \'ip\'" data-mask', !$use_dhcp ? ' disabled="disabled"' : '', '>
						</div>
					</div>
				</div>
				<div class="row" style="margin-top: 5px">
					<div class="col-6">
						<label for="dhcp_lease">Client Lease Time:</label>
					</div>
					<div class="col-6">
						<div class="input-group col-6 p-0">
							<div class="input-group-prepend">
								<span class="input-group-text"><i class="far fa-clock"></i></span>
							</div>
							<input id="dhcp_lease" type="text" class="dhcp form-control" value="', (int) $lease_time, '"', !$use_dhcp || $lease_time == 'infinite' ? ' disabled="disabled"' : '', '>
							<div class="input-group-append">
								<select class="custom-select form-control" id="dhcp_units">
									<option value="">Seconds</option>
									<option value="m"', ($lease_units == "m" ? ' selected="selected"' : ''), '>Minutes</option>
									<option value="h"', ($lease_units == "h" ? ' selected="selected"' : ''), '>Hours</option>
									<option value="d"', ($lease_units == "d" ? ' selected="selected"' : ''), '>Days</option>
									<option value="w"', ($lease_units == "w" ? ' selected="selected"' : ''), '>Weeks</option>
									<option value="infinite"', ($lease_time == "infinite" ? ' selected="selected"' : ''), '>Infinite</option>
								</select>
							</div>
						</div>
					</div>';

###################################################################################################
# IP Address Reservation section
###################################################################################################
echo '
					<div class="col-12">
						<hr style="border-width: 2px" />
						<h5>
							<a href="javascript:void(0);"><button type="button" id="reservations-refresh" class="btn btn-sm btn-primary float-right">Refresh</button></a>
							Address Reservations
						</h5>
						<div class="table-responsive p-0">
							<table class="table table-hover text-nowrap table-sm table-striped">
								<thead class="bg-primary">
									<td width="30%">Device Name</td>
									<td width="30%">IP Address</td>
									<td width="30%">MAC Address</td>
									<td width="3%">&nbsp;</td>
									<td width="3%">&nbsp;</td>
								</thead>
								<tbody id="reservations-table">
									<tr><td colspan="5"><center>Loading...</center></td></tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" id="apply_reboot" class="btn btn-success float-right hidden" data-toggle="modal" data-target="#reboot-modal" id="reboot_button">Apply and Reboot</button></a>
		<a href="javascript:void(0);"><button type="button" id="apply_changes" class="btn btn-success float-right">Apply Changes</button></a>
		<a id="add_reservation_href" href="javascript:void(0);"', !$use_dhcp || $netcfg['op_mode'] == 'dhcp' ? ' class="hidden"' : '', '><button type="button" id="add_reservation" class="dhcp_div btn btn-primary"><i class="fas fa-plus"></i>&nbsp;&nbsp;Add</button></a>
	</div>';

###################################################################################################
# IP Reservation Confirmation modal:
###################################################################################################
echo '
<div class="modal fade" id="confirm-modal" data-backdrop="static" style="display: none; z-index: 9000;" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content bg-danger">
			<div class="modal-header">
				<h4 class="modal-title">Confirm IP Reservation</h4>
				<a href="javascript:void(0);"><button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button></a>
			</div>
			<div class="modal-body" id="confirm-mac"></div>
			<div class="modal-footer justify-content-between">
				<a href="javascript:void(0);"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button></a>
				<a href="javascript:void(0);"><button id="confirm-proceed" type="button" class="btn btn-primary">Proceed</button></a>
			</div>
		</div>
	</div>
</div>';

###################################################################################################
# DHCP Reservations modal:
###################################################################################################
echo '
<div class="modal fade" id="reservation-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">DHCP Reservations</h4>
				<a href="javascript:void(0);"><button type="button hidden alert_control" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button></a>
			</div>
			<div class="modal-body table-responsive" style="max-height: 300px;">
				<a href="javascript:void(0);"><button type="button" id="leases_refresh" class="btn btn-sm btn-primary float-right">Refresh</button></a>
				<h5><center>Select Client from DHCP Tables</center></h5>
                <table class="table table-sm table-head-fixed text-nowrap table-striped">
					<thead>
						<tr>
							<th width="30%">Client Name</th>
							<th width="30%">IP Address</th>
							<th width="30%">MAC Address</th>
							<th width="10%"></th>
						</tr>
					</thead>
					<tbody id="clients-table">
						<tr><td colspan="5"><center>Loading...</center></td></tr>
					</tbody>
				</table>
			</div>
			<div class="modal-body table-responsive">
				<div class="alert alert-danger hidden" id="dhcp_error_box">
					<a href="javascript:void(0);"><button type="button" class="close" id="dhcp_error_close">&times;</button></a>
					<i class="fas fa-ban"></i>&nbsp;<span id="dhcp_error_msg" />
				</div>
				<table class="table table-sm table-head-fixed text-nowrap table-striped">
					<thead>
						<tr>
							<th width="30%">Enter Client Name</th>
							<th width="30%">Assign IP Address</th>
							<th width="30%">To This MAC Address</th>
							<th width="10%">&nbsp;</th>
						</tr>
					</thead>
					<tbody id="reservation-table">
						<tr>
							<td><input type="text" class="form-control hostname" id="dhcp_client_name" placeholder="Client Name"></td>
							<td><input type="text" class="form-control ip_address" id="dhcp_ip_addr" placeholder="IP Address"></td>
							<td><input type="text" class="form-control" id="dhcp_mac_addr" placeholder="MAC Address"></td>
							<td><a href="javascript:void(0);"><button type="button" id="reservation_remove" class="btn btn-sm btn-primary center">Clear</button></a></td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="modal-footer justify-content-between alert_control">
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary float-right" data-dismiss="modal">Cancel</button></a>
				<a href="javascript:void(0);"><button type="button" id="dhcp_add" class="btn btn-success">Add Reservation</button></a>
			</div>
		</div>
	</div>
</div>';

###################################################################################################
# Close page
###################################################################################################
apply_changes_modal('Please wait while the networking service is restarted....', true);
reboot_modal();
site_footer('Init_Wired("' . $iface . '");');
