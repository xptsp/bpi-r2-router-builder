<?php
require_once("subs/admin.php");

###########################################################################################
# Supporting Functions:
###########################################################################################
function timezone_list()
{
    $timezones = [];
    $offsets = [];
    $now = new DateTime('now', new DateTimeZone('UTC'));

    foreach (DateTimeZone::listIdentifiers() as $timezone) {
        $now->setTimezone(new DateTimeZone($timezone));
        $offsets[] = $offset = $now->getOffset();
        $timezones[$timezone] = '(' . format_GMT_offset($offset) . ') ' . format_timezone_name($timezone);
    }
    array_multisort($offsets, $timezones);
    return $timezones;
}

function format_GMT_offset($offset)
{
    $hours = intval($offset / 3600);
    $minutes = abs(intval($offset % 3600 / 60));
    return 'GMT' . ($offset ? sprintf('%+03d:%02d', $hours, $minutes) : '');
}

function format_timezone_name($name)
{
    $name = str_replace('/', ', ', $name);
    $name = str_replace('_', ' ', $name);
    $name = str_replace('St ', 'St. ', $name);
    return $name;
}

function get_os_locales()
{
	$lang = null;
	$locales = array();
	foreach (explode("\n", trim(@shell_exec("locale -v -a"))) as $line)
	{
		if (preg_match("/locale\:\s([^\s]*)\s+/", $line, $matches))
			$lang = $matches[1];
		else if (preg_match("/language \| (.*)/", $line, $matches) && $lang != "C.UTF-8")
			$locales[$lang] = $matches[1];
	}
	return $locales;
}

###########################################################################################
# Main code for this page:
###########################################################################################
site_menu();
#echo '<pre>'; print_r($current); exit;
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Router Settings</h3>
	</div>';

###########################################################################################
# Hostname:
###########################################################################################
echo '
	<div class="card-body">
		<div class="row" style="margin-top: 10px">
			<div class="col-6">
				<label for="hostname">Host Name</label></td>
			</div>
			<div class="col-6">
				<div class="input-group">
					<div class="input-group-prepend">
						<span class="input-group-text"><i class="fas fa-laptop-code"></i></span>
					</div>
					<input id="hostname" type="text" class="hostname form-control" value="', @file_get_contents('/etc/hostname'), '" data-inputmask-regex="([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)">
				</div>
			</div>
		</div>';

###########################################################################################
# Time Zone:
###########################################################################################
echo '
		<div class="row" style="margin-top: 10px">
			<div class="col-6">
				<label for="hostname">System Time Zone</label></td>
			</div>
			<div class="col-6 input-group">
				<select class="form-control" id="timezone">';
$current = date_default_timezone_get();
foreach (timezone_list() as $id => $text)
	echo '
					<option value="', trim($id), '"', $id == $current ? ' selected="selected"' : '', '>', $text, '</option>';
echo '
				</select>
				<span class="input-group-append">
					<button type="button" class="btn btn-info btn-flat" id="tz_detect">Detect</button>
				</span>
			</div>
		</div>';

###########################################################################################
# OS Locales Installed:
###########################################################################################
echo '
		<div class="row" style="margin-top: 10px">
			<div class="col-6">
				<label for="hostname">Available OS Locales:</label></td>
			</div>
			<div class="col-6 input-group">
				<select class="form-control" id="locale">';
foreach (get_os_locales() as $id => $text)
	echo '
					<option value="', trim($id), '"', $id == $current ? ' selected="selected"' : '', '>[', $id, '] ', $text, '</option>';
echo '
				</select>
			</div>
		</div>
	</div>';

###################################################################################################
# Router MAC Address settings:
###################################################################################################
$wan = parse_ifconfig('wan');
#echo '<pre>'; print_r($wan); exit();
$mac = trim($wan['ether']);
$parts = explode("=", trim(@file_get_contents("/boot/eth0.conf")));
$def = isset($parts[1]) ? $parts[1] : $mac;
$mac_com = trim(@shell_exec("arp -n | grep " . $_SERVER['REMOTE_ADDR'] . " | awk '{print $3}'"));
$mac_chk = ($mac == $def || $mac == $mac_com);
echo '
	<div class="card-header">
		<h3 class="card-title">Router MAC Address</h3>
	</div>
	<!-- /.card-header -->
	<div class="card-body">
		<div class="row">
			<div class="col-6">
				<div class="icheck-primary">
					<input class="mac_opt" type="radio" id="mac_custom" name="router_mac"', !$mac_chk ? ' checked="checked"' : '', '>
					<label for="mac_custom">Use this MAC Address</label>
				</div>
				<div class="icheck-primary">
					<input class="mac_opt" type="radio" id="mac_default" name="router_mac"', $mac == $def ? ' checked="checked"' : '', '>
					<label for="mac_default">Current MAC Address</label>
				</div>
				<div class="icheck-primary">
					<input class="mac_opt" type="radio" id="mac_computer" name="router_mac"', $mac == $mac_com ? ' checked="checked"' : '', ' data-mac="', $mac_com, '"', $mac_com == "" ? ' disabled="disabled"' : '', '>
					<label for="mac_computer">Use Computer MAC Address</label>
				</div>
				<div class="icheck-primary">
					<input class="mac_opt" type="radio" id="mac_random" name="router_mac"', $mac == $mac_com ? ' checked="checked"' : '', ' data-mac="', $mac_com, '"', $mac_com == "" ? ' disabled="disabled"' : '', '>
					<label for="mac_random">Use Randomly Generated MAC Address</label>
				</div>
			</div>
			<div class="col-6">
				<span class="float-right">
					<input id="mac_addr" name="mac_addr" type="text" class="form-control" value="', $mac, '" maxlength="17"', $mac_chk ? ' disabled="disabled"' : '', '>
				</span>
			</div>
		</div>';

###########################################################################################
# Finalize Page:
###########################################################################################
echo '
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
site_footer('Init_Router("' . $mac_com . '", "' . $mac . '");');
