<?php

#################################################################################################
# If a supported action is being specified, perform that action on the service provided:
#################################################################################################
if (empty($called_as_sub) && isset($_POST['action']))
{
	# "systemctl" needs superuser permission to execute actions on specified service:
	if (in_array($_POST['action'], array('enable', 'disable', 'start', 'stop', 'restart', 'reload')))
	{
		@shell_exec('router-helper systemctl ' . $_POST['action'] . ' ' . $_POST['misc']);
		die('OK');
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
function services_start($service, $reload = false)
{
	$enabled = trim(@shell_exec("systemctl is-enabled " . $service)) == "enabled";
	$active = trim(@shell_exec("systemctl is-active " . $service)) == "active";
	$skipped = !empty(trim(@shell_exec('systemctl status ' . $service . ' | grep "' . $service . '.service: Skipped due to \'exec-condition\'."')));
	$mode = ($enabled && $active) ? "success" : (($enabled && !$active && !$skipped) ? "warning" : ($skipped || (!$enabled && $active) ? "info" : "danger"));

	# Output site header with switch to enable service:
	site_menu('
		<div class="float-right">
			<div class="btn-group">
				<button type="button" class="btn btn-default" id="service_status">Service Status</button>
				<button type="button" class="btn btn-default dropdown-toggle dropdown-icon" data-toggle="dropdown">
					<span class="sr-only">Toggle Dropdown</span>
				</button>
				<div class="dropdown-menu" role="menu">' . ($active ? ' 
					<a class="dropdown-item" href="#" id="service_restart">Restart Service</a>
					<a class="dropdown-item" href="#" id="service_stop">Stop Service</a>' . ($reload ? '
					<a class="dropdown-item" href="#" id="service_reload">Reload Service</a>' : '') : '
					<a class="dropdown-item" href="#" id="service_start">Start Service</a>') . '
					<div class="dropdown-divider"></div>
					<a class="dropdown-item" href="#" id="service_enable">Enable Service</a>
					<a class="dropdown-item" href="#" id="service_enable">Disable Service</a>
				</div>
			</div>
		</div>');
	echo '
	<div class="alert alert-' . $mode . ' alert-dismissible"" id="disabled_div">
		<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
		<h5>&quot;', $service, '&quot; is ', 
			!$enabled ? '<strong>NOT</strong> ' : '', 'enabled', 
			$enabled && (!$active || $skipped) ? ', but ' : ' and ', 
			!$skipped ? (!$active ? '<strong>NOT</strong> ' : '') . 'running.' : 'skipped.', '</h5>
	</div>';
}
