<?php
$site_title = '';
$header_done = false;

################################################################################################################
# Define the default sidebar menu:
################################################################################################################
$sidebar_menu = array(
	'home'   => menu_link('/', 'Home', 'fas fa-home'),
	'setup'  => menu_submenu('Setup', 'fas fa-cog', array(
		'internet' => menu_link('/setup/internet', 'Internet Setup', 'fas fa-ethernet'),
		'wireless' => menu_link('/setup/wireless', 'Wireless Setup', 'fas fa-wifi'),
		'firewall' => menu_link('/setup/firewall', 'WAN Setup', 'fas fa-ethernet'),
		'lan'      => menu_link('/setup/lan', 'LAN Setup', 'fas fa-ethernet'),
	)),
	'storage'    => menu_submenu('Storage', 'fas fa-hdd', array(
		'basic'    => menu_link('/storage/usb-basic', 'Basic Settings', 'fab fa-usb'),
	)),
	'admin'  => menu_submenu('Administration', 'fas fa-cog', array(
		'status'   => menu_link('/admin/status', 'Router Status', 'fas fa-ethernet'),
		'attached' => menu_link('/admin/attached', 'Attached Devices', 'fas fa-link'),
		'backup'   => menu_link('/admin/backup', 'Backup Settings', 'fas fa-file-export'),
		'creds'    => menu_link('/admin/creds', 'Login Credentials', 'fas fa-user-edit'),
		'logs'     => menu_link('/admin/logs', 'Router Logs', 'far fa-list-alt'),
		'update'   => menu_link('/admin/update', 'Router Update', 'fab fa-linux'),
	)),
	'logout' => menu_link('/logout', 'Logout', 'fas fa-sign-out-alt'),
);

################################################################################################################
# Get the WebUI version once per this session:
################################################################################################################
if (isset($_SESSION['webui_version']) and isset($_SESSION['webui_version_last']) and $_SESSION['webui_version_last'] > time() - 600)
	unset($_SESSION['webui_version']);
if (!isset($_SESSION['webui_version']))
{
	$_SESSION['webui_version'] = date('Y.md.Hi', (int) trim(@shell_exec('/usr/local/bin/router-helper webui current')));
	$_SESSION['webui_version_last'] = time();
}
$webui_version = $_SESSION['webui_version'];

################################################################################################################
# Function that outputs the header of the web page:
################################################################################################################
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

################################################################################################################
# Function that returns a menu item:
################################################################################################################
function menu_link($url, $text, $icon = "far fa-circle")
{
	global $site_title;

	$test_url = ltrim(preg_replace('/[\s\W]+/', '-', $url), '-');
	$active = ($test_url == $_GET['action'] or ($url == '/' and $_GET['action'] == 'home')) ? ' active' : '';
	if (!empty($active))
		$site_title = $text;
	return
		'<li class="nav-item">' .
			'<a href="' . $url . '" class="nav-link' . $active . '">' .
				'<i class="nav-icon ' . $icon . '"></i>' .
				'<p>' . $text . '</p>' .
			'</a>' .
		'</li>';
}

################################################################################################################
# Function that returns a menu with submenu items in it:
################################################################################################################
function menu_submenu($text, $icon = "far fa-circle", $items = array())
{
	$items = (is_array($items) ? implode('', $items) : $items);
	return
		'<li class="nav-item' . (strrpos($items, 'class="nav-link active">') > 0 ? ' menu-open' : '') . '">' .
			'<a href="#" class="nav-link' . (strrpos($items, 'class="nav-link active">') > 0 ? ' active' : '') . '">' .
				'<i class="nav-icon ' . $icon . '"></i>' .
				'<p>' .$text . '<i class="fas fa-angle-left right"></i></p>' .
			'</a>' .
		'<ul class="nav nav-treeview">' .
		$items .
		'</ul>';
}

################################################################################################################
# Function that returns a menu seperator:
################################################################################################################
function menu_sep($text = '<hr />')
{
	return '<li class="nav-item">' . $text . '</li>';
}

################################################################################################################
# Function that outputs the sidebar menu, and the header if not already done:
################################################################################################################
function site_menu()
{
	global $site_title, $header_done, $sidebar_menu;

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
				<ul class="nav nav-pills nav-sidebar flex-column nav-child-indent nav-collapse-hide-child" data-widget="treeview" role="menu" data-accordion="false">
					<!-- Add icons to the links using the .nav-icon class
							 with font-awesome or any other icon font library -->
					', implode('
					', $sidebar_menu), '
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

################################################################################################################
# Function that outputs the footer of the web page:
################################################################################################################
function site_footer($javascript = '')
{
	global $webui_version;

	echo '
		</section>
	</div>
	<!-- /.content-wrapper -->

	<footer class="main-footer text-sm">
		<div class="float-right d-none d-sm-block">
			<b>WebUI</b> v', $webui_version, '
		</div>
		<strong>Copyright &copy; 2021 <a href="https://github.com/xptsp/bpi-r2-router-builder">BPi-R2 Router Builder</a>.</strong> All rights reserved.
	</footer>
</div>
<!-- ./wrapper -->

<script src="/plugins/jquery/jquery.min.js"></script>
<script src="/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/js/adminlte.min.js"></script>
<script src="/js/site.js?', time(), '"></script>
<script>
	SID="', strrev(session_id()), '";
', 
!empty($javascript) ? trim($javascript, "\n") : '', '
</script>
</body>
</html>';
}
