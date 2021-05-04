<?php

# Defaults to "Prompt".  Change this to "Match" to disable login:
$_SESSION['login_result'] = empty($_SESSION['suppress_login']) ? "Prompt" : "Match";

# Uncomment next line to force testing the login code:
if ($_GET['action'] == '/logout')
{
	unset($_SESSION['login_valid_until']);
	$_GET['action'] = '/';
}

# Are we in a valid session?  If so, mark the result as a "Match":
if (isset($_SESSION['login_valid_until']) and $_SESSION['login_valid_until'] >= time())
	$_SESSION['login_result'] = "Match";

# Otherwise, check if the passed "username" and "password" fields are valid.
else if (isset($_POST['username']) and isset($_POST['password']))
{
	$_SESSION['login_user']   = preg_replace('/[\s\W]+/', '-', $_POST['username']);
	$_SESSION['login_pass']   = preg_replace('/[\s\W]+/', '-', $_POST['password']);
	$_SESSION['login_result'] = trim(@shell_exec('/usr/local/bin/router-helper login check ' . $_SESSION['login_user'] . ' ' . $_SESSION['login_pass']));
}
else
	$_SESSION['login_user'] = '';
	
# If we have a valid username/password combo, set/extend the timeout for 10 minutes:
if ($_SESSION['login_result'] == "Match")
{
	$_SESSION['login_valid_until'] = time() + 10*60;
	$_SESSION['force_refresh'] = true;
}
else
{
	# Not a valid username/password combo!  Prompt for username and password:
	$site_title = "Banana Pi Router - Login";
	site_header();
	echo '
<body class="hold-transition login-page">
	<div class="login-box">
		<div class="login-logo">
			<img src="/dist/img/favicon/favicon-192x192.png" /><br />
			<a href="/"><b>Banana Pi</b> Router</a>
		</div>
		<!-- /.login-logo -->
		<div class="card">
			<div class="card-body login-card-body">';
	if ($_SESSION['login_result'] == "No match")
	{
		echo '
				<div class="callout callout-danger bg-danger">
					<p><i class="icon fas fa-info-circle"></i>&nbsp;&nbsp;Incorrect username/password!</p>
				</div>';
	}
	echo '
				<h5 class="login-box-msg">Sign in to start your session</h5>
				<form action="', str_replace('/basic', '/', $_GET['action']), '" method="post">
					<div class="input-group mb-3">
						<input name="username" type="text" value="', $_SESSION['login_user'], '" class="form-control" placeholder="Username">
						<div class="input-group-append">
							<div class="input-group-text">
								<span class="fas fa-user"></span>
							</div>
						</div>
					</div>
					<div class="input-group mb-3">
						<input name="password" type="password" class="form-control" placeholder="Password">
						<div class="input-group-append">
							<div class="input-group-text">
								<span class="fas fa-lock"></span>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-8">&nbsp;</div>
						<!-- /.col -->
						<div class="col-4">
							<button type="submit" class="btn btn-primary btn-block">Sign In</button>
						</div>
						<!-- /.col -->
					</div>
				</form>
			</div>
			<!-- /.login-card-body -->
		</div>
	</div>
	<!-- /.login-box -->

	<!-- jQuery -->
	<script src="../../plugins/jquery/jquery.min.js"></script>
	<!-- Bootstrap 4 -->
	<script src="../../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
	<!-- AdminLTE App -->
	<script src="../../dist/js/adminlte.min.js"></script>
</body>
</html>';
	exit();
}