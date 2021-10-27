<?php
site_menu();
echo '
<div class="container-fluid">
	<div class="row">
		<div class="col-md-12">
			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title"><i class="fab fa-linux"></i> Debian Updates</h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive" id="debian-div">
					<h2 class="card-title"><strong>Available Updates</strong></h2>
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
					<a href="javascript:void(0);"><button type="button" class="btn btn-primary float-right hidden" id="apt_pull">Update Packages</button></a>
					<a href="javascript:void(0);"><button type="button" class="btn btn-primary float-right" id="apt_check">Check for Updates</button></a>
				</div>
			</div>
		</div>
	</div>
</div>';
site_footer('Init_Debian();');
