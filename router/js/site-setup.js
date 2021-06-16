//======================================================================================================
// Javascript functions for "Setup / Internet"
//======================================================================================================
function Setup_Internet(mac)
{
	$('.ip_address').each(function() {
		$(this).inputmask("ip");
	});
	$('.dns_address').each(function() {
		$(this).inputmask("ip");
	});
	$("#dynamic_ip").click(function() {
		$(".ip_address").each(function() {
			$(this).attr("disabled", "disabled");
		});
	});
	$("#static_ip").click(function() {
		$(".ip_address").each(function() {
			$(this).removeAttr("disabled");
		});
	});
	$("#dns_doh").click(function() {
		$(".dns_address").each(function() {
			$(this).attr("disabled", "disabled");
		});
		$("#doh_server").removeAttr("disabled");
	});
	$("#dns_custom").click(function() {
		$(".dns_address").each(function() {
			$(this).removeAttr("disabled");
		});
		$("#doh_server").attr("disabled", "disabled");
	});
	$("#mac_default").click(function() {
		$("#mac_addr").val("08:00:00:00:00:01").attr("disabled", "disabled");
	});
	$("#mac_computer").click(function() {
		$("#mac_addr").val(mac).attr("disabled", "disabled");
	});
	$("#mac_custom").click(function() {
		$("#mac_addr").removeAttr("disabled");
	});
	$("#mac_addr").inputmask("mac");
	$("#submit").click(Setup_Internet_Submit);
}

function Setup_Internet_Submit()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':     SID,
		'static':  $("#static_ip").val(),
		'ip_addr': $("#ip_addr").val(),
		'ip_mask': $("#ip_mask").val(),
		'ip_gate': $("#ip_gate").val(),
		'doh':     $("#cloudflare").val(),
		'dns1':    $("#dns1").val(),
		'dns2':    $("#dns2").val(),
		'mac':     $("#mac_addr").val()
	};
	$(".alert_control").addClass("hidden");
	$("#apply-modal").modal("show");

	// Perform our AJAX request to change the WAN settings:
	$.post("/ajax/setup-wan", postdata, function(data) {
		$("#apply_msg").html(data);
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$(".alert_control").removeClass("hidden");
	});
}