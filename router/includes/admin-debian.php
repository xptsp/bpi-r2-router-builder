<?php
site_menu();

if (isset($_SESSION['debian']['refreshed']) && $_SESSION['debian']['refreshed'] + 600 > time())
	unset($_SESSION['debian']);

####################################################################################################
# Output the Debian Updates page:
####################################################################################################
$installable = isset($_SESSION['debian']['count'][1]) ? $_SESSION['debian']['count'][1] : 0;
$updates = isset($_SESSION['debian']['count'][1]) ? $_SESSION['debian']['count'][1] + $_SESSION['debian']['count'][2] + $_SESSION['debian']['count'][3] + $_SESSION['debian']['count'][4] : '';
$hidden = isset($_SESSION['debian']['count']) ? '' : ' hidden';
echo '
<div class="row">
	<div class="col-md-12">
		<div class="card card-primary">
			<div class="card-header">
				<h3 class="card-title"><i class="fab fa-linux"></i> Debian Updates</h3>
			</div>
			<!-- /.card-header -->
			<div class="card-body table-responsive" id="debian-div">
				<div class="callout callout-info', $hidden, '" id="updates-div">
					<a href="javascript:void(0);"><button type="button" class="btn btn-primary float-right apt_pull" style="margin-right: 10px;" data-toggle="modal" data-target="#output-modal">Update Packages</button></a>
					<h5><i class="icon fas fa-info-circle"></i> <span id="updates-available">', $updates, '</span> Updates Available, <span id="updates-installable">', $installable, '</span> Installable</h5>
					APT test reported &quot;<span id="updates-msg">', isset($_SESSION['debian']['count'][0]) ? $_SESSION['debian']['count'][0] : '', '</span>&quot;
				</div>
				<h2 class="card-title"><strong>Available Updates: </strong></h2>
				<table class="table table-striped table-bordered table-sm">
					<thead>
						<tr>
							<th width="2%"><input type="checkbox"', $installable ? ' checked="checked"' : '', '></th>
							<th width="30%">Package Name</th>
							<th width="25%">New Version</th>
							<th width="25%">Old Version</th>
							<th width="10%">Status</th>
						</tr>
					</thead>
					<tbody id="packages_div">';
if (isset($_SESSION['debian']['list']))
	echo $_SESSION['debian']['list'];
else
	echo '
						<tr>
							<td colspan="5"><center>Press <strong>Check for Updates</strong> to Update</center></td>
						</tr>';
echo '
					</tbody>
				</table>
			</div>
			<div class="card-footer">
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary float-right" id="apt_check">Check for Updates</button></a>
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary float-right apt_pull', $hidden, '" style="margin-right: 10px;" data-toggle="modal" data-target="#output-modal">Update Packages</button></a>
			</div>
		</div>
	</div>';

####################################################################################################
# Define the command-output modal:
####################################################################################################
echo '
<div class="modal fade" id="output-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title"><i class="fab fa-linux"></i> Package Installation Progress</h3>
			</div>
			<div class="modal-body">
				<textarea id="output_div" class="form-control" rows="15" readonly="readonly" style="overflow-y: scroll;"></textarea>
			</div>
			<div class="modal-footer justify-content-between">
				<button type="button" id="modal-close" class="btn btn-primary disabled float-right">Close</button>
			</div>
		</div>
		<!-- /.modal-content -->
		</div>
	<!-- /.modal-dialog -->
	</div>
</div>';

####################################################################################################
# Close the page:
####################################################################################################
site_footer('Init_Debian();');
