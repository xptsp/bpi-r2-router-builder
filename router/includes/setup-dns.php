<?php
require_once("subs/manage.php");
$options = parse_options();

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

	#################################################################################################
	# ACTION: SUBMIT => Make the requested changes to the firewall
	#################################################################################################
	if ($_POST['action'] == 'submit')
	{
		// Apply configuration file changes:
		$options['use_isp']         = option('use_isp');
		$options['use_cloudflared'] = option_allowed('use_cloudflared', array("N", "1", "2", "3"));
		$options['dns1']            = option_ip('dns1', false, true);
		$options['dns2']            = option_ip('dns2', true, true);
		$options['redirect_dns']    = option('redirect_dns');
		$options['block_dot']       = option('block_dot');
		$options['block_doq']       = option('block_doq');
		#echo '<pre>'; print_r($options); exit;
		die(apply_options());
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

###################################################################################################
# Domain Name (DNS) Servers
###################################################################################################
site_menu();
$isp = $current = array();
foreach (@file("/etc/dnsmasq.d/01-pihole.conf") as $line)
{
	if (preg_match("/^server=(.*)/", $line, $regex))
		$current[ count($current) ] = $regex[1];
}
$primary = empty($current[0]) ? '' : $current[0];
$secondary = empty($current[1]) ? '' : $current[1];
foreach (@file("/etc/resolv.conf") as $line)
{
	if (preg_match("/^nameserver (.*)/", $line, $regex))
		$isp[ count($isp) ] = $regex[1];
}
$use_isp = (empty($isp[0]) || $primary == $isp[0]) && (empty($isp[1]) ? true : ($secondary == $isp[1]));
$cloudflared_mode = preg_match('/^127\.0\.0\.1\#505(\d)$/', $primary, $regex) ? $regex[1] : 'N';
$providers = array(
	array('Google', '8.8.8.8', '8.8.4.4'),
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
	array('Level3 DNS', '4.2.2.1', '4.2.2.2'),
	array('Comodo Secure DNS', '8.26.56.26', '8.20.247.20'),
	array('DNS.WATCH', '84.200.69.80', '84.200.70.40'),
);
$use_provider = false;
foreach ($providers as $provider)
	$use_provider |= ($primary == $provider[1] && $secondary == $provider[2]);
$use_custom = !($use_isp || $cloudflared_mode != "N"  || $use_provider);
#echo '<pre>$current = '; print_r($current); echo '$primary = ' . $primary . "\n" . '$secondary = ' . $secondary . "\n" . '$isp = '; print_r($isp); echo '$use_isp = ' . ($use_isp ? 'Y' : 'N') . "\n"; echo '$cloudflared_mode = ' . ($cloudflared_mode ? 'Y' : 'N') . "\n"; echo '$use_provider = ' . ($use_provider ? 'Y' : 'N') . "\n"; echo '$use_custom = ' . ($use_custom ? 'Y' : 'N') . "\n"; exit;

###################################################################################################
# Output the DNS Settings page:
###################################################################################################
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
						<input type="radio" id="dns_cloud" value="cloudflared" name="dns_server_opt"', $cloudflared_mode != "N" ? ' checked="checked"' : '', '>
						<label for="dns_cloud">Use Cloudflare DNS Servers (DoH)</label>
					</div>
					<div class="icheck-primary">
						<input type="radio" id="dns_isp" value="isp" name="dns_server_opt"', $use_isp ? ' checked="checked"' : '', '>
						<label for="dns_isp">Get Automatically from ISP</label>
					</div>
					<div class="icheck-primary">
						<input type="radio" id="dns_custom" value="custom" name="dns_server_opt"', $use_custom ? ' checked="checked"' : '', '>
						<label for="dns_custom">Manually Set DNS Servers</label>
					</div>
				</div>
			</div>
			<div class="col-6">
				<div class="form-group">
					<select class="provider form-control', !$use_provider ? ' hidden' : '', '" id="select_provider">';
foreach ($providers as $provider)
	echo '
						<option value="', $provider[1], '/', $provider[2], '"', ($primary == $provider[1] && $secondary == $provider[2]) ? ' selected="selected"' : '', '>', $provider[0], '</option>';
echo '
					</select>
					<select class="provider form-control', $cloudflared_mode == "N" ? ' hidden' : '', '" id="select_cloudflared">
						<option value="127.0.0.1#5051"', $cloudflared_mode == "1" ? ' selected="selected"' : '', '>Cloudflare</option>
						<option value="127.0.0.1#5052"', $cloudflared_mode == "2" ? ' selected="selected"' : '', '>Cloudflare - Malware Filter</option>
						<option value="127.0.0.1#5053"', $cloudflared_mode == "3" ? ' selected="selected"' : '', '>Cloudflare - Malware and Adult Filter</option>
					</select>
				</div>
			</div>
		</div>
		<div id="dns_settings">
			<hr />
			<div class="row">
				<div class="col-6">
					<label for="ip_address">Primary DNS Server</label>
				</div>
				<div class="col-4">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="dns1" type="text" placeholder="127.0.0.1" class="dns_address form-control" value="', explode("#", $primary . "#")[0], '"', !$use_isp ? ' disabled="disabled"' : '', '>
					</div>
				</div>
				<div class="col-2">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text" title="Port Number"><i class="fas fa-hashtag"></i></span>
						</div>
						<input id="dns_port1" type="text" class="dns_port form-control" placeholder="53" value="', explode("#", $primary . "#")[1], '"', empty($use_isp) ? ' disabled="disabled"' : '', '>
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_address">Secondary DNS Server</label>
				</div>
				<div class="col-4">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="dns2" type="text" placeholder="127.0.0.1" class="dns_address form-control" value="', explode("#", $secondary . "#")[0], '"', !$use_isp ? ' disabled="disabled"' : '', '>
					</div>
				</div>
				<div class="col-2">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text" title="Port Number"><i class="fas fa-hashtag"></i></span>
						</div>
						<input id="dns_port2" type="text" class="dns_port form-control" placeholder="53" value="', explode("#", $secondary . "#")[1], '"', empty($use_isp) ? ' disabled="disabled"' : '', '>
					</div>
				</div>
			</div>
		</div>
		<hr />
		', checkbox("redirect_dns", "Redirect all DNS requests to Integrated Pi-Hole"), '
		', checkbox("block_dot", "Block outgoing DoT (DNS-over-TLS - port 853) requests not from router"), '
		', checkbox("block_doq", "Block outgoing DoQ (DNS-over-QUIC - port 8853) requests not from router"), '
	</div>';

###################################################################################################
# Apply Changes button:
###################################################################################################
echo '
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="submit">Apply Changes</button></a>
	</div>';

###################################################################################################
# Close page
###################################################################################################
apply_changes_modal("Please wait while the Pi-Hole FTL service is restarted....", true);
site_footer('Init_DNS("' . (!empty($isp[0]) ? $isp[0] : '') . '", "' . (!empty($isp[1]) ? $isp[1] : '') . '");');
