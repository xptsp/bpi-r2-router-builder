<?php
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
$type = strpos($wan['iface'], 'dhcp') > 0 ? 'DHCP' : 'Static IP';
$dhcp = explode(' ', @shell_exec('/usr/local/bin/router-helper dhcp-info'));

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
							<div class="card-body table-responsive p-0">
								<table class="table table-hover text-nowrap">
									<tr>
										<td><strong>Internal IP Address</strong></td>
										<td>', $br0['address'], '</td>
									</tr>
									<tr>
										<td width="50%"><strong>Internal MAC Address</strong></td>
										<td>', explode(' ', trim($br0['hwaddress']))[1], '</td>
									</tr>
									<tr>
										<td><strong>PiHole Adblocking</strong></td>
										<td>', strpos($adblocking, 'enabled') ? 'Enabled' : 'Disabled', '</td>
									</tr>
									<tr>
										<td colspan="2"><strong><i>Operating System Information</i></strong></td>
									</tr>
									<tr>
										<td width="50%"><strong>Hardware Version</strong></td>
										<td>Banana Pi R2</td>
									</tr>
									<tr>
										<td><strong>OS Version</strong></td>
										<td>Debian ', @file_get_contents('/etc/debian_version'), '</td>
									</tr>
									<tr>
										<td><strong>OS Kernel</strong></td>
										<td>', explode(' ', @file_get_contents('/proc/version'))[2], '</td>
									</tr>
									<tr>
										<td><strong>Web UI Version</strong></td>
										<td>v', date('Y.md.Hi', @filemtime('/opt/bpi-r2-router-builder/.git/refs/heads/master')), '</td>
									</tr>
									<tr>
										<td colspan="2" class="centered">
											<button type="button" class="btn btn-block btn-outline-danger center_50" data-toggle="modal" data-target="#reboot-modal">Reboot Router</button>
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
# Reboot Router confirmation modal:
#######################################################################################################
echo '
					<div class="modal fade" id="reboot-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
						<div class="modal-dialog">
							<div class="modal-content">
								<div class="modal-header">
									<h4 class="modal-title">Confirm Reboot Router</h4>
									<button type="button" class="close" data-dismiss="modal" aria-label="Close">
										<span aria-hidden="true">&times;</span>
									</button>
								</div>
								<div class="modal-body">
									<p id="reboot_msg">Rebooting the router will disrupt active traffic on the network.</p>
									<p id="reboot_timer">Are you sure you want to do this?</p>
								</div>
								<div class="modal-footer justify-content-between" id="reboot_control">
									<button type="button" class="btn btn-default" id="reboot_nah" data-dismiss="modal">Not Now</button>
									<button type="button" class="btn btn-primary" id="reboot_yes">Reboot Router</button>
								</div>
							</div>
							<!-- /.modal-content -->
						</div>
						<!-- /.modal-dialog -->
					</div>';
      
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
							<div class="card-body table-responsive p-0">
								<table class="table table-hover text-nowrap">
									<tr>
										<td><strong>External IP Address</strong></td>
										<td>', $wan_if['inet'], '</td>
									</tr>
									<tr>
										<td width="50%"><strong>External MAC Address</strong></td>
										<td>', $wan_if['ether'], '</td>
									</tr>
									<tr>
										<td><strong>External Subnet Mask</strong></td>
										<td>', $wan_if['netmask'], '</td>
									</tr>
									<tr>
										<td><strong>Domain Name Server', isset($dns[1]) ? 's' : '', '</strong></td>
										<td>', $dns[0], isset($dns[1]) ? ', ' . $dns[1] : '', '</td>
									</tr>
									<tr>
										<td><strong>Connection</strong></td>
										<td>', $type, '</td>
									</tr>';
if ($type == 'DHCP')
	echo '
									<tr>
										<td><strong>External DHCP Server</strong></td>
										<td>', $dhcp[0], '</td>
									</tr>
									<tr>
										<td><strong>DHCP Lease Began</strong></td>
										<td>', date('Y-m-d H:i:s', $dhcp[1]), '</td>
									</tr>
									<tr>
										<td><strong>DHCP Lease Expires</strong></td>
										<td>', date('Y-m-d H:i:s', (int)$dhcp[1] + (int)$dhcp[2]), '</td>
									</tr>';
echo '
									<tr>
										<td colspan="2" class="centered">
											<button type="button" class="btn btn-block btn-outline-primary center_50" data-toggle="modal" data-target="#stats-modal" id="stats_button">Show Statistics</button>
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
# Reboot Router confirmation modal:
#######################################################################################################
echo '
					<div class="modal fade" id="stats-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
						<div class="modal-dialog modal-lg">
							<div class="modal-content">
								<div class="modal-header">
									<h4 class="modal-title">Network Statistics</h4>
								</div>
								<div class="modal-body">
									<p id="stats_body"></p>
								</div>
								<div class="modal-footer justify-content-between">
									<button type="button" class="btn btn-default bg-primary" id="stats_close" data-dismiss="modal">Close</button>
								</div>
							</div>
							<!-- /.modal-content -->
						</div>
						<!-- /.modal-dialog -->
					</div>';
      
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
							<div class="card-body table-responsive p-0">
								<table class="table table-hover text-nowrap">
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
							<div class="card-body table-responsive p-0">
								<table class="table table-hover text-nowrap">
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
							<div class="card-body table-responsive p-0">
								<table class="table table-hover text-nowrap">
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
							<div class="card-body table-responsive p-0">
								<table class="table table-hover text-nowrap">
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

site_footer('
	$("#reboot_yes").click(Confirm_Reboot);
	$("#stats_button").click(Stats_Show);
	$("#stats_close").click(Stats_Close);
');
