<?php
site_menu();
$cur_ver = 'v' . date('Y.md.Hi', @filemtime('/opt/bpi-r2-router-builder/.git/refs/heads/master'));
echo '
			<div class="container-fluid">
				<div class="row">
					<div class="col-md-6">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title">Web UI</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body table-responsive p-0">
								<table class="table">
									<tr>
										<td width="50%"><strong>Current Version</strong></td>
										<td><span id="current_ver">', $cur_ver, '</span></td>
									</tr>
									<tr>
										<td><strong>Latest Version</strong></td>
										<td><span id="latest_ver">n/a</span></td>
									</tr>
									<tr id="check_div">
										<td colspan="2">
											<button type="button" class="btn btn-block btn-outline-info center_50" id="check_button">Check for Update</button>
										</td>
									</tr>
									<tr class="hidden" id="pull_div">
										<td colspan="2">
											<button type="button" class="btn btn-block btn-outline-primary center_50" id="pull_button">Update Web UI</button>
										</td>
									</tr>
								</table>
							</div>
							<!-- /.card-body -->
						</div>
						<!-- /.card -->
					</div>
					<!-- /.col -->
				</div>
			</div>';
site_footer('
	WebUI_Check();
	$("#check_button").click(WebUI_Check);
	$("#pull_button").click(WebUI_Pull);
');