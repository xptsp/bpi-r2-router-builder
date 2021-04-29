<?php
$site_title = 'Basic Status';
site_header();
site_menu();
require_once('subs-detailed.php');

#######################################################################################################
# Display system overview
#######################################################################################################
$load = sys_getloadavg();
$temp = number_format((float) @file_get_contents('/sys/devices/virtual/thermal/thermal_zone0/temp') / 1000, 1);
echo '
			<div class="row">
				<div class="col-md-3">
					<div class="card card-', ($temp > 60 ? 'danger' : ($temp > 50 ? 'warning' : ($temp > 40 ? 'info ' : 'success'))), '">
						<div class="card-header">
							<h3 class="card-title">
								<i class="fas fa-thermometer-', ($temp > 70 ? 'full' : ($temp > 60 ? 'three-quarters' : ($temp > 50 ? 'half' : ($temp > 40 ? 'quarter' : ($temp > 30 ? 'low' : 'empty'))))), '"></i>
								<i class="fas fa-thermometer-"></i>
								&nbsp;&nbsp;Temperature:
							</h3>
						</div>
						<div class="card-body centered">
							<h5>', $temp, '&deg;C</h5>
						</div>';
if ($temp > 60)
	echo '
						<div class="ribbon-wrapper ribbon-lg">
							<div class="ribbon bg-warning text-lg">Danger!</div>
						</div>';
echo '

					</div>
				</div>
				<div class="col-md-3">
					<div class="card card-secondary">
						<div class="card-header">
							<h3 class="card-title">
								<i class="fas fa-truck-loading"></i>
								&nbsp;&nbsp;Average Load:
							</h3>
						</div>
						<div class="card-body centered">
							<h5>',
								number_format((float)$load[0], 2), ', ',
								number_format((float)$load[1], 2), ', ',
								number_format((float)$load[2], 2), '
							</h5>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card card-info">
						<div class="card-header">
							<h3 class="card-title">
								<i class="fas fa-stopwatch"></i>
								&nbsp;&nbsp;System Uptime:
							</h3>
						</div>
						<div class="card-body centered">
							<h5>', system_uptime(),' </h5>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card card-primary">
						<div class="card-header">
							<h3 class="card-title">
								<i class="fas fa-clock"></i>
								&nbsp;&nbsp;System Time:
							</h3>
						</div>
						<div class="card-body centered">
							<h5>', date('Y-m-d H:i:s'), '</h5>
						</div>
					</div>
				</div>
			</div>';

#######################################################################################################
# Close this page:
#######################################################################################################
site_footer();