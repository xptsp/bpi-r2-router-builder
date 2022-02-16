<?php
require_once("subs/manage.php");
require_once("subs/setup.php");

$config = parse_options("/etc/miniupnpd/miniupnpd.conf");
#echo '<pre>'; print_r($config); exit;
$ifaces = get_network_adapters();
#echo '<pre>'; print_r($ifaces); exit();
$ext_ifaces = explode("\n", @trim(@shell_exec("grep masquerade /etc/network/interfaces.d/* | cut -d: -f 1 | cut -d\/ -f 5")));
#echo '<pre>'; print_r($ext_ifaces); exit();
$exclude_arr = array("docker.+", "lo", "sit.+", "eth0", "eth1", "aux");
#echo $exclude_regex; exit;
$valid_listen = array_diff( array_keys($ifaces), $exclude_arr, $ext_ifaces );
#echo '<pre>'; print_r($valid_listen); exit();

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
		$params  = (option("natpmp") == "Y" ? 'natpmp-on' : 'natpmp-off') . ' ';
		$params .= (option("secure") == "Y" ? 'secure-on' : 'secure-off') . ' ';
		$params .= 'ext=' . option_allowed("ext_iface", $ext_ifaces) . ' ';
		$params .= 'listen=' . option_allowed("listen", $valid_listen, false) . ' ';
		die(shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh upnp ' . $params . ' restart'));
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
$listen = array();
foreach (explode(" ", $config['listening_ip']) as $tface)
	$listen[$tface] = $tface;
#echo '<pre>'; print_r($listen); exit();

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
		<div class="row" style="margin-top: 5px">
			<div class="col-6">
				<label for="listening_on">Listening Interfaces:</label>
			</div>
			<div class="col-3">
				<select class="form-control" id="listening_on" multiple>';
foreach ($valid_listen as $tface)
{
	echo '
					<option value="', $tface, '"', isset($listen[$tface]) ? ' selected="selected"' : '', '>' . $tface . '</option>';
}
echo '
				</select>
			</div>
		</div>
		<div class="row" style="margin-top: 5px">
			<div class="col-6">
				<label for="ext_iface">External Interface:</label>
			</div>
			<div class="col-3">
				<select class="form-control" id="ext_iface">';
foreach ($ext_ifaces as $tface)
{
	echo '
					<option value="', $tface, '"', $config['listening_ip'] == $tface ? ' selected="selected"' : '', '>' . $tface . '</option>';
}
echo '
				</select>
			</div>
		</div>
		<div class="row" style="margin-top: 5px">
			<div class="col-6">
				<label for="upnp_secure">Enable Secure Mode: (Clients can only map to own IP)</label>

			</div>
			<div class="col-6">
				', checkbox("upnp_secure", '&nbsp;'), '
			</div>
		</div>
		<div class="row">
			<div class="col-6">
				<label for="upnp_natpmp">Enable NAT Port Mapping Protocol:</label>

			</div>
			<div class="col-6">
				', checkbox("upnp_secure", '&nbsp;'), '
			</div>
		</div>
		<hr style="border-width: 2px" />';

#################################################################################################
# Output the current UPnP port mappings:
#################################################################################################
echo '
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
