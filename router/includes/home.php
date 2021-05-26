<?php
site_menu();

#######################################################################################################
# Display WAN (internet) connectivity:
#######################################################################################################
echo '
			<div class="row">
				<div class="col-md-4">
					<div id="connectivity-div" class="small-box bg-success">
						<div class="overlay dark" id="connectivity-spinner">
							<i class="fas fa-2x fa-sync-alt fa-spin"></i>
						</div>
						<div class="inner">
							<p class="text-lg">Internet Status</p>
							<h3 id="connectivity-text">&nbsp;</h3>
						</div>
						<div class="icon">
							<i class="fas fa-ethernet"></i>
						</div>
						<a href="/admin/status" class="small-box-footer">
							Detailed Status <i class="fas fa-arrow-circle-right"></i>
						</a>
					</div>
				</div>';

#######################################################################################################
# Display number of attached devices:
#######################################################################################################
echo '
				<div class="col-md-4">
					<div class="small-box bg-indigo">
						<div class="overlay dark" id="devices-spinner">
							<i class="fas fa-2x fa-sync-alt fa-spin"></i>
						</div>
						<div class="inner">
							<p class="text-lg">Attached Devices</p>
							<h3 id="num_of_devices">&nbsp;</h3>
						</div>
						<div class="icon">
							<i class="fas fa-laptop-house"></i>
						</div>
						<a href="/admin/attached" class="small-box-footer">
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
							<h3 id="usb-sharing">Disabled</span></h3>
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
# Display 5GHz wireless connectivity:
#######################################################################################################
echo '
				<div class="col-md-4">
					<div class="small-box bg-info">
						<div class="inner">
							<p class="text-lg">Guest Networks</p>
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
								Total Queries (<span id="unique_clients">0</span> Clients)
							</h3>
						</div>
						<div class="card-body centered text-lg" id="dns_queries_today">0</div>
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
						<div class="card-body centered text-lg" id="ads_blocked_today">0</div>
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
						<div class="card-body centered text-lg"><span id="ads_percentage_today">0</span>&percnt;</div>
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
						<div class="card-body centered text-lg" id="domains_being_blocked">0</div>
					</div>
				</div>
			</div>';

#######################################################################################################
# Display system overview
#######################################################################################################
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
							<span id="temp"></span>&deg; C
						</div>
						<div class="ribbon-wrapper ribbon-lg invisible" id="temp-danger">
							<div class="ribbon bg-danger text-lg">Danger!</div>
						</div>
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
						<div class="card-body centered text-lg">
								<span id="load0"></span>,
								<span id="load1"></span>,
								<span id="load2"></span>
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
						<div class="card-body centered text-lg" id="system_uptime">&nbsp;</div>
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
						<div class="card-body centered text-lg" id="server_time">&nbsp;</div>
					</div>
				</div>
			</div>';

#######################################################################################################
# Close this page, including the AJAX call to get information:
#######################################################################################################
site_footer('Init_Home();');
