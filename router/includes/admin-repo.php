<?php
site_menu();

function show_repo($title, $repo, $url, $alt_desc = null)
{
	echo '
		<div class="col-md-6">
			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title"><i class="fab fa-github"></i> ', $title, '</h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive p-0" id="', $repo, '_div">
					<table class="table">
						<tr>
							<td width="50%"><strong>Current Version</strong></td>
							<td>v<span id="', $repo, '_current">', $_SESSION[$repo . '_version'], '</span></td>
						</tr>
						<tr>
							<td><strong>Latest Version</strong></td>
							<td><span id="', $repo, '_latest"><i>Retrieving...</i></span></td>
						</tr>
						<tr>
							<td><strong>Repository Location</strong></td>
							<td><a href="', $url, '" target="_blank">', $alt_desc == null ? $title : $alt_desc, '</a></td>
						</tr>
						<tr id="', $repo, '_check_div">
							<td colspan="2">
								<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-info center_50 check_repo" id="', $repo, '_check">Check for Update</button></a>
							</td>
						</tr>
						<tr class="hidden" id="', $repo, '_pull_div">
							<td colspan="2">
								<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-primary center_50 pull_repo" id="', $repo, '_pull">Pull Updates</button></a>
							</td>
						</tr>
					</table>
				</div>
				<!-- /.card-body -->
			</div>
			<!-- /.card -->
		</div>
		<!-- /.col -->';
}

echo '
<div class="container-fluid">
	<div class="row">';
show_repo('Web UI', 'webui', 'https://github.com/xptsp/bpiwrt-builder', 'BPI-R2 Router Builder');
show_repo('Wifi Regulatory Database', 'regdb', 'https://github.com/sforshee/wireless-regdb');
echo '
	</div>
</div>';
site_footer('Init_Repo();');
