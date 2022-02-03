var SID;
var LoginTimer;
var RefreshTimer;
var timer;
var MyTimer;
var max_timer;

//======================================================================================================
// Function dealing with global site initialization stuff:
//======================================================================================================
function Init_Site(sid)
{
	SID=sid;
	$("#dark-mode_label").click(Site_DarkMode);
}

function Site_DarkMode()
{
	$("body").toggleClass("dark-mode bodybg bodybg-dark");
	check = $("#dark-mode");
	check.toggleClass("far fas");
	$.get("/home?sid=" + SID + "&dark_mode=" + (check.hasClass("fas") ? 'N' : 'Y'), function(data) {
		if (data.trim() == "RELOAD")
			document.location.reload(true);
		else if (data.trim() != "OK")
			alert(data);
	});	
	return false;
}

function __postdata(action, misc = '')
{
	return {
		'sid':    SID,
		'action': action,
		'misc': misc,
	};
}

//======================================================================================================
// Javascript functions for "Login" page:
//======================================================================================================
function Init_Login()
{
	$("#login_button").click(Login_Submit);
}

function Login_Submit()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':      SID,
		'action':   'submit',
		'password': $("#password").val(),
		'username': $("#username").val(),
		'remember': $("#remember").prop("checked") ? "Y" : "N",
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the password:
	$.post("/login", postdata, function(data) {
		if (data.trim() == "OK")
			document.location.reload(true);
		else
			DHCP_Error();
	});
	return false;
}

//======================================================================================================
// Javascript functions for "Home" page:
//======================================================================================================
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
	$.get("/home?sid=" + SID, function(results) {
		if (results == "RELOAD")
			document.location.reload(true);

		// Update internet connectivity status:
		$("#connectivity-text").html(results.status);

		// Update number of attached devices:
		$("#devices-spinner").remove();
		$("#num_of_devices").html(results.lan_count);

		// Update number of mounted USB devices:
		$("#usb-sharing").html(results.usb_count.toString());

		// Update system temperature:
		$("#temp").html(results.temp);
		if (results.temp > 60)
			$("#temp_div").removeClass("bg-info").addClass('bg-danger');
		else
			$("#temp_div").addClass("bg-info").removeClass('bg-danger');

		// Update system load averages:
		$("#load0").html(results.load0);
		$("#load1").html(results.load1);
		$("#load2").html(results.load2);

		// Update server uptime and local time:
		$("#system_uptime").html(results.system_uptime);
		$("#server_time").html(results.server_time);
	
		// Update number of domains blocked:
		$("#domains-blocked").html(results.domains_being_blocked);
		if (results.domains_being_blocked == 0)
			$("#pihole_div").removeClass("bg-info").addClass("bg-danger");
		else
			$("#pihole_div").addClass("bg-info").removeClass("bg-danger");
	});
}

//======================================================================================================
// Helper functions dealing with reboot modals and overlays:
//======================================================================================================
function Reboot_Message()
{
	txt = timer.toString();
	per = parseInt(100 * timer / max_timer);
	$("#reboot_timer").html('<h1 class="centered">' + txt + '</h1><div class="progress mb-3">' +
		'<div class="progress-bar bg-info" role="progressbar" aria-valuenow="' + txt + '" aria-valuemin="0" aria-valuemax="' + max_timer + '" style="width: ' + per.toString() + '%"></div></div>');
	--timer;
	if (timer == 0) {
		clearInterval(MyTimer);
		document.location.reload(true);
	}
}

function Reboot_Confirmed()
{
	mode = "";
	$.post("/manage/status", __postdata("reboot"));
	$("#reboot_control").addClass("hidden");
	$("#reboot_close").addClass("hidden");
	max_timer = 120;
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
