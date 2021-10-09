<?php
require_once("subs/admin.php");
require_once("subs/setup.php");
site_menu();

###################################################################################################
# Assemble a list of all of the network adapters available on the system:
###################################################################################################
$ifaces = get_network_adapters();
#echo '<pre>'; print_r($ifaces); exit();
$adapters = get_network_adapters_list();
#echo '<pre>'; print_r($adapters); exit();
$iface = isset($_GET['iface']) ? $_GET['iface'] : 'br0';
#echo $iface; exit();
$exclude_regex = '/^(' . implode('|',array_merge(explode("\n", @trim(@shell_exec("iw dev | grep Interface | awk '{print $2}'"))), array("docker.+", "lo", "sit.+", "eth0", "wan"))) . ')$/';
#echo $exclude_regex; exit;
$invalid = get_invalid_adapters($iface);
#echo '<pre>'; print_r($invalid); exit();

###################################################################################################
# Get leases for entire system:
###################################################################################################
$leases = explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases")));
foreach ($leases as $id => $lease)
	$leases[$id] = explode(' ', $lease);
#echo '<pre>'; print_r($leases); exit();

###################################################################################################
# Assemble some information about the adapter:
###################################################################################################
$ifcfg = parse_ifconfig($iface);
#echo '<pre>'; print_r($ifcfg); echo '</pre>'; #exit();
$dhcp = explode(",", explode("=", trim(@shell_exec("cat /etc/dnsmasq.d/" . $iface . ".conf | grep dhcp-range=")) . '=')[1]);
#echo '<pre>'; print_r($dhcp); exit();
$use_dhcp = isset($dhcp[1]);
#echo (int) $use_dhcp; exit;

###################################################################################################
# Assemble DHCP hostname and IP address reservations:
###################################################################################################
$reserve = array();
foreach (explode("\n", trim(@shell_exec("cat /etc/dnsmasq.d/" . $iface . ".conf | grep dhcp-host="))) as $line)
	$reserve[] = explode(',', explode('=', $line . '=')[1]);
#echo '<pre>'; print_r($reserve); exit();

$hostname = array();
foreach (explode("\n", trim(@shell_exec("cat /etc/dnsmasq.d/" . $iface . ".conf | grep host-record="))) as $line)
{
	$parts = explode(",", explode("=", $line . '=,')[1]);
	$hostname[$parts[1]] = $parts[0];
}
#echo '<pre>'; print_r($hostname); exit();

###################################################################################################
# Device Name
###################################################################################################
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Wired Network Setup</h3>
	</div>
	<div class="card-body">
		<table width="100%">
			<tr>
				<td width="50%"><label for="hostname">Device Name</label></td>
				<td>
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop-code"></i></span>
						</div>
						<input id="hostname" type="text" class="form-control" value="', @file_get_contents('/etc/hostname'), '" data-inputmask-regex="([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)">
					</div>
				</td>
			</tr>
		</table>
	</div>';
	
###################################################################################################
# Tabbed interface with list of wired adapters:
###################################################################################################
echo '
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
				<a class="nav-link', $iface == $tface ? ' active' : '', '" href="', $URL, $tface == "br0" ? '' : '?iface=' . $tface, '">', $tface, '</a>
			</li>';
	}
}
echo '
		</ul>
	</div>
	<div class="card-body">';

###################################################################################################
# Interfaces bound to the specified adapter:
###################################################################################################
#echo '<pre>'; print_r($ifaces[$iface]); exit();
echo '
		<table width="100%">
			<tr>
				<td><label for="', $iface . '_bound">Bound Network Adapters:</td>
				<td>
					<input id="iface" type="hidden" value="', $iface, '" />
					<ul class="pagination pagination-sm">';
foreach ($adapters as $tface)
{
	if (!preg_match($exclude_regex, $tface))
	{
		if (!in_array($tface, $invalid))
			echo '
						<li class="bridge page-item', $tface == $iface || in_array($tface, $ifaces[$iface]) ? ' active' : '', '">
							<div class="page-link">', $tface, '</div>
						</li>';
	}
}
echo '
					</ul>
				</td>
			</tr>';

###################################################################################################
# Internet IP Address section
###################################################################################################
echo '
			<tr>
				<td width="50%"><label for="ip_addr">IP Address:</label></td>
				<td>
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="ip_addr" type="text" class="ip_address form-control" value="', isset($ifcfg['inet']) ? $ifcfg['inet'] : '', '" data-inputmask="\'alias\': \'ip\'" data-mask>
					</div>
				</td>
			</tr>
			<tr>
				<td width="50%"><label for="ip_mask">IP Subnet Mask:</label></td>
				<td>
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="ip_mask" type="text" class="ip_address form-control" value="', isset($ifcfg['netmask']) ? $ifcfg['netmask'] : '', '" data-inputmask="\'alias\': \'ip\'" data-mask>
					</div>
				</td>
			</tr>
		</table>
		<hr style="border-width: 2px" />';

###################################################################################################
# DHCP Settings and IP Range Section
###################################################################################################
$lease_time = isset($dhcp[4]) ? $dhcp[4] : '1h';
$lease_units = substr($lease_time, strlen($lease_time) - 1, 1);
echo '
		<div class="icheck-primary">
			<input type="checkbox" id="use_dhcp"', $use_dhcp ? ' checked="checked"' : '', '>
			<label for="use_dhcp">Use DHCP on interface ', $iface, '</label>
		</div>
		<table width="100%" class="dhcp_div">
			<tr>
				<td width="50%"><label for="dhcp_start">Starting IP Address:</label></td>
				<td>
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="dhcp_start" type="text" class="dhcp ip_address form-control" value="', isset($dhcp[1]) ? $dhcp[1] : '', '" data-inputmask="\'alias\': \'ip\'" data-mask', !$use_dhcp ? ' disabled="disabled"' : '', '>
					</div>
				</td>
			</tr>
			<tr>
				<td width="50%"><label for="dhcp_end">Ending IP Address:</label></td>
				<td>
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="dhcp_end" type="text" class="dhcp ip_address form-control" value="', isset($dhcp[2]) ? $dhcp[2] : '', '" data-inputmask="\'alias\': \'ip\'" data-mask', !$use_dhcp ? ' disabled="disabled"' : '', '>
					</div>
				</td>
			</tr>
			<tr>
				<td width="50%"><label for="dhcp_lease">Client Lease Time:</label></td>
				<td>
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
				</td>
			</tr>
		</table>
		<hr style="border-width: 2px" class="dhcp_div" />';

###################################################################################################
# IP Address Reservation section
###################################################################################################
echo '
		<h5 class="dhcp_div">Address Reservations</h5>
		<div class="table-responsive p-0 centered dhcp_div">
			<table class="table table-hover text-nowrap table-sm table-striped">
				<thead class="bg-primary">
					<td>IP Address</td>
					<td>Device Name</td>
					<td>MAC Address</td>
					<td width="10px">&nbsp;</td>
					<td width="10px">&nbsp;</td>
				</thead>
				<tbody>';
$count = 0;
foreach ($reserve as $count => $parts)
{
	if (isset($parts[1]))
	{
		echo '
					<tr>
						<td>', $parts[2], '</td>
						<td>', isset($hostname[$parts[2]]) ? $hostname[$parts[2]] : device_name(strtoupper($parts[1])), '</td>
						<td>', strtoupper($parts[1]), '</td>
						<td><a href="#"><i class="fas fa-pen"></i></a>&nbsp;</td>
						<td><a href="#"><i class="fas fa-trash-alt"></i></a></td>
					</tr>';
		$count++;
	}
}
if ($count == 0)
	echo '
					<tr>
						<td colspan="5">No IP Address Reservations</td>
					</tr>';
echo '
				</tbody>
			</table>
		</div>
	</div>
	<div class="card-footer">
		<button type="button" id="apply_changes" class="btn btn-success float-right">Apply Changes</button>
		<button type="button" id="add_ip_address" class="dhcp_div btn btn-primary"><i class="fas fa-plus"></i>&nbsp;&nbsp;Add</button>
	</div>
</div>';
 
###################################################################################################
# Apply Changes modal:
###################################################################################################
echo '
<div class="modal fade" id="apply-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header bg-info">
				<h4 class="modal-title">Applying Changes</h4>
				<button type="button hidden alert_control" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p id="apply_msg">Please wait while the networking service is restarted....</p>
			</div>
			<div class="modal-footer justify-content-between hidden alert_control">
				<button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>';

###################################################################################################
# Close page
###################################################################################################
site_footer('Init_LAN();');
