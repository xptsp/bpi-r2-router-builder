<?php
require_once("subs/admin.php");
site_menu();
$exclude_regex = "/(docker.+|lo|sit0|wlan.+|eth0|wan)/";
$leases = explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases")));
foreach ($leases as $id => $lease)
	$leases[$id] = explode(' ', $lease);
#echo '<pre>'; print_r($leases); exit();

###################################################################################################
# Function displaying settings for the specified network interface:
###################################################################################################
function device_name($mac)
{
	global $leases;
	foreach ($leases as $id => $lease)
	{
		if (isset($lease[3]) && $mac == strtoupper($lease[1]))
			return $lease[3];
	}
	return "Unknown";
}

function display_iface($iface)
{
	###################################################################################################
	# Assemble some information about the adapter:
	###################################################################################################
	$ifcfg = parse_ifconfig($iface);
	#echo '<pre>'; print_r($iface); exit();
	$cfg = get_mac_info($iface);
	#echo '<pre>'; print_r($cfg); exit();
	$dhcp = explode(",", explode("=", trim(@shell_exec("cat /etc/dnsmasq.d/" . $iface . ".conf | grep dhcp-range=")) . '=')[1]);
	#echo '<pre>'; print_r($dhcp); exit();
	$use_dhcp = isset($dhcp[1]);
	#echo (int) $use_dhcp; exit;

	###################################################################################################
	# Assemble DHCP hostname and IP address reservations:
	###################################################################################################
	$reserve = array();
	foreach (explode("\n", trim(@shell_exec("cat /etc/dnsmasq.d/" . $iface . ".conf | grep dhcp-host="))) as $line)
		$reserve[] = explode(',', explode('=', $line)[1]);
	#echo '<pre>'; print_r($reserve); exit();

	$hostname = array();
	foreach (explode("\n", trim(@shell_exec("cat /etc/dnsmasq.d/" . $iface . ".conf | grep host-record="))) as $line)
	{
		$parts = explode(",", explode("=", $line)[1]);
		$hostname[$parts[1]] = $parts[0];
	}
	#echo '<pre>'; print_r($hostname); exit();

	###################################################################################################
	# Internet IP Address section
	###################################################################################################
	echo '
<table width="100%">
	<tr>
		<td width="50%"><label for="', $iface . '_ip_address">IP Address</label></td>
		<td>
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text"><i class="fas fa-laptop"></i></span>
				</div>
				<input id="', $iface, '_ip_addr" type="text" class="', $iface, '_ip_address form-control" value="', $ifcfg['inet'], '" data-inputmask="\'alias\': \'ip\'" data-mask>
			</div>
		</td>
	</tr>
	<tr>
		<td width="50%"><label for="ip_mask">IP Subnet Mask</label></td>
		<td>
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text"><i class="fas fa-laptop"></i></span>
				</div>
				<input id="', $iface, '_ip_mask" type="text" class="', $iface, '_ip_address form-control" value="', $ifcfg['netmask'], '" data-inputmask="\'alias\': \'ip\'" data-mask>
			</div>
		</td>
	</tr>
</table>
<hr style="border-width: 2px" />';

	###################################################################################################
	# Internet IP Address section
	###################################################################################################
	echo '
<div class="icheck-primary">
	<input type="checkbox" id="', $iface, '_use_dhcp"', $use_dhcp ? ' checked="checked"' : '', '>
	<label for="', $iface, '_use_dhcp">Use DHCP on interface ', $iface, '</label>
</div>
<table width="100%">
	<tr>
		<td width="50%"><label for="', $iface, '_dhcp_start">Starting IP Address</label></td>
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
		<td width="50%"><label for="', $iface, '_dhcp_end">Ending IP Address</label></td>
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
		<td width="50%"><label for="', $iface, '_dhcp_end">IP Subnet Mask</label></td>
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

	###################################################################################################
	# IP Address Reservation section
	###################################################################################################
	echo '
<h5>Address Reservation</h5>
<div class="table-responsive p-0 centered">
	<table class="table table-hover text-nowrap table-sm">
		<thead class="bg-primary">
			<td width="10px"><strong>#</strong></td>
			<td><strong>IP Address</strong></td>
			<td><strong>Device Name</strong></td>
			<td><strong>MAC Address</strong></td>
			<td width="10px"><strong>&nbsp;</strong></td>
			<td width="10px"><strong>&nbsp;</strong></td>
		</thead>
		<tbody>';
	foreach ($reserve as $count => $parts)
	{
		echo '
			<tr>
				<td>', $count, '.</td>
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
$ifaces = get_network_adapters();
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
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
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
