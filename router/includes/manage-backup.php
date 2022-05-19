<?php
#################################################################################################
# If "?download" is passed in the URL, create and download the backup:
#################################################################################################
if (isset($_GET['download']))
{
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh backup squash");
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
	# Make sure that persistent storage is available before proceeding:
	if (trim(@shell_exec("mount | grep ^overlayfs-root")) == "")
		die("ERROR: Persistent storage not enabled on this router!");

	####################################################################################
	# ACTION: UPLOAD ==> Verify the contents of the upload:
	####################################################################################
	if ($_POST['action'] == "upload")
	{
		if (!isset($_FILES['file']['name']))
			die("ERROR: No file specified!");
		else if (strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) != "cfg")
			die('ERROR: File extension must be "cfg"!');
		else
		{
			@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh backup unlink");
			if (@move_uploaded_file($_FILES['file']['tmp_name'], '/tmp/bpiwrt.cfg'))
				die(trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh backup prep")));
			die("ERROR: File prep failed");
		}
	}
	####################################################################################
	# ACTION: FACTORY ==> Signal a reformat is needed and return to caller:
	####################################################################################
	else if ($_POST['action'] == "factory")
		die(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh reformat -y"));
	####################################################################################
	# ACTION: FILE ==> Process the uploaded file:
	####################################################################################
	else if ($_POST['action'] == "file")
		die(trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh backup copy")));
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#######################################################################################################
# Otherwise, show the backup and restore options:
#######################################################################################################
site_menu();
#######################################################################################################
# If there is no overlay filesystem, notify the user that no options are available.
#######################################################################################################
echo trim(@shell_exec("mount | grep ^overlayfs-root")) == "" ? 
'<div class="alert alert-danger">
	<h5><i class="icon fas fa-ban"></i> No Persistent Storage Detected!</h5>
	You must enable persistent storage before you can backup and restore settings on this router!
</div>' : 
#######################################################################################################
# Otherwise, show the backup and restore options:
#######################################################################################################
'<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Backup Settings</h3>
	</div>
	<div class="card-body">
		<div class="input-group mb-4">
			<label class="col-sm-6 col-form-label">Save a copy of current settings</label>
			<div class="col-sm-6"><a href="/manage/backup?download"><button type="button" class="btn btn-block btn-outline-info">Backup Settings</button></a></div>
		</div>
	</div>
</div>
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Restore Settings</h3>
	</div>
	<div class="card-body">
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
		</div>' . (strpos(trim(@shell_exec("mount | grep ' /rw '")), "/dev/") !== false ? '
		<hr style="border-width: 2px" />
		<div class="input-group mb-4">
			<label class="col-sm-6 col-form-label">Restore to default settings</label>
			<div class="col-sm-6"><a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-danger" data-toggle="modal" data-target="#reboot-modal" id="factory_settings">Erase Settings</button></a></div>
		</div>' : '') . '
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
