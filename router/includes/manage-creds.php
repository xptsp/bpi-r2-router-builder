<?php
#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	#################################################################################################
	# ACTION: SUBMIT => Validate the password parameters sent:
	#################################################################################################
	if ($_POST['action'] == 'submit')
	{
		####################################################################################
		# Make sure that the passwords are valid:
		####################################################################################
		$oldPass = isset($_POST['oldPass']) ? $_POST['oldPass'] : '';
		$newPass = isset($_POST['newPass']) ? $_POST['newPass'] : '';
		$conPass = isset($_POST['conPass']) ? $_POST['conPass'] : '';
		if ($oldPass == "")
			die("ERROR: Old Password cannot be empty!");
		else if ($oldPass != preg_replace("/[^A-Za-z0-9 ]/", '-', $oldPass))
			die("ERROR: Old Password cannot contain characters other than alphanumeric characters!");
		else if ($newPass == "")
			die("ERROR: New Password cannot be empty!");
		else if ($conPass == "")
			die("ERROR: Confirmation Password cannot be empty!");
		else if ($newPass != $conPass)
			die("ERROR: New Password and Confirmation Password do not match!");
		else if ($newPass != preg_replace("/[^A-Za-z0-9 ]/", '-', $newPass))
			die("ERROR: New Password cannot contain characters other than alphanumeric characters!");
		else if ($conPass != preg_replace("/[^A-Za-z0-9 ]/", '-', $newPass))
			die("ERROR: Confirmation Password cannot contain characters other than alphanumeric characters!");
		else if ($newPass == $oldPass)
			die("ERROR: New Password cannot be the same as the Old Password!");
		die(trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login passwd ' . $oldPass . ' ' . $newPass)));
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

###################################################################################################
# Output the Credentials page:
###################################################################################################
site_menu();
$debug = false;
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Set Password</h3>
	</div>
	<div class="card-body">
		<div class="input-group mb-4">
			<label for="oldPass" class="col-sm-2 col-form-label">Old Password:</label>
			<div class="input-group col-sm-10">
				<input type="password" class="form-control" id="oldPass" name="oldPass"', $debug ? ' value="bananapi"' : '', ' placeholder="Old Password">
				<div class="input-group-append">
					<span class="input-group-text"><i class="fas fa-eye"></i></span>
				</div>
			</div>
		</div>
		<div class="input-group mb-4">
			<label for="newPass" class="col-sm-2 col-form-label">New Password:</label>
			<div class="input-group col-sm-10">
				<input type="password" class="form-control" id="newPass" name="newPass"', $debug ? ' value="meh"' : '', ' placeholder="Required">
				<div class="input-group-append">
					<span class="input-group-text"><i class="fas fa-eye"></i></span>
				</div>
			</div>
		</div>
		<div class="input-group mb-4">
			<label for="conPass" class="col-sm-2 col-form-label">Confirm Password:</label>
			<div class="input-group col-sm-10">
				<input type="password" class="form-control" id="conPass" name="conPass"', $debug ? ' value="meh"' : '', ' placeholder="Required">
				<div class="input-group-append">
					<span class="input-group-text"><i class="fas fa-eye"></i></span>
				</div>
			</div>
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-danger center_50" id="submit">Set Password</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while changes are pending....', true);
site_footer('Init_Creds();');
