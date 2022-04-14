<?php
require_once("subs/manage.php");
require_once("subs/setup.php");

$options = parse_options("/etc/default/multicast-relay");
#echo '<pre>'; print_r($options); exit;
$ifaces = get_network_adapters();
#echo '<pre>'; print_r($ifaces); exit();
$ext_ifaces = explode("\n", @trim(@shell_exec("grep masquerade /etc/network/interfaces.d/* | cut -d: -f 1 | cut -d\/ -f 5")));
#echo '<pre>'; print_r($ext_ifaces); exit();
$exclude_arr = array("docker0", "lo", "sit0", "eth0", "eth1", "aux");
#echo $exclude_regex; exit;
$valid_listen = array_diff( array_keys($ifaces), $exclude_arr, $ext_ifaces );
#echo '<pre>'; print_r($valid_listen); exit();

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	#################################################################################################
	# ACTION: SUBMIT ==> Update the UPnP configuration, per user settings:
	#################################################################################################
	if ($_POST['action'] == 'submit')
	{
		$options['MULTICAST_IFACES'] = '"' . str_replace(",", "", option_allowed("listen_on", $valid_listen, false)) . '"';
		apply_options("upnp");
		die(shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh multicast move restart'));
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
foreach (explode(" ", str_replace('"', '', $options['MULTICAST_IFACES'])) as $tface)
	$listen[$tface] = $tface;
#echo '<pre>'; print_r($listen); exit();

$service_enabled = trim(@shell_exec("systemctl is-active multicast-relay")) == "active";
#echo (int) $service_enabled; exit;
site_menu(true, "Enabled", $service_enabled);

#########################################################################################
# Create an alert showing vnstat is disabled and must be started to gather info:
#########################################################################################
echo '
<div class="alert alert-danger', $service_enabled ? ' hidden' : '', '" id="disabled_div">
	<button type="button" id="toggle_service" class="btn bg-gradient-success float-right">Enable</button>
	<h5><i class="icon fas fa-ban"></i> Service Disabled!</h5>
	Service <i>multicast-relay</i> must be enabled to use Universal Plug and Play services!
</div>';

#################################################################################################
# Output the UPnP Settings page:
#################################################################################################
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Multicast Relay Settings</h3>
	</div>
	<div class="card-body">
		<div class="row" style="margin-top: 5px">
			<div class="col-sm-6">
				<label for="listening_on">Listening Interfaces:</label>
			</div>
			<div class="col-sm-3">
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
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="multicast_submit">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while the UPnP settings are managed....', true);
site_footer('Init_Multicast();');
