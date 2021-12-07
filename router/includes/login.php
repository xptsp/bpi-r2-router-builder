<?php
#################################################################################################
# Output the Router Login page if no action was specified:
#################################################################################################
if (!isset($_POST['action']))
{
	echo '
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>BPiWRT | Log in</title>
	<link rel="stylesheet" href="/css/fonts.googleapis.com.css">
	<link rel="stylesheet" href="/plugins/fontawesome-free/css/all.min.css">
	<link rel="stylesheet" href="/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
	<link rel="stylesheet" href="/css/adminlte.min.css">
	<link rel="stylesheet" href="/css/custom.css">
</head>
<body class="hold-transition login-page bodybg">
	<div class="login-box">
		<div class="card card-outline card-primary">
			<div class="card-header text-center">
				<img src="/img/wifi-router-large.png"><br />
				<a href="/" class="h1"><b>BPi</b>WRT</a>
			</div>
			<div class="card-body">
				<form>
					<div class="alert alert-danger hidden" id="dhcp_error_box">
						<a href="javascript:void(0);"><button type="button" class="close" id="dhcp_error_close">&times;</button></a>
						<i class="fas fa-ban"></i> Invalid Username and/or Password!
					</div>
					<div class="input-group mb-3">
						<input type="username" id="username" class="form-control" placeholder="Username">
						<div class="input-group-append">
							<div class="input-group-text">
								<span class="fas fa-user"></span>
							</div>
						</div>
					</div>
					<div class="input-group mb-3">
						<input type="password" id="password" class="form-control" placeholder="Password">
						<div class="input-group-append">
							<div class="input-group-text">
								<span class="fas fa-lock"></span>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-8">
							<div class="icheck-primary">
								<input type="checkbox" id="remember">
								<label for="remember">Remember Me</label>
							</div>
						</div>
						<div class="col-4">
							<button type="submit" id="login_button" class="btn btn-primary btn-block">Sign In</button>
						</div>
					</div>
					<!-- <p class="mb-1"><a href="/login?forgot">I forgot my password</a></p> -->
				</form>
			</div>
		</div>
	</div>
	<script src="/plugins/jquery/jquery.min.js"></script>
	<script src="/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
	<script src="/js/adminlte.min.js"></script>
	<script src="/js/site.js"></script>
	<script src="/js/site-setup.js"></script>
	<script>
		Init_Login();
	</script>
</body>
</html>';
}
#################################################################################################
# Output the Router Login page if no action was specified:
#################################################################################################
else if ($_POST['action'] == 'submit')
{
	// Is the username correct?  If not, abort with error:
	$username = preg_replace("/[^A-Za-z0-9 ]/", '-', isset($_POST['username']) ? $_POST['username'] : '');
	if (trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login webui')) != $username)
		die("Invalid");

	// Is the password correct?  If not, abort with error:
	$password = preg_replace("/[^A-Za-z0-9 ]/", '-', isset($_POST['password']) ? $_POST['password'] : '');
	if (trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login check ' . $username . ' ' . $password)) != "Match")
		die("Invalid");

	// Set the session timeout value and return "OK":
	$_SESSION['login_expires'] = (isset($_POST['remember']) && $_POST['remember'] == "Y") ? 86400 * 365 : 600;
	$_SESSION['login_valid_until'] = time() + $_SESSION['login_expires'];
	die("OK");
}
