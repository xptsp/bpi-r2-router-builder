var parent;

//======================================================================================================
// Javascript functions for "Advanced / Firewall Settings"
//======================================================================================================
function Init_Firewall()
{
	$(".checkbox").bootstrapSwitch();
	$("#drop_port_scan").on('switchChange.bootstrapSwitch', function(event, state) {
		if (state == true)
			$("#port_scan_options").slideDown(400);
		else
			$("#port_scan_options").slideUp(400);
	});

	// Handler to submit form settings:
	$("#apply_changes").click(function() {
		postdata = {
			'sid':             SID,
			'action':          'submit',
			'disable_ddos':    $("#disable_ddos").prop("checked") ? "Y" : "N",
			'allow_ping':      $("#allow_ping").prop("checked") ? "Y" : "N",
			'allow_ident':     $("#allow_ident").prop("checked") ? "Y" : "N",
			'allow_multicast': $("#allow_multicast").prop("checked") ? "Y" : "N",
			'redirect_dns':    $("#redirect_dns").prop("checked") ? "Y" : "N",
			'allow_dot':       $("#allow_dot").prop("checked") ? "Y" : "N",
		};
		//alert(JSON.stringify(postdata, null, 5)); return;
		WebUI_Post("/advanced/firewall", postdata);
	});
}

//======================================================================================================
// Javascript functions for "Advanced / DMZ Settings"
//======================================================================================================
function Init_DMZ()
{
	$("#enable_dmz").bootstrapSwitch().on('switchChange.bootstrapSwitch', function(event, state) {
		if (state == true)
			$("#dmz_info").slideDown(400);
		else
			$("#dmz_info").slideUp(400);
	});
	$("#src_any").click(function() {
		$("#range_from").attr("disabled", "disabled");
		$("#range_to").attr("disabled", "disabled");
		$("#mask_ip").attr("disabled", "disabled");
		$("#mask_bits").attr("disabled", "disabled");
	});
	$("#src_range").click(function() {
		$("#range_from").removeAttr("disabled");
		$("#range_to").removeAttr("disabled");
		$("#mask_ip").attr("disabled", "disabled");
		$("#mask_bits").attr("disabled", "disabled");
	});
	$("#src_mask").click(function() {
		$("#range_from").attr("disabled", "disabled");
		$("#range_to").attr("disabled", "disabled");
		$("#mask_ip").removeAttr("disabled");
		$("#mask_bits").removeAttr("disabled");
	});
	$("#range_from").change(function() {
		$("#range_to").val( $("#range_from").val().substring( $("#range_from").val().lastIndexOf('.') + 1) );
	});
	$("#range_to").inputmask('integer', {min:0, max:254});
	$("#mask_bits").inputmask('integer', {min:0, max: 32});
	$("#dest_ip").click(function() {
		$("#ip_addr").removeAttr("disabled");
		$("#mac_addr").attr("disabled", "disabled");
	});
	$("#dest_mac").click(function() {
		$("#ip_addr").attr("disabled", "disabled");
		$("#mac_addr").removeAttr("disabled");
	});
	$("#mac_addr").inputmask('mac');

	// Handler to submit form settings:
	$("#apply_changes").click(function() {
		postdata = {
			'sid':        SID,
			'action':     'submit',
			'enable_dmz': $("#enable_dmz").prop("checked") ? "Y" : "N",
			'src_type':   $("[name=src_type]:checked").val(),
			'range_from': $("#range_from").val(),
			'range_to':   $("#range_to").val(),
			'mask_ip':    $("#mask_ip").val(),
			'mask_bits':  $("#mask_bits").val(),
			'dest_type':   $("[name=dest_type]:checked").val(),
			'dest_ip':    $("#ip_addr").val(),
			'dest_mac':   $("#mac_addr").val(),
		};
		//alert(JSON.stringify(postdata, null, 5)); return;
		WebUI_Post("/advanced/dmz", postdata);
	});
}

//======================================================================================================
// Javascript functions for "Advanced / UPnP Setup":
//======================================================================================================
function Init_PortForward(ip)
{
	$("#forward_refresh").click(function() {
		$.post('/advanced/forward', __postdata("list"), function(data) {
			if (data == "RELOAD")
				document.location.reload(true);
			$("#forward_table").html(data);
			$(".fa-trash-alt").click(function() {
				PortForward_Delete( $(this).parent().parent().parent().parent() );
			});
			$(".fa-pencil-alt").click(function() {
				// Populate the port forward modal with the settings from the selected line:
				parent = $(this).parent().parent().parent().parent();
				$('#app_select').val(",,tcp").change();
				$("#iface").val( parent.find(".iface").text() );
				parts = parent.find(".ext_port").text().split(":");
				$("#ext_min").val( parts[0] );
				$("#ext_max").val( parts.length == 1 ? parts[0] : parts[1] );
				$("#ip_addr").val( parent.find(".ip_addr").text() );
				$("#int_port").val( parent.find(".int_port").text() );
				$("#protocol").val( parent.find(".proto").text() );
				$("#comment").val( parent.find(".comment").text() );
				$("#enabled").prop("checked", parent.find(".enabled").text() == "Y" );

				// Now that the fields are set, show the modal to the user:
				$("#forward-modal").modal("show");

			});
		});
	}).click();
	$("#add_forward").click(function() {
		parent = null;
		$('#app_select').val(",,tcp").change();
		$("#enabled").prop("checked", true);
		$("#ip_addr").val(ip);
		$("#comment").val("");
	});
	$("#app_select").change(function() {
		val = $("#app_select").val();
		arr = val.split(',');
		$("#protcol").val(arr[2]);
		$("#comment").val(arr[0]);
		$("#ext_min").val(arr[1]);
		$("#ext_max").val(arr[1]);
		$("#int_port").val(arr[1]);
		if (arr[0] != "") {
			$("#ext_min").attr("disabled", "disabled");
			$("#ext_max").attr("disabled", "disabled");
			$("#int_port").attr("disabled", "disabled");
			$("#protcol").attr("disabled", "disabled");
		} else {
			$("#ext_min").removeAttr("disabled");
			$("#ext_max").removeAttr("disabled");
			$("#int_port").removeAttr("disabled");
			$("#protcol").removeAttr("disabled");
		}
	});
	$(".port_number").inputmask("integer", {min:0, max:65535});
	$("#ext_min").change(function() {
		val = $(this).val();
		$("#ext_max").val( val ).change();
		$("#int_port").val( val ).change();
	});
	$("#ext_max").change(function() {
		min = $("#ext_min").val();
		max = $(this).val();
		if (min != max)
			$("#int_port").val(min).attr("disabled", "disabled");
		else
			$("#int_port").removeAttr("disabled");
	});
	$("#int_port").change(function() {
		src = $("#ext_min").val();
		dst = $("#int_port").val();
		if (src != dst)
			$("#ext_max").val(min).attr("disabled", "disabled");
		else
			$("#ext_max").removeAttr("disabled");
	});
	$('#ip_addr').inputmask("ip");
	$("#submit_forward").click(function() {
		// If "parent" is not null, then delete the port forwarding rule that we are editing:
		if (parent != null)
			PortForward_Delete( parent );

		// Assemble the post data for the AJAX call:
		postdata = {
			'sid':      SID,
			'action':   'add',
			'iface':    $("#iface").val(),
			'ext_min':  $("#ext_min").val(),
			'ext_max':  $("#ext_max").val(),
			'ip_addr':  $("#ip_addr").val(),
			'int_port': $("#int_port").val(),
			'protocol': $("#protocol").val(),
			'comment':  $("#comment").val(),
			'enabled':  $("#enabled").prop("checked") ? "Y" : "N",
		};
		//alert(JSON.stringify(postdata, null, 5)); return;

		// Perform our AJAX request to change the WAN settings:
		WebUI_Post("/advanced/forward", postdata, null, false, function() {
			$("#apply-modal").modal("hide");
			$("#forward-modal").modal("hide");
			$("#forward_refresh").click();
		});
	});
}

function PortForward_Delete(line)
{
	// Handler to submit form settings:
	postdata = {
		'sid':      SID,
		'action':   'del',
		'iface':    line.find(".iface").text(),
		'protocol': line.find(".proto").text(),
		'ext_min':  line.find(".ext_port").text(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;
	WebUI_Post("/advanced/forward", postdata, null, false, function() {
		$("#forward_refresh").click();
		$("#apply-modal").modal("hide");
	});			
}

//======================================================================================================
// Javascript functions for "Advanced / Mosquitto Settings"
//======================================================================================================
function Init_Notify()
{
	$(".checkbox").bootstrapSwitch();
	$("#enable_mosquitto").bootstrapSwitch().on('switchChange.bootstrapSwitch', function(event, state) {
		if (state == true)
			$("#mosquitto_options").slideDown(400);
		else
			$("#mosquitto_options").slideUp(400);
	});
	$('.ip_address').inputmask("ip");
	$(".ip_port").inputmask("integer", {min:0, max:65535});

	// Handler to submit form settings:
	$("#apply_changes").click(function() {
		postdata = {
			'sid':        SID,
			'action':     'submit',
			'enabled':    $("#enable_mosquitto").prop("checked") ? "Y" : "N",
			'ip_addr':    $("#ip_addr").val(),
			'ip_port':    $("#ip_port").val(),
			'username':   $("#username").val(),
			'password':   $("#password").val(),
			'send_on':    $("#send_on").val().join(","),
		};
		//alert(JSON.stringify(postdata, null, 5)); return;
		WebUI_Post("/advanced/notify", postdata);
	});
}

//======================================================================================================
// Javascript functions for "Advanced / Network Routing"
//======================================================================================================
function Init_Routing()
{
	$('.ip_address').inputmask("ip");
	$("#dest_addr").focus();

	// HAndler to refresh the network routing table:
	$("#routing-refresh").click(function() {
		Add_Overlay("routing-div");
		$.post("/setup/routing", __postdata("show"), function(data) {
			Del_Overlay("routing-div");
			$("#routing-table").html(data);
			$(".fa-trash-alt").click(Routing_Delete);
		}).fail(function() {
			$("#routing-table").html('<td colspan="6"><center>AJAX call failed!</center></td>');
		});
	}).click();

	// Handler to add network routing to system:
	$("#add_route").click(function() {
		postdata = {
			'sid':       SID,
			'action':    'add',
			'dest_addr': $("#dest_addr").val(),
			'mask_addr': $("#mask_addr").val(),
			'gate_addr': $("#gate_addr").val(),
			'metric':    $("#metric").val(),
			'iface':     $("#iface").val(),
		};
		//alert(JSON.stringify(postdata, null, 5)); return;
		WebUI_Post("/advanced/routing", postdata);
	});
}

function Routing_Delete()
{
	line = $(this).parent().parent().parent().parent();
	postdata = {
		'sid':       SID,
		'action':    'delete',
		'dest_addr': line.find(".dest_addr").html(),
		'mask_addr': line.find(".mask_addr").html(),
		'gate_addr': line.find(".gate_addr").html(),
		'metric':    line.find(".metric").html(),
		'iface':     line.find(".iface").html(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;
	WebUI_Post("/advanced/routing", postdata);
}
