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
			'accepted': $("#accept").prop("checked") ? "Y" : "N",
			'password': $("#password").val(),
			'username': $("#username").val(),
		};
		//alert(JSON.stringify(postdata, null, 5)); return;

		// Perform our AJAX request to change the password:
		$.post("/login.php", postdata, function(data) {
			if (data.trim() != "OK")
				return Portal_Error(data);
			$("#success_modal").modal("show");
		}).fail(function() {
			Portal_Error("AJAX call failed!");
		});
	});
}

function Portal_Error(msg)
{
	$("#portal_msg").html(msg);
	$("#portal_box").slideDown(400, function() {
		timer = setInterval(function() {
			$("#portal_box").slideUp();
			clearInterval(timer);
		}, 5000);
	});
}
