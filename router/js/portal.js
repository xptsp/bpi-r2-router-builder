var timer;

//======================================================================================================
// Javascript functions for "Login" page:
//======================================================================================================
function Init_Portal(mode)
{
	$("#submit_button").click(function() {
		// Assemble the post data for the AJAX call:
		postdata = {
			'action':   mode,
			'password': $("#password").val(),
			'username': $("#username").val(),
		};
		//alert(JSON.stringify(postdata, null, 5)); return;

		// Perform our AJAX request to change the password:
		$.post("/login.php", postdata, function(data) {
			alert(data);
			if (data.trim() != "OK")
				return Portal_Error(data);
			Portal_Error("Success!!");
		}).fail(function() {
			Portal_Error("AJAX call failed!");
		});
	});
}

function Portal_Error(msg)
{
	$("#dhcp_error_msg").html(msg);
	$("#dhcp_error_box").slideDown(400, function() {
		timer = setInterval(function() {
			$("#dhcp_error_box").slideUp();
			clearInterval(timer);
		}, 5000);
	});
}
