<?php

#################################################################################################
# If a supported action is being specified, perform that action on the service provided:
#################################################################################################
if (empty($called_as_sub) && isset($_POST['action']))
{
	# "systemctl" needs superuser permission to execute actions on specified service:
	if (in_array($_POST['action'], array('enable', 'disable', 'start', 'stop')))
	{
		@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh systemctl ' . $_POST['action'] . ' ' . $_POST['misc']);
		die(trim(@shell_exec("systemctl is-active " . $service)) == "inactive" ? 'active' : 'inactive');
	}
	if ($_POST['action'] == 'status')
		die('<textarea id="output_div" class="form-control" rows="15" readonly="readonly" style="overflow-y: scroll;">' . @shell_exec('systemctl status ' . $_POST['misc']) . '</textarea>');
}
else if (empty($called_as_sub))
	die(require_once("404.php"));

#############################################################################################
# Start the services page, showing if the service is disabled and must be started to use
# the functionality provided:
#############################################################################################
function services_start($service)
{
	# Output site header with switch to enable service:
	$enabled = trim(@shell_exec("systemctl is-enabled " . $service)) == "enabled";
	site_menu(true, "Enabled", $enabled);

	# Output an alert box showing the service isn't running, and why it must be started:
	if (trim(@shell_exec("systemctl is-active " . $service)) == "inactive")
		echo '
	<div class="alert alert-danger" id="disabled_div">
		<div class="float-right">
			<button type="button" id="service_status" class="btn btn-sm bg-success">Service Status</button>
			<button type="button" id="service_start" class="btn btn-sm bg-success">Start Service</button>
		</div>
		<h5><i class="fas fa-thumbs-down"></i> &quot;', $service, '&quot; is ', !$enabled ? '<strong>NOT</strong> ' : '', 'enabled and <strong>NOT</strong> running.</h5>
	</div>';
	else
		echo '
	<div class="alert alert-primary">
		<div class="float-right">
			<button type="button" id="service_status" class="btn btn-sm bg-danger">Service Status</button>
			<button type="button" id="service_stop" class="btn btn-sm bg-danger">Stop Service</button>
		</div>
		<h5><i class="fas fa-thumbs-up"></i> &quot;', $service, '&quot; ', $enabled ? '' : 'not ', 'enabled and is running.</h5>
	</div>';
}
