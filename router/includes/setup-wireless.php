<?php

########################################################################################################
# Determine what wireless interfaces exist on the system, then remove the AP0 interface if client-mode
#  is specified for the R2's onboard wifi:
########################################################################################################
$ifaces = array();
$options = parse_options();
foreach (explode("\n", @trim(@shell_exec("iw dev | grep Interface | awk '{print $2}' | sort"))) as $tface)
{
	$include = $tface != "mt6625_0" && $tface != "ap0";
	$include |= ($tface == 'mt6625_0' && isset($options['ONBOARD_WIFI']) && $options['ONBOARD_WIFI'] == '1');
	$include |= ($tface == 'ap0' && isset($options['ONBOARD_WIFI']) && $options['ONBOARD_WIFI'] == 'A');
	if ($include)
		$ifaces[] = $tface;
}
$iface = isset($_GET['iface']) ? $_GET['iface'] : $ifaces[0];
#echo '<pre>'; print_r($ifaces); exit;
#echo '<pre>'; print_r($options); exit;

########################################################################################################
# Main code for the page:
########################################################################################################
site_menu();
echo '
<div class="card card-primary">
<div class="card card-primary">
    <div class="card-header p-0 pt-1">
		<ul class="nav nav-tabs">';
$init_list = array();
$URL = explode("?", $_SERVER['REQUEST_URI'])[0];
foreach ($ifaces as $tface)
{
	echo '
			<li class="nav-item">
				<a class="ifaces nav-link', $iface == $tface ? ' active' : '', '" href="', $URL, $tface == $ifaces[0] ? '' : '?iface=' . $tface, '">', $tface, '</a>
			</li>';
}
echo '
		</ul>
	</div>
	<div class="card-body">
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
site_footer('Init_Creds();');
