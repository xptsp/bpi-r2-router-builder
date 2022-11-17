<?php
require_once("subs/manage.php");

#################################################################################################
# If action specified, then process this block of code:
#################################################################################################
if (isset($_POST['action']))
{
	####################################################################################
	# ACTION: WOL ==> Send the magic packet (WOL) to the specified PC
	# Adapted from: https://stackoverflow.com/a/62591806
	####################################################################################
	if ($_POST['action'] == "wol")
	{
		// Create Magic Packet
		$mac = option_mac("misc");
		$packet = sprintf('%s%s', str_repeat(chr(255), 6), str_repeat(pack('H*', str_replace(":", "", $mac)), 20));
		if (($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) !== false) 
		{
			if (socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, true) !== false) 
			{
				socket_sendto($socket, $packet, strlen($packet), 0, "255.255.255.255", 9);
				socket_close($socket);
				die("OK");
			}
		}
		die("Error sending WOL packet to " . $mac);
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#########################################################################################
# Gather the leases attached to each interface:
#########################################################################################
$leases = @file("/var/lib/misc/dnsmasq.leases");
$table = array();
foreach (get_network_adapters() as $iface => $bridged)
{
	$subnet = "";
	if (!preg_match('/address\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.)/', @file_get_contents("/etc/network/interfaces.d/" . $iface), $regex))
		continue;
	$subnet = $regex[1];
	$s =
                '<table class="table table-hover text-nowrap">' .
					'<thead>' .
						'<tr>' .
							'<th style="width: 10px">#</th>' .
							'<th>MAC Address</th>' .
							'<th>Device Name</th>' .
							'<th>IP Address</th>' .
							'<th width="5%">WOL</th>' .
						'</tr>' .
					'</thead>' .
					'<tbody>';
	$dev = array();
	foreach ($leases as $lease)
	{
		$parts = explode(" ", $lease);
		if (substr($parts[2], 0, strlen($subnet)) == $subnet)
			$dev[ $parts[2] ] =
						'<tr>' .
							'<td>{count}</td>' .
							'<td class="mac_addr">' . strtoupper($parts[1]) . '</td>' .
							'<td>' . $parts[3] . '</td>' .
							'<td>' . $parts[2] . '</td>' .
							'<td><a href="javascript:void(0);"><button type="button" class="btn btn-block btn-primary btn-xs"><i class="fas fa-power-off"></i></button></a></td>' .
						'</tr>';
	}
	if (count($dev) == 0)
		$s .= 
						'<tr>' .
							'<td colspan="5" class="centered"><h4>No Devices Attached</h4></td>' .
						'</tr>';
	ksort($dev);
	$count = 0;
	foreach ($dev as $id => $elem)
		$dev[$id] = str_replace("{count}", strval(++$count), $elem);
	$s .= implode("", $dev) .
					'</tbody>' .
				'</table>';
	if (count($dev) > 0)
		$table[$iface] = $s;
}

#########################################################################################
# Main code
#########################################################################################
site_menu();
echo '
<div class="col-12 col-sm-12">
	<div class="card card-primary card-tabs">
		<div class="card-header p-0 pt-1">
			<ul class="nav nav-tabs" id="custom-tabs-one-tab" role="tablist">';
$first = false;
foreach ($table as $iface => $s)
{
	if (!$first)
		$first = $iface;
	echo '
				<li class="nav-item">
					<a class="nav-link', $iface == $first ? ' active' : '', '" id="', $iface, '-tab" data-toggle="pill" href="#', $iface, '" role="tab" aria-controls="', $iface,' " aria-selected="true">', $iface, '</a>
				</li>';
}
echo '
			</ul>
		</div>
		<div class="card-body table-responsive p-0">
			<div class="tab-content" id="custom-tabs-one-tabContent">';
foreach ($table as $iface => $s)
	echo '
				<div class="tab-pane fade show', ($iface == $first ? ' active' : ''), '" id="', $iface, '" role="tabpanel" aria-labelledby="', $iface, '-tab">
					', $s, '
				</div>';
echo '
			</div>
		</div>
	</div>
	<!-- /.card -->
</div>';
site_footer('Init_Attached();');
