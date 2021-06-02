var SID;
var LoginTimer;
var RefreshTimer;

function Init_Site(sid)
{
	SID=sid;
	$("#login_close").click(Login_Close);
	$("#login_submit").click(Login_Submit);
	$("input[data-bootstrap-switch]").each(function(){
		$(this).bootstrapSwitch('state', $(this).prop('checked')).bootstrapSwitch('size', 'small');
    });
}

function Init_Home()
{
	Home_Data();
	RefreshTimer = setInterval(Home_Data, 5000);
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
