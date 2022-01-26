<?php
$options = parse_options();

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

	#################################################################################################
	# ACTION: SUBMIT ==> Update the configuration file using the parameters specified:
	#################################################################################################
	if ($_POST['action'] == 'list')
	{
		$str = '';
		foreach (explode("\n", trim(@shell_exec('upnpc -l'))) as $line)
		{
			if (preg_match("/(\d+)\s+(TCP|UDP)\s+(\d+)\-\>(\d+\.\d+\.\d+\.\d+):(\d+)\s+\'([^\']*)\'\s+\'([^\']*)\' (\d+)/", $line, $regex))
			{
				$str .=
					'<tr>' .
						'<td>' . $regex[2] . '</td>' .	// UDP or TCP
						'<td>' . $regex[3] . '</td>' .	// External Port
						'<td>' . $regex[4] . '</td>' .	// IP Address
						'<td>' . $regex[5] . '</td>' .	// Internal Port(s)
						'<td>' . $regex[6] . '</td>' .	// Description
						'<td>' . $regex[7] . '</td>' .	// Remote Host (?)
						'<td>' . $regex[8] . '</td>' .	// Lease Time
					'</tr>';
			}
		}
		die( !empty($str) ? $str : '<tr><td colspan="5"><center>No UPnP Port Mappings</center></td></tr>' );
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# Output the Firewall settings page:
#################################################################################################
site_menu();
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Universal Plug and Play (UPnP) Settings</h3>
	</div>
	<div class="card-body" id="upnp-div">
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" id="apply_changes" class="btn btn-block btn-success center_50" id="reboot_button">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait while the UPnP settings are managed....', true);
site_footer('Init_Firewall();');
