<?php
$site_title = '';
$header_done = false;

function site_header($override_title = "")
{
	global $site_title, $header_done;

	$site_title = !empty($override_title) ? $override_title : $site_title;
	echo '
<!DOCTYPE html>
<html lang="en">
<head>
	<title>', $site_title, '</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<link rel="icon" type="image/png" sizes="32x32" href="/img/favicon/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/img/favicon/favicon-16x16.png">
	<link rel="icon" type="image/png" sizes="96x96" href="/img/favicon/favicon-96x96.png">
	<link rel="icon" type="image/png" sizes="192x192" href="/img/favicon/favicon-192x192.png">

	<link rel="stylesheet" href="/css/fonts.googleapis.com.css">
	<link rel="stylesheet" href="/css/adminlte.min.css">
	<link rel="stylesheet" href="/css/ionicons.min.css">
	<link rel="stylesheet" href="/plugins/fontawesome-free/css/all.min.css">
	<link rel="stylesheet" href="/css/custom.css">
</head>';
	$header_done = true;
}

function menu_link($url, $text, $icon = "far fa-circle")
{
	global $site_title;

	$active = ($url == '/' . $_GET['action'] or ($url == '/' and $_GET['action'] == 'basic')) ? ' active' : '';
	if (!empty($active) and empty($site_title))
		$site_title = $text;
	return 
		'<li class="nav-item">' .
			'<a href="' . $url . '" class="nav-link' . $active . '">' .
				'<i class="nav-icon ' . $icon . '"></i>' .
				'<p>' . $text . '</p>' .
			'</a>' . 
		'</li>';
}

function menu_submenu($url, $text, $icon = "far fa-circle", $items = array())
{
	$items = (is_array($items) ? implode('', $items) : $items);
	return
		'<li class="nav-item">' .
			'<a href="#" class="nav-link' . (strrpos($items, 'class="nav-link active">') > 0 ? ' menu-open' : '') . '">' .
				'<i class="nav-icon ' . $icon . '"></i>' .
				'<p>' .$text . '<i class="fas fa-angle-left right"></i></p>' .
			'</a>' .
		'<ul class="nav nav-treeview">' .
		$items .
		'</ul>';
}
	
function menu_sep($text = '<hr />')
{
	return '<li class="nav-item">' . $text . '</li>';
}

function site_menu()
{
	global $site_title, $header_done;

	# If header not written yet, cache our output for now:
	if (!$header_done)
		ob_start();
		
	# Write the menu:
	echo '
<body class="hold-transition sidebar-mini layout-boxed bodybg">
<div class="wrapper">
	<!-- Main Sidebar Container -->
	<aside class="main-sidebar sidebar-dark-primary elevation-4">
		<!-- Brand Logo -->
		<a href="/" class="brand-link">
			<img src="/img/wifi-router.png" alt="Banana Pi Router" class="brand-image" style="opacity: .8">
			<span class="brand-text font-weight-light">Banana Pi Router</span>
		</a>
		<!-- Sidebar -->
		<div class="sidebar">
			<!-- Sidebar Menu -->
			<nav class="mt-2">
				<ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
					<!-- Add icons to the links using the .nav-icon class
							 with font-awesome or any other icon font library -->
					', menu_link('/', 'Basic Status', 'fas fa-home'), '
					', menu_link('/detailed', 'Detailed Status', 'fas fa-info'), '
					', menu_sep(), '
					', menu_link('/logout', 'Logout', 'fas fa-sign-out-alt'), '
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
					<div class="col-sm-6">
						<a class="float-left nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
						<h1>', $site_title, '</h1>
					</div>
					<div class="col-sm-6">
                    </div>
            	</div>
			</div><!-- /.container-fluid -->
		</section>

		<!-- Main content -->
		<section class="content">';

	# If header not written yet, write the header, then the output we cached:
	if (!$header_done)
	{
		$contents = ob_get_clean();
		site_header();
		echo $contents;
	}
}

function site_footer($javascript = '')
{
	echo '
		</section>
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

<script src="/plugins/jquery/jquery.min.js"></script>
<script src="/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/js/adminlte.min.js"></script>
<script src="/js/site.js?', time(), '"></script>
<script>
	SID="', strrev(session_id()), '";
', !empty($javascript) ? '
' . $javascript : '', '
</script>
</body>
</html>';
}
