//======================================================================================================
// Javascript functions for "Setup / Internet"
//======================================================================================================
function Init_WAN(mac)
{
	$('.ip_address').inputmask("ip");
	$('.dns_address').inputmask("ip");
	$("#dynamic_ip").click(function() {
		$(".ip_address").attr("disabled", "disabled");
	});
	$("#static_ip").click(function() {
		$(".ip_address").removeAttr("disabled");
	});
	$("#dns_doh").click(function() {
		$(".dns_address").attr("disabled", "disabled");
		$("#doh_server").removeAttr("disabled");
	});
	$("#dns_custom").click(function() {
		$(".dns_address").removeAttr("disabled");
		$("#doh_server").attr("disabled", "disabled");
	});
	$("#mac_default").click(function() {
		$("#mac_addr").val("08:00:00:00:00:01").attr("disabled", "disabled");
	});
	$("#mac_random").click(function() {
		$("#mac_addr").val("XX:XX:XX:XX:XX:XX".replace(/X/g, function() {
			return "0123456789ABCDEF".charAt(Math.floor(Math.random() * 16))
		})).attr("disabled", "disabled");
	});
	$("#mac_computer").click(function() {
		$("#mac_addr").val(mac).attr("disabled", "disabled");
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
	doh_addr = "127.0.0.1#505" + $("#doh_server").val();
	use_doh = ($("[name=dns_server_opt]:checked").val()) == "doh";
	postdata = {
		'sid':      SID,
		'static':   $("[name=static_dynamic]:checked").val() == 'static' ? 1 : 0,
		'ip_addr':  $("#ip_addr").val(),
		'ip_mask':  $("#ip_mask").val(),
		'ip_gate':  $("#ip_gate").val(),
		'dns1':     use_doh ? doh_addr : $("#dns1").val(),
		'dns2':     use_doh ? '' : $("#dns2").val(),
		'mac':      $("#mac_addr").val()
	};

	// Perform our AJAX request to change the WAN settings:
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
// Javascript functions for "Setup / Internet"
//======================================================================================================
function Init_LAN()
{
	$('.ip_address').inputmask("ip");
	$('#use_dhcp').click(function() {
		if ($(this).is(":checked"))
			$(".dhcp").removeAttr("disabled");
		else
			$(".dhcp").attr("disabled", "disabled");
	});
	$(".page-item").click( function() {
		$(this).toggleClass("active");
	});
	$(".ip_address").change(LAN_IP);
	$("#apply_changes").click(LAN_Apply);
}

function LAN_IP()
{
	// Update the DHCP start and end range when the IP address changes:
	parts = $("#ip_addr").val().substring(0, $("#ip_addr").val().lastIndexOf('.'));
	$("#dhcp_start").val( parts + $("#dhcp_start").val().substring( $("#dhcp_start").val().lastIndexOf('.')) );
	$("#dhcp_end").val( parts + $("#dhcp_end").val().substring( $("#dhcp_end").val().lastIndexOf('.')) );
}

function LAN_Apply()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':        SID,
		'hostname':   $("#hostname").val(),
		'iface':      $("#iface").val(),
		'ip_addr':    $("#ip_addr").val(),
		'ip_mask':    $("#ip_mask").val(),
		'use_dhcp':   $("#use_dhcp").is(":checked") ? 1 : 0,
		'dhcp_start': $("#dhcp_start").val(),
		'dhcp_end':   $("#dhcp_end").val(),
		'bridge':     '',
	};
	$(".bridge").each(function() {
		if ($(this).hasClass("active"))
			postdata.bridge += " " + $(this).text().trim();
	});
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
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
