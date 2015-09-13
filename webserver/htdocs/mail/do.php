<?php
require_once("inc/header.inc.php");
?>
<div class="container">
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title">Edit</h3>
				</div>
				<div class="panel-body">
<?php
require_once "inc/triggers.inc.php";
if (isset($_SESSION['mailcow_cc_loggedin']) && $_SESSION['mailcow_cc_loggedin'] == "yes" ) {
	if (isset($_GET['adddomain'])) {
?>
				<h4>Add domain</h4>
				<form class="form-horizontal" role="form" method="post">
					<div class="form-group">
						<label class="control-label col-sm-2" for="domain">Domain name:</label>
						<div class="col-sm-10">
						<input type="text" pattern="\b((?=[a-z0-9-]{1,63}\.)[a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,63}\b" class="form-control" name="domain" id="domain" placeholder="Domain to receive mail for">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="description">Description:</label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="description" id="description" placeholder="Description">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="aliases">Max. aliases:</label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="aliases" id="aliases" value="200">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="mailboxes">Max. mailboxes:</label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="mailboxes" id="mailboxes" value="50">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="maxquota">Max. size per mailbox (MB):</label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="maxquota" id="maxquota" value="4096">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="quota">Domain quota:</label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="quota" id="quota" value="10240">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="backupmx"> Backup MX</label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" checked> Active</label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="adddomain" class="btn btn-success btn-sm">Submit</button>
						</div>
					</div>
				</form>
<?php }
	elseif (isset($_GET['addalias'])) {
?>
				<h4>Add alias</h4>
				<form class="form-horizontal" role="form" method="post">
					<div class="form-group">
						<label class="control-label col-sm-2" for="address">Alias address <small>(full email address OR @domain.tld for <span style='color:#ec466a'>catch-all</span>)</small>:</label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="address" id="address">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="goto" placeholder="first@example.net, second@example.net">Destination address(es) - comma separated:</label>
						<div class="col-sm-10">
							<textarea class="form-control" rows="10" name="goto"></textarea>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" checked> Active</label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="addalias" class="btn btn-success btn-sm">Submit</button>
						</div>
					</div>
				</form>
<?php }
	elseif (isset($_GET['editalias'])) {
		if (!filter_var($_GET["editalias"], FILTER_VALIDATE_EMAIL) || empty($_GET["editalias"])) {
			echo 'Your provided alias name is invalid';
		}
		else {
			$editalias = mysqli_real_escape_string($link, $_GET["editalias"]);
			if (mysqli_fetch_array(mysqli_query($link, "SELECT address, domain FROM alias WHERE address='$editalias' AND domain IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'='$logged_in_role';"))) {
			$result = mysqli_fetch_assoc(mysqli_query($link, "SELECT active, goto FROM alias WHERE address='$editalias'"));
?>
				<h4>Change alias attributes for <strong><?php echo $editalias ?></strong></h4>
				<br />
				<form class="form-horizontal" role="form" method="post">
				<input type="hidden" name="address" value="<?php echo $editalias ?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="name">Destination address(es) <small>(comma-separated values)</small>:</label>
						<div class="col-sm-10">
							<textarea class="form-control" rows="10" name="goto"><?=$result['goto'] ?></textarea>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" <?php if (isset($result['active']) && $result['active']=="1") { echo "checked"; }; ?>> Active</label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="editalias" class="btn btn-success btn-sm">Submit</button>
						</div>
					</div>
				</form>
<?php
			}
			else {
				echo 'Action not supported.';
			}
		}
	}
	elseif (isset($_GET['editdav'])) {
		if (!filter_var($_GET["editdav"], FILTER_VALIDATE_EMAIL) || empty($_GET["editdav"])) {
			echo 'Your provided DAV user is invalid';
		}
		else {
			$editdav = mysqli_real_escape_string($link, $_GET["editdav"]);
			if ($editdav == $logged_in_as) {
?>
				<h4>Change DAV folder properties</h4>
				<br />
				<form class="form-horizontal" role="form" method="post">
				<div class="table-responsive">
				<table class="table table-striped" id="domainadminstable">
					<thead>
					<tr>
						<th>Type</th>
						<th>Display name</th>
						<th>Read access</th>
						<th>Write access</th>
					</tr>
					</thead>
					<tbody>
<?php
				$result = mysqli_query($link, "SELECT id, displayname FROM calendars WHERE principaluri='principals/$logged_in_as'");
				while ($row_cal = mysqli_fetch_array($result)) {
				echo '<tr>
				<td>Calendar, Tasks</td>
				<td><input type="text" name="cal_displayname['.$row_cal['id'].']" value="', $row_cal['displayname'], '"></td>
				<td>
					<select style="width:100%" name="cal_ro_share['.$row_cal['id'].'][]" size="5" multiple>';
$result = mysqli_query($link, "SELECT username FROM users
	WHERE id IN (SELECT member_id FROM groupmembers
		WHERE principal_id=(SELECT id FROM principals
			WHERE uri='principals/$logged_in_as/calendar-proxy-read'))
	AND username!='$logged_in_as'");
while ($row_cal_ro_selected = mysqli_fetch_array($result)) {
	echo '<option selected>', $row_cal_ro_selected['username'], '</option>';
}
$result = mysqli_query($link, "SELECT username FROM users
	WHERE id NOT IN (SELECT member_id FROM groupmembers
		WHERE principal_id=(SELECT id FROM principals
			WHERE uri='principals/$logged_in_as/calendar-proxy-read'))
	AND username!='$logged_in_as'");	
while ($row_cal_ro_unselected = mysqli_fetch_array($result)) {
	echo '<option>', $row_cal_ro_unselected['username'], '</option>';
}
					echo '</select>
				</td>
				<td>
					<select style="width:100%" name="cal_rw_share['.$row_cal['id'].'][]" size="5" multiple>';
$result = mysqli_query($link, "SELECT username FROM users
	WHERE id IN (SELECT member_id FROM groupmembers
		WHERE principal_id=(SELECT id FROM principals
			WHERE uri='principals/$logged_in_as/calendar-proxy-write'))
	AND username!='$logged_in_as'");
while ($row_cal_rw_selected = mysqli_fetch_array($result)) {
	echo '<option selected>', $row_cal_rw_selected['username'], '</option>';
}
$result = mysqli_query($link, "SELECT username FROM users
	WHERE id NOT IN (SELECT member_id FROM groupmembers
		WHERE principal_id=(SELECT id FROM principals
			WHERE uri='principals/$logged_in_as/calendar-proxy-write'))
	AND username!='$logged_in_as'");
while ($row_cal_rw_unselected = mysqli_fetch_array($result)) {
	echo '<option>', $row_cal_rw_unselected['username'], '</option>';
}
					echo '</select>
				</td>
				</tr>';
				}
				$result = mysqli_query($link, "SELECT id, displayname FROM addressbooks WHERE principaluri='principals/$logged_in_as'");
				while ($row_adb = mysqli_fetch_array($result)) {
				echo '<tr>
				<td>Address book</td>
				<td><input type="text" name="adb_displayname['.$row_adb['id'].']" value="', $row_adb['displayname'], '"></td>
				<td></td>
				<td></td>
				</tr>';
				}
				?>
					</tbody>
				</table>
				</div>
				<div class="form-group">
					<div class="col-sm-offset-2 col-sm-10">
						<button type="submit" name="trigger_mailbox_action" value="editdav" class="btn btn-success btn-sm">Apply</button>
					</div>
				</div>
				</form>
	<?php
			}
			else {
				echo 'Your provided username does not exist or cannot be edited.';
			}
		}
	}
	elseif (isset($_GET['addaliasdomain'])) {
?>
				<h4>Add domain alias</h4>
				<form class="form-horizontal" role="form" method="post">
					<div class="form-group">
						<label class="control-label col-sm-2" for="alias_domain">Alias domain:</label>
						<div class="col-sm-10">
							<select name="alias_domain" size="1">
<?php
$result = mysqli_query($link, "SELECT domain FROM domain WHERE domain IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'='$logged_in_role'");
while ($row = mysqli_fetch_array($result)) {
	echo "<option>", $row['domain'], "</option>";
}
?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="name">Target domain:</label>
						<div class="col-sm-10">
							<select name="target_domain" size="1">
<?php
$result = mysqli_query($link, "SELECT domain FROM domain WHERE domain IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'='$logged_in_role'");
while ($row = mysqli_fetch_array($result)) {
	echo "<option>", $row['domain'], "</option>";
}
?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" checked> Active</label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="addaliasdomain" class="btn btn-success btn-sm">Submit</button>
						</div>
					</div>
				</form>
<?php }
	elseif (isset($_GET['editdomainadmin'])) {
		if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $_GET["editdomainadmin"])) || empty($_GET["editdomainadmin"])) {
			echo 'Your provided domain administrator username is invalid.';
		}
		else {
			$editdomainadmin = mysqli_real_escape_string($link, $_GET["editdomainadmin"]);
			if (mysqli_fetch_array(mysqli_query($link, "SELECT username FROM domain_admins")) && $logged_in_role == "admin") {
			$result = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM domain_admins WHERE username='$editdomainadmin'"));
	?>
				<h4>Change assigned domains for domain admin <strong><?php echo $editdomainadmin ?></strong></h4>
				<br />
				<form class="form-horizontal" role="form" method="post">
				<input type="hidden" name="username" value="<?php echo $editdomainadmin ?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="name">Target domain <small>(hold CTRL to select multiple domains)</small>:</label>
						<div class="col-sm-10">
							<select name="domain[]" size="5" multiple>
<?php
$resultselect = mysqli_query($link, "SELECT domain FROM domain");
while ($row = mysqli_fetch_array($resultselect)) {
	echo "<option>", $row['domain'], "</option>";
}
?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" <?php if (isset($result['active']) && $result['active']=="1") { echo "checked"; }; ?>> Active</label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="editdomainadmin" class="btn btn-success btn-sm">Submit</button>
						</div>
					</div>
				</form>
	<?php
			}
			else {
				echo 'Action not supported.';
			}
		}
	}
	elseif (isset($_GET['addmailbox'])) {
	?>
				<h4>Add a mailbox</h4>
				<form class="form-horizontal" role="form" method="post">
					<div class="form-group">
						<label class="control-label col-sm-2" for="local_part">Mailbox Alias (left part of mail address) <small>(alphanumeric)</small>:</label>
						<div class="col-sm-10">
							<input type="text" pattern="[a-zA-Z0-9.- ]+" class="form-control" name="local_part" id="local_part" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="name">Select domain:</label>
						<div class="col-sm-10">
							<select name="domain" size="1">
<?php
$result = mysqli_query($link, "SELECT domain FROM domain WHERE domain IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'='$logged_in_role'");
while ($row = mysqli_fetch_array($result)) {
	echo "<option>", $row['domain'], "</option>";
}
?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="name">Name:</label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="name" id="name">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="quota">Quota (MB), 0 = unlimited:</label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="quota" id="quota" value="1024">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password">Password:</label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password" id="password" placeholder="">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password2">Password (repeat):</label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password2" id="password2" placeholder="">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="default_cal">Default calendar name:</label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="default_cal" id="default_cal" value="Calendar">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="default_card">Default address book name:</label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="default_card" id="default_card" value="Address book">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" checked> Active</label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="addmailbox" class="btn btn-success btn-sm">Submit</button>
						</div>
					</div>
				</form>
	<?php
	}
	elseif (isset($_GET['editdomain'])) {
		if (!is_valid_domain_name($_GET["editdomain"]) || empty($_GET["editdomain"])) {
			echo 'Your provided domain name is invalid.';
		}
		else {
			$editdomain = mysqli_real_escape_string($link, $_GET["editdomain"]);
			if (mysqli_fetch_array(mysqli_query($link, "SELECT domain FROM domain WHERE domain='$editdomain' AND ((domain IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'='$logged_in_role'))"))) {
			$result = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM domain WHERE domain='$editdomain'"));
	?>
				<h4>Change settings for domain <strong><?php echo $editdomain ?></strong></h4>
				<form class="form-horizontal" role="form" method="post">
				<input type="hidden" name="domain" value="<?php echo $editdomain ?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="description">Description:</label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="description" id="description" value="<?php echo htmlspecialchars($result['description']); ?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="aliases">Max. aliases:</label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="aliases" id="aliases" value="<?php echo $result['aliases']; ?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="mailboxes">Max. mailboxes:</label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="mailboxes" id="mailboxes" value="<?php echo $result['mailboxes']; ?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="maxquota">Max. size per mailbox (MB):</label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="maxquota" id="maxquota" value="<?php echo $result['maxquota']; ?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="quota">Domain quota:</label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="quota" id="quota" value="<?php echo $result['quota']; ?>">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="backupmx" <?php if (isset($result['backupmx']) && $result['backupmx']=="1") { echo "checked"; }; ?>> Backup MX</label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" <?php if (isset($result['active']) && $result['active']=="1") { echo "checked"; }; ?>> Active</label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="editdomain" class="btn btn-success btn-sm">Submit</button>
						</div>
					</div>
				</form>
	<?php
			}
			else {
				echo 'Your provided domain name does not exist or cannot be edited.';
			}
		}
	}
	elseif (isset($_GET['editmailbox'])) {
		if (!filter_var($_GET["editmailbox"], FILTER_VALIDATE_EMAIL) || empty($_GET["editmailbox"])) {
			echo 'Your provided mailbox name is invalid.';
		}
		else {
			$editmailbox = mysqli_real_escape_string($link, $_GET["editmailbox"]);
			if (mysqli_result(mysqli_query($link, "SELECT username, domain FROM mailbox WHERE username='$editmailbox' AND ((domain IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'='$logged_in_role'))"))) {
			$result = mysqli_fetch_assoc(mysqli_query($link, "SELECT username, name, round(sum(quota / 1048576)) as quota, active FROM mailbox WHERE username='$editmailbox'"));
	?>
				<h4>Change settings for mailbox <strong><?php echo $editmailbox ?></strong></h4>
				<form class="form-horizontal" role="form" method="post">
				<input type="hidden" name="username" value="<?php echo $result['username']; ?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="name">Name:</label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="name" id="name" value="<?php echo $result['name']; ?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="quota">Quota (MB), 0 = unlimited:</label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="quota" id="quota" value="<?php echo $result['quota']; ?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password">Password:</label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password" id="password" placeholder="Leave empty to not change password">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password2">Password (repeat):</label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password2" id="password2">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" <?php if (isset($result['active']) && $result['active']=="1") { echo "checked"; }; ?>> Active</label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="editmailbox" class="btn btn-success btn-sm">Submit</button>
						</div>
					</div>
				</form>
	<?php
			}
			else {
				echo 'Your provided mailbox does not exist.';
			}
		}
	}
	elseif (isset($_GET["deletedomain"])) {
		if (!is_valid_domain_name($_GET["deletedomain"]) || empty($_GET["deletedomain"])) {
			echo 'Your provided domain name is invalid.';
		}
		else {
			$deletedomain = mysqli_real_escape_string($link, $_GET["deletedomain"]);
			if (mysqli_result(mysqli_query($link, "SELECT domain FROM domain WHERE domain='$deletedomain' AND ((domain IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'='$logged_in_role'))"))) {
				echo '<div class="alert alert-warning" role="alert"><strong>Warning:</strong> You are about to delete a domain!</div>';
				echo "<p>This will also delete domain alises assigned to the domain</p>";
				echo "<p><strong>Domain must be empty to be deleted!</b></p>";
				?>
				<form class="form-horizontal" role="form" method="post" action="mailbox.php">
				<input type="hidden" name="domain" value="<?php echo $deletedomain ?>">
					<div class="form-group">
						<div class="col-sm-offset-1 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="deletedomain" class="btn btn-default btn-sm">Delete</button>
						</div>
					</div>
				</form>
				<?php
			}
			else {
				echo 'Your provided domain name does not exist or cannot be removed.';
			}
		}
	}
	elseif (isset($_GET["deletealias"])) {
		if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $_GET["deletealias"])) || empty($_GET["deletealias"])) {
			echo 'Your provided alias name is invalid';
		}
		else {
			$deletealias = mysqli_real_escape_string($link, $_GET["deletealias"]);
			if (mysqli_result(mysqli_query($link, "SELECT goto domain FROM alias WHERE (address='$deletealias' AND goto!='$deletealias') AND (domain IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'='$logged_in_role')"))) {
				echo '<div class="alert alert-warning" role="alert"><strong>Warning:</strong> You are about to delete an alias!</div>';
				echo "<p>The following users will no longer receive mail for/send mail from alias address <strong>$deletealias:</strong></p>";
				$query = "SELECT goto, domain FROM alias WHERE (address='$deletealias' AND goto!='$deletealias) AND ((domain IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'='$logged_in_role'))";
				$result = mysqli_query($link, $query);
				echo "<ul>";
				while ($row = mysqli_fetch_array($result)) {
					echo "<li>", $row['goto'], "</li>";
				}
				echo "</ul>";
				?>
				<form class="form-horizontal" role="form" method="post" action="mailbox.php">
				<input type="hidden" name="address" value="<?php echo $deletealias ?>">
					<div class="form-group">
						<div class="col-sm-offset-1 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="deletealias" class="btn btn-default btn-sm">Delete</button>
						</div>
					</div>
				</form>
				<?php
			}
			else {
				echo 'Your provided alias name does not exist or cannot be removed.';
			}
		}
	}
	elseif (isset($_GET["deletealiasdomain"])) {
		if (!is_valid_domain_name($_GET["deletealiasdomain"]) || empty($_GET["deletealiasdomain"])) {
			echo 'Alias domain name invalid';
		}
		else {
			$deletealiasdomain = mysqli_real_escape_string($link, $_GET["deletealiasdomain"]);
			if (mysqli_result(mysqli_query($link, "SELECT alias_domain, target_domain FROM alias_domain WHERE alias_domain='$deletealiasdomain' AND (target_domain IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'='$logged_in_role')"))) {
				echo '<div class="alert alert-warning" role="alert"><strong>Warning:</strong> You are about to delete an alias domain!</div>';
				echo "<p>The server will stop accepting mails for the domain name <strong>$deletealiasdomain</strong>.</p>";
				?>
				<form class="form-horizontal" role="form" method="post" action="mailbox.php">
				<input type="hidden" name="alias_domain" value="<?php echo $deletealiasdomain ?>">
					<div class="form-group">
						<div class="col-sm-offset-1 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="deletealiasdomain" class="btn btn-default btn-sm">Delete</button>
						</div>
					</div>
				</form>
				<?php
			}
			else {
				echo 'Your provided alias domain name does not exist or cannot be removed.';
			}
		}
	}
	elseif (isset($_GET["deletedomainadmin"])) {
		if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $_GET["deletedomainadmin"])) || empty($_GET["deletedomainadmin"])) {
			echo 'Domain administrator name invalid';
		}
		else {
			$deletedomainadmin = mysqli_real_escape_string($link, $_GET["deletedomainadmin"]);
			if (mysqli_result(mysqli_query($link, "SELECT username FROM domain_admins WHERE username='$deletedomainadmin'")) && $logged_in_role == "admin") {
				echo '<div class="alert alert-warning" role="alert"><strong>Warning:</strong> You are about to delete a domain administrator!</div>';
				echo "<p>The domain administrator <strong>$deletedomainadmin</strong> will not be able to login after deletion.</p>";
				?>
				<form class="form-horizontal" role="form" method="post" action="admin.php">
				<input type="hidden" name="username" value="<?php echo $deletedomainadmin ?>">
					<div class="form-group">
						<div class="col-sm-offset-1 col-sm-10">
							<button type="submit" name="trigger_delete_domain_admin" class="btn btn-default btn-sm">Delete</button>
						</div>
					</div>
				</form>
				<?php
			}
			else {
				echo 'Action not supported.';
			}
		}
	}
	elseif (isset($_GET["deletemailbox"])) {
		if (!filter_var($_GET["deletemailbox"], FILTER_VALIDATE_EMAIL)) {
			echo 'Your provided alias name is invalid';
		}
		else {
			$deletemailbox = mysqli_real_escape_string($link, $_GET["deletemailbox"]);
			if (mysqli_result(mysqli_query($link, "SELECT address, domain FROM alias WHERE address='$deletemailbox' AND (domain IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'='$logged_in_role')"))) {
				echo '<div class="alert alert-warning" role="alert"><strong>Warning:</strong> You are about to delete a mailbox!</div>';
				echo "<p>The mailbox user <strong>$deletemailbox</strong> + its address books and calendars will be deleted.</p>";
				echo "<p>The user will also be removed from the alias addresses listed below (if any).</p>";
				echo "<ul>";
				$result = mysqli_query($link, "SELECT address FROM alias WHERE goto='$deletemailbox' and address!='$deletemailbox'");
				while ($row = mysqli_fetch_array($result)) {
					echo "<li>", $row['address'], "</li>";
				}
				echo "</ul>";
				?>
				<form class="form-horizontal" role="form" method="post" action="mailbox.php">
				<input type="hidden" name="username" value="<?php echo $deletemailbox ?>">
					<div class="form-group">
						<div class="col-sm-offset-1 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="deletemailbox" class="btn btn-default btn-sm">Delete</button>
						</div>
					</div>
				</form>
				<?php
			}
			else {
				echo 'Your provided mailbox name does not exist.';
			}
		}
	}
	else {
		echo '<div class="alert alert-warning" role="alert"><strong>Error:</strong>  No action specified.</div>';
	}
}
else {
	echo '<div class="alert alert-warning" role="alert"><strong>You are not logged in.</strong> Permission denied.</div>';
}?>
				</div>
			</div>
		</div>
	</div>
<a href="<?=$_SESSION['return_to'];?>">&#8592; go back</a>
</div> <!-- /container -->
<?php
require_once("inc/footer.inc.php");
?>