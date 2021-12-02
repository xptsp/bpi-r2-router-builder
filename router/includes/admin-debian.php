<?php
$disabled = ' disabled="disabled"';

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

	#################################################################################################
	# ACTION: CHECK => Returns the current version of the specified repo:
	#################################################################################################
	if ($_POST['action'] == 'check')
	{
		# Define everything we need for the entire operation:
		header('Content-type: application/json');
		$round = 'kept';
		$start_indent = 0;
		$packages = array('good' => array(), 'hold' => array(), 'kept' => array());
		$debian = array('list' => array('good' => '', 'hold' => '', 'kept' => ''));	
		$button = array(
			'good' => '<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-xs btn-success pkg-upgrade"' . $disabled . '>Upgrade</button></a>',
			'hold' => '<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-xs btn-danger pkg-held"' . $disabled . '>Held</button></a>',
			'kept' => '<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-xs btn-info pkg-kept"' . $disabled . '>Kept Back</button></a>',
		);

		# Get current list of packages for Debian:
		#################################################################################
		@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh apt update');

		# Get which packages (if any) are marked as hold:
		#################################################################################
		foreach (explode("\n", trim(@shell_exec("apt-mark showhold"))) as $package)
			$packages['hold'][$package] = true;

		# Run a simulated upgrade to get the packages that can actually be installed:
		#################################################################################
		foreach (explode("\n", trim(trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh apt upgrade -s")))) as $id => $line)
		{
			if (preg_match("/^\s\s(.*)/", $line, $regex))
			{
				if ($start_indent > 0 && $start_indent + 1 != $id)
					$packages[$round = 'good'] = array();
				$start_indent = $id;
				foreach (explode(" ", $regex[1]) as $package)
				{
					if (!isset($packages['hold'][$package]))
						$packages[$round][$package] = true;
				}
			}
			else if (preg_match("/(\d+)\s.*\s(\d+)\s.*\s(\d+)\s.*\s(\d+).*/", $line, $regex))
				$debian['count'] = $regex;
		}
		if (empty($debian['count'][1]) && !empty($packages['good']) && empty($packages['kept']))
		{
			$packages['kept'] = $packages['good'];
			$packages['good'] = array();
		}

		# Gather a complete list of upgradable packages:
		#################################################################################
		foreach (explode("\n", trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh apt list --upgradable'))) as $line)
		{
			if (preg_match("/(.*)\/.*\s(.*)\s.*\s\[upgradable from: (.*)\]/", $line, $matches))
			{
				$package = $matches[1];
				$status = isset($packages['hold'][$package]) ? 'hold' : (isset($packages['kept'][$package]) ? 'kept' : 'good');
				$debian['list'][$status] .=
					'<tr>' .
						'<td><input type="checkbox"' . (isset($packages['good'][$package]) ? ' checked="checked"' : '') . $disabled . '></td>' .
						'<td>' . $package . '</td>' .
						'<td>' . $matches[2] . '</td>' .
						'<td>' . $matches[3] . '</td>' .
						'<td>' . $button[$status] . '</td>' .
					'</tr>';
			}
		}
		$_SESSION['debian']['refreshed'] = time();
		#echo '<pre>'; print_r($_SESSION['debian']); exit;

		# Output the gathered information as a JSON array:
		#################################################################################
		$debian['list'] = implode("", $debian['list']);
		$_SESSION['debian'] = $debian;
		die(json_encode($debian));
	}
	#################################################################################################
	# ACTION: UPGRADE/INSTALL => Updates to the current version of the specified repo:
	#################################################################################################
	else if ($_POST['action'] == 'upgrade' || ($_POST['action'] == 'install' && isset($_POST['packages'])))
	{
		# Send headers and turn off buffering and compression:
		header("Content-type: text/plain");
		ini_set('output_buffering', 'off');
		ini_set('zlib.output_compression', false);
		ini_set('implicit_flush', true);

		# Flush everything in the cache right now:
		@ob_implicit_flush(true);
		@ob_end_flush();

		# Start sending the output of the "apt upgrade -y" command:
		$descriptorspec = array(
			0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
			1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
			2 => array("pipe", "w")    // stderr is a pipe that the child will write to
		);
		flush();
		$cmd = '/opt/bpi-r2-router-builder/helpers/router-helper.sh apt ' . $_POST['action'] . ($_POST['action'] == 'install' ? ' ' . $_POST['packages']  : '');
		$process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), array());
		if (is_resource($process))
		{
			$buffer = str_repeat(' ', 2048);
			while ($s = fgets($pipes[1], 4096))
			{
				print rtrim($s) . $buffer;
				flush();
			}
		}
		die();
	}
	#################################################################################################
	# ACTION: UPGRADE/INSTALL => Updates to the current version of the specified repo:
	#################################################################################################
	else if ($_POST['action'] == 'test')
	{
		# Send headers and turn off buffering and compression:
		header("Content-type: text/plain");
		ini_set('output_buffering', 'off');
		ini_set('zlib.output_compression', false);
		ini_set('implicit_flush', true);

		# Flush everything in the cache right now:
		@ob_implicit_flush(true);
		@ob_end_flush();

		$buffer = str_repeat(' ', 2048);
		foreach (explode("\n", trim(@file_get_contents("/opt/bpi-r2-router-builder/misc/apt-test.txt"))) as $s)
		{
			print rtrim($s) . $buffer;
			sleep(250);
			flush();
		}
		die();
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# Main code for this page:
#################################################################################################
site_menu();

if (isset($_SESSION['debian']['refreshed']) && $_SESSION['debian']['refreshed'] + 600 > time())
	unset($_SESSION['debian']);

####################################################################################################
# Output the Debian Updates page:
####################################################################################################
$installable = isset($_SESSION['debian']['count'][1]) ? $_SESSION['debian']['count'][1] : 0;
$updates = isset($_SESSION['debian']['count'][1]) ? $_SESSION['debian']['count'][1] + $_SESSION['debian']['count'][2] + $_SESSION['debian']['count'][3] + $_SESSION['debian']['count'][4] : '';
$hidden = isset($_SESSION['debian']['count']) ? '' : ' hidden';
echo '
<div class="row">
	<div class="col-md-12">
		<div class="card card-primary">
			<div class="card-header">
				<h3 class="card-title"><i class="fab fa-linux"></i> Debian Updates</h3>
			</div>
			<!-- /.card-header -->
			<div class="card-body table-responsive" id="debian-div">
				<div class="callout callout-info', $hidden, '" id="updates-div">
					<a href="javascript:void(0);"><button type="button" class="btn btn-primary float-right apt_pull" style="margin-right: 10px;" data-toggle="modal" data-target="#output-modal">Update Packages</button></a>
					<h5><i class="icon fas fa-info-circle"></i> <span id="updates-available">', $updates, '</span> Updates Available, <span id="updates-installable">', $installable, '</span> Installable</h5>
					APT test reported &quot;<span id="updates-msg">', isset($_SESSION['debian']['count'][0]) ? $_SESSION['debian']['count'][0] : '', '</span>&quot;
				</div>
				<h2 class="card-title"><strong>Available Updates: </strong></h2>
				<table class="table table-striped table-bordered table-sm">
					<thead>
						<tr>
							<th width="2%"><input type="checkbox"', $installable ? ' checked="checked"' : '', $disabled, '></th>
							<th width="30%">Package Name</th>
							<th width="25%">New Version</th>
							<th width="25%">Old Version</th>
							<th width="10%">Status</th>
						</tr>
					</thead>
					<tbody id="packages_div">';
if (isset($_SESSION['debian']['list']))
	echo $_SESSION['debian']['list'];
else
	echo '
						<tr>
							<td colspan="5"><center>Press <strong>Check for Updates</strong> to Update</center></td>
						</tr>';
echo '
					</tbody>
				</table>
			</div>
			<div class="card-footer">
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary float-right" id="apt_check">Check for Updates</button></a>
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary float-right apt_pull', $hidden, '" style="margin-right: 10px;" data-toggle="modal" data-target="#output-modal">Update Packages</button></a>
			</div>
		</div>
	</div>';

####################################################################################################
# Define the command-output modal:
####################################################################################################
echo '
<div class="modal fade" id="output-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title"><i class="fab fa-linux"></i> Package Installation Progress</h3>
			</div>
			<div class="modal-body">
				<textarea id="output_div" class="form-control" rows="15" readonly="readonly" style="overflow-y: scroll;"></textarea>
			</div>
			<div class="modal-footer justify-content-between">
				<button type="button" id="modal-close" class="btn btn-primary disabled float-right">Close</button>
			</div>
		</div>
		<!-- /.modal-content -->
		</div>
	<!-- /.modal-dialog -->
	</div>
</div>';

####################################################################################################
# Close the page:
####################################################################################################
site_footer('Init_Debian();');
