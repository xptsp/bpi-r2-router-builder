//======================================================================================================
// Javascript functions for "Services / UPnP Setup":
//======================================================================================================
function __Services_Init(service)
{
	$("#refresh_switch").bootstrapSwitch();
	$("#refresh_switch").on('switchChange.bootstrapSwitch', function(event, state) {
		__Service_Call( state ? 'enable' : 'disable', service, state );
	});
	$("#service_status").click(function() {
		__Service_Call( 'status', service );
	});
	$("#service_start").click(function() {
		__Service_Call( 'start', service );
	});
	$("#service_stop").click(function() {
		__Service_Call( 'stop', service );
	});
}

function __Service_Call(cmd, service, state = null)
{
	WebUI_Post("/services", __postdata(cmd, service), state, true);
}

//======================================================================================================
// Javascript functions for "Services / UPnP Setup":
//======================================================================================================
function Init_UPnP()
{
	__Services_Init('miniupnpd');

	// Handler to refresh current UPnP port forwards:
	$("#upnp_refresh").click(function() {
		$.post('/services/upnp', __postdata("list"), function(data) {
			if (data == "RELOAD")
				document.location.reload(true);
			else
				$("#upnp-table").html(data);
		});
	}).click();

	// Handler to submit UPnP settings:
	$("#upnp_submit").click(function() {
		postdata = {
			'sid':           SID,
			'action':        'submit',
			'secure_mode':   $("#secure_mode").prop("checked") ? "Y" : "N",
			'enable_natpmp': $("#enable_natpmp").prop("checked") ? "Y" : "N",
			'ext_ifname':    $("#ext_ifname").val(),
			'listening_ip':  $("#listening_on").val().join(","),
		};
		//alert(JSON.stringify(postdata, null, 5)); return;
		WebUI_Post("/services/upnp", postdata);
	});
}

//======================================================================================================
// Javascript functions for "Advanced / Bandwidth"
//======================================================================================================
function Init_Bandwidth(tx, rx)
{
	__Services_Init('vnstat');
	barTX = tx;
	barRX = rx;
	barChart = false;

	// Handler to submit update bar graph chart:
	$("#update_chart").click(function() {
		// Assemble the post data for the AJAX call:
		postdata = {
			'sid':        SID,
			'action':     $("#mode").val(),
			'iface':      $("#interface").val(),
		};
		//alert(JSON.stringify(postdata, null, 5)); return;

		// Perform our AJAX request to update the bar graph chart:
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
	}).click();

	// Handlers that change what interface and mode the bar graph shows: 
	$("#interface").change(function() {
		$("#update_chart").click();
	});
	$("#mode").change(function() {
		$("#update_chart").click();
	});
}

//======================================================================================================
// Javascript functions for "Services / UPnP Setup":
//======================================================================================================
function Init_Multicast()
{
	__Services_Init('multicast-relay');

	// Handler to submit form settings:
	$("#multicast_submit").click(function() {
		postdata = {
			'sid':       SID,
			'action':    'submit',
			'listen_on': $("#listening_on").val().join(","),
		};
		//alert(JSON.stringify(postdata, null, 5)); return;
		WebUI_Post("/services/multicast", postdata); 
	});
}

//======================================================================================================
// Javascript functions for "Services / UPnP Setup":
//======================================================================================================
function Init_Compose()
{
	__Services_Init('docker-compose');
	$("#compose_submit").click(function() {
		WebUI_Post("/services/compose", __postdata("submit", $("#contents-div").val()));
	}); 
}

//======================================================================================================
// Javascript functions for "Services / Dynamic DNS Client":
//======================================================================================================
function Init_DDClient()
{
	__Services_Init('ddclient');
	$("#compose_submit").click(function() {
		WebUI_Post("/services/ddclient", __postdata("submit", $("#contents-div").val()));
	}); 
}

//======================================================================================================
// Javascript functions for "Services / Transmission Daemon":
//======================================================================================================
function Init_Transmission()
{
	__Services_Init('transmission-daemon');
	$("#transmission_submit").click(function() {
		// Assemble the post data for the AJAX call:
		postdata = {
			'sid':         SID,
			'action':      'submit',
			'TRANS_PORT':  $("#td_port").val(),
			'TRANS_USER':  $("#username").val(),
			'TRANS_PASS':  $("#password").val(),
			'TRANS_WEBUI': $("#webui").val(),
		};
		//alert(JSON.stringify(postdata, null, 5)); return;
		WebUI_Post("/services/transmission", postdata); 
	});
}

