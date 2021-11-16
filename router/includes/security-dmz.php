<?php
require_once("subs/security.php");
$options = parse_file();
site_menu();
$src_type = isset($config['dmz_src_type']) ? $config['dmz_src_type'] : 'any';
$dest_type = isset($config['dmz_dest_type']) ? $config['dmz_dest_type'] : 'addr';
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">DMZ Setup</h3>
	</div>
	<div class="card-body" id="dmz_div">
		', checkbox("enable_dmz", "DMZ Default Server", false), '
		<hr />
		<table class="table table-sm', !empty($options['enable_dmz']) && $options['enable_dmz'] == "Y" ? '' : ' hidden', '" id="dmz_info">
			<tr>
				<td width="33%">Source IP Address:</td>
				<td>
					<table>
						<tr>
							<td width="150px;"><input type="radio" value="any" id="src_any" name="src_type"', $src_type == 'any' ? ' checked="checked"' : '', '> <label for="src_any">Any IP Address</label></td>
							<td></td>
						</tr>
						<tr>
							<td><input type="radio" value="range" id="src_range" name="src_type"> <label for="src_spec"', $src_type == 'range' ? ' checked="checked"' : '', '>IP Range</label</td>
							<td>		
								<label for="src_specific"><input id="range_from" type="text"', isset($config['dmz_range_from']) ? ' value="' . $config['dmz_range_from'] . '"' : '', $src_type == "range" ? '' : 'disabled="disabled"', ' class="ip_address form-control" data-inputmask="\'alias\': \'ip\'" data-mask></label> to
								<label for="src_specific"><input id="range_to" type="text"', isset($config['dmz_range_to']) ? ' value="' . $config['dmz_range_to'] . '"' : '', $src_type == "range" ? '' : 'disabled="disabled"', ' class="form-control" maxlength="3" style="width: 50px" /></label>
							</td>
						</tr>
						<tr>
							<td><input type="radio" value="mask" id="src_mask" name="src_type"', $src_type == 'mask' ? ' checked="checked"' : '', '> <label for="src_spec">IP Mask</label</td>
							<td>		
								<label for="src_mask"><input id="mask_ip" type="text"', isset($config['dmz_mask_ip']) ? ' value="' . $config['dmz_mask_ip'] . '"' : '', $src_type == "mask" ? '' : 'disabled="disabled"', ' class="ip_address form-control" data-inputmask="\'alias\': \'ip\'" data-mask></label>/<label for="src_mask"><input type="text"', $src_type == "mask" ? '' : 'disabled="disabled"', ' id="mask_bits" maxlength="2" style="max-width: 50px" class="form-control" /></label>
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td>Destination:</td>
				<td>
					<table>
						<tr>
							<td width="150px;"><input type="radio" value="addr" id="dest_ip" name="dest_type"', $dest_type == 'addr' ? ' checked="checked"' : '', '> <label for="dest_ip">IP Address</label></td>
							<td><input id="ip_addr" type="text" class="ip_address form-control" data-inputmask="\'alias\': \'ip\'" data-mask', $dest_type == 'addr' ? '' : ' disabled="disabled"', '></td>
						</tr>
						<tr>
							<td><input type="radio" value="mac" id="dest_mac" name="dest_type"', $dest_type == 'mac' ? ' checked="checked"' : '', '> <label for="dest_mac">MAC Address</label></td>
							<td><input id="mac_addr" type="text" class="mac_address form-control"', $dest_type == 'mac' ? '' : ' disabled="disabled"', '></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<hr />
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
security_apply_changes();
site_footer('Init_DMZ();');
