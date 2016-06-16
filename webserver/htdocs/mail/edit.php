<?php
require_once("inc/header.inc.php");
?>
<div class="container">
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['edit']['title'];?></h3>
				</div>
				<div class="panel-body">
<?php
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin"  || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
		if (isset($_GET['alias']) &&
			ctype_alnum(str_replace(array('@', '.', '-'), '', $_GET["alias"])) &&
			!empty($_GET["alias"])) {
				$alias = mysqli_real_escape_string($link, $_GET["alias"]);
				$domain = substr(strrchr($alias, "@"), 1);
				$qstring = "SELECT * FROM alias
					WHERE address='".$alias."' 
					AND goto!='".$alias."'
					AND (
						domain IN (
							SELECT domain FROM domain_admins
								WHERE active='1'
								AND username='".$_SESSION['mailcow_cc_username']."'
						)
						OR 'admin'='".$_SESSION['mailcow_cc_role']."'
					)";
				$qresult = mysqli_query($link, $qstring)
					OR die('<div class="alert alert-danger" role="alert">'.mysqli_error($link).'</div>');
				$num_results = mysqli_num_rows($qresult);
				$result = mysqli_fetch_assoc($qresult);
				if ($num_results != 0 && !empty($num_results)) {
				?>
					<h4><?=$lang['edit']['alias'];?></h4>
					<br />
					<form class="form-horizontal" role="form" method="post">
					<input type="hidden" name="address" value="<?=$alias;?>">
						<div class="form-group">
							<label class="control-label col-sm-2" for="name"><?=$lang['edit']['target_address'];?></label>
							<div class="col-sm-10">
								<textarea class="form-control" autocapitalize="none" autocorrect="off" rows="10" name="goto"><?=$result['goto'] ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<div class="checkbox">
								<label><input type="checkbox" name="active" <?php if (isset($result['active']) && $result['active']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['active'];?></label>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<button type="submit" name="trigger_mailbox_action" value="editalias" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
							</div>
						</div>
					</form>
				<?php
				}
				else {
				?>
					<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
				<?php
				}
		}
		elseif (isset($_GET['domainadmin']) && 
			ctype_alnum(str_replace(array('@', '.', '-'), '', $_GET["domainadmin"])) &&
			!empty($_GET["domainadmin"]) &&
			$_GET["domainadmin"] != 'admin' &&
			$_SESSION['mailcow_cc_role'] == "admin") {
				$domain_admin = mysqli_real_escape_string($link, $_GET["domainadmin"]);
				$qstring = "SELECT * FROM `domain_admins`
					WHERE `username`='".$domain_admin."'";
				$qresult = mysqli_query($link, $qstring)
					OR die(mysqli_error($link));
				$num_results = mysqli_num_rows($qresult);
				$result = mysqli_fetch_assoc($qresult);
				if ($num_results != 0 && !empty($num_results)) {
				?>
				<h4><?=$lang['edit']['domain_admin'];?></h4>
				<br />
				<form class="form-horizontal" role="form" method="post">
				<input type="hidden" name="username" value="<?=$domain_admin;?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="name"><?=$lang['edit']['domains'];?></label>
						<div class="col-sm-10">
							<select name="domain[]" multiple>
							<?php
							$result_selected = mysqli_query($link, "SELECT `domain` FROM `domain`
								WHERE `domain` IN (
									SELECT `domain` FROM `domain_admins`
										WHERE username='".$domain_admin."')")
								OR die(mysqli_error($link));
							while ($row_selected = mysqli_fetch_array($result_selected)):
							?>
								<option selected><?=$row_selected['domain'];?></option>
							<?php
							endwhile;
							$result_unselected = mysqli_query($link, "SELECT `domain` FROM `domain`
								WHERE `domain` NOT IN (
									SELECT `domain` FROM `domain_admins`
										WHERE username='".$domain_admin."')")
								OR die(mysqli_error($link));
							while ($row_unselected = mysqli_fetch_array($result_unselected)):
							?>
								<option><?=$row_unselected['domain'];?></option>
							<?php
							endwhile;
							?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password"><?=$lang['edit']['password'];?></label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password" id="password" placeholder="">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password2"><?=$lang['edit']['password_repeat'];?></label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password2" id="password2">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" <?php if (isset($result['active']) && $result['active']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="editdomainadmin" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
						</div>
					</div>
				</form>
			<?php
			}
			else {
			?>
				<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
			<?php
			}
	}
	elseif (isset($_GET['domain']) &&
		is_valid_domain_name($_GET["domain"]) &&
		!empty($_GET["domain"])) {
			$domain = mysqli_real_escape_string($link, $_GET["domain"]);
			$qstring = "SELECT * FROM `domain` WHERE domain='".$domain."' 
				AND (
					`domain` IN (
						SELECT `domain` from `domain_admins`
							WHERE active='1'
							AND username='".$_SESSION['mailcow_cc_username']."'
					) 
					OR 'admin'='".$_SESSION['mailcow_cc_role']."'
				)";
			$qresult = mysqli_query($link, $qstring)
				OR die(mysqli_error($link));
			$num_results = mysqli_num_rows($qresult);
			$result = mysqli_fetch_assoc($qresult);
			if ($num_results != 0 && !empty($num_results)) {
			?>
				<h4><?=$lang['edit']['domain'];?></h4>
				<form class="form-horizontal" role="form" method="post">
				<input type="hidden" name="domain" value="<?=$domain;?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="description"><?=$lang['edit']['description'];?></label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="description" id="description" value="<?=htmlspecialchars($result['description']);?>">
						</div>
					</div>
					<?php
					if ($_SESSION['mailcow_cc_role'] == "admin") {
					?>
					<div class="form-group">
						<label class="control-label col-sm-2" for="aliases"><?=$lang['edit']['max_aliases'];?></label>
						<div class="col-sm-10">
							<input type="number" class="form-control" name="aliases" id="aliases" value="<?=$result['aliases'];?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="mailboxes"><?=$lang['edit']['max_mailboxes'];?></label>
						<div class="col-sm-10">
							<input type="number" class="form-control" name="mailboxes" id="mailboxes" value="<?=$result['mailboxes'];?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="maxquota"><?=$lang['edit']['max_quota'];?></label>
						<div class="col-sm-10">
							<input type="number" class="form-control" name="maxquota" id="maxquota" value="<?=$result['maxquota'];?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="quota"><?=$lang['edit']['domain_quota'];?></label>
						<div class="col-sm-10">
							<input type="number" class="form-control" name="quota" id="quota" value="<?=$result['quota'];?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2"><?=$lang['edit']['backup_mx_options'];?></label>
						<div class="col-sm-10">
							<div class="checkbox">
								<label><input type="checkbox" name="backupmx" <?php if (isset($result['backupmx']) && $result['backupmx']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['relay_domain'];?></label>
								<br />
								<label><input type="checkbox" name="relay_all_recipients" <?php if (isset($result['relay_all_recipients']) && $result['relay_all_recipients']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['relay_all'];?></label>
								<p><?=$lang['edit']['relay_all_info'];?></p>
							</div>
						</div>
					</div>
					<?php
					}
					?>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
								<label><input type="checkbox" name="active" <?php if (isset($result['active']) && $result['active']=="1") { echo "checked "; }; if ($_SESSION['mailcow_cc_role']=="domainadmin") { echo "disabled"; }; ?>> <?=$lang['edit']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="editdomain" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
						</div>
					</div>
				</form>
				<?php
				$dnstxt_folder = scandir($GLOBALS["MC_ODKIM_TXT"]);
				$dnstxt_files = array_diff($dnstxt_folder, array('.', '..'));
				foreach($dnstxt_files as $file) {
					if(explode("_", $file)[1] == "$domain") {
						$str = file_get_contents($GLOBALS["MC_ODKIM_TXT"]."/".$file);
						$str = preg_replace('/\r|\t|\n/', '', $str);
						preg_match('/\(.*\)/im', $str, $matches);
						if(isset($matches[0])) {
							$str = str_replace(array(' ', '"', '(', ')'), '', $matches[0]);
						}
				?>
						<div class="row">
							<div class="col-xs-2">
								<p class="text-right"><?=$lang['edit']['dkim_signature'];?></p>
							</div>
							<div class="col-xs-10">
								<div class="col-md-2"><b><?=$lang['edit']['dkim_txt_name'];?></b></div>
								<div class="col-md-10">
									<pre>default._domainkey</pre>
								</div>
								<div class="col-md-2"><b><?=$lang['edit']['dkim_txt_value'];?></b></div>
								<div class="col-md-10">
									<pre><?=$str;?></pre>
									<?=$lang['edit']['dkim_record_info'];?>
								</div>
							</div>
						</div>
				<?php
					}
				}
			}
			else {
			?>
				<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
			<?php
			}
	}
	elseif (isset($_GET['mailbox']) && filter_var($_GET["mailbox"], FILTER_VALIDATE_EMAIL) && !empty($_GET["mailbox"])) {
			$mailbox = mysqli_real_escape_string($link, $_GET["mailbox"]);
			$domain = substr(strrchr($mailbox, "@"), 1);
			$qstring = "SELECT
				username,
				domain,
				name,
				round(sum(quota / 1048576)) as quota,
				active
					FROM mailbox
						WHERE username='".$mailbox."'
						AND (
							domain IN (
								SELECT domain FROM domain_admins
									WHERE active='1'
									AND username='".$_SESSION['mailcow_cc_username']."'
							)
							OR 'admin'='".$_SESSION['mailcow_cc_role']."'
						)";
			$qresult = mysqli_query($link, $qstring) 
				OR die(mysqli_error($link));
			$num_results = mysqli_num_rows($qresult);
			$result = mysqli_fetch_assoc($qresult);
			if ($num_results != 0 && !empty($num_results)) {
			?>
				<h4><?=$lang['edit']['mailbox'];?></h4>
				<form class="form-horizontal" role="form" method="post">
				<input type="hidden" name="username" value="<?=$result['username'];?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="name"><?=$lang['edit']['name'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="name" id="name" value="<?=utf8_encode($result['name']);?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="quota"><?=$lang['edit']['quota_mb'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="quota" id="quota" value="<?=$result['quota'];?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="quota"><?=$lang['edit']['sender_acl'];?></label>
						<div class="col-sm-10">
							<select title="Durchsuchen..." style="width:100%" name="sender_acl[]" size="10" multiple>
							<?php
							$result_goto_from_alias = mysqli_query($link, "SELECT address FROM alias WHERE goto='".$mailbox."'")
								OR die(mysqli_error($link));
							while ($row_goto_from_alias = mysqli_fetch_array($result_goto_from_alias)):
							?>
								<option selected disabled="disabled"><?=$row_goto_from_alias['address'];?></option>
							<?php
							endwhile;

							$result_selected_sender_acl = mysqli_query($link, "SELECT `send_as` FROM `sender_acl`
								WHERE `logged_in_as`='".$mailbox."'")
									OR die(mysqli_error($link));
							while ($row_selected_sender_acl = mysqli_fetch_array($result_selected_sender_acl)):
									if (!filter_var($row_selected_sender_acl['send_as'], FILTER_VALIDATE_EMAIL)):
									?>
										<option data-subtext="(gesamte Domain)" selected><?=$row_selected_sender_acl['send_as'];?></option>
									<?php
									else:
									?>
										<option selected><?=$row_selected_sender_acl['send_as'];?></option>
									<?php
									endif;
							endwhile;

							$result_unselected_sender_acl = mysqli_query($link, "SELECT DISTINCT `domain` FROM `alias`
								WHERE `domain`='".$result['domain']."'
									AND	CONCAT('@', domain) NOT IN (
										SELECT `send_as` FROM `sender_acl` 
											WHERE `logged_in_as`='".$mailbox."')")
								OR die(mysqli_error($link));
							while ($row_unselected_sender_acl = mysqli_fetch_array($result_unselected_sender_acl)):
							?>
								<option data-subtext="(gesamte Domain)">@<?=$row_unselected_sender_acl['domain'];?></option>
							<?php
							endwhile;

							$result_unselected_sender_acl = mysqli_query($link, "SELECT address FROM alias
								WHERE goto!='".$mailbox."'
									AND `domain`='".$result['domain']."'
									AND `address` NOT IN (
										SELECT `send_as` FROM `sender_acl` 
											WHERE `logged_in_as`='".$mailbox."')")
								OR die(mysqli_error($link));
							while ($row_unselected_sender_acl = mysqli_fetch_array($result_unselected_sender_acl)):
							?>
								<option><?=$row_unselected_sender_acl['address'];?></option>
							<?php
							endwhile;
							?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password"><?=$lang['edit']['password'];?></label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password" id="password" placeholder="">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password2"><?=$lang['edit']['password_repeat'];?></label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password2" id="password2">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" <?=($result['active']=="1") ? "checked" : "";?>> <?=$lang['edit']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="editmailbox" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
						</div>
					</div>
				</form>
			<?php
			}
			else {
			?>
				<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
			<?php
			}
	}
	else {
	?>
		<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
	<?php
	}
}
else {
?>
	<div class="alert alert-danger" role="alert"><?=$lang['danger']['access_denied'];?></div>
<?php
}
?>
				</div>
			</div>
		</div>
	</div>
<a href="<?=$_SESSION['return_to'];?>">&#8592; <?=$lang['edit']['previous'];?></a>
</div> <!-- /container -->
<?php
require_once("inc/footer.inc.php");
?>
