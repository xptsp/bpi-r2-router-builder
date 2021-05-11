<?php
#########################################################################################
# Function that displays the leases attached to a particular interface.
#########################################################################################
function list_leases($iface)
{
	global $leases;
	$subnet = "";
	foreach (explode("\n", trim(@file_get_contents("/etc/network/interfaces.d/" . $iface))) as $iface)
	{
		if (preg_match('/address\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.)/', $iface, $regex))
			$subnet = $regex[1];
	}
	echo '
				<table class="table">
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
			echo '
						<tr>
							<td>', ++$count, '</td>
							<td>', strtoupper($parts[1]), '</td>
							<td>', $parts[3], '</td>
							<td>', $parts[2], '</td>
						</tr>';
	}
	if ($count == 0)
		echo '
						<tr>
							<td colspan="5" class="centered"><h4>No Devices Attached</h4></td>
						</tr>';
	echo '
					</tbody>
				</table>';
}

#########################################################################################
# Main code
#########################################################################################
site_menu();
$leases = explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases")));
echo '
<div class="col-12 col-sm-12">
	<div class="card card-primary card-tabs">
		<div class="card-header p-0 pt-1">
			<ul class="nav nav-tabs" id="custom-tabs-one-tab" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="wired-tab" data-toggle="pill" href="#wired" role="tab" aria-controls="wired" aria-selected="true">Wired</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="wlan_24g-tab" data-toggle="pill" href="#wlan_24g" role="tab" aria-controls="wlan_24g" aria-selected="false">Wireless (2.4GHz)</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="wlan_5g-tab" data-toggle="pill" href="#wlan_5g" role="tab" aria-controls="wlan_5g" aria-selected="false">Wireless (5GHz)</a>
				</li>
			</ul>
		</div>
		<div class="card-body p-0">
			<div class="tab-content" id="custom-tabs-one-tabContent">
				<div class="tab-pane fade show active" id="wired" role="tabpanel" aria-labelledby="wired-tab">';
list_leases('br0');
echo '
				</div>
				<div class="tab-pane fade" id="wlan_24g" role="tabpanel" aria-labelledby="wlan_24g-tab">';
list_leases('mt_24g');
echo '
				</div>
				<div class="tab-pane fade" id="wlan_5g" role="tabpanel" aria-labelledby="wlan_5g-tab">';
list_leases('mt_5g');
echo '
				</div>
			</div>
		</div>
		<!-- /.card -->
	</div>
</div>';
site_footer();
