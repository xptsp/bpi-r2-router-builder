function get_Basic_Data()
{
	$.getJSON("/api/status", function(results) {
		$("#temp").html(results.temp);
		$("#load0").html(results.load0);
		$("#load1").html(results.load1);
		$("#load2").html(results.load2);
		$("#system_uptime").html(results.system_uptime);
		$("#server_time").html(results.server_time);
		$("#unique_clients").html(results.unique_clients);
		$("#dns_queries_today").html(results.dns_queries_today);
		$("#ads_blocked_today").html(results.ads_blocked_today);
		$("#ads_percentage_today").html(results.ads_percentage_today);
		$("#domains_being_blocked").html(results.domains_being_blocked);
		$("#devices-spinner").remove();
		$("#num_of_devices").html(results.count);
		$("#connectivity-spinner").remove();
		$("#connectivity-text").html(results.wan_status);
		if (results.wan_status == "Online")
			$("#connectivity-div").removeClass("bg-danger");
		else
			$("#connectivity-div").addClass("bg-danger");
		if (results.temp > 60)
			$("#temp-danger").removeClass("invisible");
		else
			$("#temp-danger").addClass("invisible");
	});
}
