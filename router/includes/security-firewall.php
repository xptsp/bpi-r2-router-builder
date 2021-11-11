<?php
##############################################################################
# Read in the iptables configuration file:
##############################################################################
$file = '/etc/default/firewall';
$options = array();
foreach (explode("\n", trim(@file_get_contents($file))) as $line)
{
	$parts = explode("=", $line . '=');
	$options[$parts[0]] = $parts[1];
}

##############################################################################
# Helper function to simplify checkbox creation task:
##############################################################################
function checkbox($name, $description, $default = true, $disabled_by = '')
{
	global $options;
	$options[$name] = $checked = (!isset($options[$name]) ? $default : ($options[$name] == "Y"));
	$enabled = (!empty($disabled_by) ? $options[$disabled_by] : true);
	return '<p><input type="checkbox" id="' . $name . '" class="checkbox"' . ($checked ? ' checked="checked"' : '') . ' data-bootstrap-switch="" data-off-color="danger" data-on-color="success" ' . ($enabled ? '' : ' disabled="disabled"') . '> <strong id="' . $name . '_txt" ' . ($enabled ? '' : ' disabled="disabled"') . '>' . $description . '</strong></p>';
}

##############################################################################
# Output the configuration screen:
##############################################################################
site_menu();
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Firewall Settings</h3>
	</div>
	<div class="card-body">
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
		<a href="javascript:void(0);"><button type="button" id="apply_changes" class="btn btn-block btn-success center_50" data-toggle="modal" data-target="#apply-modal" id="reboot_button">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';

###################################################################################################
# Apply Changes modal:
###################################################################################################
echo '
<div class="modal fade" id="apply-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header bg-primary">
				<h4 class="modal-title">Applying Changes</h4>
				<a href="javascript:void(0);"><button type="button hidden alert_control" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button></a>
			</div>
			<div class="modal-body">
				<p id="apply_msg">Please wait while the firewall service is restarted....</p>
			</div>
			<div class="modal-footer justify-content-between hidden alert_control">
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary" data-dismiss="modal">Close</button></a>
			</div>
		</div>
	</div>
</div>';

###################################################################################################
# Close the page:
###################################################################################################
site_footer('Init_Firewall();');
