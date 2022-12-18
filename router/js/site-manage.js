//======================================================================================================
// Javascript functions for "Management / Router Status"
//======================================================================================================
function Init_Stats()
{
	$("#refresh_switch").bootstrapSwitch();

	// Handler dealing with showing the network statistics modal:
	$("#stats_button").click(function() {
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
	});

	// Handler dealing with closing the network statistics modal:
	$("#stats_close").click(function() {
		clearInterval(myTimer);
	});

	// Handler dealing with the reboot button:
	$("#reboot_button").click(function()
	{
		restore_type = "reboot";
		$("#title_msg").html("Reboot");
		$("#body_msg").html("Rebooting");
		$("#reboot_yes").html("Reboot Router");
	});

	// Handler dealing with the power button:  
	$("#power_button").click(function() {
		restore_type = "power";
		$("#title_msg").html("Power Off");
		$("#body_msg").html("Powering off");
		$("#reboot_yes").html("Power Off Router");
	});

	// Handler dealing with the reboot confirmed modal:
	$("#reboot_yes").click(Reboot_Confirmed);
}

function Stats_Fetch(iface)
{
	$.post("/manage/status", __postdata("status", iface), function(data) {
		if (data == "RELOAD")
			document.location.reload(true);
		$("#" + iface + "_dhcp_server").html( data.dhcp_server );
		$("#" + iface + "_dhcp_begin").html( data.dhcp_begin );
		$("#" + iface + "_dhcp_expire").html( data.dhcp_expire );
		timer = setInterval(function() {
			if (timer === 0) {
				clearInterval(timer);
				Stats_Fetch();
			}
		}, data.dhcp_refresh + 60);
	}).fail(function() {
		$("#" + iface + "_dhcp_server").html('AJAX call failed');
		$("#" + iface + "_dhcp_begin").html('AJAX call failed');
		$("#" + iface + "_dhcp_expire").html('AJAX call failed');
	});
}


function Stats_Network_Get()
{
	$.post("/manage/status", __postdata("network"), function(data) {
		if (data == "RELOAD")
			document.location.reload(true);
		$("#stats_body").html(data);
	}).fail(function() {
		$("#stats_body").html("AJAX call failed");
	});
}

//======================================================================================================
// Javascript functions for "Management / Credentials"
//======================================================================================================
function Init_Creds()
{
	// Handler to submit password-change form:
	$("#submit").click(function() {
		postdata = {
			'sid':     SID,
			'action':  'submit',
			'oldPass': $("#oldPass").val(),
			'newPass': $("#newPass").val(),
			'conPass': $("#conPass").val()
		};
		//alert(JSON.stringify(postdata, null, 5)); return;
		WebUI_Post("/manage/creds", postdata);
	});

	// Handler to toggle password visibility:
	$(".input-group-append").click(function() {
		input = $(this).parent().find(".form-control");
		if (input.attr("type") === "password")
			input.attr("type", "text");
		else
		    input.attr("type", "password");
		$(this).find(".fas").toggleClass("fa-eye fa-eye-slash");
	});
}

//======================================================================================================
// Javascript functions for "Management / Repository Updates"
//======================================================================================================
function Init_Repo()
{
	// Handler for checking repo status:
	$(".check_repo").click(function()
	{
		elem = $(this).attr('id').split("_")[0];
		Add_Overlay(elem + "_div");
		$.post("/manage/repo", __postdata("check", elem), function(data) {
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
				$("#" + data.elem + "_ribbon").removeClass("hidden");
			}
		}).fail( function() {
			Del_Overlay(elem + "_div");
			$("#" + elem + "_latest").html("AJAX Call Failed");
		});
	}).click();

	// Handler for pulling repo updates:
	$(".pull_repo").click(function() {
		elem = $(this).attr('id').split("_")[0];
		WebUI_Post("/manage/repo", __postdata("pull", elem));
	});
}

//======================================================================================================
// Javascript functions for "Management / Debian Updates"
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
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply_title").html( "Stage 1" );
	$("#apply_cancel").addClass("hidden");
	$("#apply-modal").modal("show");
	$.post("/manage/debian", __postdata('update'), function(data) {
		if (data.trim() == "RELOAD")
			document.location.reload(true);
		else if (data.trim() == "OK")
		{
			$("#apply_msg").html( $("#apply_default2").html() );
			$("#apply_title").html( "Stage 2" );
			$.post("/manage/debian", __postdata('parse'), function(data) {
				$("#apply-modal").modal("hide");
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
				$("#apply_msg").html("AJAX Call Failed");
				$("#apply_cancel").removeClass("hidden");
			});
		}
		else
		{
			$("#apply_msg").html(data);
			$("#apply_cancel").removeClass("hidden");
		}
	}).fail( function() {
		$("#apply_msg").html("AJAX Call Failed");
		$("#apply_cancel").removeClass("hidden");
	});
}

function Debian_Pull(mode, packages = [])
{
	element = $("#output_div");
	element.html("");
	$("#modal-close").addClass("disabled");
	last_response_len = 0;
	$.ajax({
		url: '/manage/debian',
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
// Javascript functions for "Management / Router Logs"
//======================================================================================================
function Init_Logs(pages)
{
	MaxPages=pages;
	$("#which").change(function() {
		$("#apply_msg").html( $("#apply_default").html() );
		$("#apply_cancel").addClass("hidden");
		$("#apply-modal").modal("show");
		which = $("#which").val();
		document.location.replace( document.location.href.split("?")[0] + (which != '' ? '?which=' + which : '') );
	});
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
	$("#search").on("propertychange input", function() { 
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
	});
	Logs_Page(1);
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
// Javascript functions for "Management / Backup Settings"
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
		url: '/manage/backup',
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
	$.post("/manage/backup", __postdata(restore_type), function(data) {
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

//======================================================================================================
// Javascript functions for "Management / WebUI Management"
//======================================================================================================
function Init_Management()
{
	$(".checkbox").bootstrapSwitch();

	// Handler to submit form settings:
	$("#apply_changes").click(function() {
		postdata = {
			'sid':               SID,
			'action':            'submit',
			'allow_local_http':  $("#allow_local_http").prop("checked") ? "Y" : "N",
			'allow_local_https': $("#allow_local_https").prop("checked") ? "Y" : "N",
			'pihole_http':       $("#pihole_http").prop("checked") ? "Y" : "N",
			'pihole_https':      $("#pihole_https").prop("checked") ? "Y" : "N",
		};
		//alert(JSON.stringify(postdata, null, 5)); return;
		WebUI_Post("/manage/management", postdata);
	});
}

//======================================================================================================
// Javascript functions for "Management / Attached Devices"
//======================================================================================================
function Init_Attached()
{
	$(".fa-power-off").click(function() {
		root = $(this).parent().parent().parent().parent();
		$.post("/manage/attached", __postdata("wol", root.find('.mac_addr').html()), function(data) {
			if (data == "RELOAD")
				document.location.reload(true);
			$(document).Toasts('create', {
		        class: data.trim() == "OK" ? 'bg-success' : 'bg-danger',
				title: 'Wake on LAN Command',
				autohide: true,
				delay: 5000,
				body: 'Result: ' + data,
			});
		}).fail(function() {
			alert("AJAX call failed!");
		});
	});
}
