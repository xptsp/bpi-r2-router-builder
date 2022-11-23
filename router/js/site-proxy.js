//======================================================================================================
// Javascript functions for "Management / Credentials"
//======================================================================================================
function Init_Filters()
{
	// Handler to submit password-change form:
	$("#apply_changes").click(function() {
		var postdata = $('.filters:checkbox:checked').map(function() {
			return this.value;
		}).get();
		//alert(JSON.stringify(postdata, null, 5)); return;
		WebUI_Post("/proxy/filters", __postdata("submit", postdata));
	});
}
