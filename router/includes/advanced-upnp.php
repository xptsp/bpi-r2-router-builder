<?php
$options = parse_options();

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

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
		$enabled = option("enabled");
		$secured = option("secured");
		shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh upnp ' . ($secured ? 'secure-on' : 'secure-off') . ' ' . ($enabled ? 'enable' : 'disable'));
		echo "OK";
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# Output the UPnP Settings page:
#################################################################################################
$options['upnp-enable'] = trim(@shell_exec("systemctl is-enabled miniupnpd")) == "enabled" ? "Y" : "N";
$options['upnp-secure'] = trim(@shell_exec("cat /etc/miniupnpd/miniupnpd.conf | grep '^secure_mode' | cut -d= -f 2")) == "yes" ? "Y" : "N";
site_menu();
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Universal Plug and Play (UPnP) Settings</h3>
	</div>
	<div class="card-body">
		', checkbox("upnp_enable", "Enable UPnP (Universal Plug and Play) on this router"), '
		', checkbox("upnp_secure", "UPnP clients can only add mappings to their own IP"), '
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
