<?php
require_once("subs/admin.php");
require_once("subs/setup.php");
site_menu();
$ifaces = get_network_adapters();
#echo '<pre>'; print_r($ifaces); exit();
$adapters = get_network_adapters_list();
#echo '<pre>'; print_r($adapters); exit();
$exclude_regex = "/(docker.+|lo|sit0|wlan.+|eth0|wan)/";

# Get leases for entire system:
$leases = explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases")));
foreach ($leases as $id => $lease)
	$leases[$id] = explode(' ', $lease);
#echo '<pre>'; print_r($leases); exit();

###################################################################################################
# Function displaying settings for the specified network interface:
###################################################################################################
function display_iface($iface)
{
	global $ifaces, $exclude_regex, $adapters;

	#==================================================================================================
	# Assemble some information about the adapter:
	#==================================================================================================
	$ifcfg = parse_ifconfig($iface);
	#echo '<pre>'; print_r($iface); exit();
	$cfg = get_mac_info($iface);
	#echo '<pre>'; print_r($cfg); exit();
	$dhcp = explode(",", explode("=", trim(@shell_exec("cat /etc/dnsmasq.d/" . $iface . ".conf | grep dhcp-range=")) . '=')[1]);
	#echo '<pre>'; print_r($dhcp); exit();
	$use_dhcp = isset($dhcp[1]);
	#echo (int) $use_dhcp; exit;

	#==================================================================================================
	# Assemble DHCP hostname and IP address reservations:
	#==================================================================================================
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

	#==================================================================================================
	# Interfaces bound to the specified adapter:
	#==================================================================================================
	#echo '<pre>'; print_r($ifaces[$iface]); exit();
	echo '
<table width="100%">
	<tr>
		<td><label for="', $iface . '_bound">Bound Network Adapters:</td>
		<td>
			<ul class="pagination pagination-sm">';
	foreach (array_merge(array('wan'), $adapters) as $sub)
	{
		if (!preg_match($exclude_regex, $sub) || $sub == 'wan')
		{
			echo '
				<li class="', $iface, '_bound page-item', $sub == $iface || in_array($sub, $ifaces[$iface]) ? ' active' : '', '"><a href="#" class="page-link">', $sub, '</a></li>';
		}
	}
	echo '
			</ul>
		</td>
	</tr>';

	#==================================================================================================
	# Internet IP Address section
	#==================================================================================================
echo '
	<tr>
		<td width="50%"><label for="', $iface . '_ip_address">IP Address:</label></td>
		<td>
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text"><i class="fas fa-laptop"></i></span>
				</div>
				<input id="', $iface, '_ip_addr" type="text" class="', $iface, '_ip_address form-control" value="', isset($ifcfg['inet']) ? $ifcfg['inet'] : '', '" data-inputmask="\'alias\': \'ip\'" data-mask>
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
				<input id="', $iface, '_ip_mask" type="text" class="', $iface, '_ip_address form-control" value="', isset($ifcfg['netmask']) ? $ifcfg['netmask'] : '', '" data-inputmask="\'alias\': \'ip\'" data-mask>
			</div>
		</td>
	</tr>
</table>
<hr style="border-width: 2px" />';

	#==================================================================================================
	# DHCP Settings and IP Range Section
	#==================================================================================================
	echo '
<div class="icheck-primary">
	<input type="checkbox" id="', $iface, '_use_dhcp"', $use_dhcp ? ' checked="checked"' : '', '>
	<label for="', $iface, '_use_dhcp">Use DHCP on interface ', $iface, '</label>
</div>
<table width="100%">
	<tr>
		<td width="50%"><label for="', $iface, '_dhcp_start">Starting IP Address:</label></td>
		<td>
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text"><i class="fas fa-laptop"></i></span>
				</div>
				<input id="', $iface, '_dhcp_start" type="text" class="', $iface, '_dhcp ', $iface, '_ip_address form-control" value="', isset($dhcp[1]) ? $dhcp[1] : '', '" data-inputmask="\'alias\': \'ip\'" data-mask', !$use_dhcp ? ' disabled="disabled"' : '', '>
			</div>
		</td>
	</tr>
	<tr>
		<td width="50%"><label for="', $iface, '_dhcp_end">Ending IP Address:</label></td>
		<td>
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text"><i class="fas fa-laptop"></i></span>
				</div>
				<input id="', $iface, '_dhcp_end" type="text" class="', $iface, '_dhcp ', $iface, '_ip_address form-control" value="', isset($dhcp[2]) ? $dhcp[2] : '', '" data-inputmask="\'alias\': \'ip\'" data-mask', !$use_dhcp ? ' disabled="disabled"' : '', '>
			</div>
		</td>
	</tr>
	<tr>
		<td width="50%"><label for="', $iface, '_dhcp_end">IP Subnet Mask:</label></td>
		<td>
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text"><i class="fas fa-laptop"></i></span>
				</div>
				<input id="', $iface, '_dhcp_end" type="text" class="', $iface, '_dhcp ', $iface, '_ip_address form-control" value="', isset($dhcp[3]) ? $dhcp[3] : '', '" data-inputmask="\'alias\': \'ip\'" data-mask', !$use_dhcp ? ' disabled="disabled"' : '', '>
			</div>
		</td>
	</tr>
</table>
<hr style="border-width: 2px" />';

	#==================================================================================================
	# IP Address Reservation section
	#==================================================================================================
	echo '
<h5>Address Reservations</h5>
<div class="table-responsive p-0 centered">
	<table class="table table-hover text-nowrap table-sm table-striped">
		<thead class="bg-primary">
			<td>IP Address</td>
			<td>Device Name</td>
			<td>MAC Address</td>
			<td width="10px">&nbsp;</td>
			<td width="10px">&nbsp;</td>
		</thead>
		<tbody>';
	foreach ($reserve as $count => $parts)
	{
		if (isset($parts[1]))
			echo '
			<tr>
				<td>', $parts[2], '</td>
				<td>', isset($hostname[$parts[2]]) ? $hostname[$parts[2]] : device_name(strtoupper($parts[1])), '</td>
				<td>', strtoupper($parts[1]), '</td>
				<td><a href="#"><i class="fas fa-pen"></i></a>&nbsp;</td>
				<td><a href="#"><i class="fas fa-trash-alt"></i></a></td>
			</tr>';
	}
	echo '
		</tbody>
	</table>
</div>';
}

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
						<input id="hostname" type="text" class="form-control" value="', @file_get_contents('/etc/hostname'), '">
					</div>
				</td>
			</tr>
		</table>
	</div>
    <div class="card-header p-0 pt-1">
		<ul class="nav nav-tabs" id="iface-tab" role="tablist">';
$init_list = array();
foreach ($ifaces as $iface => $details)
{
	if (!preg_match($exclude_regex, $iface))
	{
		echo '
			<li class="nav-item">
				<a class="nav-link', empty($init_list) ? ' active' : '', '" id="', $iface, '-tab" data-toggle="pill" href="#', $iface, '" role="tab" aria-controls="', $iface,' " aria-selected="true">', $iface, '</a>
			</li>';
		$init_list[] = $iface;
	}
}
echo '
		</ul>
	</div>
	<div class="card-body">
		<div class="tab-content" id="iface-tabContent">';
$first = true;
foreach ($ifaces as $iface => $details)
{
	if (!preg_match($exclude_regex, $iface))
	{
		echo '
			<div class="tab-pane fade', ($first ? ' show active' : ''), '" id="', $iface, '" role="tabpanel" aria-labelledby="', $iface, '-tab">';
		display_iface($iface);
		echo '
			</div>
		</div>';
		$first = false;
	}
}
echo '
	</div>
</div>';
 
###################################################################################################
# Close page
###################################################################################################
site_footer('Init_Wired("' . implode(",", $init_list) . '");');
