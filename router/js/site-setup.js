//======================================================================================================
// Javascript functions for "Setup / Internet"
//======================================================================================================
function Setup_Internet(mac)
{
	$('.ip_address').each(function() {
		$(this).inputmask({
			alias: "ip",
			"placeholder": "_"
		});
	});
	$('.dns_address').each(function() {
		$(this).inputmask({
			alias: "ip",
			"placeholder": "_"
		});
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
	alert("Got Here!");
}