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
		$options['drop_port_scan'] = option('drop_port_scan');
		$options['log_port_scan']  = option('log_port_scan');
		$options['log_udp_flood']  = option('log_udp_flood');
		$options['drop_ping']      = option('drop_ping');
		$options['drop_ident']     = option('drop_ident');
		$options['drop_multicast'] = option('drop_multicast');
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
		', checkbox("drop_port_scan", "Enable Port Scan protection from Internet"), '
		<div id="port_scan_options"', empty($options['drop_port_scan']) ? ' class="hidden"' : '', ' style="margin-left: 20px">
			', checkbox("log_port_scan",  "Log Port Scan attempts from Internet", false, 'drop_port_scan'), '
			', checkbox("log_udp_flood",  "Log UDP Floods from Internet", false, 'drop_port_scan'), '
		</div>
		<hr />
		', checkbox("drop_ping",      "Do Not Respond to Pings from the Internet"), '
		', checkbox("drop_ident",     "Do Not Respond to IDENT requests from Internet (port 113)"), '
		', checkbox("drop_multicast", "Filter Multicast Packets from Internet", false), '
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" id="apply_changes" class="btn btn-block btn-success center_50" id="reboot_button">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while the firewall service is restarted....', true);
site_footer('Init_Firewall();');
