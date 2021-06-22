<?php
$mode = isset($_POST['restore_type']) ? $_POST['restore_type'] : 'fail';
if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'] || !in_array($mode, array('file', 'factory', 'upload')))
{
	require_once("404.php");
	exit;
}

####################################################################################
# If this is a settings upload, then verify the contents of the upload:
####################################################################################
if ($mode == "upload")
{
	if (!isset($_FILES['file']['name']))
		echo "ERROR: No file specified!";
	else if (strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) != "cfg")
		echo 'ERROR: File extension must be "cfg"!';
	else
	{
		@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh backup remove");
		if (@move_uploaded_file($_FILES['file']['tmp_name'], '/tmp/bpiwrt.cfg'))
			echo trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh backup unpack"));
		else
			echo "ERROR: File move failed";
	}
}

####################################################################################
# If this is a factory restore, signal a reformat is needed and return to caller:
####################################################################################
else if ($mode == "factory")
	echo explode("\n", trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh reformat -y")))[0];

####################################################################################
# Otherwise, we process the uploaded file:
####################################################################################
else
	echo trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh backup restore"));
