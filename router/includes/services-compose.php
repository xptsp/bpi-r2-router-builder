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
		$handle = fopen("/tmp/router-settings", "w");
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
$URL = explode("?", $_SERVER['REQUEST_URI'])[0];
$file = isset($_GET['file']) ? $_GET['file'] : 'docker-compose';
services_start('docker-compose@' . $file);
echo '
<div class="card card-primary">
	<div class="card-header p-0 pt-1">
		<ul class="nav nav-tabs" id="custom-tabs-two-tab" role="tablist">';
foreach (array_unique(array_merge(array('/etc/docker/compose.d/docker-compose.yaml'), glob("/etc/docker/compose.d/*"))) as $nfile)
{
	$tfile = str_replace('.yaml', '', basename($nfile));
	echo '
			<li class="nav-item">
				<a class="ifaces nav-link', $tfile == $file ? ' active' : '', '" href="', $URL, $tfile == "docker-compose" ? '' : '?file=' . $tfile, '">', basename($tfile), '</a>
			</li>';
}
echo '
		</ul>
	</div>
	<div class="card-body">
		<div class="row" style="margin-top: 5px">
			<textarea id="contents-div" class="form-control" rows="15" style="overflow-y: scroll;">',
				str_replace("    ", "\t", @file_get_contents("/etc/docker/compose.d/" . $file . ".yaml")),
			'</textarea>
			<input id="file" type="hidden" value="', $file, '" />
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="compose_submit">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while the Docker Compose settings are saved....', true);
site_footer('Init_Compose();');
