var iface_used;
var reboot_suggested = false;

//======================================================================================================
// Javascript functions for "Setup / WAN Settings"
//======================================================================================================
function Init_WAN(mac_com, mac_cur)
{
	$('.ip_address').inputmask("ip");
	$("#dynamic_ip").click(function() {
		$(".ip_address").attr("disabled", "disabled");
	});
	$("#static_ip").click(function() {
		$(".ip_address").removeAttr("disabled");
	});
	$('.dns_address').inputmask("ip");
	$("#dns_isp").click(function() {
		$(".dns_address").attr("disabled", "disabled");
	});
	$("#dns_custom").click(function() {
		$(".dns_address").removeAttr("disabled");
	});
	$("#mac_default").click(function() {
		$("#mac_addr").val(mac_cur).attr("disabled", "disabled");
	});
	$("#mac_random").click(function() {
		s = "X" + "26AE".charAt(Math.floor(Math.random() * 4)) + ":XX:XX:XX:XX:XX";
		$("#mac_addr").val(s.replace(/X/g, function() {
			return "0123456789ABCDEF".charAt(Math.floor(Math.random() * 16))
		})).attr("disabled", "disabled");
	});
	$("#mac_computer").click(function() {
		$("#mac_addr").val(mac_com).attr("disabled", "disabled");
	});
	$("#mac_custom").click(function() {
		$("#mac_addr").removeAttr("disabled");
	});
	$("#mac_addr").inputmask("mac");
	$("#submit").click( WAN_Submit );
}

function WAN_Submit()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':      SID,
		'hostname': $("#hostname").val(),
		'static':   ($("[name=static_dynamic]:checked").val()) == "dynamic" ? 0 : 1,
		'ip_addr':  $("#ip_addr").val(),
		'ip_mask':  $("#ip_mask").val(),
		'ip_gate':  $("#ip_gate").val(),
		'use_isp':  ($("[name=dns_server_opt]:checked").val()) == "isp" ? 0 : 1,
		'dns1':     $("#dns1").val(),
		'dns2':     $("#dns2").val(),
		'mac':      $("#mac_addr").val()
	};

	// Perform our AJAX request to change the WAN settings:
	$("#apply_msg").html("Please wait while the networking service is restarted...");
	$(".alert_control").addClass("hidden");
	$("#apply-modal").modal("show");
	$.post("/ajax/setup-wan", postdata, function(data) {
		if (data == "OK")
			document.location.reload(true);
		else
		{
			$("#apply_msg").html(data);
			$(".alert_control").removeClass("hidden");
		}
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$(".alert_control").removeClass("hidden");
	});
}

//======================================================================================================
// Javascript functions for "Setup / LAN Setup"
//======================================================================================================
function Init_LAN(iface)
{
	iface_used = iface;

	// Main screen setup and handlers:
	$('.ip_address').inputmask("ip");
	$("#dynamic_ip").click(function() {
		$(".ip_address").attr("disabled", "disabled");
		$(".static_section").addClass("hidden");
	});
	$("#static_ip").click(function() {
		$(".ip_address").removeAttr("disabled");
		$(".static_section").removeClass("hidden");
	});
	$('#use_dhcp').click(function() {
		if ($(this).is(":checked")) {
			$(".dhcp").removeAttr("disabled");
			$(".dhcp_div").removeClass("hidden");
		} else {
			$(".dhcp").attr("disabled", "disabled");
			$(".dhcp_div").addClass("hidden");
		}
	});
	$(".bridge").click( function() {
		$(this).toggleClass("active");
	});
	$(".hostname").inputmask();
	$(".ip_address").change(function() {
		parts = $("#ip_addr").val().substring(0, $("#ip_addr").val().lastIndexOf('.'));
		$("#dhcp_start").val( parts + $("#dhcp_start").val().substring( $("#dhcp_start").val().lastIndexOf('.')) );
		$("#dhcp_end").val( parts + $("#dhcp_end").val().substring( $("#dhcp_end").val().lastIndexOf('.')) );
		$("#dhcp_ip_addr").val( parts + $("#dhcp_ip_addr").val().substring( $("#dhcp_ip_addr").val().lastIndexOf('.')) );
	});
	$("#dhcp_lease").inputmask("integer");
	$("#dhcp_units").change(function() {
		if ($(this).val() == "infinite")
			$("#dhcp_lease").attr("disabled", "disabled");			
		else
			$("#dhcp_lease").removeAttr("disabled");
	});
	$("#apply_changes").click(LAN_Submit);
	$("#reservations-refresh").click(LAN_Refresh_Reservations).click();

	//=========================================================================
	// IP Reservation setup and handlers:
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
		LAN_Refresh_Leases();
	});
	$("#leases_refresh").click(LAN_Refresh_Leases);
	$("#dhcp_add").click(LAN_Reservation_Add);
	$("#dhcp_error_close").click(function() {
		$("#dhcp_error_box").addClass("hidden");
	});
}

function LAN_Submit()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':        SID,
		'iface':      iface_used,
		'hostname':   $("#hostname").val(),
		'iface':      $("#iface").val(),
		'ip_addr':    $("#ip_addr").val(),
		'ip_mask':    $("#ip_mask").val(),
		'use_dhcp':   $("#use_dhcp").is(":checked") ? 1 : 0,
		'dhcp_start': $("#dhcp_start").val(),
		'dhcp_end':   $("#dhcp_end").val(),
		'dhcp_lease': $("#dhcp_lease").val() + $("#dhcp_units").val(),
		'reboot':     reboot_suggested,
	};
	if ($("#dhcp_units").val() == "infinite")
		postdata.dhcp_lease = 'infinite';
	$(".bridge").each(function() {
		if ($(this).hasClass("active"))
			postdata.bridge += " " + $(this).text().trim();
	});
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$("#apply_msg").html("Please wait while the networking service is restarted...");
	$(".alert_control").addClass("hidden");
	$("#apply-modal").modal("show");
	$.post("/ajax/setup-lan", postdata, function(data) {
		if (data == "OK")
			document.location.reload(true);
		else
		{
			$("#apply_msg").html(data);
			$(".alert_control").removeClass("hidden");
		}
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$(".alert_control").removeClass("hidden");
	});
}

function LAN_Refresh_Leases()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':    SID,
		'iface':  iface_used,
		'action': 'clients',
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to refresh the LAN leases:
	$("#clients-table").html('<tr><td colspan="5"><center>Loading...</center></td></tr>');
	$.post("/ajax/setup/lan/dhcp", postdata, function(data) {
		$("#clients-table").html(data);
		$(".reservation-option").click(function() {
			line = $(this).parent();
			$("#dhcp_client_name").val( line.find(".dhcp_host").html() );
			$("#dhcp_ip_addr").val( line.find(".dhcp_ip_addr").html() );
			$("#dhcp_mac_addr").val( line.find(".dhcp_mac_addr").html() );
		});
	}).fail(function() {
		LAN_Error("AJAX call failed!");
	});
}

function LAN_Refresh_Reservations()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':    SID,
		'iface':  iface_used,
		'action': 'reservations',
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	$("#reservations-table").html('<tr><td colspan="5"><center>Loading...</center></td></tr>');
	$.post("/ajax/setup/lan/dhcp", postdata, function(data) {
		$("#reservation-modal").modal("hide");
		$("#reservations-table").html(data);
		$(".dhcp_edit").click(function() {
			$("#add_reservation").click();
			line = $(this).parent();
			$("#dhcp_client_name").val( line.find(".dhcp_host").html() );
			$("#dhcp_ip_addr").val( line.find(".dhcp_ip_addr").html() );
			$("#dhcp_mac_addr").val( line.find(".dhcp_mac_addr").html() );
		});
		$(".dhcp_delete").click(LAN_Reservation_Remove);
	});
}

function LAN_Reservation_Remove()
{
	// Assemble the post data for the AJAX call:
	line = $(this).parent();
	postdata = {
		'sid':      SID,
		'action':   'remove',
		'iface':    iface_used,
		'hostname': line.find(".dhcp_host").html(),
		'ip_addr':  line.find(".dhcp_ip_addr").html(),
		'mac_addr': line.find(".dhcp_mac_addr").html(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to remove the IP reservation:
	$.post("/ajax/setup/lan/dhcp", postdata, function(data) {
		if (data.trim() == "OK")
		{
			LAN_Refresh_Reservations();
			reboot_suggested = true;
		}
		else
			LAN_Error(data);
	}).fail(function() {
		LAN_Error("AJAX call failed!");
	});
}

function LAN_Reservation_Add()
{
	// Assemble the post data for the AJAX call:
	line = $(this).parent();
	postdata = {
		'sid':      SID,
		'action':   'add',
		'iface':    iface_used,
		'hostname': $("#dhcp_client_name").val(),
		'ip_addr':  $("#dhcp_ip_addr").val(),
		'mac_addr': $("#dhcp_mac_addr").val(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Check to make sure we actually have something to pass to the AJAX call:
	if (postdata.hostname == "")
		return LAN_Error("No hostname specified!");
	else if (postdata.ip_addr == "")
		return LAN_Error("No IP address specified!");
	else if (postdata.mac_addr == "")
		return LAN_Error("No MAC address specified!");

	// Perform our AJAX request to add the IP reservation:
	$.post("/ajax/setup/lan/dhcp", postdata, function(data) {
		if (data.trim() == "OK")
		{
			LAN_Refresh_Reservations();
			reboot_suggested = true;
		}
		else
			LAN_Error(data);
	}).fail(function() {
		LAN_Error("AJAX call failed!");
	});
}

function LAN_Error(msg)
{
	$("#dhcp_error_msg").html(msg);
	$("#dhcp_error_box").removeClass("hidden");
}
