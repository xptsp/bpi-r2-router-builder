<?php
require_once("subs/manage.php");
require_once("subs/setup.php");

#################################################################################################
# Gather the information needed by this page:
#################################################################################################
$options = parse_options();
$ifaces = get_network_adapters();
#echo '<pre>'; print_r($ifaces); exit();
$ext_ifaces = explode("\n", @trim(@shell_exec("grep masquerade /etc/network/interfaces.d/* | cut -d: -f 1 | cut -d\/ -f 5")));
#echo '<pre>'; print_r($ext_ifaces); exit();
$exclude_arr = array("docker0", "lo", "sit0", "eth0", "eth1", "aux");
#echo $exclude_regex; exit;
$valid_listen = array_diff( array_keys($ifaces), $exclude_arr, $ext_ifaces );
#echo '<pre>'; print_r($valid_listen); exit();
$listen = explode(" ", str_replace('"', '', isset($options['mosquitto_ifaces']) ? $options['mosquitto_ifaces'] : ''));
#echo '<pre>'; print_r($listen); exit();

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
		$options['enable_mosquitto'] = option('enabled');
		if ($options['enable_mosquitto'] == 'Y')
		{
			$options['mosquitto_ifaces'] = '"' . str_replace(",", " ", option_allowed("send_on", $valid_listen, false)) . '"';
			$options['mosquitto_addr'] = option_ip('ip_addr', false, false, true);
			$options['mosquitto_port'] = option_range('ip_port', 0, 65535);
			$options['mosquitto_user'] = option('username', '/([\w\d\-]|)/');
			if (empty($options['mosquitto_user']))
				$options['mosquitto_user'] = '""';
			$options['mosquitto_pass'] = option('password', '/([\w\d\-]|)/');
			if (empty($options['mosquitto_pass']))
				$options['mosquitto_pass'] = '""';
		}
		die(apply_options());
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# Output the Mosquitto settings page:
#################################################################################################
site_menu();
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Mosquitto</h3>
	</div>
	<div class="card-body">
		', checkbox("enable_mosquitto", "Send DHCP notifications to Mosquitto server"), '
		<div id="mosquitto_options"', $options['enable_mosquitto'] == "N" ? ' class="hidden"' : '', '>
			<hr style="border-width: 2px" />
			<div class="row">
				<div class="col-sm-6">
					<label for="ip_address">Mosquitto Server Address:</label>
				</div>
				<div class="col-sm-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-server"></i></span>
						</div>
						<input id="ip_addr" type="text" placeholder="127.0.0.1" class="ip_address form-control" placeholder="127.0.0.1" value="', $options['mosquitto_addr'], '">
						<div class="input-group-prepend">
							<span class="input-group-text" title="Port Number"><i class="fas fa-hashtag"></i></span>
						</div>
						<input id="ip_port" type="text" class="form-control" placeholder="1883" value="', $options['mosquitto_port'], '">
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-sm-6">
					<label for="ip_addr">Mosquitto Username:</label>
				</div>
				<div class="col-sm-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-user"></i></i></span>
						</div>
						<input id="username" type="text" class="form-control" value="', $options['mosquitto_user'], '">
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-sm-6">
					<label for="ip_addr">Mosquitto User Password:</label>
				</div>
				<div class="col-sm-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-key"></i></span>
						</div>
						<input id="password" type="text" class="form-control" value="', $options['mosquitto_pass'], '">
					</div>
				</div>
			</div>
			<hr style="border-width: 2px" />
			<div class="row" style="margin-top: 5px">
				<div class="col-sm-6">
					<label for="listening_on">Notification Interfaces:</label>
				</div>
				<div class="col-sm-3">
					<select class="form-control" id="send_on" multiple="multiple">';
foreach ($valid_listen as $tface)
{
	echo '
						<option value="', $tface, '"', in_array($tface, $listen) ? ' selected="selected"' : '', '>' . $tface . '</option>';
}
echo '
					</select>
				</div>
			</div>
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while changes are pending....', true);
site_footer('Init_Notify();');
