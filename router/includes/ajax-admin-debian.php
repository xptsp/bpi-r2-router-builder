<?php
if (!isset($_POST['action']) && !isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}
set_time_limit(0);

#################################################################################################
# ACTION: CHECK => Returns the current version of the specified repo:
#################################################################################################
if ($_POST['action'] == 'check')
{
	header('Content-type: application/json');
	if (!isset($_SESSION['debian']['refreshed']) || $_SESSION['debian']['refreshed'] >= time() + 600)
		unset($_SESSION['debian']);
	if (!isset($_SESSION['debian']))
	{
		# Get number of updates available:
		$result = trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh apt update | grep "packages"'));
		$updates = 0;
		if (preg_match("/(\d+) packages/", $result, $regex))
			$_SESSION['debian']['count'] = $regex[1];

		# Gather the list of upgradable packages:
		$_SESSION['debian']['list'] = "";
		$list = explode("\n", trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh apt list --upgradable')));
		foreach ($list as $id => $text)
		{
			if ($text == "Listing...")
				unset($list[$id]);
			else
			{
				$tmp = explode(" ", $list[$id], 4);
				if (isset($tmp[3]))
				{
					$_SESSION['debian']['list'] .=
						'<tr>' .
							'<td><input type="checkbox" checked="checked"></td>' .
							'<td>' . explode("/", $tmp[0])[0] . '</td>' .
							'<td>' . $tmp[1] . '</td>' .
							'<td>' . explode(" ", str_replace(']', '', str_replace('[', '', $tmp[3])))[2] . '</td>' .
						'</tr>';
				}
			}
		}
		$_SESSION['debian']['refreshed'] = time();
	}

	# Output the gathered information as a JSON array:
	echo json_encode(array(
		'updates' => $_SESSION['debian']['count'],
		'list' => $_SESSION['debian']['list'],
		'time' => $_SESSION['debian']['refreshed'],
	));
}
#################################################################################################
# ACTION: PULL => Updates to the current version of the specified repo:
#################################################################################################
else if ($_POST['action'] == 'pull')
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
	$process = proc_open('/opt/bpi-r2-router-builder/helpers/router-helper.sh apt upgrade', $descriptorspec, $pipes, realpath('./'), array());
	if (is_resource($process))
	{
		$buffer = str_repeat(' ', 2048);
		while ($s = fgets($pipes[1], 4096))
		{
			print $s . $buffer;
			flush();
		}
	}
}
#################################################################################################
# ACTION: Anything else ==> Return "Unknown action" to user:
#################################################################################################
else
	die("ERROR: Unknown action!");
