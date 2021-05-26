<?php
$restore = isset($_POST['restore_type']) ? $_POST['restore_type'] : 'fail';
if (!isset($_POST['sid']) || $_POST['sid'] != strrev(session_id()) || !in_array($restore, array('file', 'factory')))
{
	require_once("404.php");
	exit;
}
if ($restore == "factory")
	echo @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh reformat -y");
else
	echo "ERROR: Not Implemented Yet";
