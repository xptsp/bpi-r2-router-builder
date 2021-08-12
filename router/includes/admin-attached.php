<?php
require_once("subs/admin.php");

#########################################################################################
# Function that displays the leases attached to a particular interface.
#########################################################################################
function list_leases($iface, &$s)
{
	global $leases;
	$subnet = $s = "";
	foreach (explode("\n", trim(@file_get_contents("/etc/network/interfaces.d/" . $iface))) as $iface)
	{
		if (preg_match('/address\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.)/', $iface, $regex))
			$subnet = $regex[1];
	}
	if (empty($subnet))
		return 0;
	$s = '
                <table class="table table-hover text-nowrap">
					<thead>
						<tr>
							<th style="width: 10px">#</th>
							<th>MAC Address</th>
							<th>Device Name</th>
							<th>IP Address</th>
						</tr>
					</thead>
					<tbody>';
	$count = 0;
	foreach ($leases as $lease)
	{
		$parts = explode(" ", $lease);
		if (substr($parts[2], 0, strlen($subnet)) == $subnet)
			$s .= '
						<tr>
							<td>' . ++$count . '</td>
							<td>' . strtoupper($parts[1]) . '</td>
							<td>' . $parts[3] . '</td>
							<td>' . $parts[2] . '</td>
						</tr>';
	}
	if ($count == 0)
		$s .= '
						<tr>
							<td colspan="5" class="centered"><h4>No Devices Attached</h4></td>
						</tr>';
	$s .= '
					</tbody>
				</table>';
	return $count;
}

$leases = explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases")));
$table = array();
$s = '';
foreach (get_network_adapters() as $iface => $bridged)
{
	if (list_leases($iface, $s) > 0)
	{
		$nickname = isset($bridged['nickname']) ? $bridged['nickname'] : $iface;
		$table[$nickname] = $s;
	}
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
site_footer();
