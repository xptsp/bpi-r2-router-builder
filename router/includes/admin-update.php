<?php
site_menu();

################################################################################################
# Display Web UI current version and latest version:
################################################################################################
echo '
			<div class="container-fluid">
				<div class="row">
					<div class="col-md-6">
						<div class="card">
							<div class="card-header">
								<h3 class="card-title">Web UI</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body table-responsive p-0" id="webui-div">
								<table class="table">
									<tr>
										<td width="50%"><strong>Current Version</strong></td>
										<td><span id="current_ver">v', date('Y.md.Hi', @filemtime('/opt/bpi-r2-router-builder/.git/refs/heads/master')), '</span></td>
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
								<h3 class="card-title">Debian Packages</h3>
							</div>
							<!-- /.card-header -->
							<div class="card-body table-responsive p-0" id="debian_div">
								<table class="table">
									<tr>
										<td width="50%"><strong>Packages to Update</strong></td>
										<td><span id="updates_avail">Unknown</span></td>
									</tr>
									<tr>
										<td><strong>&nbsp;</strong></td>
										<td>&nbsp;</td>
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
					<!-- /.col -->
				</div>
			</div>';
site_footer('
	WebUI_Check();
	$("#webui_check").click(WebUI_Check);
	$("#webui_pull").click(WebUI_Pull);
	$("#apt_check").click(Debian_Check);
');