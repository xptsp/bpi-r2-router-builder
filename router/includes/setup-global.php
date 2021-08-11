<?php
require_once("subs/admin.php");
site_menu();
$wan = parse_ifconfig('wan');
#echo '<pre>'; print_r($wan); exit();
$cfg = parse_ifconfig('wan');
#echo '<pre>'; print_r($cfg); exit();
$gateway = @trim(shell_exec("ip route | grep default | grep wan | awk '{print $3}'"));
#echo $gateway; exit();

###################################################################################################
# Device Name
###################################################################################################
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title"><i class="fas fa-tag"></i> Wired Network Setup</h3>
	</div>
	<div class="card-body">
		<table width="100%">
			<tr>
				<td width="30px"></td>
				<td width="45%"><label for="hostname">Device Name</label></td>
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
# Domain Name (DNS) Servers
###################################################################################################
$dns1 = $dns2 = $cloudflare = '';
foreach (file("/etc/pihole/setupVars.conf") as $line)
{
	if (preg_match("/PIHOLE_DNS_1=(.*)/", $line, $regex))
		$dns1 = $regex[1];
	else if (preg_match("/PIHOLE_DNS_2=(.*)/", $line, $regex))
		$dns2 = $regex[1];
}
if (preg_match("/\#505(\d)/", $dns1, $regex))
{
	$cloudflare = $regex[1];
	$dns1 = $dns2 = '1.1.1.1';
}
echo '
	<div class="card-header">
		<h3 class="card-title"><i class="fas fa-server"></i> Domain Name (DNS) Servers</h3>
	</div>
	<div class="card-body">
		<div class="form-group clearfix">
			<div class="icheck-primary">
				<input type="radio" id="dns_doh" value="doh" name="dns_server_opt"', !empty($cloudflare) ? ' checked="checked"' : '', '>
				<label for="dns_doh">Use Cloudflare DNS over HTTPS (DoH) Server:</label>
				<span class="float-right">
					<select class="form-control select2" style="width: 100%;" id="doh_server" name="doh_server"', !empty($cloudflare) ? '' : ' disabled="disabled"', '>
						<option value="1"', $cloudflare == 1 ? ' selected="selected"' : '', '>Regular 1.1.1.1</option>
						<option value="2"', $cloudflare == 2 ? ' selected="selected"' : '', '>Malware Blocking Only</option>
						<option value="3"', $cloudflare == 3 ? ' selected="selected"' : '', '>Malware and Adult Content Blocking</option>
					</select>
  				</span>
			</div>
			<div class="icheck-primary">
				<input type="radio" id="dns_custom" value="custom" name="dns_server_opt"', !empty($cloudflare) ? '' : ' checked="checked"', '>
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
						<input id="dns1" type="text" class="dns_address form-control" value="', $dns1, '" data-inputmask="\'alias\': \'ip\'" data-mask', !empty($cloudflare) ? ' disabled="disabled"' : '', '>
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
						<input id="dns2" type="text" class="dns_address form-control"  value="', $dns2, '"data-inputmask="\'alias\': \'ip\'" data-mask', !empty($cloudflare) ? ' disabled="disabled"' : '', '>
					</div>
				</td>
			</tr>
		</table>
	</div>';
	
###################################################################################################
# Router MAC Address settings:
###################################################################################################
$mac = trim($cfg['ether']);
$def = @file_get_contents('/boot/eth0.conf');
$def = empty($def) ? $mac : $def;
$leases = explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases")));
$mac_com = trim(@shell_exec("arp -n | grep " . $_SERVER['REMOTE_ADDR'] . " | awk '{print $3}'"));
$mac_chk = ($mac == $def || $mac == $mac_com);
echo '
	<div class="card-header">
		<h3 class="card-title"><i class="far fa-address-card"></i> Router MAC Address</h3>
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
				<label for="mac_random">Generate Random MAC Address</label>
			</div>
		</div>
	</div>';

###################################################################################################
# Apply Changes button:
###################################################################################################
echo '
	<div class="card-footer">
		<button type="button" class="btn btn-block btn-success center_50" id="submit">Apply Changes</button>
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
site_footer('Init_Global("' . $mac_com . '");');
