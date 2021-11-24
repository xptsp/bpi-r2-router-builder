//======================================================================================================
// Javascript functions for "Admin / Router Status"
//======================================================================================================
function Init_Stats()
{
	Stats_Update();
	$("#stats_button").click(Stats_Network_Show);
	$("#stats_close").click(Stats_Network_Close);
	$("#reboot_button").click(Stats_Reboot_Button);
	$("#power_button").click(Stats_Power_Button);
	$("#reboot_yes").click(Reboot_Confirmed);
	$("#refresh_switch").bootstrapSwitch();
}

function Stats_Update()
{
	if ($("#connection_type").html() == "DHCP")
	{
		$.post("/ajax/admin/status", __postdata("status"), function(data) {
			if (data == "RELOAD")
				document.location.reload(true);
			$("#dhcp_server").html( data.dhcp_server );
			$("#dhcp_begin").html( data.dhcp_begin );
			$("#dhcp_expire").html( data.dhcp_expire );
			timer = setInterval(function() {
				if (timer === 0) {
					clearInterval(timer);
					Stats_Update();
				}
			}, data.dhcp_refresh + 60);
		}).fail(function() {
			$("#dhcp_server").html('AJAX call failed');
			$("#dhcp_begin").html('AJAX call failed');
			$("#dhcp_expire").html('AJAX call failed');
		});
	}
}

function Stats_Reboot_Button()
{
	restore_type = "reboot";
	$("#title_msg").html("Reboot");
	$("#body_msg").html("Rebooting");
	$("#reboot_yes").html("Reboot Router");
}

function Stats_Power_Button()
{
	restore_type = "power";
	$("#title_msg").html("Power Off");
	$("#body_msg").html("Powering off");
	$("#reboot_yes").html("Power Off Router");
}

function Stats_Network_Get()
{
	$.post("/ajax/admin/status", __postdata("network"), function(data) {
		if (data == "RELOAD")
			document.location.reload(true);
		$("#stats_body").html(data);
	}).fail(function() {
		$("#stats_body").html("AJAX call failed");
	});
}

function Stats_Network_Show()
{
	Stats_Network_Get();
	myTimer = setInterval(Stats_Network_Get, 5000);
	$("#refresh_switch").on('switchChange.bootstrapSwitch', function(event, state) {
		if (state == true)
		{
			Stats_Network_Get();
			myTimer = setInterval(Stats_Network_Get, 5000);
		}
		else
			clearInterval(myTimer);
	});
}

function Stats_Network_Close()
{
	clearInterval(myTimer);
}

//======================================================================================================
// Javascript functions for "Admin / 7Credentials"
//======================================================================================================
function Init_Creds()
{
	$("#submit").click(Creds_Password_Submit);
	$(".input-group-append").click(Creds_Password_Toggle);
}

function Creds_Password_Toggle()
{
	input = $(this).parent().find(".form-control");
	if (input.attr("type") === "password")
		input.attr("type", "text");
	else
        input.attr("type", "password");
	$(this).find(".fas").toggleClass("fa-eye fa-eye-slash");
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
	$.post("/ajax/admin/creds", postdata, function(data) {
		if (data == "RELOAD")
			document.location.reload(true);
		else if (data == "oldPass")
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
// Javascript functions for "Admin / Repository Updates"
//======================================================================================================
function Init_Repo()
{
	$(".check_repo").click(Repo_Check).click();
	$(".pull_repo").click(Repo_Pull);
}

function Repo_Check()
{
	elem = $(this).attr('id').split("_")[0];
	Add_Overlay(elem + "_div");
	$.post("/ajax/admin/repo", __postdata("check", elem), function(data) {
		if (data == "RELOAD")
			document.location.reload(true);
		data = JSON.parse(data);
		Del_Overlay(data.elem + "_div");
		valid = (data.time != "Invalid Data");
		$("#" + data.elem + "_latest").html( (valid ? 'v' : '') + data.time );
		if (valid && data.time > $("#" + data.elem + "_current").text())
		{
			$("#" + data.elem + "_check_div").addClass("hidden");
			$("#" + data.elem + "_pull_div").removeClass("hidden");
		}
	}).fail( function() {
		Del_Overlay(elem + "_div");
		$("#" + elem + "_latest").html("AJAX Call Failed");
	});
}

function Repo_Pull()
{
	elem = $(this).attr('id').split("_")[0];
	Add_Overlay(elem + "_div");
	$.post("/ajax/admin/repo", __postdata("pull", elem), function(data) {
		document.location.reload(true);
	}).fail( function() {
		Del_Overlay(elem + "_div");
		$("#" + elem + "_latest").html("AJAX Call Failed");
	});
}

//======================================================================================================
// Javascript functions for "Admin / Debian Updates"
//======================================================================================================
function Init_Debian()
{
	$("#apt_check").click(Debian_Check);
	$(".apt_pull").click(function() {
		Debian_Pull('upgrade');
	});
	$("#modal-close").click(function() {
		if (!$(this).hasClass("disabled"))
			$("#output-modal").modal('hide');
	});
}

function Debian_Check()
{
	Add_Overlay("debian-div");
	$("#Repo_avail").html("<i>Retrieving...</i>");
	$.post("/ajax/admin/debian", __postdata('check'), function(data) {
		data = data.trim();
		if (data == "RELOAD")
			document.location.reload(true);
		Del_Overlay("debian-div");
		$("#updates-msg").html(data.count[0]);
		total = Number(data.count[1]) + Number(data.count[2]) + Number(data.count[4]);
		$("#updates-available").html(total);
		$("#updates-installable").html(data.count[1]);
		$("#updates-div").removeClass("hidden");
		if (total == 0)
			$('#packages_div').html('<tr><td colspan="5"><center><strong>No Updates Available</strong></center></td></tr>');
		else
		{
			$(".apt_pull").removeClass("hidden");
			$("#packages_div").html( data.list );
		}
	}).fail( function() {
		Del_Overlay("debian-div");
		$('#packages_div').html('<tr><td colspan="5"><center>AJAX Call Failed</center></td></tr>');
	});
}

function Debian_Pull(mode, packages = [])
{
	element = $("#output_div");
	element.html("");
	$("#modal-close").addClass("disabled");
	last_response_len = 0;
	$.ajax({
		url: '/ajax/admin/debian',
		dataType: 'text',
		type: 'post',
		contentType: 'application/x-www-form-urlencoded',
		data: 'sid=' + SID + '&action=' + mode + '&packages=' + packages.join(","),
		success: function( data, textStatus, jQxhr ){
			$("#modal-close").removeClass("disabled");
		},
		error: function( jqXhr, textStatus, errorThrown ){
			$("#modal-close").removeClass("disabled");
			element.append("<< AJAX ERROR: " + errorThrown + " >>");
		},
		xhrFields: {
			onprogress: function(e)
			{
				var this_response, response = e.currentTarget.response;
				this_response = response.substring(last_response_len);
				last_response_len = response.length;
				msg = this_response.trim().replace("\n", "") + "\n";
				if (msg != "\n")
					element.append(msg);
				element.scrollTop( element.prop('scrollHeight') - element.height() );
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
	$("#pages .pagelink").on("click", function() {
		Logs_Page( $(this).text() );
	});
	$("#pages .page_prev").on("click", function() {
		Logs_Page( Math.max(1, parseInt($("#pages .active").text()) - 1) );
	});
	$("#pages .page_next").on("click", function() {
		Logs_Page( Math.min(parseInt($("#pages .active").text()) + 1, MaxPages) );
	});
	$("#pages .page_first").on("click", function() {
		Logs_Page( 1 );
	});
	$("#pages .page_last").on("click", function() {
		Logs_Page( MaxPages );
	});
	$("#search").on("propertychange input", Logs_Filter);
	Logs_Page(1);
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

function Logs_Page(page)
{
	// Show only the page that we have selected:
	$(".everything").addClass("hidden");
	$("#lines .page_" + page).removeClass("hidden");
	$("#pages .active").removeClass("active");
	$("#pages .pagelink_" + page).addClass("active");

	// Show only 10 pages in the pagination element:
	min = Math.max( 1, page - 5);
	max = Math.min( MaxPages, min + 9 );
	min = Math.min( min, max - 9 );
	$("#pages .pagelink").addClass("hidden");
	for (let i = min; i <= max; i++)
		$("#pages .pagelink_" + i).removeClass("hidden");
}

//======================================================================================================
// Javascript functions for "Admin/Backup Settings"
//======================================================================================================
function Init_Restore()
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
	postdata.append('action', 'upload');
	$.ajax({
		url: '/ajax/admin/backup',
		type: 'post',
		data: postdata,
		contentType: false,
		processData: false,
		success: function(data) {
			data = data.trim();
			if (data == "RELOAD")
				document.location.reload(true);
			else if (data.indexOf("ERROR:") > -1)
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
}

function Restore_Confirm()
{
	// Make the AJAX call to confirm restoration:
	$.post("/ajax/admin/backup", __postdata(restore_type), function(data) {
		if (data == "RELOAD")
			document.location.reload(true);
		else if (data.indexOf("ERROR:") > -1)
			Restore_Alert(data);
		else
			Stats_Confirm_Reboot();
	}).fail(function() {
		Restore_Alert("AJAX call failed!");
	});
}
