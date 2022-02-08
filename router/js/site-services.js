//======================================================================================================
// Javascript functions for "Services / UPnP Setup":
//======================================================================================================
function Init_UPnP()
{
	$("#upnp_enable").bootstrapSwitch().on('switchChange.bootstrapSwitch', function(event, state) {
		if (state == true)
			$("#upnp_div").slideDown(400);
		else
			$("#upnp_div").slideUp(400);
	});
	$("#upnp_refresh").click(function() {
		$.post('/services/upnp', __postdata("list"), function(data) {
			if (data == "RELOAD")
				document.location.reload(true);
			else
				$("#upnp-table").html(data);
		});
	}).click();
	$("#upnp_submit").click(UPnP_Submit);
}

function UPnP_Submit()
{
	// Hide confirmation dialog if shown:
	$("#confirm-modal").modal("hide");

	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':      SID,
		'action':   'submit',
		'enable':   $("#upnp_enable").prop("checked") ? "Y" : "N",
		'secure':   $("#upnp_secure").prop("checked") ? "Y" : "N",
		'natpmp':   $("#upnp_natpmp").prop("checked") ? "Y" : "N",
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply-modal").modal("show");
	$("#apply_cancel").addClass("hidden");
	$.post("/services/upnp", postdata, function(data) {
		data = data.trim();
		if (data == "RELOAD" || data == "OK")
			document.location.reload(true);
		else
		{
			$("#apply_msg").html(data);
			$(".alert_control").removeClass("hidden");
		}
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$("#apply_cancel").removeClass("hidden");
	});
}

//======================================================================================================
// Javascript functions for "Advanced / Mosquitto Settings"
//======================================================================================================
function Init_Notify()
{
	$(".checkbox").bootstrapSwitch();
	$("#enable_mosquitto").bootstrapSwitch().on('switchChange.bootstrapSwitch', function(event, state) {
		if (state == true)
			$("#mosquitto_options").slideDown(400);
		else
			$("#mosquitto_options").slideUp(400);
	});
	$('.ip_address').inputmask("ip");
	$(".ip_port").inputmask("integer", {min:0, max:65535});
	$("#apply_changes").click( Notify_Apply );
}

function Notify_Apply()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':        SID,
		'action':     'submit',
		'enabled':    $("#enable_mosquitto").prop("checked") ? "Y" : "N",
		'ip_addr':    $("#ip_addr").val(),
		'ip_port':    $("#ip_port").val(),
		'username':   $("#username").val(),
		'password':   $("#password").val(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply-modal").modal("show");
	$("#apply_cancel").addClass("hidden");
	$.post("/services/notify", postdata, function(data) {
		data = data.trim();
		if (data == "RELOAD")
			document.location.reload(true);
		else if (data == "OK")
			$("#apply-modal").modal("hide");
		else
		{
			$("#apply_msg").html(data);
			$(".alert_control").removeClass("hidden");
		}
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$("#apply_cancel").removeClass("hidden");
	});
}

//======================================================================================================
// Javascript functions for "Advanced / Bandwidth"
//======================================================================================================
function Init_Bandwidth(tx, rx)
{
	barTX = tx;
	barRX = rx;
	barChart = false;
	$("#update_chart").click(Bandwidth_Update).click();
	$("#interface").change(Bandwidth_Update);
	$("#mode").change(Bandwidth_Update);
	$("#refresh_switch").bootstrapSwitch();
	$("#refresh_switch").on('switchChange.bootstrapSwitch', function(event, state) {
		$("#apply_msg").html( $("#apply_default").html() );
		$("#apply_cancel").addClass("hidden");
		$("#apply-modal").modal("show");
		$.post("/services/bandwidth", __postdata(state ? 'enable' : 'disable'), function(data) {
			$("#apply-modal").modal("hide");
			data = data.trim();
			if (data == "RELOAD")
				document.location.reload(true);
			else if (data == "disable")
				$("#disabled_div").slideDown(400);
			else if (data == "enable")
				$("#disabled_div").slideUp(400);
		});
	});
	$("#toggle_service").click(function() {
		$("#refresh_switch").bootstrapSwitch('state', true);
	});
}

function Bandwidth_Update()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':        SID,
		'action':     $("#mode").val(),
		'iface':      $("#interface").val(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$.post("/services/bandwidth", postdata, function(data) {
		if (data.reload == true)
			document.location.reload(true);
		if (Object.keys(data.rx).length == 0)
		{
			$("#table_data").addClass("hidden");
			$("#table_empty").removeClass("hidden");
		}
		else
		{
			$("#table_data").removeClass("hidden");
			$("#table_empty").addClass("hidden");
		}
		$("#table_header").html( data.title );
		$("#table_data").html( data.table );
		if (barChart != false)
			barChart.destroy();
		barChart = new Chart($("#barChart"), {
			type: "bar",
			data: {
			  labels  : Object.values(data.label),
			  datasets: [
					{
						label               : barTX,
						backgroundColor     : 'rgba(60,141,188,0.9)',
						borderColor         : 'rgba(60,141,188,0.8)',
						pointRadius          : false,
						pointColor          : '#3b8bba',
						pointStrokeColor    : 'rgba(60,141,188,1)',
						pointHighlightFill  : '#fff',
						pointHighlightStroke: 'rgba(60,141,188,1)',
						data                : Object.values(data.tx)
					},
					{
						label               : barRX,
						backgroundColor     : 'rgba(210, 214, 222, 1)',
						borderColor         : 'rgba(210, 214, 222, 1)',
						pointRadius         : false,
						pointColor          : 'rgba(210, 214, 222, 1)',
						pointStrokeColor    : '#c1c7d1',
						pointHighlightFill  : '#fff',
						pointHighlightStroke: 'rgba(220,220,220,1)',
						data                : Object.values(data.rx)
					},
				]
			},
			options: {
				responsive              : true,
				maintainAspectRatio     : false,
				datasetFill             : false,
				tooltips: {
					borderWidth: 1,
					borderColor: "white",
					callbacks: {
						label: function(tooltipItem, chartdata) {
							var dataItem = chartdata.datasets[ tooltipItem.datasetIndex ].data[ tooltipItem.index ];
							var labelItem = chartdata.datasets[ tooltipItem.datasetIndex ].label;
							return labelItem + ": " + dataItem + " " + data.unit;
						}
					}
				},
				scales: {
					yAxes: [{
						display: true,
						ticks: {
							beginAtZero: true,
		                    callback: function(value, index, values) {
		                        return value + " " + data.unit;
		                    }
						}
					}]
				}
			}
		});
	}).fail(function() {
		$("#table_data").removeClass("hidden");
		$("#table_empty").addClass("hidden");
		$("#table_data").html('<tr><td colspan="4"><center><strong>AJAX Call Failed</strong></center></td></tr>');
	});
}
