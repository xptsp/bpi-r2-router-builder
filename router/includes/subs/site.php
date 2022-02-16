<?php
$site_title = '';
$header_done = false;
$output_null = false;
$options_changed = false;

################################################################################################################
# Define the default sidebar menu:
################################################################################################################
$sidebar_menu = array(
	'home'   => menu_link('/', 'Home', 'fas fa-home'),
	'setup'  => array('Setup', 'fas fa-cog', array(
		'settings'  => menu_link('/setup/settings', 'Router Settings', 'fab fa-linux'),
		'wired'     => menu_link('/setup/wired', 'Wired Setup', 'fas fa-ethernet'),
		'wireless'  => menu_link('/setup/wireless', 'Wireless Setup', 'fas fa-wifi'),
		'dns'       => menu_link('/setup/dns', 'DNS Settings', 'fas fa-signature'),
		'routing'   => menu_link('/setup/routing', 'Network Routing', 'fas fa-route'),
	)),
	'storage' => array('Storage', 'fas fa-hdd', array(
#		'basic'     => menu_link('/storage/usb-basic', 'Basic Settings', 'fab fa-usb'),
	)),
	'advanced' => array('Advanced', 'fas fa-shield-alt', array(
		'firewall'  => menu_link('/advanced/firewall', 'Firewall Setup', 'fas fa-shield-alt'),
		'dmz'       => menu_link('/advanced/dmz', 'DMZ Setup', 'fas fa-server'),
		'dhcp'      => menu_link('/advanced/dhcp', 'DHCP Reservations', 'fas fa-address-card'),
		'forward'   => menu_link('/advanced/forward', 'Port Forwarding', 'fas fa-forward'),
	)),
	'services'  => array('Services', 'fas fa-concierge-bell', array(
		'notify'    => menu_link('/services/notify', 'DHCP Notifications', 'fas fa-bullhorn', file_exists("/usr/bin/mosquitto_pub")),
		'upnp'      => menu_link('/services/upnp', 'UPnP Setup', 'fas fa-plug', file_exists("/lib/systemd/system/miniupnpd.service")),
		'usage'     => menu_link('/services/bandwidth', 'Bandwidth Usage', 'fas fa-chart-bar', file_exists("/lib/systemd/system/vnstat.service")),
	)),
	'manage'  => array('Management', 'fas fa-cog', array(
		'status'   => menu_link('/manage/status', 'Router Status', 'fas fa-ethernet'),
		'manage'   => menu_link('/manage/webui', 'WebUI Management', 'fas fa-server'),
		'attached' => menu_link('/manage/attached', 'Attached Devices', 'fas fa-link'),
		'backup'   => menu_link('/manage/backup', 'Backup &amp; Restore', 'fas fa-file-export'),
		'creds'    => menu_link('/manage/creds', 'Credentials', 'fas fa-user-edit'),
		'logs'     => menu_link('/manage/logs', 'System Logs', 'far fa-list-alt'),
		'repo'     => menu_link('/manage/repo', 'Repository Updates', 'fab fa-github'),
		'debian'   => menu_link('/manage/debian', 'Debian Updates', 'fab fa-linux'),
	)),
	'plugins' => array('Plug-Ins', 'fas fa-puzzle-piece', array(
	)),
);

# Get the WebUI version once per this session:
################################################################################################################
if (isset($_SESSION['webui_version']) && isset($_SESSION['webui_version_last']) && $_SESSION['webui_version_last'] > time())
{
	unset($_SESSION['webui_version']);
	unset($_SESSION['regdb_version']);
	unset($_SESSION['webui_version_last']);
}
if (!isset($_SESSION['webui_version']))
{
	$time = trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh git current'));
	$_SESSION['webui_version'] = ($time == (int) $time ? date('Y.md.Hi', (int) $time) : "Invalid Data");
	$_SESSION['webui_version_last'] = time() + 600;
}
if (!isset($_SESSION['regdb_version']))
{
	$time = trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh git current wireless-regdb'));
	$_SESSION['regdb_version'] = ($time == (int) $time ? date('Y.md.Hi', (int) $time) : "Invalid Data");
}
$webui_version = $_SESSION['webui_version'];

# Get whether the router is operating on a temporary overlay in RAM:
################################################################################################################
if (!isset($_SESSION['critical_alerts']))
	$_SESSION['critical_alerts'] = explode("\n", trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh security-check')));
#echo '<pre>'; print_r($_SESSION['critical_alerts']); exit;

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
	<link rel="stylesheet" href="/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
</head>';
	$header_done = true;
}

################################################################################################################
# Function that returns a menu item:
################################################################################################################
function menu_link($url, $text, $icon = "far fa-circle", $enabled = true, $id = false)
{
	global $site_title, $logged_in;

	if (!isset($_GET['action']))
		return;
	$test_url = ltrim(preg_replace('/[\s\W]+/', '-', $url), '-');
	$active = ($test_url == $_GET['action'] || ($url == '/' && $_GET['action'] == 'home')) ? ' active' : '';
	if (!$enabled)
		return '';
	if (!empty($active))
		$site_title = $text;
	return
		'<li class="nav-item"' . (!empty($id) ? ' id="' . $id . '_label"' : '') . '>' .
			'<a href="' . $url . '" class="nav-link' . $active . '">' .
				'<i' . (!empty($id) ? ' id="' . $id . '"' : '') . ' class="nav-icon ' . $icon . '"></i>' .
				'<p>' . $text . '</p>' .
			'</a>' .
		'</li>';
}

################################################################################################################
# Function that returns a menu with submenu items in it:
################################################################################################################
function menu_submenu($text, $icon = "far fa-circle", $items = array())
{
	global $logged_in;
	$items = (is_array($items) ? implode('', $items) : $items);
	if (empty($items))
		return '';
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
# Function that produces the login/logout menu button:
################################################################################################################
function menu_log()
{
	global $logged_in;
	return
		'<li class="nav-item">' .
			'<a href="' . ($logged_in ? '/logout"' : '#" data-toggle="modal" data-target="#login-modal" id="menu_log" ') . ' class="nav-link" >' .
				'<i class="nav-icon fas fa-sign-' . ($logged_in ? 'out' : 'in') . '-alt"></i>' .
				'<p>' . ($logged_in ? "Logout" : "Login") . '</p>' .
			'</a>' .
		'</li>';
}

################################################################################################################
# Function that outputs the sidebar menu, and the header if not already done:
################################################################################################################
function site_menu($refresh_switch = false, $refresh_text = "Refresh", $refresh_checked = true)
{
	global $site_title, $header_done, $sidebar_menu, $logged_in, $output_null;

	# If header not written yet, cache our output for now:
	if (!$header_done)
		ob_start();

	# Write the menu:
	$dark_mode = $_SESSION['dark_mode'] == "Y";
	echo '
<body class="hold-transition sidebar-mini layout-boxed layout-fixed ', $dark_mode ? 'bodybg-dark dark-mode' : 'bodybg', '">
<div class="wrapper">
	<!-- Main Sidebar Container -->
	<aside class="main-sidebar main-sidebar-custom sidebar-dark-primary elevation-4 ">
		<!-- Brand Logo -->
		<a href="/" class="brand-link">
			<img src="/img/wifi-router.png" width="32" height="32" class="brand-image" style="opacity: .8">
			<span class="brand-text font-weight-light">Banana Pi Router</span>
		</a>
		<!-- Sidebar -->
		<div class="sidebar">
			<!-- Sidebar Menu -->
			<nav class="mt-2">
				<ul class="nav nav-pills nav-sidebar flex-column nav-child-indent nav-collapse-hide-child" data-widget="treeview" role="menu" data-accordion="false">
					<!-- Add icons to the links using the .nav-icon class
							 with font-awesome or any other icon font library -->
					';
foreach ($sidebar_menu as $item)
	echo !is_array($item) ? $item : ((isset($item[2]) & is_array($item[2])) ? menu_submenu($item[0], $item[1], $item[2]) : '');
echo '
					', menu_log(), '
				</ul>
				<span 
			</nav>
			<!-- /.sidebar-menu -->
		</div>
		<!-- /.sidebar -->
		<div class="sidebar-custom">
			<ul class="nav nav-pills nav-sidebar flex-column" style="position: absolute; bottom: 0; left: 8;">
				', menu_link('#', "Dark Mode", (!$dark_mode ? 'fas' : 'far') . ' fa-lightbulb', true, 'dark-mode'), '
			</ul>
		</div>
	</aside>

	<!-- Content Wrapper. Contains page content -->
	<div class="content-wrapper">
		<section class="content-header">
			<div class="container-fluid">
				<div class="row mb-2">
					<div class="col-sm-6">
						<a class="float-left nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
						<h1>', $site_title, '</h1>
					</div>', $refresh_switch ? '
					<div class="col-sm-6">
						<span class="float-right">' . $refresh_text . ' <input type="checkbox" id="refresh_switch" ' . ($refresh_checked ? ' checked' : '') . ' data-bootstrap-switch></span>
					</div>' : '', '
            	</div>
			</div><!-- /.container-fluid -->
		</section>';

	# If header not written yet, write the header, then the output we cached:
	if (!$header_done)
	{
		$contents = ob_get_clean();
		site_header();
		echo $contents;
	}

	# If the user isn't logged in, we can't show them the contents of anything other than the home page and 404:
	if (!$logged_in && $_GET['action'] != 'home' && $_GET['action'] != '404')
	{
		$output_null = true;
		ob_start();
	}

	# Output the main contents from here:
	echo '
		<!-- Main content -->
		<section class="content">';
}

################################################################################################################
# Function that outputs the footer of the web page:
################################################################################################################
function site_footer($init_str = '')
{
	global $webui_version, $logged_in, $output_null, $include_js;
	$post_js = '?' . time();

	# Purge the output buffer if we aren't allowed to show anything:
	if ($output_null)
		ob_clean();

	# Start output the footer:
	echo '
		</section>
	</div>
	<!-- /.content-wrapper -->

	<footer class="main-footer text-sm">
		<div class="float-right d-none d-sm-block">
			<b>WebUI</b> v', $webui_version, '
		</div>
		<strong>Copyright &copy; 2021 <a href="https://github.com/xptsp/bpi-r2-router-builder" target="_blank">BPi-R2 Router Builder</a>.</strong> All rights reserved.
	</footer>
</div>
<!-- ./wrapper -->

<script src="/plugins/jquery/jquery.min.js"></script>
<script src="/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/plugins/bs-custom-file-input/bs-custom-file-input.min.js"></script>
<script src="/js/adminlte.min.js"></script>
<script src="/plugins/bs-custom-file-input/bs-custom-file-input.min.js"></script>
<script src="/plugins/bootstrap-switch/js/bootstrap-switch.min.js"></script>
<script src="/plugins/inputmask/jquery.inputmask.min.js"></script>
<script src="/plugins/chart.js/Chart.js"></script>
<script src="/js/site.js', $post_js, '"></script>';

	# Include any additional javascript files requested by the pages:
	if (!empty($include_js))
		echo '
<script src="/js/', $include_js, '.js', $post_js, '"></script>';

	# Insert the SID we're using, and set the login/logout handlers:
	echo '
<script>
	Init_Site("', $_SESSION['sid'], '");', !empty($init_str) ? '
	' . trim($init_str) : '', '
</script>
</body>
</html>';
}

#######################################################################################################
# Function showing "Reboot Router" confirmation modal:
#######################################################################################################
function reboot_modal()
{
echo '
<div class="modal fade" id="reboot-modal" data-backdrop="static" style="display: none; z-index: 9000;" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Confirm <span id="title_msg">Reboot</span> Router</h4>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close" id="reboot_close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p id="reboot_msg"><span id="body_msg">Rebooting</span> the router will disrupt active traffic on the network.</p>
				<p id="reboot_timer">Are you sure you want to do this?</p>
			</div>
			<div class="modal-footer justify-content-between" id="reboot_control">
				<button type="button" class="btn btn-default" id="reboot_nah" data-dismiss="modal">Not Now</button>
				<button type="button" class="btn btn-primary" id="reboot_yes">Reboot Router</button>
			</div>
		</div>
		<!-- /.modal-content -->
	</div>
	<!-- /.modal-dialog -->
</div>';
}

#######################################################################################################
# Function showing the "Apply Changes" modal:
#######################################################################################################
function apply_changes_modal($text = 'Please wait while the firewall service is restarted....', $close_hidden = false, $text2 = '')
{
	echo '
<div class="modal fade" id="apply-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header bg-primary">
				<h4 class="modal-title" id="apply_title">Applying Changes</h4>
				<a href="javascript:void(0);"><button type="button hidden alert_control" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button></a>
			</div>
			<div class="modal-body">
				<p id="apply_msg"></p>
				<p id="apply_default" class="hidden">', $text, '</p>', !empty($text2) ? '
				<p id="apply_default2" class="hidden">' . $text2 . '</p>' : '', '
			</div>
			<div class="modal-footer justify-content-between', $close_hidden ? ' hidden' : '', '" id="apply_cancel">
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary" data-dismiss="modal">Close</button></a>
			</div>
		</div>
	</div>
</div>';
}

#######################################################################################################
# Function showing an option checkbox:
#######################################################################################################
function checkbox($name, $description, $default = true, $disabled_by = '')
{
	global $options;
	$checked = (!isset($options[$name]) ? $default : ($options[$name] != "N"));
	$enabled = (!empty($disabled_by) ? $options[$disabled_by] : true);
	return '<div class="icheck-primary">' .
				'<input type="checkbox" id="' . $name . '"' . ($checked ? ' checked="checked"' : '') . ($enabled ? '' : ' disabled="disabled"') . '>' .
				'<label for="' . $name . '">' . $description . '</label>' .
			'</div>';
}

#######################################################################################################
# Function showing interface status:
#######################################################################################################
function show_interface_status($iface, $status, $url = '#', $icon = 'fa-ethernet')
{
	return 
	'<div class="col-md-4">' .
		'<div id="connectivity-div" class="small-box ' . ($status == "Offline" || $status == "Not Running" || $status == "Disconnected" || $status == "Down" ? 'bg-danger' : 'bg-success') . '">' .
			'<div class="inner">' .
				'<p class="text-lg">Interface <b>' . $iface . '</b></p>' .
				'<h3>' . $status . '</h3>' .
			'</div>' .
			'<div class="icon">' .
				'<i class="fas ' . $icon . '"></i>' .
			'</div>' .
			'<a href="' . $url . '" class="small-box-footer">' .
				'Interface Configuration <i class="fas fa-arrow-circle-right"></i>' .
			'</a>' .
		'</div>' .
	'</div>';
}

function access_point_status($iface, $subnet, $leases)
{
	################################################################################################
	# Determine which physical wireless interface the specified interface is on:
	################################################################################################
	$phys = $num = -1;
	foreach (explode("\n", trim(@shell_exec("iw dev | egrep 'phy|Interface|ssid'"))) as $line)
	{
		if (preg_match("/^phy\#(\d+)/", trim($line), $regex))
			$num = $regex[1];
		else if (preg_match("/Interface ([\w\d\_]+)/", trim($line), $regex) && $iface == $regex[1])
			$phys = $num;
		else if ($phys != -1 && preg_match("/ssid (.*)/", $line))
		{
			$count = 0;
			foreach ($leases as $lease)
			{
				if (preg_match('/' . $subnet .'/', $lease['ip_address']))
					$count++;
			}
			return strval($count) . ' ' . ($count == 1 ? 'Client' : 'Clients');
		}
	}
	return 'Not Running';
}

#######################################################################################################
# Functions dealing with passed parameters:
#######################################################################################################
function parse_options($file = '/etc/default/router-settings')
{
	$options = array();
	foreach (explode("\n", trim(@file_get_contents($file))) as $line)
	{
		$parts = explode("=", $line . '=');
		$options[ (empty($parts[1]) ? count($options) : $parts[0]) ] = trim(empty($parts[1]) ? $parts[0] : $parts[1]);
	}
	#echo '<pre>'; print_r($options); exit;
	return $options;
}

function option($name, $allowed = "/^[Y|N]$/", $post = true)
{
	global $options, $options_changed;
	if ($post)
		$tmp = isset($_POST[$name]) ? $_POST[$name] : '';
	else
		$tmp = isset($_GET[$name]) ? $_GET[$name] : '';
	if (empty($allowed) || !preg_match($allowed, $tmp))
		die('ERROR: Missing or invalid value for option "' . $name . '"!');
	$options_changed |= !isset($options[$name]) || $options[$name] != $tmp;
	return $tmp;
}

function option_allowed($name, $allowed = array())
{
	global $options, $options_changed;
	if (!in_array($tmp = isset($_POST[$name]) ? $_POST[$name] : '', $allowed))
		die('ERROR: Missing or invalid value for option "' . $name . '"!');
	$options_changed |= !isset($options[$name]) || $options[$name] != $tmp;
	return $tmp;
}

function option_range($name, $min, $max)
{
	global $options, $options_changed;
	$tmp = isset($_POST[$name]) ? (int) $_POST[$name] : -99999999;
	if ($tmp < $min || $tmp > $max)
		die('ERROR: Missing or invalid value for option "' . $name . '"!');
	$options_changed |= !isset($options[$name]) || $options[$name] != $tmp;
	return $tmp;
}

function option_ip($name, $empty_allowed = false, $port_allowed = false, $local_okay = false)
{
	global $options, $options_changed;
	$parts = explode("#", $tmp = isset($_POST[$name]) ? $_POST[$name] : '');
	if ($empty_allowed && empty($tmp))
		return $tmp;
	else 
	{
		if ($tmp == "127.0.0.1" && !$local_okay)
			die('ERROR: Invalid value for option "' . $name . '"!');
		if (!filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
			die('ERROR: Missing or invalid value for option "' . $name . '"!');
		if (isset($parts[1]) && (!$port_allowed || !is_numeric($parts[1]) || $parts[1] < 0 || $parts[1] > 65535))
			die('ERROR: Missing or invalid value for option "' . $name . '"!');
	}
	$options_changed |= !isset($options[$name]) || $options[$name] != $tmp;
	return $tmp;
}

function option_mac($name)
{
	global $options, $options_changed;
	$tmp = isset($_POST[$name]) ? $_POST[$name] : '';
	if (!filter_var($tmp, FILTER_VALIDATE_MAC))
		die('ERROR: Missing or invalid value for option "' . $name . '"!');
	$options_changed |= !isset($options[$name]) || $options[$name] != $tmp;
	return $tmp;
}

function apply_options($mode = "reload")
{
	global $options, $options_changed;
	if (!$options_changed)
		return;
	$text = '';
	foreach ($options as $name => $setting)
	{
		if (is_numeric($name))
			$text .= $setting . "\n";
		else if ($name != "dns1" && $name != "dns2")
			$text .= (!empty($setting) ? $name . '=' . $setting : $name). "\n";
	}
	#echo '<pre>'; echo $text; exit;
	$handle = fopen("/tmp/router-settings", "w");
	fwrite($handle, $text);
	fclose($handle);
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh firewall " . $mode);
	if ($mode == "reload" && isset($options['use_isp']) && isset($options['dns1']))
		@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dns " . ($options['use_isp'] == 'Y' ? 'config' : $options['dns1'] . ' ' . $options['dns2']));
	return "OK";
}
