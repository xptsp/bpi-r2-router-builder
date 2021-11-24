<?php
if (!isset($_POST['action']) || !isset($_POST['sid']))
	require_once("404.php");
if ($_POST['sid'] != $_SESSION['sid'])
	die('RELOAD');

#################################################################################################
# ACTION: CHECK => Returns the current version of the specified repo:
#################################################################################################
if (!isset($_POST['action']) || $_POST['action'] == 'check')
{
	# Define everything we need for the entire operation:
	header('Content-type: application/json');
	$round = 'kept';
	$start_indent = 0;
	$packages = array('good' => array(), 'hold' => array(), 'kept' => array());
	$debian = array('list' => array('good' => '', 'hold' => '', 'kept' => ''));	
	$button = array(
		'good' => '<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-xs btn-success pkg-upgrade">Upgrade</button></a>',
		'hold' => '<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-xs btn-danger pkg-held">Held</button></a>',
		'kept' => '<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-xs btn-info pkg-kept">Kept Back</button></a>',
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
					'<td><input type="checkbox"' . (isset($packages['good'][$package]) ? ' checked="checked"' : '') . '></td>' .
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
	echo json_encode($debian);
	$_SESSION['debian'] = $debian;
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
}
#################################################################################################
# ACTION: Anything else ==> Return "Unknown action" to user:
#################################################################################################
else
	die("ERROR: Unknown action!");
