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
	<div class="row">
		<div class="col-md-12">
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
site_footer('Init_Debian();');
