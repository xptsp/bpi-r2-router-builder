<?php
require_once("subs/manage.php");
require_once("subs/setup.php");
$called_as_sub = true;
require_once("services.php");

#########################################################################################
# Get everything we need to show the user:
#########################################################################################
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

$listen = array();
foreach (explode(" ", str_replace('"', '', $options['MULTICAST_IFACES'])) as $tface)
	$listen[$tface] = $tface;
#echo '<pre>'; print_r($listen); exit();

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
		$options['MULTICAST_IFACES'] = '"' . str_replace(",", " ", option_allowed("listen_on", $valid_listen, false)) . '"';
		apply_options("upnp");
		die(@shell_exec('router-helper move multicast-relay restart'));
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# Output the Multicast Relay page:
#################################################################################################
services_start('multicast-relay');
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
