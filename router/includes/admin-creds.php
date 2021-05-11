<?php
site_menu();

#######################################################################################################
# Change Password form:
#######################################################################################################
echo '
<div class="card card-info">
	<div class="card-header">
		<h3 class="card-title">Set Password</h3>
	</div>
	<div class="card-body">
		<div class="alert alert-danger hidden" id="alert_msg">
			<h5><i id="passwd_icon" class="fas fa-thumbs-down"></i> <span id="passwd_msg"></span></h5>
		</div>
		<div class="input-group mb-4">
			<label for="oldPass" class="col-sm-2 col-form-label">Old Password:</label>
			<div class="col-sm-10">
				<input type="password" class="form-control" id="oldPass" value="bananapi" placeholder="Old Password">
			</div>
		</div>
		<div class="input-group mb-4">
			<label for="newPass" class="col-sm-2 col-form-label">New Password:</label>
			<div class="col-sm-10">
				<input type="password" class="form-control" id="newPass" value="meh" placeholder="Required">
			</div>
		</div>
		<div class="input-group mb-4">
			<label for="conPass" class="col-sm-2 col-form-label">Confirm Password:</label>
			<div class="col-sm-10">
				<input type="password" class="form-control" id="conPass" value="meh"  placeholder="Required">
			</div>
		</div>
		<button type="button" class="btn btn-block btn-outline-danger center_50" id="submit">Set Password</button>
	</div>
	<!-- /.card-body -->
</div>';
      
site_footer('
	$("#submit").click(Password_Submit);
');	
