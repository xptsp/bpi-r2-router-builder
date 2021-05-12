function Basic_Data()
{
	$.getJSON("/ajax/basic?sid=" + SID, function(results) {
		// Update internet connectivity status:
		if (results.wan_status == "Online")
			$("#connectivity-div").removeClass("bg-danger");
		else
			$("#connectivity-div").addClass("bg-danger");
		$("#connectivity-spinner").remove();
		$("#connectivity-text").html(results.wan_status);

		// Update number of attached devices:
		$("#devices-spinner").remove();
		$("#num_of_devices").html(results.lan_count);

		// Update number of mounted USB devices:
		if (results.usb_count == 1)
			$("#usb-sharing").html(results.usb_count.toString() + " Device");
		else if (results.usb_count >= 0)
			$("#usb-sharing").html(results.usb_count.toString() + " Devices");
		else
			$("#usb-sharing").html("Disabled");

		// Update PiHole statistics:
		$("#unique_clients").html(results.unique_clients);
		$("#dns_queries_today").html(results.dns_queries_today);
		$("#ads_blocked_today").html(results.ads_blocked_today);
		$("#ads_percentage_today").html(results.ads_percentage_today);
		$("#domains_being_blocked").html(results.domains_being_blocked);

		// Update system temperature:
		$("#temp").html(results.temp);
		if (results.temp > 60)
			$("#temp-danger").removeClass("invisible");
		else
			$("#temp-danger").addClass("invisible");

		// Update system load averages:
		$("#load0").html(results.load0);
		$("#load1").html(results.load1);
		$("#load2").html(results.load2);

		// Update server uptime and local time:
		$("#system_uptime").html(results.system_uptime);
		$("#server_time").html(results.server_time);
	});
}

var timer;
var MyTimer;

function Confirm_Reboot()
{
	$.get("/ajax/reboot?sid=" + SID);
	$("#reboot_nah").addClass("invisible");
	$("#reboot_yes").addClass("invisible");
	$("#reboot_msg").html("Please be patient while the router is rebooting.<br/>Page will reload after approximately 60 seconds.");
	timer = 60;
	$("#reboot_timer").html('<h1 class="centered">' + timer.toString() + '</h1>');
	myTimer = setInterval(function() {
		--timer;
		$("#reboot_timer").html('<h1 class="centered">' + timer.toString() + '</h1>');
		if (timer === 0) {
			clearInterval(MyTimer);
			document.location.reload(true);
		}
	}, 1000);
}

function Stats_Get()
{
	$.get("/ajax/stats?sid=" + SID, function(data) {
		$("#stats_body").html(data);
	});
}

function Stats_Show()
{
	Stats_Get();
	myTimer = setInterval(Stats_Get, 5000);
}

function Stats_Close()
{
	clearInterval(myTimer);
}

function Password_Fail(msg)
{
	$("#passwd_msg").html(msg);
	$("#passwd_icon").removeClass("fa-thumbs-up");
	$("#alert_msg").addClass("alert-danger").removeClass("alert-success").removeClass("hidden");
}

function Password_Submit()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid': SID,
		'oldPass': $("#oldPass").val(),
		'newPass': $("#newPass").val(),
		'conPass': $("#conPass").val()
	};

	// Confirm all information has been entered correctly:
	$("#passwd_icon").removeClass("fa-thumbs-up");
	if (postdata.oldPass == "")
		return Password_Fail("Current password not specified!");
	if (postdata.newPass == "")
		return Password_Fail("New password not specified!");
	if (postdata.conPass == "")
		return Password_Fail("New password not specified!");
	if (postdata.conPass != postdata.newPass)
		return Password_Fail("New password does not match Confirm Password!");

	// Perform our AJAX request to change the password:
	$.post("/ajax/password", postdata, function(data) {
		if (data == "Successful")
		{
			$("#passwd_msg").html("Password Change Successful!");
			$("#passwd_icon").addClass("fa-thumbs-up");
			$("#alert_msg").removeClass("alert-danger").addClass("alert-success").removeClass("hidden");
		}
		else if (data == "No match")
			Password_Fail("Incorrect Old Password!");
		else
			Password_Fail("Password Change failed for unknown reason!");
	});
}

function WebUI_Check()
{
	$.getJSON("/ajax/webui/check?sid=" + SID, function(data) {
		$('#current_ver').html( 'v' + data.local_ver );
		$('#latest_ver').html( 'v' + data.remote_ver );
		//if (data.status == "Update Available")
		{
			$("#check_div").addClass("hidden");
			$("#pull_div").removeClass("hidden");
		}
	});
}

function WebUI_Pull()
{
	$("#main_div").append('<div class="overlay-wrapper" id="loading_div"><div class="overlay"><i class="fas fa-3x fa-sync-alt fa-spin"></i><div class="text-bold pt-2">Loading...</div></div>');
	$.get("/ajax/webui/pull?sid=" + SID, function(data) {
		document.location.reload(true);
	});
}
