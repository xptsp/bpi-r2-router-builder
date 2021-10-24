<?php
require_once("subs/admin.php");
site_menu();

function thead()
{
	return '
				<thead>
					<tr>
						<th width="25%">Destination LAN IP</th>
						<th width="25%">Subnet Mask</th>
						<th width="25%">Gateway</th>
						<th width="10%">Metric</th>
						<th width="10%">Interface</th>
						<th width="5%">&nbsp;</th>
					</tr>
				</thead>';
}

echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Network Routing</h3>
	</div>
	<div class="card-body">
		<div class="alert alert-danger hidden" id="dhcp_error_box">
			<a href="javascript:void(0);"><button type="button" class="close" id="dhcp_error_close">&times;</button></a>
			<i class="fas fa-ban"></i>&nbsp;<span id="dhcp_error_msg" />
		</div>
		<h5 class="dhcp_div">
			<a href="javascript:void(0);"><button type="button" id="routing-refresh" class="btn btn-sm btn-primary float-right">Refresh</button></a>
			Routing Table
		</h5>
		<div class="table-responsive p-0 dhcp_div">
			<table class="table table-hover text-nowrap table-sm table-striped">' . thead() . '
				<tbody id="routing-table">
					<tr><td colspan="6"><center>Loading...</center></td></tr>
				</tbody>
			</table>
		</div>
		<br />
		<h5 class="dhcp_div">Add New Routing</h5>
		<div class="table-responsive p-0 dhcp_div">
			<table class="table table-hover text-nowrap table-sm table-striped">' . thead() . '
				<tbody>
					<tr>
						<td><input id="dest_addr" type="text" class="ip_address form-control" /></td>
						<td><input id="mask_addr" type="text" class="ip_address form-control" value="255.255.255.0" /></td>
						<td><input id="gate_addr" type="text" class="ip_address form-control" value="0.0.0.0" /></td>
						<td><input id="metric" class="form-control" value="0" /></td>
						<td colspan="2">
							<select class="custom-select" id="iface">';
foreach (get_network_adapters() as $iface => $ignore)
{
	if ($iface != "eth0" && $iface != "lo" && $iface != "sit0")
		echo '
								<option value="' . $iface . '"' . ($iface == 'br0' ? ' selected="selected"' : '') . '>' . $iface . '</option>';
}
echo '
							</select>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<a href="javascript:void(0);"><button type="button" id="add_route" class="btn btn-success float-right">Add Route</button></a>
	</div>
</div>';
site_footer('Init_Routing();');
