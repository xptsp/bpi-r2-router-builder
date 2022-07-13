<?php
#################################################################################################
# Internal functions:
#################################################################################################
function deduplicate_proto(&$arr, $ext_port)
{
	if (isset($arr['TCP'][$ext_port], $arr['UDP'][$ext_port]) && $arr['TCP'][$ext_port]['enabled'] == $arr['UDP'][$ext_port]['enabled'])
	{
		$arr['Both'][$ext_port] = $arr['UDP'][$ext_port];
		unset($arr['UDP'], $arr['TCP']);
	}
}

function output_port($proto, $arr)
{
	return	'<tr>' .
				'<td class="proto"><center>' . $proto . '</center></td>' .
				'<td class="ext_port"><span class="float-right" style="margin-right: 10px">' . $arr['ext_port'] . '</span></td>' .
				'<td class="ip_addr">' . $arr['int_addr'] . '</td>' .
				'<td class="int_port"><span class="float-right" style="margin-right: 10px">' . $arr['int_port'] . '</span></td>' .
				'<td class="comment">' . $arr['comment'] . '</td>' .
				'<td class="enabled"><center>' . $arr['enabled'] . '</center></td>' .
				'<td class="persistent"><center>' . $arr['persistent'] . '</center></td>' .
				'<td><center><a href="javascript:void(0);" title="Edit Rule"><i class="fas fa-pencil-alt"></i></a></center></td>' .
				'<td><center><a href="javascript:void(0);" title="Delete Rule"><i class="fas fa-trash-alt"></i></a></center></td>' .
			'</tr>';
} 

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	#################################################################################################
	# ACTION: LIST => List current ports being forwarded through the router:
	#################################################################################################
	if ($_POST['action'] == 'list')
	{
		$ports = array('FORWARD_PORT' => array('Both' => array(), 'TCP' => array(), 'UDP' => array()));
		$ports['TRIGGER_PORT'] = $ports['FORWARD_RANGE'] = $ports['FORWARD_PORT'];

		#################################################################################################
		# Process the persistent nftables rules at this time:
		#################################################################################################
		foreach (@file("/etc/persistent-nftables.conf") as $line)
		{
			#################################################################################################
			# Process the nftables statement as a line:
			#################################################################################################
			if (preg_match("/(\#\s*|)add element inet firewall (FORWARD_PORT|FORWARD_RANGE|TRIGGER_PORT)_(TCP|UDP) { (\d+\-\d+|\d+)( : (\d+\.\d+\.\d+\.\d+)( \. (\d+)|)|) }( \# (.+)|)/", $line, $regex))
			{
				#echo '<pre>'; print_r($regex); exit;
				$elem     = $regex[2];
				$proto    = $regex[3];
				$ext_port = $regex[4];
				$ports[$elem][$proto][$ext_port] = array(
					'ext_port'   => $ext_port,
					'int_addr'   => $regex[6],
					'int_port'   => empty($regex[8]) ? $port : $regex[8],
					'enabled'    => empty($regex[1]) ? 'Y' : 'N', 
					'comment'    => $regex[10],
					'persistent' => 'Y'
				);
				#echo '<pre>'; print_r($ports[$elem][$proto][$ext_port]); exit;
				deduplicate_proto($ports[$elem], $ext_port);
			}
		}
		#echo '<pre>'; print_r($ports); exit;

		#################################################################################################
		# Output the nftables rules so that the user can understand it:
		#################################################################################################
		$str = array();
		foreach ($ports as $elem)
		{
			foreach ($elem as $proto => $proto_data)
			{
				foreach ($proto_data as $ext_port => $info)
					$str[ strval($ext_port) . '_' . $proto ] = output_port($proto, $info);
			}
		}				 
		ksort($str);
		die( !empty($str) ? implode('', $str) : '<tr><td colspan="9"><center>No Ports Forwarded</center></td></tr>' );
	}
	#################################################################################################
	# ACTION: ADD => Add the new port forwarding rule
	#################################################################################################
	else if ($_POST['action'] == 'add')
	{
		$ext_min  = option_range("ext_min", 0, 65535);
		$ext_max  = option_range("ext_max", (int) $options['ext_min'], 65535);
		$param = array();
		$param['type'] = ($ext_min == $ext_max) ? 'port' : 'range';
		$param['protocol'] = option("protocol", "/^(tcp|udp|both)/");
		$param['ext_port'] = strval($ext_min) . ($ext_min != $ext_max ? '-' . str_val($ext_max) : ''); 
		$param['ip_addr']  = option_ip("ip_addr");
		$param['int_port'] = option_range("int_port", 0, 65535);
		$param['enabled']  = option("enabled");
		$param['comment']  = '"' . option("comment", "/^([^\"\']*)$/") . '"';
		//echo '<pre>'; print_r($param); exit;
		die(@shell_exec("router-helper forward add " . implode(" ", $param)));
	}
	#################################################################################################
	# ACTION: ADD => Delete the specified port forwarding rule
	#################################################################################################
	else if ($_POST['action'] == 'del')
	{
		$param = array();
		$param['protocol'] = option("protocol", "/^(tcp|udp|both)/");
		$param['ext_min']  = option_range("ext_min", 0, 65535);
		//echo '<pre>'; print_r($param); exit;
		die(@shell_exec("router-helper forward del " . implode(" ", $param)));
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# Output the UPnP Settings page:
#################################################################################################
site_menu();
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Port Forwarding</h3>
	</div>
	<div class="card-body">
		<h5>
			<a href="javascript:void(0);"><button type="button" id="forward_refresh" class="btn btn-sm btn-primary float-right">Refresh</button></a>
			Current Port Forwards
		</h5>
		<div class="table-responsive p-0">
			<table class="table table-hover text-nowrap table-sm table-striped table-bordered">
				<thead class="bg-primary">
					<td width="10%"><center>Protocol</center></td>
					<td width="10%"><center>Ext. Port</center></td>
					<td width="10%"><center>IP Address</center></td>
					<td width="10%"><center>Int. Port</center></td>
					<td width="35%">Description</td>
					<td width="10%"><center>Enabled</center></td>
					<td width="10%"><center>Persistent</center></td>
					<td width="3%">&nbsp;</td>
					<td width="3%">&nbsp;</td>
				</thead>
				<tbody id="forward_table">
					<tr><td colspan="9"><center>Loading...</center></td></tr>
				</tbody>
			</table>
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" id="add_forward" class="btn btn-success float-right" data-toggle="modal" data-target="#forward-modal"><i class="fas fa-plus"></i>&nbsp;&nbsp;Add Port Forward</button></a>
	</div>';

###################################################################################################
# Port Forwarding modal:
###################################################################################################
$subnet = trim(@shell_exec("ifconfig br0 | grep 'inet ' | awk '{print $2}'"));
$subnet = substr($subnet, 0, strrpos($subnet, '.') + 1);
echo '
	<div class="modal fade" id="forward-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="add_port">Add A New Port Forwarding</h4>
					<h4 class="modal-title hidden" id="edit_port">Edit Port Forwarding</h4>
					<a href="javascript:void(0);"><button type="button hidden alert_control" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button></a>
				</div>
				<div class="modal-body">
					<div class="row" style="margin-top: 5px">
						<div class="col-sm-6">
							<label for="app_select">Application:</label>
						</div>
						<div class="col-sm-6">
							<select id="app_select" class="form-control">
								<option value=",,tcp" selected="selected">Manual</option>
								<option value="FTP,21,tcp">FTP</option>
								<option value="Telnet,23,tcp">Telnet</option>
								<option value="SMTP,25,tcp">SMTP</option>
								<option value="DNS,53,udp">DNS</option>
								<option value="TFTP,69,udp">TFTP</option>
								<option value="Finger,79,tcp">Finger</option>
								<option value="HTTP,80,tcp">HTTP</option>
								<option value="POP3,110,tcp">POP3</option>
								<option value="NNTP,119,tcp">NNTP</option>
								<option value="SNMP,161,tcp">SNMP</option>
								<option value="HTTPS,443,tcp">HTTPS</option>
								<option value="PPTP,1723,tcp">PPTP</option>
							</select>
						</div>
					</div>
					<div class="row" style="margin-top: 5px">
						<div class="col-sm-6">
							<label for="comment">Application Name:</label>
						</div>
						<div class="col-sm-6">
							<div class="input-group">
								<input id="comment" type="text" class="form-control" value="">
							</div>
						</div>
					</div>
					<hr style="border-width: 2px" />
					<div class="row" style="margin-top: 5px">
						<div class="col-sm-6">
							<label for="ext_min">Min External Port:</label>
						</div>
						<div class="col-3">
							<div class="input-group">
								<div class="input-group-prepend">
									<span class="input-group-text"><i class="fas fa-hashtag"></i></span>
								</div>
								<input id="ext_min" type="text" class="ext-port form-control port_number" value="">
							</div>
						</div>
					</div>
					<div class="row" style="margin-top: 5px">
						<div class="col-sm-6">
							<label for="ext_max">Max External Port:</label>
						</div>
						<div class="col-3">
							<div class="input-group">
								<div class="input-group-prepend">
									<span class="input-group-text"><i class="fas fa-hashtag"></i></span>
								</div>
								<input id="ext_max" type="text" class="ext-port form-control port_number" value="">
							</div>
						</div>
					</div>
					<hr style="border-width: 2px" />
					<div class="row" style="margin-top: 5px">
						<div class="col-sm-6">
							<label for="int_port">Internal Port:</label>
						</div>
						<div class="col-3">
							<div class="input-group">
								<div class="input-group-prepend">
									<span class="input-group-text"><i class="fas fa-hashtag"></i></span>
								</div>
								<input id="int_port" type="text" class="form-control port_number" value="">
							</div>
						</div>
					</div>
					<div class="row" style="margin-top: 5px">
						<div class="col-sm-6">
							<label for="protocol">Protocol:</label>
						</div>
						<div class="col-sm-6">
							<div class="input-group">
								<div class="input-group-prepend">
									<span class="input-group-text"><i class="fas fa-caret-down"></i></span>
								</div>
								<select id="protocol" class="form-control">
									<option value="tcp">TCP</option>
									<option value="udp">UDP</option>
									<option value="both">Both</option>
								</select>
							</div>
						</div>
					</div>
					<div class="row" style="margin-top: 5px">
						<div class="col-sm-6">
							<label for="ip_addr">IP Address:</label>
						</div>
						<div class="col-sm-6">
							<div class="input-group">
								<div class="input-group-prepend">
									<span class="input-group-text"><i class="fas fa-laptop"></i></span>
								</div>
								<input id="ip_addr" type="text" class="ip_address form-control" value="', $subnet, '" data-inputmask="\'alias\': \'ip\'" data-mask>
							</div>
						</div>
					</div>
					<div class="row" style="margin-top: 5px">
						<div class="col-sm-6">
							<label for="int_port">Enabled:</label>
						</div>
						<div class="col-sm-6">
							', checkbox("enabled", ""), '
							<input type="hidden" id="old_iface" value="" />
							<input type="hidden" id="old_proto" value="" />
							<input type="hidden" id="old_ext_port" value="" />
						</div>
					</div>
				</div>
				<div class="modal-footer justify-content-between alert_control">
					<a href="javascript:void(0);"><button type="button" class="btn btn-primary float-right" data-dismiss="modal">Cancel</button></a>
					<a href="javascript:void(0);"><button type="button" id="submit_forward" class="btn btn-success">Submit</button></a>
				</div>
			</div>
		</div>
	</div>';

###################################################################################################
# Close page
###################################################################################################
echo '
	<!-- /.card-body -->
</div>';
apply_changes_modal('Please wait....', true);
site_footer('Init_PortForward("' . $subnet . '");');
