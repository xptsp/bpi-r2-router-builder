<?php
###########################################################################################
# Supporting Functions:
###########################################################################################
function timezone_list()
{
    $timezones = [];
    $offsets = [];
    $now = new DateTime('now', new DateTimeZone('UTC'));

    foreach (DateTimeZone::listIdentifiers() as $timezone) {
        $now->setTimezone(new DateTimeZone($timezone));
        $offsets[] = $offset = $now->getOffset();
        $timezones[$timezone] = '(' . format_GMT_offset($offset) . ') ' . format_timezone_name($timezone);
    }
    array_multisort($offsets, $timezones);
    return $timezones;
}

function format_GMT_offset($offset)
{
    $hours = intval($offset / 3600);
    $minutes = abs(intval($offset % 3600 / 60));
    return 'GMT' . ($offset ? sprintf('%+03d:%02d', $hours, $minutes) : '');
}

function format_timezone_name($name)
{
    $name = str_replace('/', ', ', $name);
    $name = str_replace('_', ' ', $name);
    $name = str_replace('St ', 'St. ', $name);
    return $name;
}

###########################################################################################
# Main code for this page:
###########################################################################################
site_menu();
$current = date_default_timezone_get();
#echo '<pre>'; print_r($current); exit;
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">System Settings</h3>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-6">
				<p><label for="hostname">Host Name</label></td></p>
			</div>
			<div class="col-6">
				<div class="input-group">
					<div class="input-group-prepend">
						<span class="input-group-text"><i class="fas fa-laptop-code"></i></span>
					</div>
					<input id="hostname" type="text" class="hostname form-control" value="', @file_get_contents('/etc/hostname'), '" data-inputmask-regex="([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)">
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-6">
				<label for="hostname">System Time Zone</label></td>
			</div>
			<div class="col-6 input-group">
				<select class="form-control" id="timezone">';
foreach (timezone_list() as $id => $text)
	echo '
					<option value="', trim($id), '"', $id == $current ? ' selected="selected"' : '', '>', $text, '</option>';
echo '
				</select>
				<span class="input-group-append">
					<button type="button" class="btn btn-info btn-flat" id="tz_detect">Detect</button>
				</span>
			</div>
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
site_footer('Init_System();');
