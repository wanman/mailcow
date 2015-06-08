<?php
require_once("inc/header.inc.php");
if (isset($_SESSION['mailcow_cc_loggedin']) && $_SESSION['mailcow_cc_loggedin'] == "yes" && $_SESSION['mailcow_cc_role'] == "user") {
?>
<div class="container">

<div class="panel panel-default">
<div class="panel-heading">Change password</div>
<div class="panel-body">
<form method="post">
	<input type="hidden" name="mailboxaction" value="setuserpassword">
	<input type="hidden" name="user_now" value="<?php echo $logged_in_as; ?>">
	<div class="form-group">
		<label class="control-label col-sm-2" for="user_old_pass">Password now:</label>
		<div class="col-sm-10">
		<input type="password" class="form-control" name="user_old_pass" id="user_old_pass">
		</div>
	</div>
	<div class="form-group">
		<label class="control-label col-sm-2" for="user_new_pass">New password:</label>
		<div class="col-sm-10">
		<input type="password" class="form-control" name="user_new_pass" id="user_new_pass">
		</div>
	</div>
	<div class="form-group">
		<label class="control-label col-sm-2" for="user_new_pass2">New password (repeat):</label>
		<div class="col-sm-10">
		<input type="password" class="form-control" name="user_new_pass2" id="user_new_pass2">
		</div>
	</div>
	<div class="form-group">        
		<div class="col-sm-offset-2 col-sm-10">
			<button type="submit" class="btn btn-default btn-raised btn-sm">Save changes</button>
		</div>
	</div>
</form>
</div>
</div>

<div class="panel panel-default">
<div class="panel-heading">Migrate mail</div>
<div class="panel-body">
<form method="post">
	<div class="form-group">
		<label class="control-label col-sm-4" for="imapc_host">IMAP host <small>(will be created if missing)</small>:</label>
		<div class="col-sm-8">
		<input type="text" class="form-control" name="imapc_host" id="imapc_host">
		</div>
	</div>
	<div class="form-group">
		<label class="control-label col-sm-4" for="imapc_user">IMAP login name:</label>
		<div class="col-sm-8">
		<input type="text" class="form-control" name="imapc_user" id="imapc_user">
		</div>
	</div>
	<div class="form-group">
		<label class="control-label col-sm-4" for="imapc_pass">IMAP password:</label>
		<div class="col-sm-8">
		<input type="password" class="form-control" name="imapc_pass" id="imapc_pass">
		</div>
	</div>
	<br /><br />
	<div class="form-group">
		<div class="col-sm-offset-4 col-sm-8">
			<div class="checkbox">
			<label><input type="checkbox" name="fetch_active"> Active</label>
			</div>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-8">
			<button type="submit" class="btn btn-default btn-raised btn-sm">Save changes</button>
		</div>
	</div>
</form>
</div>
</div>
</div>
<?php }
else {
	header('Location: admin.php');
}
 ?>
<script src="//code.jquery.com/jquery-1.11.3.min.js"></script>
<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
<script src="js/ripples.min.js"></script>
<script src="js/material.min.js"></script>
<script>
$(document).ready(function() {
        $.material.init();
});
</script>
</body>
</html>
