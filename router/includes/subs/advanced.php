<?php
$options_changed = false;

function parse_file()
{
	$file = '/etc/default/firewall';
	$options = array();
	foreach (explode("\n", trim(@file_get_contents($file))) as $line)
	{
		$parts = explode("=", $line . '=');
		if (!empty($parts[0]))
			$options[$parts[0]] = $parts[1];
	}
	return $options;
}

function apply_file()
{
	global $options, $options_changed;
	#if (!$options_changed)
	#	return;
	$text = '';
	foreach ($options as $name => $setting)
		$text .= (!empty($setting) ? $name . '=' . $setting . "\n" : $name);
	#echo '<pre>'; echo $text; exit;
	$handle = fopen("/tmp/firewall", "w");
	fwrite($handle, $text);
	fclose($handle);
	$options['use_isp'] = isset($options['use_isp']) ? $options['use_isp'] : 'N';
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dns " . ($options['use_isp'] == 'Y' ? 'use_isp' : $options['dns1'] . ' ' . $options['dns2']));
	return @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh firewall reload");
}

function checkbox($name, $description, $default = true, $disabled_by = '')
{
	global $options;
	$checked = (!isset($options[$name]) ? $default : ($options[$name] == "Y"));
	$enabled = (!empty($disabled_by) ? $options[$disabled_by] : true);
	return '<p><input type="checkbox" id="' . $name . '" class="checkbox"' . ($checked ? ' checked="checked"' : '') . ' data-bootstrap-switch="" data-off-color="danger" data-on-color="success" ' . ($enabled ? '' : ' disabled="disabled"') . '> <strong id="' . $name . '_txt" ' . ($enabled ? '' : ' disabled="disabled"') . '>' . $description . '</strong></p>';
}

function security_apply_changes()
{
	echo '
<div class="modal fade" id="apply-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header bg-primary">
				<h4 class="modal-title">Applying Changes</h4>
				<a href="javascript:void(0);"><button type="button hidden alert_control" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button></a>
			</div>
			<div class="modal-body">
				<p id="apply_msg">Please wait while the firewall service is restarted....</p>
			</div>
			<div class="modal-footer justify-content-between alert_control">
				<a href="javascript:void(0);"><button type="button" class="btn btn-primary" data-dismiss="modal">Close</button></a>
			</div>
		</div>
	</div>
</div>';
}
