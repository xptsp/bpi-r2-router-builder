<?php
$site_title = 'Detailed Status';
site_header();
site_menu();
require_once('subs-detailed.php');

#######################################################################################################
# Gather as much information before starting the overview display as we can:
#######################################################################################################
$load = sys_getloadavg();
$br0 = get_mac_info('br0');
$wan = get_mac_info('wan');
$wan_if = parse_ifconfig('wan');
$adblocking = @shell_exec('/usr/local/bin/router-helper pihole status');
$dns = get_dns_servers();

#######################################################################################################
# Display information about the router:
#######################################################################################################
echo '
			<div class="container-fluid">
				<div class="row">
					<div class="col-md-6">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title">Router Information</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body p-0">
								<table class="table">
									<tr>
										<td width="50%"><strong>Hardware Version</strong></td>
										<td>Banana Pi R2</td>
									</tr>
									<tr>
										<td><strong>OS Version</strong></td>
										<td>Debian ', @file_get_contents('/etc/debian_version'), '</td>
									</tr>
									<tr>
										<td><strong>OS Builder Version</strong></td>
										<td>v', date('Y.md.Hi', @filemtime('/opt/bpi-r2-router-builder/.git/refs/heads/master')), '</td>
									</tr>
									<tr>
										<td><strong>OS Load Average</strong></td>
										<td>',
											number_format((float)$load[0], 2), ', ',
											number_format((float)$load[1], 2), ', ',
											number_format((float)$load[2], 2), '
										</td>
									</tr>
									<tr>
										<td><strong>OS Kernel</strong></td>
										<td>', explode(' ', @file_get_contents('/proc/version'))[2], '</td>
									</tr>
									<tr>
										<td><strong>System Uptime</strong></td>
										<td>', system_uptime(), '</td>
									</tr>
									<tr>
										<td><strong>Router IP Address</strong></td>
										<td>', $br0['address'], '</td>
									</tr>
									<tr>
										<td colspan="2">
											<button type="button" class="btn btn-block btn-outline-danger center_50">Reboot Router</button>
										</td>
									</tr>
								</table>
							</div>
							<!-- /.card-body -->
						</div>
						<!-- /.card -->
					</div>
					<!-- /.col -->';
					
#######################################################################################################
# Display information about the Internet Port ("wan" interface):
#######################################################################################################
echo '
					<div class="col-md-6">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title">Internet Port</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body p-0">
								<table class="table">
									<tr>
										<td width="50%"><strong>MAC Address</strong></td>
										<td>', $wan_if['ether'], '</td>
									</tr>
									<tr>
										<td><strong>IP Address</strong></td>
										<td>', $wan_if['inet'], '</td>
									</tr>
									<tr>
										<td><strong>Connection</strong></td>
										<td>', strpos($wan['iface'], 'dhcp') > 0 ? 'DHCP' : 'Static IP', '</td>
									</tr>
									<tr>
										<td><strong>Subnet Mask</strong></td>
										<td>', $wan_if['netmask'], '</td>
									</tr>
									<tr>
										<td><strong>Domain Name Servers</strong></td>
										<td>', $dns[0], '</td>
									</tr>
									<tr>
										<td><strong>&nbsp;</strong></td>
										<td>', isset($dns[1]) ? $dns[1] : '&nbsp;', '</td>
									</tr>
									<tr>
										<td><strong>PiHole Adblocking</strong></td>
										<td>', strpos($adblocking, 'enabled') > 0 ? 'Enabled' : 'Disabled', '</td>
									</tr>
									<tr>
										<td><button type="button" class="btn btn-block btn-outline-info">Show Statistics</button></td>
										<td><button type="button" class="btn btn-block btn-outline-info">Connection Status</button></td>
									</tr>
								</table>
							</div>
							<!-- /.card-body -->
						</div>
						<!-- /.card -->
					</div>
					<!-- /.col -->';
					
#######################################################################################################
# Display information about the normal Wireless Network (2.4GHz)
#######################################################################################################
echo '
					<div class="col-md-6">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title">Wireless Network (2.4GHz)</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body p-0">
								<table class="table">
									<tr>
										<td width="50%"><strong>Name (SSID)</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Region</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Channel</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Mode</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Wireless AP</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Broadcast Name</strong></td>
										<td>N/A</td>
									</tr>
								</table>
							</div>
							<!-- /.card-body -->
						</div>
						<!-- /.card -->
					</div>
					<!-- /.col -->';

#######################################################################################################
# Display information about the normal Wireless Network (5GHz)
#######################################################################################################
echo '
					<div class="col-md-6">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title">Wireless Network (5GHz)</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body p-0">
								<table class="table">
									<tr>
										<td width="50%"><strong>Name (SSID)</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Region</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Channel</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Mode</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Wireless AP</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Broadcast Name</strong></td>
										<td>N/A</td>
									</tr>
								</table>
							</div>
							<!-- /.card-body -->
						</div>
						<!-- /.card -->
					</div>
					<!-- /.col -->';

#######################################################################################################
# Display information about the Guest Wireless Network (2.4GHz)
#######################################################################################################
echo '
					<div class="col-md-6">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title">Guest Network (2.4GHz)</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body p-0">
								<table class="table">
									<tr>
										<td width="50%"><strong>Name (SSID)</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Wireless AP</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Broadcast Name</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Can access local network</strong></td>
										<td>N/A</td>
									</tr>
								</table>
							</div>
							<!-- /.card-body -->
						</div>
						<!-- /.card -->
					</div>
					<!-- /.col -->';

#######################################################################################################
# Display information about the Guest Wireless Network (5GHz)
#######################################################################################################
echo '
					<div class="col-md-6">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title">Guest Network (5GHz)</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body p-0">
								<table class="table">
									<tr>
										<td width="50%"><strong>Name (SSID)</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Wireless AP</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Broadcast Name</strong></td>
										<td>N/A</td>
									</tr>
									<tr>
										<td><strong>Can access local network</strong></td>
										<td>N/A</td>
									</tr>
								</table>
							</div>
							<!-- /.card-body -->
						</div>
						<!-- /.card -->
					</div>
					<!-- /.col -->';

#######################################################################################################
# Close this overview page:
#######################################################################################################
echo '
				</div>
				<!-- /.row -->
			</div>
			<!-- container-fluid -->
		</section>
		<!-- content -->';
site_footer();