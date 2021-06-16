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
echo '
<div class="card card-info">
	<div class="card-header">
		<h3 class="card-title">Internet IP Address</h3>
	</div>
	<div class="card-body">
		<div class="form-group clearfix">
			<div class="icheck-primary">
				<input type="radio" id="dynamic_ip" name="static_dynamic"', strpos($cfg['iface'], 'dhcp') > -1 ? ' checked="checked"' : '', '>
				<label for="dynamic_ip">Get Dynamically from ISP</label>
			</div>
			<div class="icheck-primary">
				<input type="radio" id="static_ip" name="static_dynamic"', strpos($cfg['iface'], 'dhcp') > -1 ? '' : ' checked="checked"', '>
				<label for="static_ip">Use Static IP Address</label>
			</div>
		</div>
		<div class="card-body table-responsive p-0">
			<table width="100%">
				<tr>
					<td width="30px"></td>
					<td width="45%"><label for="ip_address">IP Address</label></td>
					<td>
						<div class="input-group">
							<div class="input-group-prepend">
								<span class="input-group-text"><i class="fas fa-laptop"></i></span>
							</div>
							<input id="ip_address" type="text" class="ip_address form-control" value="', $wan['inet'], '" data-inputmask="\'alias\': \'ip\'" data-mask', strpos($cfg['iface'], 'dhcp') > -1 ? ' disabled="disabled"' : '', '>
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
							<input id="ip_address" type="text" class="ip_address form-control"  value="', $wan['netmask'], '"data-inputmask="\'alias\': \'ip\'" data-mask', strpos($cfg['iface'], 'dhcp') > -1 ? ' disabled="disabled"' : '', '>
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
							<input id="ip_address" type="text" class="ip_address form-control" value="', $gateway, '" data-inputmask="\'alias\': \'ip\'" data-mask', strpos($cfg['iface'], 'dhcp') > -1 ? ' disabled="disabled"' : '', '>
						</div>
					</td>
				</tr>
			</table>
		</div>
	</div>
	<!-- /.card-body -->';

###################################################################################################
# Domain Name (DNS) Servers
###################################################################################################
$dns1 = $dns2 = '';
foreach (file("/etc/pihole/setupVars.conf") as $line)
{
	if (preg_match("/PIHOLE_DNS_1=(.*)/", $line, $regex))
		$dns1 = $regex[1];
	else if (preg_match("/PIHOLE_DNS_2=(.*)/", $line, $regex))
		$dns2 = $regex[1];
}
echo '
	<div class="card-header">
		<h3 class="card-title">Domain Name (DNS) Servers</h3>
	</div>
	<div class="card-body">
		<div class="form-group clearfix">
			<div class="icheck-primary">
				<input type="radio" id="dns_doh" name="dns_server_opt"', strpos($dns1, '127.0.0.1#505') > -1 ? '' : ' checked="checked"', '>
				<label for="dns_doh">Use Cloudflare DNS over HTTPS (DoH) Server:</label>
				<span class="float-right">
					<select class="form-control select2" style="width: 100%;" id="doh_server" name="doh_server"', strpos($dns1, '127.0.0.1#505') > -1 ? ' disabled="disabled"' : '', '>
						<option value="127.0.0.1#5051">Regular 1.1.1.1</option>
						<option value="127.0.0.1#5052">Malware Blocking Only</option>
						<option value="127.0.0.1#5053">Malware And Adult Content Blocking</option>
					</select>
  				</span>
			</div>
			<div class="icheck-primary">
				<input type="radio" id="dns_custom" name="dns_server_opt"', strpos($dns1, '127.0.0.1#505') > -1 ? ' checked="checked"' : '', '>
				<label for="dns_custom">Use These DNS Servers</label>
			</div>
		</div>
		<div class="card-body table-responsive p-0">
			<table width="100%"">
				<tr>
					<td width="30px"></td>
					<td width="45%"><label for="ip_address">Primary DNS Server</label></td>
					<td>
						<div class="input-group">
							<div class="input-group-prepend">
								<span class="input-group-text"><i class="fas fa-laptop"></i></span>
							</div>
							<input id="dns1" type="text" class="dns_address form-control" value="', $dns1, '" data-inputmask="\'alias\': \'ip\'" data-mask', strpos($dns1, '127.0.0.1#505') > -1 ? '' : ' disabled="disabled"', '>
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
							<input id="dns2" type="text" class="dns_address form-control"  value="', $dns2, '"data-inputmask="\'alias\': \'ip\'" data-mask', strpos($dns1, '127.0.0.1#505') > -1 ? '' : ' disabled="disabled"', '>
						</div>
					</td>
				</tr>
			</table>
		</div>
	</div>
	<!-- /.card-body -->';
	
###################################################################################################
# Domain Name (DNS) Servers
###################################################################################################
$mac = explode(" ", trim($cfg['hwaddress']))[1];
$def = '08:00:00:00:00:01';
$leases = explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases")));
$mac_com = trim(@shell_exec("arp -n | grep " . $_SERVER['REMOTE_ADDR'] . " | awk '{print $3}'"));
echo '
	<div class="card-header">
		<h3 class="card-title">Router MAC Address</h3>
	</div>
	<div class="card-body">
		<div class="form-group clearfix">
			<div class="icheck-primary">
				<input class="mac_opt" type="radio" id="mac_default" name="router_mac"', $mac == $def ? ' checked="checked"' : '', '>
				<label for="mac_default">Use Default Address</label>
				<span class="float-right">
					<input id="mac_addr" name="mac_addr" type="text" class="form-control" value="', $mac, '" maxlength="17"', $mac == $def || $mac == $mac_com ? ' disabled="disabled"' : '', '>
  				</span>
			</div>
			<div class="icheck-primary">
				<input class="mac_opt" type="radio" id="mac_computer" name="router_mac"', $mac == $mac_com ? ' checked="checked"' : '', ' data-mac="', $mac_com, '"', $mac_com == "" ? ' disabled="disabled"' : '', '>
				<label for="mac_computer">Use Computer MAC Address</label>
			</div>
			<div class="icheck-primary">
				<input class="mac_opt" type="radio" id="mac_custom" name="router_mac"', $mac != $def and $mac != $mac_com ? ' checked="checked"' : '', '>
				<label for="mac_custom">Use this MAC Address</label>
			</div>
		</div>
	</div>
</div>';

site_footer('Setup_Internet("' . $mac_com . '");');
