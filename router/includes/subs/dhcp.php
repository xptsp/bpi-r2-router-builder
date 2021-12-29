<?php
require_once("admin.php");

function get_dhcp_actions($other = array())
{
	return array_merge($other, array('reservations', 'clients', 'remove', 'check', 'add'));
}

function do_dhcp_actions()
{
	$reserve = $hostname = $leases = array();

	###################################################################################################
	# If the "misc" parameter was passed, it has something to do with the DHCP code in this file:
	###################################################################################################
	if (!isset($_POST['misc']))
		return;
	$iface   = option('misc', '/^(' . implode("|", array_keys(get_network_adapters())) . ')$/');

	###################################################################################################
	# Parse the DNSMASQ configuration file for the specified interface:
	###################################################################################################
	foreach (explode("\n", @file_get_contents("/etc/dnsmasq.d/" . $iface . ".conf")) as $line)
	{
		$parts = explode("=", trim($line));
		$sub_parts = explode(",", !empty($parts[1]) ? $parts[1] : '');
		$sub_parts[1] = strtoupper(!empty($sub_parts[1]) ? $sub_parts[1] : '');
		if ($parts[0] == 'dhcp-host')
		{
			$arr = array();
			foreach ($sub_parts as $part)
			{
				if (filter_var($part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
					$arr['ip'] = $part;
				else if (filter_var($part, FILTER_VALIDATE_MAC))
					$arr['mac'] = strtoupper($part);
				else if ($part != $iface)
					$arr['host'] = $part;
			}
			$reserve[$arr['ip']] = $reserve[$arr['mac']] = $arr;
			$reserve[$arr['mac']]['hide'] = true;
		}
		else if ($parts[0] == 'host-record' && !empty($sub_parts[1]))
			$hostname[$sub_parts[1]] = $sub_parts[0];
		else if ($parts[0] == 'dhcp-range')
			$subnet = substr($sub_parts[1], 0, strrpos($sub_parts[1], ".") + 1);
	}
	if (empty($subnet))
	{
		$parts = parse_ifconfig($iface);
		$subnet = substr($parts['inet'], 0, strrpos($parts['inet'], ".") + 1);
	}
	#echo '<pre>$reserve >> '; print_r($reserve); exit();
	#echo '<pre>$hostname >> '; print_r($hostname); exit();

	###################################################################################################
	# Parse the DNSMASQ leases files:
	###################################################################################################
	foreach (file("/var/lib/misc/dnsmasq.leases") as $lease)
	{
		$sub_parts = explode(' ', trim($lease));
		if (strpos($sub_parts[2], $subnet) != -1)
		{
			$leases[$sub_parts[1]] = $leases[strtoupper($sub_parts[2])] = $sub_parts;
			$leases[$sub_parts[1]]['hide'] = true;
		}
	}
	#echo '<pre>$leases >> '; print_r($leases); exit();

	###################################################################################################
	# ACTION: RESERVATIONS ==> Output list of DHCP leases for the specified adapter:
	###################################################################################################
	if ($_POST['action'] == 'reservations')
	{
		foreach ($reserve as $parts)
		{
			if (isset($parts['ip']) && empty($parts['hide']))
			{
				echo
					'<tr>' .
						'<td class="dhcp_host">' . (isset($parts['host']) ? $parts['host'] : (isset($hostname[$parts['mac']]) ? $hostname[$parts['mac']] : 'Unknown')) . '</td>' .
						'<td class="dhcp_ip_addr">' . $parts['ip'] . '</td>' .
						'<td class="dhcp_mac_addr">' . $parts['mac'] . '</td>' .
						'<td class="dhcp_edit"><a href="javascript:void(0);"><i class="fas fa-pen"></i></a></td>' .
						'<td class="dhcp_delete"><a href="javascript:void(0);"><i class="fas fa-trash-alt"></i></a></td>' .
					'</tr>';
			}
		}
		die( empty($reserve) ? '<tr><td colspan="5"><center>No IP Address Reservations</center></td></tr>' : '' );
	}
	###################################################################################################
	# ACTION: CLIENTS ==> Output list of DHCP clients for the specified adapter:
	###################################################################################################
	else if ($_POST['action'] == 'clients')
	{
		foreach ($leases as $id => $parts)
		{
			$parts[1] = strtoupper($parts[1]);
			if (empty($parts['hide']) && strpos($parts[2], $subnet) !== false)
				echo
				'<tr class="reservation-option">' .
					'<td class="dhcp_host">' . (!empty($parts[3]) ? $parts[3] : (isset($hostname[$parts[1]]) ? $hostname[$parts[1]] : 'Unknown')) . '</td>' .
					'<td class="dhcp_ip_addr">' . $parts[2] . '</td>' .
					'<td class="dhcp_mac_addr">' . $parts[1] . '</td>' .
					'<td><center><a href="javascript:void(0);"><i class="far fa-plus-square"></i></a></center></td>' .
				'</tr>';
			else
				unset($leases[$id]);
		}
		die( empty($leases) ? '<tr><td colspan="5"><center>ERROR: No Leases Found</center></td></tr>' : '' );
	}
	###################################################################################################
	# ACTION: REMOVE ==> Remove the specific DHCP reservation from the specified adapter:
	# ACTION: ADD ==> Add the specified DHCP reservation to the specified adapter:
	###################################################################################################
	else if ($_POST['action'] == 'remove' || $_POST['action'] == 'add')
	{
		$ip_addr = option_ip('ip_addr');
		$mac_addr = option_mac('mac_addr');
		$hostname = option('hostname', "/^([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)$/");
		die( @shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp ' . $_POST['action'] . ' ' . $iface . ' ' . $mac_addr . ' ' . $ip_addr . ' ' . $hostname) );
	}
	###################################################################################################
	# ACTION: CHECK ==> Check to see if the IP and/or MAC have already been assigned.
	###################################################################################################
	else if ($_POST['action'] == 'check')
	{
		$ip_addr = option_ip('ip_addr');
		$mac_addr = option_mac('mac_addr');

		if (isset($reserve[$_POST['mac_addr']]))
			die( $reserve[$_POST['mac_addr']]['ip'] == $_POST['ip_addr'] ? 'SAME' : 'This MAC address has already been assigned to ' . $reserve[$_POST['mac_addr']]['ip'] . '.' );
		else if (isset($reserve[$_POST['ip_addr']]) && $reserve[$_POST['ip_addr']]['mac'] != $_POST['mac_addr'])
		{
			$res = &$reserve[$_POST['ip_addr']];
			$s = (!empty($res['host']) ? '&quot;' . $res['host'] . '&quot; with ' : '') . ' MAC Address ' . $res['mac'];
			die( 'This IP address has already been assigned to ' . $s . '.' );
		}
		else
			die( $_POST['ip_addr'] == trim(@shell_exec("arp | grep " . $iface . " | grep -i " . $_POST['mac_addr'] . " | awk '{print $1}'")) ? 'ADD' : 'OK' );
	}
	###################################################################################################
	# ACTION: Everything else ==> Let's just tell the user this page doesn't exist....
	###################################################################################################
	return;
}

function dhcp_reservations_settings()
{
	global $use_dhcp, $dhcp, $iface, $subnet;

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
				<div class="dhcp_div ', !$use_dhcp ? ' hidden' : '', '">
					<hr style="border-width: 2px" />
					<div class="icheck-primary">
						<input type="checkbox" id="use_dhcp"', $use_dhcp ? ' checked="checked"' : '', '>
						<label for="use_dhcp">Use DHCP on interface ', $iface, '</label>
					</div>
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
				</div>';
}

###################################################################################################
# Functions dealing with IP Reservation Confirmation modals:
###################################################################################################
function dhcp_reservations_modals()
{
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
}