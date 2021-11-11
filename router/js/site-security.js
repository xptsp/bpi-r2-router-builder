//======================================================================================================
// Javascript functions for "Security / Firewall Settings"
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
		'drop_port_scan': $("#drop_port_scan").prop("checked") ? "Y" : "N",
		'log_port_scan':  $("#log_port_scan").prop("checked") ? "Y" : "N",
		'log_udp_flood':  $("#log_udp_flood").prop("checked") ? "Y" : "N",
		'drop_ping':      $("#drop_ping").prop("checked") ? "Y" : "N",
		'drop_ident':     $("#drop_ident").prop("checked") ? "Y" : "N",
		'drop_multicast': $("#drop_multicast").prop("checked") ? "Y" : "N",
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$(".alert_control").addClass("hidden");
	$.post("/ajax/security/firewall", postdata, function(data) {
		$(".alert_control").removeClass("hidden");
		if (data.trim() == "OK")
			$("#apply-modal").modal("hide");
		else
			$("#apply_msg").html(data);
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$(".alert_control").removeClass("hidden");
	});
}