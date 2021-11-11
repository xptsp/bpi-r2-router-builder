<?php
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

function option($name, $allowed = "/^[Y|N]$/")
{
	global $options;
	if (!isset($_POST[$name]) || (!empty($allowed) && !preg_match($allowed, $_POST[$name])))
		die('ERROR: Missing or invalid value for option "' . $name . '"!');
	return $_POST[$name];
}

function apply_file()
{
	global $options;
	$text = '';
	foreach ($options as $name => $setting)
		$text .= (!empty($setting) ? $name . '=' . $setting . "\n" : '');
	#echo '<pre>'; echo $text; exit;
	$handle = fopen("/tmp/firewall", "w");
	fwrite($handle, $text);
	fclose($handle);
	echo @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh firewall reload");
}

function checkbox($name, $description, $default = true, $disabled_by = '')
{
	global $options;
	$options[$name] = $checked = (!isset($options[$name]) ? $default : ($options[$name] == "Y"));
	$enabled = (!empty($disabled_by) ? $options[$disabled_by] : true);
	return '<p><input type="checkbox" id="' . $name . '" class="checkbox"' . ($checked ? ' checked="checked"' : '') . ' data-bootstrap-switch="" data-off-color="danger" data-on-color="success" ' . ($enabled ? '' : ' disabled="disabled"') . '> <strong id="' . $name . '_txt" ' . ($enabled ? '' : ' disabled="disabled"') . '>' . $description . '</strong></p>';
}

