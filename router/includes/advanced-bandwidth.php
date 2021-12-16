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
		die(json_encode(array('reload' => true)));

	####################################################################################
	# ACTION: RETRIEVE ==> Process the raw data dump sent from the VNSTAT program and
	#     return it as a JSON array for the JavaScript to deal with.
	####################################################################################
	#$_POST['action'] = 'hour';
	if (in_array($_POST['action'], array('hour', 'day', 'month')))
	{
		$_POST['iface'] = isset($_POST['iface']) ? $_POST['iface'] : 'wan';
		$iface = option_allowed('iface', array_keys(get_network_adapters()));
		$data = array(
			'title' => ($_POST['action'] == 'hour' ? 'Last 24 Hours' : ($_POST['action'] == 'day' ? 'Last 30 Days' : 'Last 12 Months')),
			'unit' => 'KB',
		);
	    $units = array('KB', 'MB', 'GB', 'TB'); 
		$data['table'] = $data['rx'] = $data['tx'] = $data['label'] = array();
		foreach (explode("\n", @shell_exec("vnstat --dumpdb -i " . $iface)) as $line)
		{
			$d = explode(';', trim($line));
			if ($_POST['action'] == 'day' && $d[0] == 'd' && !empty($d[2]))
			{
				$data['label'][$d[1]] = strftime($L['datefmt_days'], $d[2]);
				$data['rx'][$d[1]] = $d[3] * 1024 + $d[5];
				$data['tx'][$d[1]] = $d[4] * 1024 + $d[6];
			}
			else if ($_POST['action'] == 'month' && $d[0] == 'm' && !empty($d[2]))
			{
				$data['label'][$d[1]] = strftime($L['datefmt_months'], $d[2]);
				$data['rx'][$d[1]] = $d[3] * 1024 + $d[5];
				$data['tx'][$d[1]] = $d[4] * 1024 + $d[6];
			}
			else if ($_POST['action'] == 'hour' && $d[0] == 'h' && !empty($d[2]))
			{
			    $st = $d[2] - ($d[2] % 3600);
			    $data['label'][$d[1]] = strftime($L['datefmt_hours'], $st).' - '.strftime($L['datefmt_hours'], $st + 3600);
				$data['rx'][$d[1]] = $d[3];
				$data['tx'][$d[1]] = $d[4];
			}
		}
		$base = 0;
		foreach (array_merge($data['rx'], $data['tx']) as $kilos)
		{
			$pow = floor(($kilos ? log($kilos) : 0) / log(1024)); 
			$base = max($base, min($pow, count($units)));
		}
		$data['unit'] = $units[$base];
		foreach ($data['rx'] as $id => $bytes)
		{
			$data['rx'][$id] = round($data['rx'][$id] /= pow(1024, $base), 2);
			$data['tx'][$id] = round($data['tx'][$id] /= pow(1024, $base), 2);
			$data['table'][$id] = 
				'<tr>' .
					'<td>' . $data['label'][$id] . '</td>' .
					'<td><span class="float-right">' . number_format($data['rx'][$id], 2) . ' ' . $data['unit'] . '</span></td>' .
					'<td><span class="float-right">' . number_format($data['tx'][$id], 2) . ' ' . $data['unit'] . '</span></td>' .
					'<td><span class="float-right">' . number_format($data['tx'][$id] + $data['rx'][$id], 2) . ' ' . $data['unit'] . '</span></td>' .
				'</tr>';
		}
		//echo '<pre>'; print_r($data); exit;
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
$_GET['iface'] = isset($_GET['iface']) ? $_GET['iface'] : 'wan';
$_GET['mode'] = isset($_GET['mode']) ? $_GET['mode'] : 'hour';
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">
			Interface: <select id="interface">';
foreach (get_network_adapters() as $iface => $dummy)
{
	if (!preg_match('/^(docker.+|lo|sit.+|eth0)$/', $iface))
		echo '
				<option value="', $iface, '"', $_GET['iface'] == $iface ? ' selected="selected"' : '', '>' . $iface . '</option>';
}
echo '
			</select>
		</h3>
		<div class="card-tools">
			Display <select id="mode">
				<option value="hour"', $_GET['mode'] == 'hour' ? ' selected="selected"' : '', '>Last 24 Hours</option>
				<option value="day"', $_GET['mode'] == 'day' ? ' selected="selected"' : '', '>Last 30 Days</option>
				<option value="month"', $_GET['mode'] == 'month' ? ' selected="selected"' : '', '>Last 12 Months</option>
			</select>
		</div>
	</div>
	<div class="card-body">
		<div class="chart">
			<canvas id="barChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
		</div>
	</div>
	<div class="card-header">
		<h3 class="card-title" id="table_header">', $_GET['mode'] == 'hour' ? 'Last 24 Hours' : ($_GET['mode'] == 'day' ? 'Last 30 Days' : 'Last 12 Months'), '</h3>
		<button type="button" class="btn btn-xs btn-success float-right" id="update_chart">Refresh</button>
	</div>
	<div class="card-body table-responsive p-0">
		<table class="table table-head-fixed text-nowrap table-sm table-bordered">
			<thead>
				<tr>
					<th width="25%"><center>Period</center></th>
					<th width="25%"><center>In</center></th>
					<th width="25%"><center>Out</center></th>
					<th width="25%"><center>Total</center></th>
				</tr>
			</thead>
			<tbody id="table_empty"><tr><td colspan="4"><center><strong>No Data Available</strong></center></td></tr></tbody>
			<tbody id="table_data" class="hidden" />
		</table>
	</div>
</div>';
site_footer('Init_Bandwidth("' . 'Transmitted' . '", "' . 'Received' . '");');
