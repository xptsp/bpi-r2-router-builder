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
	$("#apply_changes").click( FireWall_Apply );
}

function FireWall_Apply()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':            SID,
		'action':         'submit',
		'drop_port_scan': $("#drop_port_scan").prop("checked") ? "Y" : "N",
		'log_port_scan':  $("#log_port_scan").prop("checked") ? "Y" : "N",
		'log_udp_flood':  $("#log_udp_flood").prop("checked") ? "Y" : "N",
		'drop_ping':      $("#drop_ping").prop("checked") ? "Y" : "N",
		'drop_ident':     $("#drop_ident").prop("checked") ? "Y" : "N",
		'drop_multicast': $("#drop_multicast").prop("checked") ? "Y" : "N",
		'redirect_dns':   $("#redirect_dns").prop("checked") ? "Y" : "N",
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply_cancel").addClass("hidden");
	$("#apply-modal").modal("show");
	$.post("/advanced/firewall", postdata, function(data) {
		data = data.trim();
		if (data == "RELOAD")
			document.location.reload(true);
		else if (data == "OK")
			$("#apply-modal").modal("hide");
		else
		{
			$("#apply_msg").html(data);
			$(".alert_control").removeClass("hidden");
		}
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$("#apply_cancel").removeClass("hidden");
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
	$("#apply_changes").click( DMZ_Apply );
}

function DMZ_Apply()
{
	// Assemble the post data for the AJAX call:
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

	// Perform our AJAX request to change the WAN settings:
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply-modal").modal("show");
	$("#apply_cancel").addClass("hidden");
	$.post("/advanced/dmz", postdata, function(data) {
		data = data.trim();
		if (data == "RELOAD")
			document.location.reload(true);
		else if (data == "OK")
			$("#apply-modal").modal("hide");
		else
		{
			$("#apply_msg").html(data);
			$(".alert_control").removeClass("hidden");
		}
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$("#apply_cancel").removeClass("hidden");
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
	$("#apply_changes").click( Notify_Apply );
}

function Notify_Apply()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':        SID,
		'action':     'submit',
		'enabled':    $("#enable_mosquitto").prop("checked") ? "Y" : "N",
		'ip_addr':    $("#ip_addr").val(),
		'ip_port':    $("#ip_port").val(),
		'username':   $("#username").val(),
		'password':   $("#password").val(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply-modal").modal("show");
	$("#apply_cancel").addClass("hidden");
	$.post("/advanced/notify", postdata, function(data) {
		data = data.trim();
		if (data == "RELOAD")
			document.location.reload(true);
		else if (data == "OK")
			$("#apply-modal").modal("hide");
		else
		{
			$("#apply_msg").html(data);
			$(".alert_control").removeClass("hidden");
		}
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$("#apply_cancel").removeClass("hidden");
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
		$("#clients-table").html(data);
		$(".reservation-option").click(function() {
			line = $(this).parent();
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
function Init_UPnP()
{
	$("#upnp_refresh").click(function() {
		$.post('/advanced/upnp', __postdata("list"), function(data) {
			if (data == "RELOAD")
				document.location.reload(true);
			else
				$("#upnp-table").html(data);
		});
	}).click();
	$("#upnp_submit").click(UPnP_Submit);
}

function UPnP_Submit()
{
	// Hide confirmation dialog if shown:
	$("#confirm-modal").modal("hide");

	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':      SID,
		'action':   'submit',
		'enabled':  $("#upnp_enable").prop("checked") ? "Y" : "N",
		'secured':  $("#upnp_secure").prop("checked") ? "Y" : "N",
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply-modal").modal("show");
	$("#apply_cancel").addClass("hidden");
	$.post("/advanced/upnp", postdata, function(data) {
		data = data.trim();
		if (data == "RELOAD")
			document.location.reload(true);
		else if (data == "OK")
		{
			$("#apply-modal").modal("hide");
			$("#upnp_refresh").click();
		}
		else
		{
			$("#apply_msg").html(data);
			$(".alert_control").removeClass("hidden");
		}
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$("#apply_cancel").removeClass("hidden");
	});
}