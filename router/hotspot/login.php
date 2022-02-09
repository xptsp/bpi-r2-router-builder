<?php
require_once("../includes/subs/site.php");
$mode = !empty($option['captive_portal_mode']) ? $option['captive_portal_mode'] : 'accept';
$mode = in_array($mode, array('accept', 'username', 'password')) ? $mode : 'accept';

#################################################################################################
# Validate the credentials sent:
#################################################################################################
if (isset($_POST['action']))
{
	if ($_POST['action'] != $mode)
		die("Invalid action");

	if ($_POST['action'] == 'username')
	{
		// Is the specified username/password combo valid?
		$username = preg_replace("/[^A-Za-z0-9 ]/", '-', isset($_POST['username']) ? $_POST['username'] : '');
		$password = preg_replace("/[^A-Za-z0-9 ]/", '-', isset($_POST['password']) ? $_POST['password'] : '');
		if (trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login check ' . $username . ' ' . $password)) != "Match")
			die("Invalid");
	}
	die("OK");
}

#################################################################################################
# Figure out the settings needed:
#################################################################################################
#echo $mode; exit;
$option = parse_options();
#echo '<pre>'; print_r($option); exit;
$URL = (file_exists('/etc/nginx/sites-enabled/router-https') ? 'https://' : 'http://') . explode(":", $_SERVER['HTTP_HOST'])[0];
#echo $URL; exit;

#################################################################################################
# Output the Router Login page if no action was specified:
#################################################################################################
echo '
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>BPiWRT | Captive Portal</title>
	<link rel="stylesheet" href="', $URL, '/css/fonts.googleapis.com.css">
	<link rel="stylesheet" href="', $URL, '/plugins/fontawesome-free/css/all.min.css">
	<link rel="stylesheet" href="', $URL, '/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
	<link rel="stylesheet" href="', $URL, '/css/adminlte.min.css">
	<link rel="stylesheet" href="', $URL, '/css/custom.css">
</head>
<body class="hold-transition login-page ', !empty($_SESSION['dark_mode']) ? 'bodybg-dark dark-mode' : 'bodybg', '">
	<div class="login-box">
		<div class="card card-outline card-primary">
			<div class="card-header text-center">
				<img src="', $URL, '/img/wifi-router-large.png"><br />
				<a href="/" class="h1"><b>BPi</b>WRT</a>
				<h4>Captive Portal</h4>
			</div>
			<div class="card-body">
				<p>
					Before continuing, you must first agree to the <a href="#">Terms of Service</a> and 
					be of the legal age to do that in your selective country or have Parental Consent.
				</p>';

#################################################################################################
# PORTAL MODE: ACCEPT ==> Requires user to press "Accept" before allowing them to continue...
#################################################################################################
if ($mode == 'accept')
	echo '
				<hr>
				<button type="submit" id="accept" class="btn btn-success btn-block">Accept</button>';

#################################################################################################
# PORTAL MODE: USERNAME ==> Requires a valid username and password before continuing..
#################################################################################################
else if ($mode == 'username')
	echo '
				<div class="alert alert-danger hidden" id="dhcp_error_box">
					<a href="javascript:void(0);"><button type="button" class="close" id="dhcp_error_close">&times;</button></a>
					<i class="fas fa-ban"></i> Invalid Username and/or Password!
				</div>
				<div class="input-group mb-3" style="margin-top: 5px">
					<input type="username" id="username" class="form-control" placeholder="Username">
					<div class="input-group-append">
						<div class="input-group-text">
							<span class="fas fa-user"></span>
						</div>
					</div>
				</div>
				<div class="input-group mb-3" style="margin-top: 5px">
					<input type="password" id="password" class="form-control" placeholder="Password">
					<div class="input-group-append">
						<div class="input-group-text">
							<span class="fas fa-lock"></span>
						</div>
					</div>
				</div>
				<div class="row">
					<button type="submit" id="login" class="btn btn-primary btn-block">Sign In</button>
				</div>';

#################################################################################################
# PORTAL MODE: PASSWORD ==> Requires a valid password before continuing...
#################################################################################################
else if ($mode == 'password')
	echo '
				<div class="alert alert-danger hidden" id="dhcp_error_box">
					<a href="javascript:void(0);"><button type="button" class="close" id="dhcp_error_close">&times;</button></a>
					<i class="fas fa-ban"></i> Invalid Password!
				</div>
				<div class="input-group mb-3" style="margin-top: 5px">
					<input type="password" id="password" class="form-control" placeholder="Password">
					<div class="input-group-append">
						<div class="input-group-text">
							<span class="fas fa-lock"></span>
						</div>
					</div>
				</div>
				<div class="row">
					<button type="submit" id="submit" class="btn btn-primary btn-block">Sign In</button>
				</div>';

#################################################################################################
# Finalize the portal page:
#################################################################################################
echo '
			</div>
		</div>
	</div>
	<script src="', $URL, '/plugins/jquery/jquery.min.js"></script>
	<script src="', $URL, '/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
	<script src="', $URL, '/js/adminlte.min.js"></script>
	<script src="', $URL, '/js/site.js"></script>
	<script>
		Init_Portal("', $mode, '");
	</script>
</body>
</html>';
