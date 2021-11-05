<?php
site_menu();
$debug = false;
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Set Password</h3>
	</div>
	<div class="card-body">
		<div class="alert alert-danger hidden" id="alert_msg">
			<h5><i id="passwd_icon" class="fas fa-thumbs-down"></i> <span id="passwd_msg"></span></h5>
		</div>
		<div class="input-group mb-4">
			<label for="oldPass" class="col-sm-2 col-form-label">Old Password:</label>
			<div class="input-group col-sm-10">
				<input type="password" class="form-control" id="oldPass" name="oldPass"', $debug ? ' value="bananapi"' : '', ' placeholder="Old Password">
				<div class="input-group-append">
					<span class="input-group-text"><i class="fas fa-eye"></i></span>
				</div>
			</div>
		</div>
		<div class="input-group mb-4">
			<label for="newPass" class="col-sm-2 col-form-label">New Password:</label>
			<div class="input-group col-sm-10">
				<input type="password" class="form-control" id="newPass" name="newPass"', $debug ? ' value="meh"' : '', ' placeholder="Required">
				<div class="input-group-append">
					<span class="input-group-text"><i class="fas fa-eye"></i></span>
				</div>
			</div>
		</div>
		<div class="input-group mb-4">
			<label for="conPass" class="col-sm-2 col-form-label">Confirm Password:</label>
			<div class="input-group col-sm-10">
				<input type="password" class="form-control" id="conPass" name="conPass"', $debug ? ' value="meh"' : '', ' placeholder="Required">
				<div class="input-group-append">
					<span class="input-group-text"><i class="fas fa-eye"></i></span>
				</div>
			</div>
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-danger center_50" id="submit">Set Password</button></a>
	</div>
	<!-- /.card-body -->
</div>';
site_footer('Init_Creds();');
