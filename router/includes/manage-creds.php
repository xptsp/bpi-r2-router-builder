<?php
#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

	#################################################################################################
	# ACTION: SUBMIT => Validate the password parameters sent:
	#################################################################################################
	else if ($_POST['action'] == 'submit')
	{
		####################################################################################
		# Make sure that the passwords are valid:
		####################################################################################
		$oldPass = isset($_POST['oldPass']) ? $_POST['oldPass'] : '';
		$newPass = isset($_POST['newPass']) ? $_POST['newPass'] : '';
		$conPass = isset($_POST['conPass']) ? $_POST['conPass'] : '';
		if ($oldPass != preg_replace("/[^A-Za-z0-9 ]/", '-', $oldPass))
			die("oldPass");
		else if ($newPass != preg_replace("/[^A-Za-z0-9 ]/", '-', $newPass))
			die("newPass");
		else if ($newPass != $conPass || $conPass != preg_replace("/[^A-Za-z0-9 ]/", '-', $conPass))
			die("conPass");

		####################################################################################
		# If old password is correct, then attempt to change the password:
		####################################################################################
		$result = trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login webui ' . $oldPass));
		if ($result == "Match")
		{
			$result = @shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login passwd ' . $newPass . ' 2>&1');
			$result = strpos($result, "password updated successfully") > 0 ? 'Successful' : 'Failed';
			if ($result == "Successful" && isset($_COOKIE['remember_me']))
				setcookie("remember_me", @trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh login get-cookie")), time() + 60*60*24*365 );
		}
		die($result);
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
		<div class="alert alert-danger hidden" id="alert_msg">
			<h5><i id="passwd_icon" class="fas fa-thumbs-down"></i> <span id="passwd_msg"></span></h5>
		</div>
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
site_footer('Init_Creds();');
