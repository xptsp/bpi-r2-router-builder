<?php
ini_set('display_errors',1); 
error_reporting(E_ALL);

function site_header()
{
	echo '
<!DOCTYPE html>
<html>
<head>
	<title>BPI Router</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <link rel="icon" type="image/png" sizes="32x32" href="/static/img/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/static/img/favicon/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/static/img/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/static/img/favicon/favicon-192x192.png">

    <link rel="stylesheet" href="/static/css/bootstrap.min.css">

    <link rel="stylesheet" href="/static/css/font-awesome.min.css">
    <link rel="stylesheet" href="/static/css/ionicons.min.css">

    <link rel="stylesheet" href="/static/css/AdminLTE.min.css">
    <link rel="stylesheet" href="/static/css/skins/skin-blue.min.css">
    <link rel="stylesheet" href="/static/css/custom.css">
</head>';
}

function site_login()
{
	echo '
<body class="hold-transition login-page">
	<div class="login-box">
		<div class="login-logo">
			<img src="/static/img/favicon/favicon-192x192.png" ><br />
			<a href="#"><b>BPI</b> Router</a>
		</div>
  
		<div class="login-box-body">
			<p class="login-box-msg">Sign in to start your session</p>
			<form action="/login" method="post">
				<div class="form-group has-feedback">
					<input type="text" class="form-control" name="login" placeholder="Login">
					<span class="glyphicon glyphicon-user form-control-feedback"></span>
				</div>
				<div class="form-group has-feedback">
					<input type="password" class="form-control" name="password" placeholder="Password">
					<span class="glyphicon glyphicon-lock form-control-feedback"></span>
				</div>
				<div class="row">
					<div class="col-xs-8">
					</div>
					<input type="hidden" name="_xsrf" value="oTvX4KTfX2WCT36nV8zXytNlLtEZmx0f" />
					<div class="col-xs-4">
						<button type="submit" class="btn btn-primary btn-block btn-flat">Sign In</button>
					</div>
				</div>
			</form>
		</div>
		<p class="login-box-msg">
			Icons made by <a href="https://www.freepik.com" title="Freepik">Freepik</a> from <a href="https://www.flaticon.com/" title="Flaticon">www.flaticon.com</a>
		</div>
	</div>';
}

function site_footer($iCheck = false)
{
	echo '
	<script src="/static/js/jquery-2.2.3.min.js"></script>
	<script src="/static/js/bootstrap.min.js"></script>
	<script src="/static/js/clipboard.min.js"></script>
	<script src="/static/js/app.js"></script>
	<script src="/static/js/custom.js"></script>';
	if ($iCheck == true)
	{
		echo '
	<script>
		$(function () {
			$("input").iCheck({
				checkboxClass: "icheckbox_square-blue",
				radioClass: "iradio_square-blue",
				increaseArea: "20%" 
			});
			$("input:text:visible:first").focus();
		});
	</script>';
	}
	echo '
</body>
</html>';
}

site_header();
site_login();
site_footer(true);
