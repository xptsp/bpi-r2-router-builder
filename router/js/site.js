var SID;
var LoginTimer;
var RefreshTimer;
var timer;
var MyTimer;
var max_timer;

function Init_Site(sid)
{
	SID=sid;
	$("#login_close").click(Login_Close);
	$("#login_submit").click(Login_Submit);
}

function Init_Home()
{
	Home_Data();
	RefreshTimer = setInterval(Home_Data, 5000);
	$("#refresh_switch").bootstrapSwitch();
	$("#refresh_switch").on('switchChange.bootstrapSwitch', function(event, state) {
		if (state == true)
		{
			Home_Data();
			RefreshTimer = setInterval(Home_Data, 5000);
		}
		else
			clearInterval(RefreshTimer);
	});
}

function Home_Data()
{
	$.getJSON("/ajax/home?sid=" + SID, function(results) {
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
	
		// Update number of domains blocked:
		$("#domains-blocked").html(results.domains_being_blocked);
	});
}

function Login_Submit()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid': SID,
		'oldPass': $("#password").val(),
		'username': $("#username").val(),
	};

	// Make sure the username and password is valid:
	$("#div").addClass("hidden");
	tmp1 = $("#username").val().replace(/[\s\W]+/, '-');
	tmp2 = $("#password").val().replace(/[\s\W]+/, '-');
	if (postdata.username == "" || postdata.username != tmp1 || postdata.oldPass == "" || postdata.oldPass != tmp2)
	{
		$("#div").removeClass("hidden");
		return;
	}

	// Perform our AJAX request to change the password:
	$.post("/ajax/password", postdata, function(data) {
		if (data != "Successful" && data != "Match")
		{
			$("#login_div").removeClass("hidden").fadeIn("slow");
			LoginTimer = setInterval(function() {
				clearInterval(LoginTimer);
				$("#login_div").fadeOut("slow");
			}, 3000);
		}
		else
			document.location.reload(true);
	});
}

function Login_Close()
{
	$("#login_div").addClass("hidden");
}

function Reboot_Message()
{
	txt = timer.toString();
	per = parseInt(100 * timer / max_timer);
	$("#reboot_timer").html('<h1 class="centered">' + txt + '</h1><div class="progress mb-3">' +
		'<div class="progress-bar bg-info" role="progressbar" aria-valuenow="' + txt + '" aria-valuemin="0" aria-valuemax="' + max_timer + '" style="width: ' + per.toString() + '%"></div></div>');
	--timer;
	if (timer <= 0) {
		clearInterval(MyTimer);
		document.location.reload(true);
	}
}

function Reboot_Confirmed()
{
	mode = "";
	$.get("/ajax/admin/reboot?sid=" + SID + mode);
	$("#reboot_control").addClass("hidden");
	$("#reboot_close").addClass("hidden");
	max_timer = 60;
	timer = max_timer;
	$("#reboot_msg").html("Please be patient while the router is rebooting.<br/>Page will reload after approximately " + max_timer + " seconds.");
	Reboot_Message();
	myTimer = setInterval(Reboot_Message, 1000);
}

function Add_Overlay(id)
{
	$("#" + id).append(
		'<div class="overlay-wrapper" id="' + id + '_loading">' +
			'<div class="overlay dark">' +
				'<i class="fas fa-3x fa-sync-alt fa-spin"></i>' +
			'</div>' +
		'</div>');
}	

function Del_Overlay(id)
{
	$("#" + id + "_loading").remove();
}

function __postdata(action, misc = '')
{
	return {
		'sid':    SID,
		'action': action,
		'misc': misc,
	};
}
