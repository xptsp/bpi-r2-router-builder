<?php
#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	if ($_POST['action'] == 'submit')
	{
		$cmd = "router-helper webui ";
		$cmd .= ' http-' . (option('allow_local_http') == "Y" ? 'on' : 'off');
		$cmd .= ' https-' . (option('allow_local_https') == "Y" ? 'on' : 'off');
		$cmd .= ' pihole-https-' . (option('pihole_http') == "Y" ? 'on' : 'off');
		$cmd .= ' pihole-https-' . (option('pihole_https') == "Y" ? 'on' : 'off');
		@shell_exec($cmd);
		die("OK");
	}
	die("Invalid action");
}

#################################################################################################
# Show the WebUI management page:
#################################################################################################
$options = parse_options();
$options['allow_local_http']  = file_exists("/etc/nginx/sites-enabled/default") ? "Y" : "N";
$options['allow_local_https'] = file_exists("/etc/nginx/sites-enabled/default-https") ? "Y" : "N";
$options['pihole_http'] = file_exists("/etc/nginx/sites-enabled/pihole") ? "Y" : "N";
$options['pihole_https'] = file_exists("/etc/nginx/sites-enabled/pihole-https") ? "Y" : "N";
site_menu();
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Local Management Access</h3>
	</div>
	<div class="card-body">
		', checkbox("allow_local_http",  "Access Router WebUI locally using HTTP"), '
		', checkbox("allow_local_https", "Access Router WebUI locally using HTTPS"), '
		<hr />
		', checkbox("pihole_http",  "Access Pi-Hole WebUI locally using HTTP"), '
		', checkbox("pihole_https", "Access Pi-Hole WebUI locally using HTTPS"), '
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal("Please wait while WebUI management changes are pending...", true);
site_footer('Init_Management();');
