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
	# ACTION: SUBMIT ==> Update the configuration file using the parameters specified:
	#################################################################################################
	if ($_POST['action'] == 'submit')
	{
		$options['enable_mosquitto'] = option('enabled');
		if ($options['enable_mosquitto'] == 'Y')
		{
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
		', checkbox("enable_mosquitto", "Notify Mosquitto server when DHCP clients connect or disconnect"), '
		<div id="mosquitto_options"', $options['enable_mosquitto'] == "N" ? ' class="hidden"' : '', '>
			<hr style="border-width: 2px" />
			<div class="row">
				<div class="col-6">
					<label for="ip_address">Mosquitto Server Address:</label>
				</div>
				<div class="col-4">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-server"></i></span>
						</div>
						<input id="ip_addr" type="text" placeholder="127.0.0.1" class="ip_address form-control" placeholder="127.0.0.1" value="', $options['mosquitto_addr'], '">
					</div>
				</div>
				<div class="col-2">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text" title="Port Number"><i class="fas fa-hashtag"></i></span>
						</div>
						<input id="ip_port" type="text" class="form-control" placeholder="1883" value="', $options['mosquitto_port'], '">
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_addr">Mosquitto Username:</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-user"></i></i></span>
						</div>
						<input id="username" type="text" class="form-control" value="', $options['mosquitto_user'], '">
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_addr">Mosquitto User Password:</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend pass_toggle">
							<span class="input-group-text"><i class="fas fa-eye"></i></span>
						</div>
						<input id="password" type="password" class="form-control" value="', $options['mosquitto_pass'], '">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while the settings are updated....', true);
site_footer('Init_Mosquitto();');
