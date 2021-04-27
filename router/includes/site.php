<?php

function site_header()
{
	echo '
<!DOCTYPE html>
<html lang="en">
<head>
	<title>AdminLTE 3 | Boxed Layout</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="dist/css/fonts.googleapis.com.css">
	<link rel="stylesheet" href="dist/css/adminlte.min.css">
	<link rel="stylesheet" href="dist/css/ionicons.min.css">
	<link rel="stylesheet" href="dist/plugins/fontawesome-free/css/all.min.css">
	<link rel="stylesheet" href="dist/css/custom.css">
</head>';
}

function site_menu($page_header = '')
{
	echo '
<body class="hold-transition sidebar-mini layout-boxed bodybg">
<div class="wrapper">
	<!-- Main Sidebar Container -->
	<aside class="main-sidebar sidebar-dark-primary elevation-4">
		<!-- Brand Logo -->
		<a href="/" class="brand-link">
			<img src="/dist/img/wifi-router.png" alt="Banana Pi Router" class="brand-image" style="opacity: .8">
			<span class="brand-text font-weight-light">Banana Pi Router</span>
		</a>
		<!-- Sidebar -->
		<div class="sidebar">
			<!-- Sidebar Menu -->
			<nav class="mt-2">
				<ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
					<!-- Add icons to the links using the .nav-icon class
							 with font-awesome or any other icon font library -->
					<li class="nav-item">
						<a href="/" class="nav-link">
							<i class="nav-icon fas fa-info"></i>
							<p>
								Basic Status
							</p>
						</a>
					</li>
					<li class="nav-item">
						<a href="/detailed" class="nav-link">
							<i class="nav-icon fas fa-info-circle"></i>
							<p>
								Detailed Status
							</p>
						</a>
					</li>
				</ul>
			</nav>
			<!-- /.sidebar-menu -->
		</div>
		<!-- /.sidebar -->
	</aside>

	<!-- Content Wrapper. Contains page content -->
	<div class="content-wrapper">
		<section class="content-header">
			<div class="container-fluid">
				<div class="row mb-2">
					<div class="col-sm-12">
						<a class="float-left nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
						<h1>', $page_header, '</h1>
					</div>
				</div>
			</div><!-- /.container-fluid -->
		</section>

		<!-- Main content -->
		<section class="content">
			<div class="container-fluid">
				<div class="row">';
}

function site_404()
{
	echo '
				</div>
			</div>
		</section>';
}

function site_footer()
{
	echo '
	</div>
	<!-- /.content-wrapper -->

	<footer class="main-footer">
		<div class="float-right d-none d-sm-block">
			<b>Version</b> 3.1.0
		</div>
		<strong>Copyright &copy; 2014-2021 <a href="https://adminlte.io">AdminLTE.io</a>.</strong> All rights reserved.
	</footer>
</div>
<!-- ./wrapper -->

<script src="dist/plugins/jquery/jquery.min.js"></script>
<script src="dist/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>
</body>
</html>';
}
