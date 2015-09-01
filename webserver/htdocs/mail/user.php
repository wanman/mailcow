<?php
require_once("inc/header.inc.php");
if (isset($_SESSION['mailcow_cc_loggedin']) && $_SESSION['mailcow_cc_loggedin'] == "yes" && $_SESSION['mailcow_cc_role'] == "user") {
?>
<div class="container">

<div class="panel panel-default">
<div class="panel-heading">Change password</div>
<div class="panel-body">
<form class="form-horizontal" role="form" method="post">
	<input type="hidden" name="mailboxaction" value="setuserpassword">
	<input type="hidden" name="user_now" value="<?php echo $logged_in_as; ?>">
	<div class="form-group">
		<label class="control-label col-sm-3" for="user_old_pass">Current password:</label>
		<div class="col-sm-5">
		<input type="password" class="form-control" name="user_old_pass" id="user_old_pass" required>
		</div>
	</div>
	<div class="form-group">
		<label class="control-label col-sm-3" for="user_new_pass"><small>New password:</small></label>
		<div class="col-sm-5">
		<input type="password" class="form-control" name="user_new_pass" id="user_new_pass" required>
		</div>
	</div>
	<div class="form-group">
		<label class="control-label col-sm-3" for="user_new_pass2"><small>New password (repeat):</small></label>
		<div class="col-sm-5">
		<input type="password" class="form-control" name="user_new_pass2" id="user_new_pass2" required>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-9">
			<button type="submit" class="btn btn-default btn-raised btn-sm">Change password</button>
		</div>
	</div>
</form>
</div>
</div>

<ul><b>Did you know?</b> You can tag your mail address like "<?php echo explode('@', $logged_in_as)[0]; ?><b>+Private</b>@<?php echo explode('@', $logged_in_as)[1]; ?>" to automatically create a subfolder named "Private" in your inbox.<$
<br />

<div class="panel panel-default">
<div class="panel-heading">Generate time-limited aliases</div>
<div class="panel-body">
<form class="form-horizontal" role="form" method="post">
<input type="hidden" name="mailboxaction" value="timelimitedaliases">
<div class="table-responsive">
<table class="table table-striped" id="timelimitedaliases">
	<thead>
	<tr>
		<th>Alias</th>
		<th>Valid until</th>
		<th>Time left (HH:MM:SS)</th>
	</tr>
	</thead>
	<tbody>
<?php
$result = mysqli_query($link, "SELECT address, goto, TIMEDIFF(validity, NOW()) as timeleft, validity FROM spamalias WHERE goto='$logged_in_as' AND validity >= NOW() ORDER BY timeleft ASC");
while ($row = mysqli_fetch_array($result)) {
echo "<tr>
<td>", $row['address'], "</td>
<td>", $row['validity'], "</td>
<td>", $row['timeleft'], "</td>
</tr>";
}
?>
	</tbody>
</table>
</div>
<div class="form-group">
	<div class="col-sm-9">
		<label for="validity">Validity</label>
		<select name="validity" size="1">
			<option value="1">1 hour</option>
			<option value="6">6 hours</option>
			<option value="24">1 day</option>
			<option value="168">1 week</option>
			<option value="672">4 weeks</option>
		</select>
	</div>
</div>
<div class="form-group">
	<div class="col-sm-9">
		<button type="submit" name="action" value="generate" class="btn btn-success btn-sm">Generate random alias</button>
		<button type="submit" name="action" value="delete" class="btn btn-danger btn-sm">Delete all aliases</button>
		<button type="submit" name="action" value="extend" class="btn btn-default btn-sm">Add 1 hour to all aliases</button>
	</div>
</div>
</form>
</div>
</div>

<div class="panel panel-default">
<div class="panel-heading">Calendars and Contacts</div>
<div class="panel-body">
<div class="table-responsive">
<table class="table table-striped" id="domainadminstable">
	<thead>
	<tr>
		<th>Components</th>
		<th>URI</th>
		<th>Display name</th>
		<th>Export</th>
		<th>Link</th>
	</tr>
	</thead>
	<tbody>
<?php
$result = mysqli_query($link, "SELECT components, uri, displayname FROM calendars WHERE principaluri='principals/$logged_in_as'");
while ($row = mysqli_fetch_array($result)) {
echo "<tr><td>", str_replace(array('VEVENT', 'VTODO', ','), array('Calendar', 'Tasks', ', '), $row['components']),
"</td><td>", $row['uri'],
"</td><td>", $row['displayname'],
"</td><td><a href=\"https://dav.".$MYHOSTNAME_1.".".$MYHOSTNAME_2."/calendars/$logged_in_as/".$row['uri']."?export\">Download (ICS format)</a>",
"</td><td><a href=\"https://dav.".$MYHOSTNAME_1.".".$MYHOSTNAME_2."/calendars/$logged_in_as/".$row['uri']."\">Open</a>",
"</td></tr>";
}
$result = mysqli_query($link, "SELECT uri, displayname FROM addressbooks WHERE principaluri='principals/$logged_in_as'");
while ($row = mysqli_fetch_array($result)) {
echo "<tr><td>Address book</td><td>", $row['uri'],
"</td><td>", $row['displayname'],
"</td><td><a href=\"https://dav.".$MYHOSTNAME_1.".".$MYHOSTNAME_2."/addressbooks/$logged_in_as/".$row['uri']."?export\">Download (VCF format)</a>",
"</td><td><a href=\"https://dav.".$MYHOSTNAME_1.".".$MYHOSTNAME_2."/addressbooks/$logged_in_as/".$row['uri']."\">Open</a>",
"</td></tr>";
}
?>
	</tbody>
</table>
</div>
</div>
</div>

<div class="panel panel-default">
<div class="panel-heading">Fetch mails</div>
<div class="panel-body">
<p>This is <b>not a recurring task</b>. This feature will perform a one-way synchronisation and leave the remote server as it is, no mails will be deleted on either sides.</p>
<p>The first synchronisation may take a while.</p>
<small>
<form class="form-horizontal" role="form" method="post">
<input type="hidden" name="mailboxaction" value="addfetchmail">
	<div class="form-group">
		<label class="control-label col-sm-2" for="imap_host">IMAP Host (with Port)</label>
		<div class="col-sm-10">
		<input type="text" class="form-control" name="imap_host" id="imap_host" placeholder="remote.example.com:993">
		</div>
	</div>
	<div class="form-group">
		<label class="control-label col-sm-2" for="imap_username">IMAP username:</label>
		<div class="col-sm-10">
		<input type="text" class="form-control" name="imap_username" id="imap_username">
		</div>
	</div>
	<div class="form-group">
		<label class="control-label col-sm-2" for="imap_password">IMAP password:</label>
		<div class="col-sm-10">
		<input type="password" class="form-control" name="imap_password" id="imap_password">
		</div>
	</div>
	<div class="form-group">
		<label class="control-label col-sm-2" for="imap_exclude">Exclude folders:</label>
		<div class="col-sm-10">
		<input type="text" class="form-control" name="imap_exclude" id="imap_exclude" placeholder="Folder1, Folder2, Folder3">
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-2 col-sm-10">
			<div class="radio">
				<label><input type="radio" name="imap_enc" value="/ssl" checked>SSL</label>
			</div>
			<div class="radio">
				<label><input type="radio" name="imap_enc" value="/tls" >STARTTLS</label>
			</div>
			<div class="radio">
				<label><input type="radio" name="imap_enc" value="none">None (this will try STARTTLS)</label>
			</div>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-2 col-sm-10">
			<button type="submit" class="btn btn-success btn-sm">Sync now</button>
		</div>
	</div>
</form>
</small>
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
