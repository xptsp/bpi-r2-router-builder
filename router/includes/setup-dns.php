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
$primary = empty($current[0]) ? '' : $current[0];
$secondary = empty($current[1]) ? '' : $current[1];
$providers = array(
	array('Google', '8.8.8.8', '8.8.4.4'),
	array('Cloudflare', '1.1.1.1', '1.0.0.1'),
	array('Cloudflare - Malware Filter', '1.1.1.2', '1.0.0.2'),
	array('Cloudflare - Malware and Adult Filter', '1.1.1.3', '1.0.0.3'),
	array('OpenDNS', '208.67.222.222', '208.67.220.220'),
	array('OpenDNS - FamilyShield', '208.67.222.123', '208.67.220.123'),
	array('Quad9', '9.9.9.9', '149.112.112.112'),
	array('Quad9 - No Malware Blocking', '9.9.9.10', '149.112.112.10'),
	array('CleanBrowsing', '185.228.168.9', '185.228.169.9'),
	array('CleanBrowsing - Adult Filter', '185.228.168.10', ''),
	array('CleanBrowsing - Family Filter', '185.228.168.168', '185.228.168.168'),
	array('AdGuard DNS', '94.140.14.14', '94.140.15.15'),
	array('AdGuard DNS - Non-Filtering', '94.140.14.140', '94.140.15.141'),
	array('AdGuard DNS - Family Protection', '94.140.14.15', '94.140.15.16'),
	array('Alternate DNS', '76.76.19.19', '76.223.122.150'),
);
$use_provider = false;
foreach ($providers as $provider)
	$use_provider |= ($primary == $provider[1] && $secondary == $provider[2]);
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Domain Name Servers</h3>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-6">
				<div class="form-group clearfix">
					<div class="icheck-primary">
						<input type="radio" id="dns_provider" value="alt" name="dns_server_opt"', $use_provider ? ' checked="checked"' : '', '>
						<label for="dns_provider">Use Public DNS Servers</label>
					</div>
					<div class="icheck-primary">
						<input type="radio" id="dns_isp" value="isp" name="dns_server_opt"', !$use_provider && empty($custom) ? ' checked="checked"' : '', '>
						<label for="dns_isp">Get Automatically from ISP</label>
					</div>
					<div class="icheck-primary">
						<input type="radio" id="dns_custom" value="custom" name="dns_server_opt"', !$use_provider && !empty($custom) ? ' checked="checked"' : '', '>
						<label for="dns_custom">Manually Set DNS Servers</label>
					</div>
				</div>
			</div>
			<div class="col-6">
				<div class="form-group">
					<select class="form-control" id="providers"', !$use_provider ? ' disabled="disabled"' : '', '>';
foreach ($providers as $provider)
	echo '
						<option value="', $provider[1], '/', $provider[2], '"', ($primary == $provider[1] && $secondary == $provider[2]) ? ' selected="selected"' : '', '>', $provider[0], '</option>';
echo '
					</select>
				</div>
			</div>
		</div>
		<hr />
		<div class="row">
			<div class="col-6">
				<label for="ip_address">Primary DNS Server</label>
			</div>
			<div class="col-6">
				<div class="input-group">
					<div class="input-group-prepend">
						<span class="input-group-text"><i class="fas fa-laptop"></i></span>
					</div>
					<input id="dns1" type="text" class="dns_address form-control" value="', $primary, '" data-inputmask="\'alias\': \'ip\'" data-mask', empty($custom) ? ' disabled="disabled"' : '', '>
				</div>
			</div>
		</div>
		<div class="row" style="margin-top: 5px">
			<div class="col-6">
				<label for="ip_address">Secondary DNS Server</label>
			</div>
			<div class="col-6">
				<div class="input-group">
					<div class="input-group-prepend">
						<span class="input-group-text"><i class="fas fa-laptop"></i></span>
					</div>
					<input id="dns2" type="text" class="dns_address form-control"  value="', $secondary, '"data-inputmask="\'alias\': \'ip\'" data-mask', empty($custom) ? ' disabled="disabled"' : '', '>
				</div>
			</div>
		</div>
	</div>';

###################################################################################################
# Apply Changes button:
###################################################################################################
echo '
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="submit">Apply Changes</button></a>
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
site_footer('Init_DNS("' . $primary . '", "' . $secondary . '");');
