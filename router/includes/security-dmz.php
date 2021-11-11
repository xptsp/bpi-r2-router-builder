<?php
require_once("subs/security.php");
$options = parse_file();
site_menu();
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">DMZ Setup</h3>
	</div>
	<div class="card-body">
		', checkbox("enable_dmz", "Enable DMZ Default Server"), '
		<hr />
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-danger center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
site_footer('Init_DMZ();');
