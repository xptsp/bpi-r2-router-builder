<?php
require_once("subs/advanced.php");
$options = parse_file();
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
security_apply_changes();
site_footer('Init_Firewall();');
