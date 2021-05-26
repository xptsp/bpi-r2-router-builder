var timer;
var MyTimer;
var restore_type;

//======================================================================================================
// Javascript functions for "Admin / Router Status"
//======================================================================================================
function Init_Stats()
{
	$("#reboot_yes").click(Stats_Confirm_Reboot);
	$("#stats_button").click(Stats_Network_Show);
	$("#stats_close").click(Stats_Network_Close);
	$.getJSON("/ajax/status?sid=" + SID, function(data) {
		$("#pihole_state").html(data.pihole_state);
		if ($("#connection_type").html() == "DHCP")
		{
			$("#dhcp_server").html( data.dhcp_server );
			$("#dhcp_begin").html( data.dhcp_begin );
			$("#dhcp_expire").html( data.dhcp_expire );
		}
	});
}

function Stats_Reboot_Msg()
{
	txt = timer.toString();
	per = parseInt(100 * timer / 60);
	$("#reboot_timer").html('<h1 class="centered">' + txt + '</h1><div class="progress mb-3">' +
		'<div class="progress-bar bg-info" role="progressbar" aria-valuenow="' + txt + '" aria-valuemin="0" aria-valuemax="60" style="width: ' + per.toString() + '%"></div></div>');
}

function Stats_Confirm_Reboot()
{
	$.get("/ajax/reboot?sid=" + SID);
	$("#reboot_control").addClass("hidden");
	$("#reboot_msg").html("Please be patient while the router is rebooting.<br/>Page will reload after approximately 60 seconds.");
	timer = 60;
	Stats_Reboot_Msg();
	myTimer = setInterval(function() {
		--timer;
		Stats_Reboot_Msg();
		if (timer === 0) {
			clearInterval(MyTimer);
			document.location.reload(true);
		}
	}, 1000);
}

function Stats_Network_Get()
{
	$.get("/ajax/network?sid=" + SID, function(data) {
		$("#stats_body").html(data);
	}).fail(function() {
		$("#stats_body").html("AJAX call failed");
	});
}

function Stats_Network_Show()
{
	Stats_Network_Get();
	myTimer = setInterval(Stats_Network_Get, 5000);
}

function Stats_Network_Close()
{
	clearInterval(myTimer);
}

//======================================================================================================
// Javascript functions for "Admin / Credentials"
//======================================================================================================
function Init_Creds()
{
	$("#submit").click(Creds_Password_Submit);
}

function Creds_Password_Fail(msg)
{
	$("#passwd_msg").html(msg);
	$("#passwd_icon").removeClass("fa-thumbs-up");
	$("#alert_msg").addClass("alert-danger").removeClass("alert-success").fadeIn("slow");
	myTimer = setInterval(function() {
		clearInterval(MyTimer);
		$("#alert_msg").fadeOut("slow");
	}, 3000);
}

function Creds_Password_Submit()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid': SID,
		'oldPass': $("#oldPass").val(),
		'newPass': $("#newPass").val(),
		'conPass': $("#conPass").val()
	};

	// Confirm all information has been entered correctly:
	if (postdata.oldPass == "")
		return Creds_Password_Fail("Current password not specified!");
	if (postdata.newPass == "")
		return Creds_Password_Fail("New password not specified!");
	if (postdata.conPass == "")
		return Creds_Password_Fail("Confirm password not specified!");
	if (postdata.conPass != postdata.newPass)
		return Creds_Password_Fail("New password does not match Confirm Password!");

	// Perform our AJAX request to change the password:
	$.post("/ajax/password", postdata, function(data) {
		if (data == "oldPass")
			Creds_Password_Fail("Old Password cannot contain characters other than alphanumeric characters!");
		else if (data == "newPass")
			Creds_Password_Fail("New Password cannot contain characters other than alphanumeric characters!");
		else if (data == "Successful")
		{
			$("#passwd_msg").html("Password Change Successful!");
			$("#passwd_icon").addClass("fa-thumbs-up");
			$("#alert_msg").removeClass("alert-danger").addClass("alert-success").fadeIn("slow");
			myTimer = setInterval(function() {
				clearInterval(MyTimer);
				$("#alert_msg").fadeOut("slow");
			}, 3000);
		}
		else if (data == "No match")
			Creds_Password_Fail("Incorrect Old Password!");
		else
			Creds_Password_Fail("Password Change failed for unknown reason!");
	}).fail(function() {
		Creds_Password_Fail("AJAX call failed!");
	});
}

//======================================================================================================
// Javascript functions for "Admin / Router Updates"
//======================================================================================================
function Init_Updates()
{
	Updates_WebUI_Check();
	$("#webui_check").click(Updates_WebUI_Check);
	$("#webui_pull").click(Updates_WebUI_Pull);
	$("#apt_check").click(Updates_Debian_Check);
	$("#apt_pull").click(Updates_Debian_Pull);
}

function Updates_Add_Overlay(id)
{
	$("#" + id).append(
		'<div class="overlay-wrapper" id="' + id + '-loading">' +
			'<div class="overlay dark">' +
				'<i class="fas fa-3x fa-sync-alt fa-spin"></i>' +
			'</div>' +
		'</div>');
}	

function Updates_Del_Overlay(id)
{
	$("#" + id + "-loading").remove();
}

function Updates_WebUI_Check()
{
	Updates_Add_Overlay("webui-div");
	$.getJSON("/ajax/webui/check?sid=" + SID, function(data) {
		Updates_Del_Overlay("webui-div");
		$('#latest_ver').html( 'v' + data.remote_ver );
		if (data.remote_ver > $("#current_ver").text())
		{
			$("#webui_check_div").addClass("hidden");
			$("#webui_pull_div").removeClass("hidden");
		}
	}).fail( function() {
		Updates_Del_Overlay("webui-div");
		$('#latest_ver').html("AJAX Call Failed");
	});
}

function Updates_WebUI_Pull()
{
	Updates_Add_Overlay("webui-div");
	$.get("/ajax/webui/pull?sid=" + SID, function(data) {
		document.location.reload(true);
	});
}

function Updates_Debian_Check()
{
	Updates_Add_Overlay("debian-div");
	$("#updates_avail").html("<i>Retrieving...</i>");
	$.getJSON("/ajax/debian/check?sid=" + SID, function(data) {
		Updates_Del_Overlay("debian-div");
		$("#updates_avail").html( data.updates );
		if (data.updates > 0)
		{
			$("#apt_check_div").addClass("hidden");
			$("#apt_pull_div").removeClass("hidden");
			$("#packages_div").html( data.list );
			$("#updates_list").removeClass("hidden");
		}
	}).fail( function() {
		Updates_Del_Overlay("debian-div");
		$('#updates_avail').html("AJAX Call Failed");
	});
}

function Updates_Debian_Pull()
{
	$("#output_group").removeClass("hidden");
	element = $("#output_div");
	element.html("");
	last_response_len = false;
	$.ajax("/ajax/debian/pull?sid=" + SID, {
		xhrFields: {
			onprogress: function(e)
			{
				var this_response, response = e.currentTarget.response;
				if(last_response_len === false)
				{
					this_response = response;
					last_response_len = response.length;
				}
				else
				{
					this_response = response.substring(last_response_len);
					last_response_len = response.length;
				}
				element.append(this_response);
				element.scrollTop = element.scrollHeight;
			}
		}
	});
}

//======================================================================================================
// Javascript functions for "Admin / Router Logs"
//======================================================================================================
function Init_Logs(pages)
{
	MaxPages=pages;
	$("#search").on("propertychange input", Logs_Filter);
	$("#pages").on("click", ".pagelink", Logs_Page);
	$("#pages").on("click", ".pageprev", Logs_Prev);
	$("#pages").on("click", ".pagenext", Logs_Next);
}

function Logs_Filter()
{
	msg = $("#search").val();
	$(".everything").addClass("hidden");
	if (msg.length >= 2)
	{
		$("#lines > div:contains('" + msg + "')").removeClass("hidden");
		$(".pagination").addClass("hidden");
	}
	else
	{
		$(".pagination").removeClass("hidden");
		$("#lines .page_" + $("#pages .active").text()).removeClass("hidden");
	}
}

function Logs_Page()
{
	page = $(this).text();
	$(".everything").addClass("hidden");
	$("#lines .page_" + page).removeClass("hidden");
	$("#pages .active").removeClass("active");
	$("#pages .pagelink_" + page).addClass("active");
}

function Logs_Prev()
{
	page = Math.max(1, parseInt($("#pages .active").text()) - 1);
	$(".everything").addClass("hidden");
	$("#lines .page_" + page).removeClass("hidden");
	$("#pages .active").removeClass("active");
	$("#pages .pagelink_" + page).addClass("active");
}

function Logs_Next()
{
	page = Math.min(parseInt($("#pages .active").text()) + 1, MaxPages);
	$(".everything").addClass("hidden");
	$("#lines .page_" + page).removeClass("hidden");
	$("#pages .active").removeClass("active");
	$("#pages .pagelink_" + page).addClass("active");
}

//======================================================================================================
// Javascript functions for "Admin/Backup Settings"
//======================================================================================================
function Init_Backup()
{
	$("#restore_settings").click(Restore_File);
	$("#factory_settings").click(Restore_Factory);
	$("#reboot_yes").click(Restore_Confirm);
	$(function () {
		bsCustomFileInput.init();
	});
}

function Restore_File()
{
	restore_type = "file";
	$("#reboot_title").html("Restore Settings");
	$("#restore_type").html("uploaded");
	
	// AJAX request
	var postdata = new FormData();
	postdata.append('sid', SID);
	postdata.append('file', $('#restore_file')[0].files[0]);
	postdata.append('restore_type', 'upload');
	$.ajax({
		url: '/ajax/restore',
		type: 'post',
		data: postdata,
		contentType: false,
		processData: false,
		success: function(data) {
			if (data.indexOf("ERROR:") > -1)
				Restore_Alert(data);
			else
				$("#reboot-modal").modal("show");
		}
	}).fail( function() {
		Restore_Alert("AJAX call failed");
	});
}

function Restore_Factory()
{
	restore_type = "factory";
	$("#reboot_title").html("Factory Reset");
	$("#restore_type").html("factory");
}

function Restore_Alert(msg)
{
	$("#reboot-modal").modal("hide");
	$("#error_msg").html(msg);
	$("#error-modal").modal("show");
	return false;
}

function Restore_Confirm()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid': SID,
		'restore_type': restore_type
	};
	$.post("/ajax/restore", postdata, function(data) {
		if (data.indexOf("ERROR:") > -1)
			Restore_Alert(data);
		else
			Stats_Confirm_Reboot();
	}).fail(function() {
		Restore_Alert("AJAX call failed!");
	});
}