<?php
#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

	if ($_POST['action'] == 'submit')
	{
		$cmd = "/opt/bpi-r2-router-builder/helpers/router-helper.sh webui ";
		$cmd .= ' http-' . (option('allow_local_http') == "Y" ? 'on' : 'off');
		$cmd .= ' https-' . (option('allow_local_https') == "Y" ? 'on' : 'off');
		@shell_exec($cmd);
		die("OK");
	}
	else if ($_POST['action'] == 'reboot')
		die(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh webui reboot"));
		
	die("Invalid action");
}

#################################################################################################
# Show the WebUI management page:
#################################################################################################
$options = parse_options();
$options['allow_local_http']  = file_exists("/etc/nginx/sites-enabled/default") ? "Y" : "N";
$options['allow_local_https'] = file_exists("/etc/nginx/sites-enabled/default-https") ? "Y" : "N";
site_menu();
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Local Management Access</h3>
	</div>
	<div class="card-body">
		', checkbox("allow_local_http",  "Access WebUI using HTTP"), '
		', checkbox("allow_local_https", "Access WebUI using HTTPS"), '
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal("Please wait while WebUI management access is set up...", true);
site_footer('Init_Management();');
