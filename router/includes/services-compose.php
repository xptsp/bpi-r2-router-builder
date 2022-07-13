<?php
require_once("subs/manage.php");
require_once("subs/setup.php");
$called_as_sub = true;
require_once("services.php");

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
		if (empty($_POST['misc']))
			die("ERROR: No contents for docker-compose.yaml passed!");
		$handle = fopen("/tmp/docker-compose.yaml", "w");
		fwrite($handle, str_replace("\t", "    ", $_POST['misc']));
		fclose($handle);
		die(@shell_exec('router-helper move docker-compose'));		
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# Output the Multicast Relay page:
#################################################################################################
services_start('docker-compose');
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Docker Compose Service</h3>
	</div>
	<div class="card-body">
		<div class="row" style="margin-top: 5px">
			<textarea id="contents-div" class="form-control" rows="15" style="overflow-y: scroll;">',
				str_replace("    ", "\t", @file_get_contents("/etc/docker-compose.yaml")),
			'</textarea>
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="compose_submit">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while the Docker Compose settings are saved....', true);
site_footer('Init_Compose();');
