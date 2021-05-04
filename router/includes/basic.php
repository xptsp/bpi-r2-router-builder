<?php
$site_title = 'Basic Status';
site_header();
site_menu();
require_once('subs-detailed.php');

#######################################################################################################
# Display WAN (internet) connectivity:
#######################################################################################################
$wan_if = parse_ifconfig('wan');
#$ping = @shell_exec('/bin/ping -I wan -i 1 -c 1 8.8.8.8');
$net = strpos($wan_if['brackets'], 'RUNNING') === false ? 'DISCONNECTED' : '&nbsp;';
echo '
			<div class="row">
				<div class="col-md-4">
					<div id="connectivity-div" class="small-box bg-', $net == '&nbsp;' ? 'success' : 'danger', '">', ($net == "&nbsp;" ? '
						<div class="overlay" id="connectivity-spinner">
							<i class="fas fa-2x fa-sync-alt fa-spin"></i>
						</div>' : ''), '
						<div class="inner">
							<p class="text-lg">Internet Status</p>
							<h3 id="connectivity-text">', $net, '</h3>
						</div>
						<div class="icon">
							<i class="fas fa-ethernet"></i>
						</div>
						<a href="/detailed" class="small-box-footer">
							Detailed Status <i class="fas fa-arrow-circle-right"></i>
						</a>
					</div>
				</div>';

#######################################################################################################
# Display number of attached devices:
#######################################################################################################
#$arp_table = explode("\n", @shell_exec('arp | grep -v wan'));
echo '
				<div class="col-md-4">
					<div class="small-box bg-indigo">
						<div class="overlay" id="connectivity-spinner">
							<i class="fas fa-2x fa-sync-alt fa-spin"></i>
						</div>
						<div class="inner">
							<p class="text-lg">Attached Devices</p>
							<h3 id="num_of_devices">', max(0, isset($arp_table) ? count($arp_table) - 2 : 0), '</h3>
						</div>
						<div class="icon">
							<i class="fas fa-laptop-house"></i>
						</div>
						<a href="#" class="small-box-footer">
							Device List <i class="fas fa-arrow-circle-right"></i>
						</a>
					</div>
				</div>';

#######################################################################################################
# Display USB drive sharing:
#######################################################################################################
$sharing = false;
echo '
				<div class="col-md-4">
					<div class="small-box bg-orange">
						<div class="inner">
							<p class="text-lg">USB Drive Sharing</p>
							<h3>', $sharing == false ? 'Disabled' : ($sharing > 0 ? strval($sharing) : 'No') . ' Devices', '</h3>
						</div>
						<div class="icon">
							<i class="fab fa-usb"></i>
						</div>
						<a href="#" class="small-box-footer">
							USB Sharing Settings <i class="fas fa-arrow-circle-right"></i>
						</a>
					</div>
				</div>';

#######################################################################################################
# Display 2.4GHz wireless connectivity:
#######################################################################################################
echo '
				<div class="col-md-4">
					<div class="small-box bg-primary">
						<div class="inner">
							<p class="text-lg">2.4GHz Wireless Status</p>
							<h3>Meh</h3>
						</div>
						<div class="icon">
							<i class="fas fa-wifi"></i>
						</div>
						<a href="#" class="small-box-footer">
							Wireless Settings <i class="fas fa-arrow-circle-right"></i>
						</a>
					</div>
				</div>';

#######################################################################################################
# Display 5GHz wireless connectivity:
#######################################################################################################
echo '
				<div class="col-md-4">
					<div class="small-box bg-secondary">
						<div class="inner">
							<p class="text-lg">5GHz Wireless Status</p>
							<h3>Meh</h3>
						</div>
						<div class="icon">
							<i class="fas fa-wifi"></i>
						</div>
						<a href="#" class="small-box-footer">
							Wireless Settings <i class="fas fa-arrow-circle-right"></i>
						</a>
					</div>
				</div>';

#######################################################################################################
# Display system overview
#######################################################################################################
$api = @json_decode(@file_get_contents('http://pi.hole/admin/api.php?summary'));
#echo '<pre>'; print_r($api); exit();
echo '
			</div>
			<div class="row mb-2">
				<div class="col-sm-12">
					<h3>Daily Pi-Hole Stats</h1>
				</div>
			</div>
			<div class="row">
				<div class="col-md-3">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title">
								<i class="fas fa-globe"></i>
								Total Queries (', isset($api->unique_clients) ? $api->unique_clients : '&quest;', ' Clients)
							</h3>
						</div>
						<div class="card-body centered text-lg">
							', isset($api->dns_queries_today) ? $api->dns_queries_today : 'n/a', '
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title">
								<i class="fas fa-hand-paper"></i>
								Queries Blocked
							</h3>
						</div>
						<div class="card-body centered text-lg">
							', isset($api->ads_blocked_today) ? $api->ads_blocked_today : 'n/a', '
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title">
								<i class="fas fa-chart-pie"></i>
								Percent Blocked
							</h3>
						</div>
						<div class="card-body centered text-lg">
							', isset($api->ads_percentage_today) ? $api->ads_percentage_today : 'n/a', '&percnt;
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title">
								<i class="fas fa-list-alt"></i>
								Domains Being Blocked
							</h3>
						</div>
						<div class="card-body centered text-lg">
							', isset($api->domains_being_blocked) ? $api->domains_being_blocked : 'n/a', '
						</div>
					</div>
				</div>
			</div>';

#######################################################################################################
# Display system overview
#######################################################################################################
$load = sys_getloadavg();
$temp = number_format((float) @file_get_contents('/sys/devices/virtual/thermal/thermal_zone0/temp') / 1000, 1);
$icon = 'fa-thermometer-' . ($temp > 70 ? 'full' : ($temp > 60 ? 'three-quarters' : ($temp > 50 ? 'half' : ($temp > 40 ? 'quarter' : 'empty'))));
echo '
			<div class="row mb-2">
				<div class="col-sm-12">
					<h3>Hardware Stats</h1>
				</div>
			</div>
			<div class="row">
				<div class="col-md-3">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title">
								<i class="fas ', $icon, '"></i>
								Temperature:
							</h3>
						</div>
						<div class="card-body centered text-lg">
							', $temp, '&deg; C
						</div>', ($temp > 60 ? '
						<div class="ribbon-wrapper ribbon-lg">
							<div class="ribbon bg-danger text-lg">Danger!</div>
						</div>' : ''), '
					</div>
				</div>
				<div class="col-md-3">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title">
								<i class="fas fa-truck-loading"></i>
								Average Load:
							</h3>
						</div>
						<div class="card-body centered text-lg">',
								number_format((float)$load[0], 2), ', ',
								number_format((float)$load[1], 2), ', ',
								number_format((float)$load[2], 2), '
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title">
								<i class="fas fa-stopwatch"></i>
								System Uptime:
							</h3>
						</div>
						<div class="card-body centered text-lg">', system_uptime(),'</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title">
								<i class="fas fa-clock"></i>
								System Time:
							</h3>
						</div>
						<div class="card-body centered text-lg">', date('Y-m-d H:i:s'), '</div>
					</div>
				</div>
			</div>';

#######################################################################################################
# Close this page:
#######################################################################################################
site_footer();