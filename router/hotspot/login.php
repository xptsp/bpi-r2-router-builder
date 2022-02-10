<?php
session_start();
require_once("../includes/subs/site.php");

#################################################################################################
# Figure out the settings needed:
#################################################################################################
$option = parse_options();
#echo '<pre>'; print_r($option); exit;
$mode = !empty($option['captive_portal_mode']) ? $option['captive_portal_mode'] : 'accept';
$mode = in_array($mode, array('accept', 'username', 'password')) ? $mode : 'accept';
#echo $mode; exit;

#################################################################################################
# Validate the credentials sent:
#################################################################################################
if (isset($_POST['action']))
{
	if ($_POST['action'] != $mode)
		die("Invalid action");
	if (empty($_POST['accepted']) || $_POST['accepted'] == "N")
		die("You must accept the terms and conditions before continuing!");

	#################################################################################################
	# ACTION: USERNAME => Validate the username/password combo sent:
	#################################################################################################
	if ($_POST['action'] == 'username')
	{
		// Is the specified username/password combo valid?
		$username = preg_replace("/[^A-Za-z0-9 ]/", '-', isset($_POST['username']) ? $_POST['username'] : '');
		$password = preg_replace("/[^A-Za-z0-9 ]/", '-', isset($_POST['password']) ? $_POST['password'] : '');
		if (trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login check ' . $username . ' ' . $password)) != "Match")
			die("Invalid Username and/or Password!");
	}
	#################################################################################################
	# ACTION: PASSWORD => Validate the password combo sent against password for user "guest":
	#################################################################################################
	else if ($_POST['action'] == 'password')
	{
		$password = preg_replace("/[^A-Za-z0-9 ]/", '-', isset($_POST['password']) ? $_POST['password'] : '');
		if (trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login check portal ' . $password)) != "Match")
			die("Invalid Password Specified!");
	}

	#################################################################################################
	# Add the MAC address associated with the calling IP address to valid portal clients list:
	#################################################################################################
	die(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh portal allow ' . $_SERVER['REMOTE_ADDR']));
}

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
	<link rel="stylesheet" href="/css/fonts.googleapis.com.css">
	<link rel="stylesheet" href="/plugins/fontawesome-free/css/all.min.css">
	<link rel="stylesheet" href="/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
	<link rel="stylesheet" href="/css/adminlte.min.css">
	<link rel="stylesheet" href="/css/custom.css">
</head>
<body class="hold-transition login-page ', !empty($_SESSION['dark_mode']) ? 'bodybg-dark dark-mode' : 'bodybg', '">
	<div class="login-box">
		<div class="card card-outline card-primary">
			<div class="card-header text-center">
				<img src="/img/wifi-router-large.png"><br />
				<a href="/" class="h1"><b>BPi</b>WRT</a>
				<h4>Captive Portal</h4>
			</div>
			<div class="card-body">
				<div class="alert alert-danger hidden" id="portal_box">
					<i class="fas fa-ban"></i> <span id="portal_msg">Invalid Message!</span>
				</div>
				<div class="input-group mb-3" style="margin-top: 5px">
					<button type="button" class="btn btn-default btn-block" data-toggle="modal" data-target="#terms_modal">Terms and Conditions of Use</button>
				</div>';

#################################################################################################
# PORTAL MODE: USERNAME ==> Requires a valid username and password before continuing..
#################################################################################################
if ($mode == 'username')
	echo '
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
				</div>';

#################################################################################################
# Show the dialog buttons:
#################################################################################################
echo '
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="accept">
					<label class="form-check-label" for="accept" >I accept the terms and conditions.</label>
				</div>
				<hr style="border-width: 2px" />
				<button type="submit" id="submit_button" class="btn btn-success btn-block">Submit</button>
			</div>
		</div>
	</div>';

#################################################################################################
# Terms and Conditions modal:
#################################################################################################
echo '
	<div class="modal fade" id="terms_modal">
		<div class="modal-dialog modal-lg modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title">Terms and Conditions</h4>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<p>If you choose to continue, you are agreeing to comply with and be bound by the following terms and conditions of use.</p>
					<p>If you disagree with any part of these terms and conditions, you may not continue.</p>
					<h5>Terms of Use</h5>
					<ol>
						<li>Your use of any information or materials on sites you access is entirely at your own risk, for which we shall not be liable.</li>
						<li>You agree that, through this portal, you will not perform any of the following acts:<ol>
							<li>Attempt to access devices or resources to which you have no explicit, legitimate rights,</li>
							<li>Copy, reproduce, or transmit any copyright files or information other than in accordance with the requirements and allowances of the copyright holder</li>
							<li>Launch network attacks of any kind, including port scans, DoS/DDoS, packet floods, replays or injections, session hijacking or interception, or other such activity with malicious intent</li>
							<li>Transmit malicious software such as viruses, Trojan horses, and worms</li>
							<li>Surreptitiously install software or make configuration changes to any device or application, by means of the installation or execution of key loggers, registry keys, or other executable or active application or script.</li>
						</ol></li>
						<li>You agree that you will use the access provided here responsibly and with full regard to the safely, security and privacy of all other users, devices, and resources.</li>
						<li>You agree that you will be mindful of the cultural sensitivites of others while using this portal so as not to provoke reaction or offense, and that you will not intentionally access pornographic, graphically violent, hateful, or other offensive material (as deemed by us) regarding of other\'s sensitivites.</li>
						<li>You understand that we reserve the right to log or monitor traffic to ensure that these terms are being followed.</li>
						<li>You understand that unauthorized use of resources through this portal may give rise to a claim for damages and/or be a criminal offense.</li>
					</ol>
				</div>
				<div class="modal-footer justify-content-between">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>';

#################################################################################################
# Show user that submission was successful!
#################################################################################################
$url = !empty($option['captive_portal_url']) ? $option['captive_portal_url'] : 'https://google.com';
echo '
	<div class="modal fade" id="success_modal" data-backdrop="static" tabindex="-1">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title">You may now browse the Internet!</h4>
				</div>
				<div class="modal-footer justify-content-between">
					<center><a href="', $url, '"><button type="button" class="btn btn-success">Continue to Internet</button></a></center>
				</div>
			</div>
		</div>
	</div>';

#################################################################################################
# Finalize the page:
#################################################################################################
$show_success = trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh portal check ' . $_SERVER['REMOTE_ADDR']));
echo '
	<script src="/plugins/jquery/jquery.min.js"></script>
	<script src="/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
	<script src="/js/adminlte.min.js"></script>
	<script src="/js/portal.js?', time(), '"></script>
	<script>
		Init_Portal("', $mode, '", "', $show_success, '");
	</script>
</body>
</html>';
