<?php
#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	#################################################################################################
	# ACTION: LIST ==> List the current UPnP port mappings, as reported by calling "upnpc":
	#################################################################################################
	if ($_POST['action'] == 'list')
	{
		$str = array();
		foreach (explode("\n", trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh upnp list'))) as $line)
		{
			if (preg_match("/(\d+)\s+(TCP|UDP)\s+(\d+)\-\>(\d+\.\d+\.\d+\.\d+):(\d+)\s+\'([^\']*)\'\s+\'([^\']*)\' (\d+)/", $line, $regex))
			{
				$str[(int) $regex[3]] =
					'<tr>' .
						'<td><center>' . strval(((int) $regex[1]) + 1) . '</center></td>' .	// ID
						'<td><center>' . $regex[2] . '</center></td>' .	// UDP or TCP
						'<td><span class="float-right">' . $regex[3] . '</span></td>' .	// External Port
						'<td><span class="float-right">' . $regex[4] . '</span></td>' .	// IP Address
						'<td><span class="float-right">' . $regex[5] . '</span></td>' .	// External Port
						'<td>' . $regex[6] . '</td>' .	// Description
					'</tr>';
			}
		}
		ksort($str);
		die( !empty($str) ? implode("", $str) : '<tr><td colspan="7"><center>No UPnP Port Mappings</center></td></tr>' );
	}
	#################################################################################################
	# ACTION: SUBMIT ==> Update the UPnP configuration, per user settings:
	#################################################################################################
	if ($_POST['action'] == 'submit')
	{
		$secure = option("secure");
		$natpmp = option("natpmp");
		$params = ($natpmp ? 'natpmp-on' : 'natpmp-off') . ' ' . ($secure ? 'secure-on' : 'secure-off');
		shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh upnp ' . $params);
		die("OK");
	}
	#################################################################################################
	# ACTION: SUBMIT ==> Update the UPnP configuration, per user settings:
	#################################################################################################
	if ($_POST['action'] == 'enable' || $_POST['action'] == 'disable')
	{
		shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh upnp ' . $_POST['action']);
		die($_POST['action']);
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#########################################################################################
# Get everything we need to show the user:
#########################################################################################
$options['upnp_secure'] = trim(@shell_exec("cat /etc/miniupnpd/miniupnpd.conf | grep '^secure_mode' | cut -d= -f 2")) == "yes" ? "Y" : "N";
$options['upnp_natpmp'] = trim(@shell_exec("cat /etc/miniupnpd/miniupnpd.conf | grep '^enable_natpmp' | cut -d= -f 2")) == "yes" ? "Y" : "N";
$service_enabled = trim(@shell_exec("systemctl is-active miniupnpd")) == "active";
#echo (int) $service_enabled; exit;
site_menu(true, "Enabled", $service_enabled);

#########################################################################################
# Create an alert showing vnstat is disabled and must be started to gather info:
#########################################################################################
echo '
<div class="alert alert-danger', $service_enabled ? ' hidden' : '', '" id="disabled_div">
	<button type="button" id="toggle_service" class="btn bg-gradient-success float-right">Enable</button>
	<h5><i class="icon fas fa-ban"></i> Service Disabled!</h5>
	Service <i>miniupnpd</i> must be enabled to use Universal Plug and Play services!
</div>';

#################################################################################################
# Output the UPnP Settings page:
#################################################################################################
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Universal Plug and Play Settings</h3>
	</div>
	<div class="card-body">
		', checkbox("upnp_secure", "Enable Secure Mode (UPnP clients can only add mappings to their own IP)"), '
		', checkbox("upnp_natpmp", "Enable NAT Port Mapping Protocol"), '
		<hr style="border-width: 2px" />
		<h5>
			<a href="javascript:void(0);"><button type="button" id="upnp_refresh" class="btn btn-sm btn-primary float-right">Refresh</button></a>
			Current UPnP Port Mappings
		</h5>
		<div class="table-responsive p-0">
			<table class="table table-hover text-nowrap table-sm table-striped table-bordered">
				<thead class="bg-primary">
					<td width="10%"><center>ID</center></td>
					<td width="10%"><center>Protocol</center></td>
					<td width="10%"><center>Ext. Port</center></td>
					<td width="10%"><center>IP Address</center></td>
					<td width="10%"><center>Int. Port</center></td>
					<td width="50%">Description</td>
				</thead>
				<tbody id="upnp-table">
					<tr><td colspan="7"><center>Loading...</center></td></tr>
				</tbody>
			</table>
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="upnp_submit">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while the UPnP settings are managed....', true);
site_footer('Init_UPnP();');
