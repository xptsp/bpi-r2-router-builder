<?php
$options = parse_options();

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	#################################################################################################
	# ACTION: SUBMIT ==> Update the configuration file using the parameters specified:
	#################################################################################################
	if ($_POST['action'] == 'submit')
	{
		$options['allow_ping']      = option('allow_ping');
		$options['allow_ident']     = option('allow_ident');
		$options['allow_multicast'] = option('allow_multicast');
		$options['redirect_dns']    = option('redirect_dns');
		$options['allow_dot']       = option('allow_dot');
		$options['allow_doq']       = option('allow_doq');
		#echo '<pre>'; print_r($options); exit;
		apply_options();
		die("OK");
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# Output the Firewall settings page:
#################################################################################################
site_menu();
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Firewall Settings</h3>
	</div>
	<div class="card-body" id="firewall-div">
		', checkbox("allow_ping",      "Respond to Pings from the Internet"), '
		', checkbox("allow_ident",     "Respond to IDENT requests (port 113) from Internet"), '
		', checkbox("allow_multicast", "Allow Multicast Packets from Internet", false), '
		<hr style="border-width: 2px" />
		', checkbox("redirect_dns", "Redirect all DNS requests to Integrated Pi-Hole"), '
		', checkbox("block_dot", "Block outgoing DoT (DNS-over-TLS - port 853) requests not from router"), '
		', checkbox("block_doq", "Block outgoing DoQ (DNS-over-QUIC - port 8853) requests not from router"), '
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" id="apply_changes" class="btn btn-block btn-success center_50" id="reboot_button">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while the firewall service is restarted....', true);
site_footer('Init_Firewall();');
