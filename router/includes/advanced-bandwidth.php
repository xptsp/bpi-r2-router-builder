<?php
require_once("subs/dhcp.php");

$L['datefmt_days'] = '%d %B';
$L['datefmt_months'] = '%B %Y';
$L['datefmt_hours'] = '%l%p';

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
#$_POST['action'] = 'hour';
if (isset($_POST['action']))
{
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

	####################################################################################
	# ACTION: RETRIEVE ==> Process the raw data dump sent from the VNSTAT program and
	#     return it as a JSON array for the JavaScript to deal with.
	####################################################################################
	if (in_array($_POST['action'], array('hour', 'day', 'month')))
	{
		$_POST['iface'] = isset($_POST['iface']) ? $_POST['iface'] : 'wan';
		$iface = option_allowed('iface', array_keys(get_network_adapters()));
		$data = array(
			'title' => ($_POST['action'] == 'hour' ? 'Last 24 Hours' : ($_POST['action'] == 'day' ? 'Last 30 Days' : 'Last 12 Months')),
			'table' => '',
		);
		$data['rx'] = $data['tx'] = $data['label'] = array();
		foreach (explode("\n", @shell_exec("vnstat --dumpdb -i " . $iface)) as $line)
		{
			$d = explode(';', trim($line));
			if ($_POST['action'] == 'day' && $d[0] == 'd' && !empty($d[2]))
			{
				$data['label'][$d[1]] = strftime($L['datefmt_days'],$d[2]);
				$data['rx'][$d[1]] = $d[3] * 1024 + $d[5];
				$data['tx'][$d[1]] = $d[4] * 1024 + $d[6];
				$data['table'] .= '<tr><td>' . $data['label'][$d[1]] . '</td><td><span class="float-right">' . number_format($data['rx'][$d[1]]) . ' KB</span></td><td><span class="float-right">' . number_format($data['tx'][$d[1]]) . ' KB</span></td><td><span class="float-right">' . number_format($data['tx'][$d[1]] + $data['rx'][$d[1]]) . ' KB</span></td></tr>';
			}
			if ($_POST['action'] == 'month' && $d[0] == 'm' && !empty($d[2]))
			{
				$data['label'][$d[1]] = strftime($L['datefmt_months'], $d[2]);
				$data['rx'][$d[1]] = $d[3] * 1024 + $d[5];
				$data['tx'][$d[1]] = $d[4] * 1024 + $d[6];
				$data['table'] .= '<tr><td>' . $data['label'][$d[1]] . '</td><td><span class="float-right">' . number_format($data['rx'][$d[1]]) . ' KB</span></td><td><span class="float-right">' . number_format($data['tx'][$d[1]]) . ' KB</span></td><td><span class="float-right">' . number_format($data['tx'][$d[1]] + $data['rx'][$d[1]]) . ' KB</span></td></tr>';
			}
			if ($_POST['action'] == 'hour' && $d[0] == 'h' && !empty($d[2]))
			{
			    $st = $d[2] - ($d[2] % 3600);
			    $data['label'][$d[1]] = strftime($L['datefmt_hours'], $st).' - '.strftime($L['datefmt_hours'], $st + 3600);
				$data['rx'][$d[1]] = $d[3];
				$data['tx'][$d[1]] = $d[4];
				$data['table'] .= '<tr><td>' . $data['label'][$d[1]] . '</td><td><span class="float-right">' . number_format($data['rx'][$d[1]]) . ' KB</span></td><td><span class="float-right">' . number_format($data['tx'][$d[1]]) . ' KB</span></td><td><span class="float-right">' . number_format($data['tx'][$d[1]] + $data['rx'][$d[1]]) . ' KB</span></td></tr>';
			}
		}
		header('Content-type: application/json');
		die(json_encode($data));
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#########################################################################################
# Create the page to show the data:
#########################################################################################
site_menu();
$_POST['iface'] = isset($_POST['iface']) ? $_POST['iface'] : 'wan';
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">
			Interface: <select id="interface">';
foreach (get_network_adapters() as $iface => $dummy)
{
	if (!preg_match('/^(docker.+|lo|sit.+|eth0)$/', $iface))
		echo '
				<option value="', $iface, '"', $_POST['iface'] == $iface ? ' selected' : '', '>' . $iface . '</option>';
}
echo '
			</select>
		</h3>
		<div class="card-tools">
			Display Mode: <select id="mode">
				<option value="hour">Last 24 Hours</option>
				<option value="day">Last 30 Days</option>
				<option value="month">Last 12 Months</option>
			</select>
		</div>
	</div>
	<div class="card-body">
		<div class="chart">
			<canvas id="barChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
		</div>
		<div>
			<a href="javascript:void(0);"><button type="button" id="update_chart" class="btn btn-sm btn-primary float-right">Refresh</button></a>
		</div>
	</div>
	<div class="card-header">
		<h3 class="card-title" id="table_header"</h3>
	</div>
	<div class="card-body p-0">
		<table class="table table-sm table-bordered">
			<thead>
				<tr>
					<th width="25%">&nbsp;</th>
					<th width="25%"><center>In</center></th>
					<th width="25%"><center>Out</center></th>
					<th width="25%"><center>Total</center></th>
				</tr>
			</thead>
			<tbody id="table_data" />
		</table>
	</div>
</div>';
site_footer('Init_Bandwidth("' . 'Transmitted' . '", "' . 'Received' . '");');
