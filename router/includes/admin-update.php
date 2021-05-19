<?php
site_menu();

# Determine last time upgrade was run:
$last_update = "Unknown";
$flag = false;
foreach (file("/var/log/apt/history.log") as $line)
{
	if (substr($line, 0, 8) == "Upgrade:")
		$flag = true;
	else if (substr($line, 0, 9) == "End-Date:" and $flag)
	{
		$flag = false;
		$last_update = explode(" ", $line, 2)[1];
	}
}

################################################################################################
# Display Web UI current version and latest version:
################################################################################################
echo '
			<div class="container-fluid">
				<div class="row">
					<div class="col-md-6">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title"><i class="fab fa-github"></i> Web UI</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body table-responsive p-0" id="webui-div">
								<table class="table">
									<tr>
										<td width="50%"><strong>Current Version</strong></td>
										<td>v<span id="current_ver">', date('Y.md.Hi', @filemtime('/opt/bpi-r2-router-builder/.git/refs/heads/master')), '</span></td>
									</tr>
									<tr>
										<td><strong>Latest Version</strong></td>
										<td><span id="latest_ver">&nbsp;</span></td>
									</tr>
									<tr id="webui_check_div">
										<td colspan="2">
											<button type="button" class="btn btn-block btn-outline-info center_50" id="webui_check">Check for Update</button>
										</td>
									</tr>
									<tr class="hidden" id="webui_pull_div">
										<td colspan="2">
											<button type="button" class="btn btn-block btn-outline-primary center_50" id="webui_pull">Update Web UI</button>
										</td>
									</tr>
								</table>
							</div>
							<!-- /.card-body -->
						</div>
						<!-- /.card -->
					</div>
					<!-- /.col -->';

################################################################################################
# Display operating system 
################################################################################################
echo '
					<div class="col-md-6">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title"><i class="fab fa-linux"></i> Debian Packages</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body table-responsive p-0" id="debian_div">
								<table class="table">
									<tr>
										<td><strong>Last Update On:</strong></td>
										<td><span id="last_update">', $last_update, '</span></td>
									</tr>
									<tr>
										<td width="50%"><strong>Available Updates</strong></td>
										<td><span id="updates_avail">Unknown</span></td>
									</tr>
									<tr id="apt_check_div">
										<td colspan="2">
											<button type="button" class="btn btn-block btn-outline-info center_50" id="apt_check">Check for Updates</button>
										</td>
									</tr>
									<tr class="hidden" id="apt_pull_div">
										<td colspan="2">
											<button type="button" class="btn btn-block btn-outline-primary center_50" id="apt_pull">Update Packages</button>
										</td>
									</tr>
								</table>
							</div>
							<!-- /.card-body -->
						</div>
						<!-- /.card -->
					</div>
					<!-- /.col -->';

################################################################################################
# Display program output in a card:
################################################################################################
echo '
					<div class="col-md-12 hidden" id="updates_list">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title"><strong>Available Updates</strong></h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body table-responsive p-0">
								<table class="table">
									<thead class="table-primary">
										<tr>
											<td>Package Name</td>
											<td>New Version</td>
											<td>Old Version</td>
										</tr>
									</thead>
									<tbody id="packages_div">
									</tbody>
								</table>
							</div>
						</div>
					</div>';

################################################################################################
# Close the page:
################################################################################################
echo '
				</div>
			</div>';
site_footer('
	WebUI_Check();
	$("#webui_check").click(WebUI_Check);
	$("#webui_pull").click(WebUI_Pull);
	$("#apt_check").click(Debian_Check);
	$("#apt_pull").click(Debian_Pull);
');