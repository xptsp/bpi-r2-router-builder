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
	}).fail(function() {
		$("#stats_body").html("AJAX call failed");
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
	if (postdata.oldPass == "")
		return Password_Fail("Current password not specified!");
	if (postdata.newPass == "")
		return Password_Fail("New password not specified!");
	if (postdata.conPass == "")
		return Password_Fail("New password not specified!");
	if (postdata.conPass != postdata.newPass)
		return Password_Fail("New password does not match Confirm Password!");

	// Make sure the specified passwords are valid:
	tmp = $("#oldPass").val().replace(/[\s\W]+/, '-');
	if (postdata.conPass != tmp)
		return Password_Fail("Old Password cannot contain characters other than alphanumeric characters!");
	tmp = $("#newPass").val().replace(/[\s\W]+/, '-');
	if (postdata.conPass != tmp)
		return Password_Fail("New Password cannot contain characters other than alphanumeric characters!");

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
	}).fail(function() {
		Password_Fail("AJAX call failed!");
	});
}

function add_overlay(id)
{
	$("#" + id).append(
		'<div class="overlay-wrapper" id="' + id + '-loading">' +
			'<div class="overlay dark">' +
				'<i class="fas fa-3x fa-sync-alt fa-spin"></i>' +
			'</div>' +
		'</div>');
}	

function del_overlay(id)
{
	$("#" + id + "-loading").remove();
}

function WebUI_Check()
{
	add_overlay("webui-div");
	$.getJSON("/ajax/webui/check?sid=" + SID, function(data) {
		del_overlay("webui-div");
		$('#latest_ver').html( 'v' + data.remote_ver );
		if (data.remote_ver > $("#current_ver").text())
		{
			$("#webui_check_div").addClass("hidden");
			$("#webui_pull_div").removeClass("hidden");
		}
	}).fail( function() {
		del_overlay("webui-div");
		$('#latest_ver').html("AJAX Call Failed");
	});
}

function WebUI_Pull()
{
	add_overlay("webui-div");
	$.get("/ajax/webui/pull?sid=" + SID, function(data) {
		document.location.reload(true);
	});
}

function Debian_Check()
{
	add_overlay("debian-div");
	$.getJSON("/ajax/debian/check?sid=" + SID, function(data) {
		del_overlay("debian-div");
		$("#updates_avail").html( data.updates );
		if (data.updates > 0)
		{
			$("#apt_check_div").addClass("hidden");
			$("#apt_pull_div").removeClass("hidden");
			$("#packages_div").html( data.list );
			$("#updates_list").removeClass("hidden");
		}
	}).fail( function() {
		del_overlay("debian-div");
		$('#updates_avail').html("AJAX Call Failed");
	});
}

function Debian_Pull()
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
