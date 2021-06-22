<?php
if (!isset($_GET['sid']) || $_GET['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}

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
$process = proc_open('/opt/bpi-r2-router-builder/helpers/router-helper.sh apt upgrade -y', $descriptorspec, $pipes, realpath('./'), array());
if (is_resource($process)) 
{
	while ($s = fgets($pipes[1], 2)) 
	{
		print $s;
		flush();
	}
}
