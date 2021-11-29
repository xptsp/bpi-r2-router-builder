<?php
require_once("subs/advanced.php");
$options = parse_file();
site_menu();

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

	#################################################################################################
	# ACTION: DMZ ==> Update the configuration file using the parameters specified:
	#################################################################################################
	if ($_POST['action'] == 'submit')
	{
		$options['enable_dmz'] = option('enable_dmz');
		$options['dmz_src_type'] = option_allowed('src_type', array('any', 'range', 'mask'));
		unset($options['dmz_range_from'], $options['dmz_range_to'], $options['dmz_mask_ip'], $options['dmz_mask_bits']);
		if ($options['dmz_src_type'] == 'range')
		{
			$options['dmz_range_from'] = option_ip('range_from');
			$options['dmz_range_to'] = option_range('range_to', 0, 255);
		}
		else if ($options['dmz_src_type'] == 'mask')
		{
			$options['dmz_mask_ip'] = option_ip('mask_ip');
			$options['dmz_mask_bits'] = option_range('mask_bits', 0, 32);
		}
		$option['dmz_dest_type'] = option_allowed('dest_type', array('addr', 'mac'));
		unset($options['dmz_mac_addr'], $options['dmz_ip_addr']);
		if ($option['dmz_dest_type'] == 'addr')
			$options['dmz_ip_addr'] = option_ip('dest_ip');
		else
			$options['dmz_mac_addr'] = option_mac('dest_mac');
		#echo '<pre>'; print_r($options); exit;
		apply_file();
		die("OK");
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
$src_type = isset($config['dmz_src_type']) ? $config['dmz_src_type'] : 'any';
$dest_type = isset($config['dmz_dest_type']) ? $config['dmz_dest_type'] : 'addr';
$default = trim(@shell_exec("ifconfig br0 | grep 'inet ' | awk '{print $2}'"));
$octet = (int) substr($default, strrpos($default, '.') + 1) + 1;
$default = substr($default, 0, strrpos($default, '.') + 1) . strval($octet);
$dest_ip = trim(@shell_exec("arp -i br0 | grep -v Address | sort | head -1 | awk '{print $1}'"));

#################################################################################################
# Output the DMZ settings page:
#################################################################################################
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
								<label for="src_specific"><input id="range_from" type="text" value="', isset($config['dmz_range_from']) ? $config['dmz_range_from'] : $default, '"', $src_type == "range" ? '' : 'disabled="disabled"', ' class="ip_address form-control" data-inputmask="\'alias\': \'ip\'" data-mask></label> to
								<label for="src_specific"><input id="range_to" type="text" value="', isset($config['dmz_range_to']) ? $config['dmz_range_to'] : $octet, '"', $src_type == "range" ? '' : 'disabled="disabled"', ' class="form-control" maxlength="3" style="width: 50px" /></label>
							</td>
						</tr>
						<tr>
							<td><input type="radio" value="mask" id="src_mask" name="src_type"', $src_type == 'mask' ? ' checked="checked"' : '', '> <label for="src_spec">IP Mask</label</td>
							<td>		
								<label for="src_mask"><input id="mask_ip" type="text" value="', isset($config['dmz_mask_ip']) ? $config['dmz_mask_ip'] : $default, '"', $src_type == "mask" ? '' : 'disabled="disabled"', ' class="ip_address form-control" data-inputmask="\'alias\': \'ip\'" data-mask></label>/<label for="src_mask"><input type="text" value="', !empty($options['dmz_mask_bits']) ? $options['dmz_mask_bits'] : 24, '"', $src_type == "mask" ? '' : 'disabled="disabled"', ' id="mask_bits" maxlength="2" style="max-width: 50px" class="form-control" /></label>
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
							<td><input id="ip_addr" type="text" value="', !empty($options['dmz_ip_addr']) ? $options['dmz_ip_addr'] : $dest_ip, '" class="ip_address form-control" data-inputmask="\'alias\': \'ip\'" data-mask', $dest_type == 'addr' ? '' : ' disabled="disabled"', '></td>
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