<?php
if (!isset($_POST['action']) || !isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit;
}

####################################################################################
# ACTION: UPLOAD ==> Verify the contents of the upload:
####################################################################################
if ($_POST['action'] == "upload")
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
# ACTION: FACTORY ==> Signal a reformat is needed and return to caller:
####################################################################################
else if ($_POST['action'] == "factory")
{
	echo explode("\n", trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh reformat -y")))[0];
}
####################################################################################
# ACTION: FILE ==> Process the uploaded file:
####################################################################################
else if ($_POST['action'] == 'file')
{
	echo trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh backup restore"));
}
####################################################################################
# ACTION: Anything else ==> Return "Unknown action"
####################################################################################
else
	die("ERROR: Unknown action!");