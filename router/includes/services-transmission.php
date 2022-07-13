<?php
require_once("subs/manage.php");
require_once("subs/setup.php");
$called_as_sub = true;
require_once("services.php");

#########################################################################################
# Get everything we need to show the user:
#########################################################################################
# Allowable WebUI interface values:
$webui_allowed = array();
foreach (array_diff(glob('/usr/share/transmission/*', GLOB_ONLYDIR), array("/usr/share/transmission/web")) as $webui)
	$webui_allowed[] = basename($webui);
#echo '<pre>'; print_r($webui_allowed); exit;

$options = parse_options("/etc/default/transmission-default");
#echo '<pre>'; print_r($options); exit;
$trans_port = isset($options['TRANS_PORT']) ? $options['TRANS_PORT'] : "9091";
#echo '<pre>'; print_r($trans_port); exit;
$trans_user = isset($options['TRANS_USER']) ? $options['TRANS_USER'] : "pi";
#echo '<pre>'; print_r($trans_user); exit;
$trans_pass = isset($options['TRANS_PASS']) ? $options['TRANS_PASS'] : "bananapi";
#echo '<pre>'; print_r($trans_pass); exit;
$trans_webui = isset($options['TRANS_WEBUI']) ? $options['TRANS_WEBUI'] : $webui_allowed[0];
#echo '<pre>'; print_r($trans_webui); exit;
$iface = parse_ifconfig('br0');
#echo '<pre>'; print_r($iface); exit;

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	#################################################################################################
	# ACTION: SUBMIT ==> Make the changes as requested by the caller:
	#################################################################################################
	if ($_POST['action'] == 'submit')
	{
		// Apply configuration file changes:
		$options['TRANS_PORT']  = option_range('TRANS_PORT', 1, 65535);
		$options['TRANS_USER']  = option_string('TRANS_USER', 'Username');
		$options['TRANS_PASS']  = option_string('TRANS_PASS', 'Password');
		$options['TRANS_WEBUI'] = option_allowed('TRANS_WEBUI', $webui_allowed);
		apply_options("upnp");
		@shell_exec('router-helper move transmission-daemon restart');
		die("OK");
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# Output the Transmission Daemon Settings page:
#################################################################################################
services_start('transmission-daemon');
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
					<input type="text" placeholder="127.0.0.1" class="dns_address form-control" value="', $iface['inet'], '" disabled="disabled">
					<div class="input-group-prepend">
						<span class="input-group-text" title="Port Number">:</span>
					</div>
					<input id="td_port" type="text" class="dns_port form-control" placeholder="9091" value="', $trans_port, '">
				</div>
			</div>
			<div class="col-sm-2">
				<a href="http://', $iface['inet'], ':', $trans_port,'"><button type="button" class="btn btn-block btn-primary">Visit WebUI</button></a>
			</div>
		</div>
		<hr style="border-width: 2px" />
		<div class="input-group mb-2">
			<label for="username" class="col-sm-6 col-form-label">Transmission User Name:</label>
			<div class="input-group col-sm-6">
				<div class="input-group-prepend">
					<span class="input-group-text"><i class="fas fa-user"></i></span>
				</div>
				<input type="text" class="form-control" id="username" name="username" value="', $trans_user, '"  placeholder="Old Password">
			</div>
		</div>
		<div class="input-group mb-2">
			<label for="password" class="col-sm-6 col-form-label">Transmission Password:</label>
			<div class="input-group col-sm-6">
				<div class="input-group-prepend">
					<span class="input-group-text"><i class="fas fa-key"></i></span>
				</div>
				<input type="text" class="form-control" id="password" name="password" value="', $trans_pass, '" placeholder="Required">
			</div>
		</div>
		<hr style="border-width: 2px" />
		<div class="input-group mb-2">
			<label for="password" class="col-sm-6 col-form-label">Transmission WebUI:</label>
			<div class="input-group col-sm-6">
				<div class="input-group-prepend">
					<span class="input-group-text"><i class="fas fa-wrench"></i></span>
				</div>
				<select class="form-control" id="webui"', count($langs) == 1 ? ' disabled="disabled"' : '', '>';
foreach ($webui_allowed as $webui)
	echo '
					<option value="', $webui, '"', $trans_webui == $webui_allowed ? ' selected="selected"' : '', '>', $webui, '</option>';
echo '
				</select>
			</div>
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="transmission_submit">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while the Transmission daemon settings are submitted....', true);
site_footer('Init_Transmission();');
