<?php
require_once("subs/admin.php");
require_once("subs/setup.php");

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.
#################################################################################################
if (isset($_POST['action']))
{
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

	#################################################################################################
	#   ACTION: DETECT ==> Detect where the machine is, according to "http://ipinfo.io":
	#################################################################################################
	if ($_POST['action'] == 'detect')
	{
		if (!isset($_SESSION['ipinfo']['time']) || $_SESSION['ipinfo']['time'] > time())
		{
			$_SESSION['ipinfo']['arr'] = array();
			foreach (explode("\n", trim(@shell_exec("curl ipinfo.io"))) as $line)
			{
				if (preg_match("/\"(.*)\"\:\s\"(.*)\"/", $line, $matches))
					$_SESSION['ipinfo']['arr'][$matches[1]] = $matches[2];
			}
			$_SESSION['ipinfo']['time'] = time() + 600;
		}
		die(json_encode($_SESSION['ipinfo']['arr']));
	}
	#################################################################################################
	#   ACTION: SET ==> Set the timezone and hostname of the system:
	#################################################################################################
	else if ($_POST['action'] == 'set')
	{
		// Set the change to the onboard wifi mode:
		$option = parse_options();
		$tmp = option_allowed('onboard_wifi', array('1', 'A'));
		if ($tmp != $option['onboard_wifi'])
		{
			$option['onboard_wifi'] = $tmp; 
			apply_options();
		}

		// Set the other options:
		$mac = option_mac('mac');
		$timezone = option_allowed('timezone', array_keys(timezone_list()) );
		$locale = option_allowed('locale', array_keys(get_os_locales()) );
		$hostname = option('hostname', "/^([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)$/");

		@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh mac " . $mac);
		die(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh device ' . $hostname . ' ' . $timezone . ' ' . $locale));
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

###########################################################################################
# Main code for this page:
###########################################################################################
site_menu();
#echo '<pre>'; print_r($current); exit;
echo '
<div class="card card-primary" id="settings-div">
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
		</div>';

###########################################################################################
# Onboard Wifi Setting:
###########################################################################################
$option = parse_options();
echo '
		<div class="row" style="margin-top: 10px">
			<div class="col-6">
				<label for="onboard_wifi">BPi R2 Onboard Wifi Mode:</label></td>
			</div>
			<div class="col-6 input-group">
				<select class="form-control" id="onboard_wifi">
					<option value="A"', $option['onboard_wifi'] == 'A' ? ' selected="selected"' : '', '>Access Point Mode Only</option>
					<option value="1"', $option['onboard_wifi'] == '1' ? ' selected="selected"' : '', '>Client Mode Only</option>
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
					<input id="mac_addr" name="mac_addr" type="text" class="form-control" placeholder="', strtoupper($mac), '" value="', $mac, '" maxlength="17"', $mac_chk ? ' disabled="disabled"' : '', '>
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
apply_changes_modal("Please wait while router settings are being set....", true);
site_footer('Init_Settings("' . $mac_com . '", "' . $mac . '");');
