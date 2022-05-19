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
		$oldPass = option_string('oldPass', 'Old Password');
		$newPass = option_string('newPass', 'New Password');
		$conPass = option_string('conPass', 'Confirm Password');
		if ($newPass != $conPass)
			die("ERROR: New Password and Confirm Password do not match!");
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
