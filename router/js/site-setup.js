//======================================================================================================
// Javascript functions for "Setup / Internet"
//======================================================================================================
function Init_Internet(mac)
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
	$("#submit").click(Internet_Submit);
}

function Internet_Submit()
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
	$(".alert_control").addClass("hidden");
	$("#apply-modal").modal("show");

	// Perform our AJAX request to change the WAN settings:
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
function Init_Wired(iface)
{
	$('.ip_address').each(function() {
		$(this).inputmask("ip");
	});
	$('#use_dhcp').click(function() {
		if ($(this).is(":checked"))
			$(".dhcp").each(function() {
				$(this).removeAttr("disabled");
			});
		else
			$(".dhcp").each(function() {
				$(this).attr("disabled", "disabled");
			});
	});
}
