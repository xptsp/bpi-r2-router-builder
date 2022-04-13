<?php
require_once("subs/manage.php");
require_once("subs/setup.php");

$options = parse_options("/etc/default/transmission-default");
#echo '<pre>'; print_r($options); exit;

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	#################################################################################################
	# ACTION: LIST ==> Make the changes as requested by the caller:
	#################################################################################################
	if ($_POST['action'] == 'submit')
	{
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#########################################################################################
# Get everything we need to show the user:
#########################################################################################
$service_enabled = trim(@shell_exec("systemctl is-active transmission-daemon")) == "active";
#echo (int) $service_enabled; exit;
site_menu(true, "Enabled", $service_enabled);
$iface = parse_ifconfig('br0');
#echo '<pre>'; print_r($iface); exit;

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
# Output the Transmission Daemon Settings page:
#################################################################################################
$trans_port = isset($options['TRANS_PORT']) ? $options['TRANS_PORT'] : "9091";
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Transmission Daemon Settings</h3>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-sm-6">
				<label for="ip_address">Transmission WebUI Address</label>
			</div>
			<div class="col-sm-4">
				<div class="input-group">
					<div class="input-group-prepend">
						<span class="input-group-text">http://</span>
					</div>
					<input id="dns1" type="text" placeholder="127.0.0.1" class="dns_address form-control" value="', $iface['inet'], '" disabled="disabled">
					<div class="input-group-prepend">
						<span class="input-group-text" title="Port Number">:</span>
					</div>
					<input id="dns_port1" type="text" class="dns_port form-control" placeholder="9091" value="', $trans_port, '">
				</div>
			</div>
			<div class="col-sm-2">
				<a href="http://', $iface['inet'], ':', $trans_port,'"><button type="button" class="btn btn-block btn-primary">Visit WebUI</button></a>
			</div>
		</div>
		<hr />
		<div class="input-group mb-2">
			<label for="username" class="col-sm-6 col-form-label">Transmission User Name:</label>
			<div class="input-group col-sm-6">
				<div class="input-group-prepend">
					<span class="input-group-text"><i class="fas fa-user"></i></span>
				</div>
				<input type="text" class="form-control" id="username" name="username" value="', isset($options['TRANS_USER']) ? $options['TRANS_USER'] : "pi", '"  placeholder="Old Password">
			</div>
		</div>
		<div class="input-group mb-2">
			<label for="password" class="col-sm-6 col-form-label">Transmission Password:</label>
			<div class="input-group col-sm-6">
				<div class="input-group-prepend">
					<span class="input-group-text"><i class="fas fa-key"></i></span>
				</div>
				<input type="text" class="form-control" id="password" name="password" value="', isset($options['TRANS_PASS']) ? $options['TRANS_PASS'] : "bananapi", '" placeholder="Required">
			</div>
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="upnp_submit">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while the Transmission daemon settings are submitted....', true);
site_footer('Init_Transmission();');
