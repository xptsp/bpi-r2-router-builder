<?php
if (isset($_GET['download']))
{
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh backup create");
	$cfg = "/tmp/bpiwrt.cfg";
	header('Content-Disposition: attachment; filename="' . basename($cfg) . '"');
	header("Content-Length: " . filesize($cfg));
	header("Content-Type: application/octet-stream");
	readfile(realpath($cfg));
	exit;
}

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

	####################################################################################
	# ACTION: UPLOAD ==> Verify the contents of the upload:
	####################################################################################
	if ($_POST['action'] == "upload")
	{
		if (!isset($_FILES['file']['name']))
			echo "ERROR: No file specified!";
		else if (strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) != "cfg")
			echo 'ERROR: File extension must be "cfg"!';
		else
		{
			@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh backup remove");
			if (@move_uploaded_file($_FILES['file']['tmp_name'], '/tmp/bpiwrt.cfg'))
				echo trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh backup unpack"));
			else
				echo "ERROR: File move failed";
		}
	}
	####################################################################################
	# ACTION: FACTORY ==> Signal a reformat is needed and return to caller:
	####################################################################################
	else if ($_POST['action'] == "factory")
	{
		echo explode("\n", trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh reformat -y")))[0];
	}
	####################################################################################
	# ACTION: FILE ==> Process the uploaded file:
	####################################################################################
	else if ($_POST['action'] == 'file')
	{
		echo trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh backup restore"));
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#######################################################################################################
# Show the backup and restore options to the user:
#######################################################################################################
site_menu();
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Backup Settings</h3>
	</div>
	<div class="card-body">
		<div class="input-group mb-4">
			<label class="col-sm-6 col-form-label">Save a copy of current settings</label>
			<div class="col-sm-6"><a href="/manage/backup?download"><button type="button" class="btn btn-block btn-outline-info">Backup Settings</button></a></div>
		</div>
	</div>
</div>';

#######################################################################################################
# Disable "Factory Restore" option if the overlay isn't active or temporary overlay is active:
#######################################################################################################
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Restore Settings</h3>
	</div>
	<div class="card-body">';
if (in_array("Temp", $_SESSION['critical_alerts']))
	echo '<label>You must enable persistent storage before you can restore settings to this router.</label>';
else
{
	echo '
		<div class="input-group mb-4">
			<label class="col-sm-6 col-form-label">
				Restore saved settings from a file
				<div class="input-group">
					<div class="custom-file col-sm-8">
						<input type="file" class="custom-file-input" id="restore_file">
						<label class="custom-file-label" for="exampleInputFile">Choose file</label>
					</div>
				</div>
			</label>
			<div class="col-sm-6"><a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-danger" id="restore_settings">Restore Settings</button></a></div>
		</div>';

	if (strpos(@file_get_contents("/boot/bananapi/bpi-r2/linux/uEnv.txt"), "bootmenu_default=2") > -1)
		echo '
		<hr />
		<div class="input-group mb-4">
			<label class="col-sm-6 col-form-label">Restore to default settings</label>
			<div class="col-sm-6"><button type="button" class="btn btn-block btn-outline-danger" data-toggle="modal" data-target="#reboot-modal" id="factory_settings">Erase Settings</button></div>
		</div>';
}
echo '
	</div>
	<!-- /.card-body -->
</div>';

#######################################################################################################
# Restore Router Settings confirmation modal:
#######################################################################################################
echo '
<div class="modal fade" id="reboot-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="reboot_title">Restore Settings</h4>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p id="reboot_msg">Restoring the <span id="restore_type"></span> settings will result in the router rebooting, which will disrupt active traffic on the network.</p>
				<p id="reboot_timer">Are you sure you want to do this?</p>
			</div>
			<div class="modal-footer justify-content-between" id="reboot_control">
				<a href="javascript:void(0);"><button type="button" class="btn btn-default" id="reboot_nah" data-dismiss="modal">Not Now</button></a>
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary" id="reboot_yes">Restore Settings</button></a>
			</div>
		</div>
		<!-- /.modal-content -->
	</div>
	<!-- /.modal-dialog -->
</div>';

#######################################################################################################
# Error Message modal box:
#######################################################################################################
echo '
<div class="modal fade" id="error-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content bg-danger">
			<div class="modal-body">
				<h5 id="error_msg"></h5>
			</div>
			<div class="modal-footer justify-content-between">
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary" data-dismiss="modal">Close</button></a>
			</div>
		</div>
		<!-- /.modal-content -->
	</div>
	<!-- /.modal-dialog -->
</div>';

#######################################################################################################
# Close the page:
#######################################################################################################
site_footer('Init_Restore();');
