<?php
$called_as_sub = true;
require_once("services.php");

services_start("squid");
services_start("privoxy", false);

echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Service Settings</h3>
	</div>
	<div class="card-body p-0">
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal("Please wait while service changes are pending...", true);
site_footer('Init_Filters();');
