<?php
require_once("subs/admin.php");
site_menu();
$wan = parse_ifconfig('wan');
#echo '<pre>'; print_r($wan); exit();
$cfg = get_mac_info('wan');
#echo '<pre>'; print_r($cfg); exit();
$gateway = @trim(shell_exec("ip route | grep default | grep wan | awk '{print $3}'"));
#echo $gateway; exit();

###################################################################################################
# Internet IP Address section
###################################################################################################
$dhcp = strpos($cfg['iface'], 'dhcp') > -1;
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Internet IP Address</h3>
	</div>
	<div class="card-body">
		<div class="form-group clearfix">
			<div class="icheck-primary">
				<input type="radio" value="dynamic" id="dynamic_ip" name="static_dynamic"', $dhcp ? ' checked="checked"' : '', '>
				<label for="dynamic_ip">Get Dynamically from ISP</label>
			</div>
			<div class="icheck-primary">
				<input type="radio" value="static" id="static_ip" name="static_dynamic"', $dhcp ? '' : ' checked="checked"', '>
				<label for="static_ip">Use Static IP Address</label>
			</div>
		</div>
		<table width="100%">
			<tr>
				<td width="30px"></td>
				<td width="45%"><label for="ip_address">IP Address</label></td>
				<td>
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="ip_addr" type="text" class="ip_address form-control" value="', $wan['inet'], '" data-inputmask="\'alias\': \'ip\'" data-mask', $dhcp ? ' disabled="disabled"' : '', '>
					</div>
				</td>
			</tr>
			<tr>
				<td></td>
				<td><label for="ip_address">IP Subnet Mask</label></td>
				<td>
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="ip_mask" type="text" class="ip_address form-control"  value="', $wan['netmask'], '"data-inputmask="\'alias\': \'ip\'" data-mask', $dhcp ? ' disabled="disabled"' : '', '>
					</div>
				</td>
			</tr>
			<tr>
				<td></td>
				<td><label for="ip_address">IP Gateway Address</label></td>
				<td>
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="ip_gate" type="text" class="ip_address form-control" value="', $gateway, '" data-inputmask="\'alias\': \'ip\'" data-mask', $dhcp ? ' disabled="disabled"' : '', '>
					</div>
				</td>
			</tr>
		</table>
	</div>';

###################################################################################################
# Domain Name (DNS) Servers
###################################################################################################
$current = array();
$contents = @file("/etc/network/interfaces.d/wan");
foreach (is_array($contents) ? $contents : array() as $line)
{
	if (preg_match("/nameserver (.*) >/", $line, $regex))
		$current[ count($current) ] = $regex[1];
}
$custom = !empty($current);
if (empty($current))
{
	$contents = @file("/etc/resolv.conf");
	foreach (is_array($contents) ? $contents : array() as $line)
	{
		if (preg_match("/nameserver (.*)/", $line, $regex))
			$current[ count($current) ] = $regex[1];
	}
}
echo '
	<div class="card-header">
		<h3 class="card-title">Domain Name (DNS) Servers</h3>
	</div>
	<div class="card-body">
		<div class="form-group clearfix">
			<div class="icheck-primary">
				<input type="radio" id="dns_isp" value="isp" name="dns_server_opt"', empty($custom) ? ' checked="checked"' : '', '>
				<label for="dns_isp">Get Automatically from ISP</label>
			</div>
			<div class="icheck-primary">
				<input type="radio" id="dns_custom" value="custom" name="dns_server_opt"', empty($custom) ? '' : ' checked="checked"', '>
				<label for="dns_custom">Use These DNS Servers</label>
			</div>
		</div>
		<table width="100%">
			<tr>
				<td width="30px"></td>
				<td width="45%"><label for="ip_address">Primary DNS Server</label></td>
				<td>
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="dns1" type="text" class="dns_address form-control" value="', empty($current[0]) ? '' : $current[0], '" data-inputmask="\'alias\': \'ip\'" data-mask', empty($custom) ? ' disabled="disabled"' : '', '>
					</div>
				</td>
			</tr>
			<tr>
				<td></td>
				<td><label for="ip_address">Secondary DNS Server</label></td>
				<td>
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="dns2" type="text" class="dns_address form-control"  value="', empty($current[1]) ? '' : $current[1], '"data-inputmask="\'alias\': \'ip\'" data-mask', empty($custom) ? ' disabled="disabled"' : '', '>
					</div>
				</td>
			</tr>
		</table>
	</div>';
	
###################################################################################################
# Router MAC Address settings:
###################################################################################################
$mac = trim($wan['ether']);
$parts = explode("=", trim(@file_get_contents("/boot/eth0.conf")));
$def = isset($parts[1]) ? $parts[1] : $mac;
$mac_com = trim(@shell_exec("arp -n | grep " . $_SERVER['REMOTE_ADDR'] . " | awk '{print $3}'"));
$mac_chk = ($mac == $def || $mac == $mac_com);
echo '
	<div class="card-header">
		<h3 class="card-title">Router MAC Address</h3>
	</div>
	<!-- /.card-header -->
	<div class="card-body">
		<div class="form-group clearfix">
			<div class="icheck-primary">
				<input class="mac_opt" type="radio" id="mac_custom" name="router_mac"', !$mac_chk ? ' checked="checked"' : '', '>
				<label for="mac_custom">Use this MAC Address</label>
				<span class="float-right">
					<input id="mac_addr" name="mac_addr" type="text" class="form-control" value="', $mac, '" maxlength="17"', $mac_chk ? ' disabled="disabled"' : '', '>
				</span>
			</div>
			<div class="icheck-primary">
				<input class="mac_opt" type="radio" id="mac_default" name="router_mac"', $mac == $def ? ' checked="checked"' : '', '>
				<label for="mac_default">Use Default Address</label>
			</div>
			<div class="icheck-primary">
				<input class="mac_opt" type="radio" id="mac_computer" name="router_mac"', $mac == $mac_com ? ' checked="checked"' : '', ' data-mac="', $mac_com, '"', $mac_com == "" ? ' disabled="disabled"' : '', '>
				<label for="mac_computer">Use Computer MAC Address</label>
			</div>
			<div class="icheck-primary">
				<input class="mac_opt" type="radio" id="mac_random" name="router_mac"', $mac == $mac_com ? ' checked="checked"' : '', ' data-mac="', $mac_com, '"', $mac_com == "" ? ' disabled="disabled"' : '', '>
				<label for="mac_random">Use Randomly Generated MAC Address</label>
			</div>
		</div>
	</div>';

###################################################################################################
# Apply Changes button:
###################################################################################################
echo '
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="submit">Apply Changes</button></a>
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
				<a href="javascript:void(0);"><button type="button hidden alert_control" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button></a>
			</div>
			<div class="modal-body">
				<p id="apply_msg">Please wait while the networking service is restarted....</p>
			</div>
			<div class="modal-footer justify-content-between hidden alert_control">
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary" data-dismiss="modal">Close</button></a>
			</div>
		</div>
	</div>
</div>';

###################################################################################################
# Close page
###################################################################################################
site_footer('Init_WAN("' . $mac_com . '", "' . $mac . '");');
