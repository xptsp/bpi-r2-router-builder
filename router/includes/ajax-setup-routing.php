<?php
if (empty($_POST['action']) || !isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}

###################################################################################################
# ACTION: SHOW ==> Show the current routing table.  Add delete icons to any custom lines we find.
###################################################################################################
if ($_POST['action'] == 'show')
{
	echo '<pre>';
	$out = array();
	foreach (explode("\n", trim(@shell_exec("route | grep -v Kernel | grep -v Destination"))) as $line)
	{
		$arr = explode(" ", preg_replace('/\s+/', ' ', $line));
		$name = "/etc/network/if-up.d/" . $arr[7] . "-route";
		$file = trim(@file_get_contents($name));
		if (!empty($file))
		{
			echo '<pre>' . $file; exit();
		}
	}

	foreach ($out as $arr)
		echo "<tr><td>" . implode("</td><td>", $arr) . "</td></tr>";
}
###################################################################################################
# ACTION: Anything else ==> Return error message
###################################################################################################
else
	echo "ERROR: Unknown action";
