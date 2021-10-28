<?php
site_menu();

####################################################################################################
# Output the Debian Updates page:
####################################################################################################
echo '
<div class="row">
	<div class="col-md-12">
		<div class="card card-primary">
			<div class="card-header">
				<h3 class="card-title"><i class="fab fa-linux"></i> Debian Updates</h3>
			</div>
			<!-- /.card-header -->
			<div class="card-body table-responsive" id="debian-div">
				<h2 class="card-title"><strong>Available Updates</strong><span id="updates-available"></span></h2>
				<table class="table table-striped table-bordered table-sm">
					<thead>
						<tr>
							<th width="2%"><input type="checkbox" checked="checked"></th>
							<th width="34%">Package Name</th>
							<th width="32%">New Version</th>
							<th width="32%">Old Version</th>
						</tr>
					</thead>
					<tbody id="packages_div">
						<tr>
							<td colspan="4"><center>Press <strong>Check for Updates</strong> to Update</center></td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="card-footer">
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary float-right hidden" id="apt_pull" data-toggle="modal" data-target="#output-modal">Update Packages</button></a>
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary float-right" id="apt_check">Check for Updates</button></a>
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
			<div class="modal-body">
				<textarea id="output_div" class="form-control" rows="15" readonly="readonly"></textarea>
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
