<?php
site_menu();

# Determine last time upgrade was run:
$last_update = "Unknown";
$flag = false;
foreach (file("/var/log/apt/history.log") as $line)
{
	if (substr($line, 0, 8) == "Upgrade:")
		$flag = true;
	else if (substr($line, 0, 9) == "End-Date:" && $flag)
	{
		$flag = false;
		$last_update = explode(" ", $line, 2)[1];
	}
}
echo '
			<div class="container-fluid">
				<div class="row">';

################################################################################################
# Display Web UI current version and latest version:
################################################################################################
echo '
					<div class="col-md-6">
						<div class="card card-primary">
							<div class="card-header">
								<h3 class="card-title"><i class="fab fa-github"></i> Web UI</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body table-responsive p-0" id="webui-div">
								<table class="table">
									<tr>
										<td width="50%"><strong>Current Version</strong></td>
										<td>v<span id="webui_current">', $webui_version, '</span></td>
									</tr>
									<tr>
										<td><strong>Latest Version</strong></td>
										<td><span id="webui_latest"><i>Retrieving...</i></span></td>
									</tr>
									<tr id="webui_check_div">
										<td colspan="2">
											<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-info center_50" id="webui_check">Check for Update</button></a>
										</td>
									</tr>
									<tr class="hidden" id="webui_pull_div">
										<td colspan="2">
											<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-primary center_50" id="webui_pull">Update Web UI</button></a>
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
# Display Web UI current version and latest version:
################################################################################################
echo '
					<div class="col-md-6">
						<div class="card card-primary">
							<div class="card-header">
								<h3 class="card-title"><i class="fab fa-github"></i> Regulatory Database</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body table-responsive p-0" id="regdb-div">
								<table class="table">
									<tr>
										<td width="50%"><strong>Current Version</strong></td>
										<td>v<span id="regdb_current">', $_SESSION['regdb_version'], '</span></td>
									</tr>
									<tr>
										<td><strong>Latest Version</strong></td>
										<td><span id="regdb_latest"><i>Retrieving...</i></span></td>
									</tr>
									<tr id="regdb_check_div">
										<td colspan="2">
											<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-info center_50" id="regdb_check">Check for Update</button></a>
										</td>
									</tr>
									<tr class="hidden" id="regdb_pull_div">
										<td colspan="2">
											<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-primary center_50" id="regdb_pull">Update Database</button></a>
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
						<div class="card card-primary">
							<div class="card-header">
								<h3 class="card-title"><i class="fab fa-linux"></i> Debian Packages</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body table-responsive p-0" id="debian-div">
								<table class="table">
									<tr>
										<td><strong>Last Update On:</strong></td>
										<td><span id="last_update">', $last_update, '</span></td>
									</tr>
									<tr>
										<td width="50%"><strong>Available Updates</strong></td>
										<td><span id="updates_avail"><i>Press <strong>Check for Updates</strong></i></span></td>
									</tr>
									<tr id="apt_check_div">
										<td colspan="2">
											<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-info center_50" id="apt_check">Check for Updates</button></a>
										</td>
									</tr>
									<tr class="hidden" id="apt_pull_div">
										<td colspan="2">
											<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-primary center_50" id="apt_pull">Update Packages</button></a>
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
						<div class="card card-primary">
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
site_footer('Init_Updates();');
