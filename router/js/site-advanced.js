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
			'allow_doq':       $("#allow_doq").prop("checked") ? "Y" : "N",
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
// Javascript functions for "Advanced / DHCP Reservations":
//======================================================================================================
function Init_DHCP(iface)
{
	iface_used = iface;

	//=========================================================================
	// IP Reservation modals and handlers:
	$("#reservations-refresh").click(DHCP_Refresh_Reservations).click();
	$("#dhcp_mac_addr").inputmask("mac");
	$("#reservation_remove").click(function() {
		$("#dhcp_client_name").val("");
		$("#dhcp_ip_addr").val("");
		$("#dhcp_mac_addr").val("");
	});
	$("#add_reservation").click(function() {
		$("#dhcp_error_box").addClass("hidden");
		$("#reservation-modal").modal("show");
		$("#reservation_remove").click();
		DHCP_Refresh_Leases();
	});
	$("#leases_refresh").click(DHCP_Refresh_Leases);
	$("#dhcp_add").click(DHCP_Reservation_Add);
	$("#dhcp_error_close").click(function() {
		$("#dhcp_error_box").addClass("hidden");
	});
	$("#confirm-proceed").click(DHCP_Reservation_Confirmed);
	$("#reboot_yes").click(Reboot_Confirmed);
}

function DHCP_Refresh_Leases()
{
	// Perform our AJAX request to refresh the LAN leases:
	$("#clients-table").html('<tr><td colspan="5"><center>Loading...</center></td></tr>');
	$.post('/advanced/dhcp', __postdata("clients", iface_used), function(data) {
		if (data == "RELOAD")
			document.location.reload(true);
		$("#clients-table").html(data);
		$(".reservation-option").click(function() {
			line = $(this);
			$("#dhcp_client_name").val( line.find(".dhcp_host").html() );
			$("#dhcp_ip_addr").val( line.find(".dhcp_ip_addr").html() );
			$("#dhcp_mac_addr").val( line.find(".dhcp_mac_addr").html() );
		});
	}).fail(function() {
		DHCP_Error("AJAX call failed!");
	});
}

function DHCP_Refresh_Reservations()
{
	// Perform our AJAX request to refresh the reservations:
	$("#reservations-table").html('<tr><td colspan="5"><center>Loading...</center></td></tr>');
	$.post('/advanced/dhcp', __postdata("reservations", iface_used), function(data) {
		$("#reservation-modal").modal("hide");
		$("#reservations-table").html(data);
		$(".dhcp_edit").click(function() {
			$("#add_reservation").click();
			line = $(this).parent();
			$("#dhcp_client_name").val( line.find(".dhcp_host").html() );
			$("#dhcp_ip_addr").val( line.find(".dhcp_ip_addr").html() );
			$("#dhcp_mac_addr").val( line.find(".dhcp_mac_addr").html() );
		});
		$(".dhcp_delete").click(DHCP_Reservation_Remove);
	});
}

function DHCP_Reservation_Remove()
{
	// Assemble the post data for the AJAX call:
	line = $(this).parent();
	postdata = {
		'sid':      SID,
		'action':   'remove',
		'misc':     iface_used,
		'hostname': line.find(".dhcp_host").html(),
		'ip_addr':  line.find(".dhcp_ip_addr").html(),
		'mac_addr': line.find(".dhcp_mac_addr").html(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to remove the IP reservation:
	$.post('/advanced/dhcp', postdata, function(data) {
		if (data.trim() == "OK")
		{
			DHCP_Refresh_Reservations();
			reboot_suggested = true;
		}
		else
			DHCP_Error(data);
	}).fail(function() {
		DHCP_Error("AJAX call failed!");
	});
}

function DHCP_Reservation_Add()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':      SID,
		'action':   'check',
		'misc':     iface_used,
		'hostname': $("#dhcp_client_name").val(),
		'ip_addr':  $("#dhcp_ip_addr").val(),
		'mac_addr': $("#dhcp_mac_addr").val(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Check to make sure we actually have something to pass to the AJAX call:
	if (postdata.hostname == "")
		return DHCP_Error("No hostname specified!");
	else if (postdata.ip_addr == "")
		return DHCP_Error("No IP address specified!");
	else if (postdata.mac_addr == "")
		return DHCP_Error("No MAC address specified!");

	// Perform our AJAX request to add the IP reservation:
	$.post('/advanced/dhcp', postdata, function(data) {
		if (data.trim() == "SAME")
			DHCP_Refresh_Reservations();
		else if (data.trim() == "OK")
			DHCP_Reservation_Add_Msg();
		else if (data.trim() == "ADD")
			DHCP_Reservation_Confirmed();
		else
		{
			$("#confirm-mac").html('<p>' + data + '</p><p>Proceed with replacement?</p>');
			$("#confirm-modal").modal("show");
		}
	}).fail(function() {
		DHCP_Error("AJAX call failed!");
	});
}

function DHCP_Reservation_Add_Msg()
{
	$("#apply_changes").addClass("hidden");
	$("#apply_reboot").removeClass("hidden");
	$("#alert-div").slideDown(400, function() {
		timer = setInterval(function() {
			$("#alert-div").slideUp();
			clearInterval(timer);
		}, 5000);
	});
	DHCP_Reservation_Confirmed();
}

function DHCP_Reservation_Confirmed()
{
	// Hide confirmation dialog if shown:
	$("#confirm-modal").modal("hide");

	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':      SID,
		'action':   'add',
		'misc':     iface_used,
		'hostname': $("#dhcp_client_name").val(),
		'ip_addr':  $("#dhcp_ip_addr").val(),
		'mac_addr': $("#dhcp_mac_addr").val(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to add the IP reservation:
	$.post('/advanced/dhcp', postdata, function(data) {
		if (data.trim() == "OK")
			DHCP_Refresh_Reservations();
		else
			DHCP_Error(data);
	}).fail(function() {
		DHCP_Error("AJAX call failed!");
	});
}

function DHCP_Error(msg)
{
	$("#dhcp_error_msg").html(msg);
	$("#dhcp_error_box").slideDown(400, function() {
		timer = setInterval(function() {
			$("#dhcp_error_box").slideUp();
			clearInterval(timer);
		}, 5000);
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
