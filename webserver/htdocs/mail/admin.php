<?php
require_once("inc/prerequisites.inc.php");

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
require_once("inc/header.inc.php");
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
?>
<div class="container">
<h4><span class="glyphicon glyphicon-user" aria-hidden="true"></span> <?=$lang['admin']['access'];?></h4>
<div class="panel-group" id="accordion_access">
	<div class="panel panel-danger">
		<div style="cursor:pointer;" class="panel-heading" data-toggle="collapse" data-parent="#accordion_access" data-target="#collapseAdmin">
			<span class="accordion-toggle"><?=$lang['admin']['admin_details'];?></span>
		</div>
		<div id="collapseAdmin" class="panel-collapse collapse">
			<div class="panel-body">
				<form class="form-horizontal" autocapitalize="none" autocorrect="off" role="form" method="post">
				<?php
				try {
				$stmt = $pdo->prepare("SELECT `username` FROM `admin`
					WHERE `superadmin`='1' and active='1'");
				$stmt->execute();
				$AdminData = $stmt->fetch(PDO::FETCH_ASSOC);
				}
				catch(PDOException $e) {
					$_SESSION['return'] = array(
						'type' => 'danger',
						'msg' => 'MySQL: '.$e
					);
				}
				?>
					<input type="hidden" name="admin_user_now" value="<?=htmlspecialchars($AdminData['username']);?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="admin_user"><?=$lang['admin']['admin'];?>:</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="admin_user" id="admin_user" value="<?=htmlspecialchars($AdminData['username']);?>" required>
							&rdsh; <kbd>a-z A-Z - _ .</kbd>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="admin_pass"><?=$lang['admin']['password'];?>:</label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="admin_pass" id="admin_pass" placeholder="<?=$lang['admin']['unchanged_if_empty'];?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="admin_pass2"><?=$lang['admin']['password_repeat'];?>:</label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="admin_pass2" id="admin_pass2">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_set_admin" class="btn btn-success"><?=$lang['admin']['save'];?></button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="panel panel-default">
	<div style="cursor:pointer;" class="panel-heading" data-toggle="collapse" data-parent="#accordion_access" data-target="#collapseDomAdmins">
		<span class="accordion-toggle"><?=$lang['admin']['domain_admins'];?></span>
	</div>
		<div id="collapseDomAdmins" class="panel-collapse collapse">
			<div class="panel-body">
				<form method="post">
					<div class="table-responsive">
					<table class="table table-striped sortable-theme-bootstrap" data-sortable id="domainadminstable">
						<thead>
						<tr>
							<th class="sort-table" style="min-width: 100px;"><?=$lang['admin']['username'];?></th>
							<th class="sort-table" style="min-width: 166px;"><?=$lang['admin']['admin_domains'];?></th>
							<th class="sort-table" style="min-width: 76px;"><?=$lang['admin']['active'];?></th>
							<th style="text-align: right; min-width: 200px;" data-sortable="false"><?=$lang['admin']['action'];?></th>
						</tr>
						</thead>
						<tbody>
							<?php
							try {
								$stmt = $pdo->query("SELECT DISTINCT
									`username`, 
									CASE WHEN `active`='1' THEN '".$lang['admin']['yes']."' ELSE '".$lang['admin']['no']."' END AS `active`
										FROM `domain_admins` 
											WHERE `username` IN (
												SELECT `username` FROM `admin`
													WHERE `superadmin`!='1'
											)");
								$rows_username = $stmt->fetchAll(PDO::FETCH_ASSOC);
							}
							catch(PDOException $e) {
								$_SESSION['return'] = array(
									'type' => 'danger',
									'msg' => 'MySQL: '.$e
								);
							}
							if(!empty($rows_username)):
							while ($row_user_state = array_shift($rows_username)):
							?>
							<tr id="data">
								<td><?=htmlspecialchars(strtolower($row_user_state['username']));?></td>
								<td>
								<?php
								try {
									$stmt = $pdo->prepare("SELECT `domain` FROM `domain_admins` WHERE `username` = :username");
									$stmt->execute(array('username' => $row_user_state['username']));
									$rows_domain = $stmt->fetchAll(PDO::FETCH_ASSOC);
								}
								catch(PDOException $e) {
									$_SESSION['return'] = array(
										'type' => 'danger',
										'msg' => 'MySQL: '.$e
									);
								}
								while ($row_domain = array_shift($rows_domain)) {
									echo htmlspecialchars($row_domain['domain']).'<br />';
								}
								?>
								</td>
								<td><?=$row_user_state['active'];?></td>
								<td style="text-align: right;">
									<div class="btn-group">
										<a href="edit.php?domainadmin=<?=$row_user_state['username'];?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['admin']['edit'];?></a>
										<a href="delete.php?domainadmin=<?=$row_user_state['username'];?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['admin']['remove'];?></a>
									</div>
								</td>
								</td>
							</tr>

							<?php
							endwhile;
							else:
							?>
								<tr id="no-data"><td colspan="4" style="text-align: center; font-style: italic;"><?=$lang['admin']['no_record'];?></td></tr>
							<?php
							endif;
							?>
						</tbody>
					</table>
					</div>
				</form>
				<small>
				<legend><?=$lang['admin']['add_domain_admin'];?></legend>
				<form class="form-horizontal" role="form" method="post">
					<div class="form-group">
						<label class="control-label col-sm-2" for="username"><?=$lang['admin']['username'];?>:</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="username" id="username" required>
							&rdsh; <kbd>a-z A-Z - _ .</kbd>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="name"><?=$lang['admin']['admin_domains'];?>:</label>
						<div class="col-sm-10">
							<select title="<?=$lang['admin']['search_domain_da'];?>" style="width:100%" name="domain[]" size="5" multiple>
							<?php
							try {
								$stmt = $pdo->query("SELECT domain FROM domain");
								$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
							}
							catch(PDOException $e) {
								$_SESSION['return'] = array(
									'type' => 'danger',
									'msg' => 'MySQL: '.$e
								);
							}
							while ($row = array_shift($rows)) {
								echo "<option>".htmlspecialchars($row['domain'])."</option>";
							}
							?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password"><?=$lang['admin']['password'];?>:</label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password" id="password" placeholder="">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password2"><?=$lang['admin']['password_repeat'];?>:</label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password2" id="password2" placeholder="">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" checked> <?=$lang['admin']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_add_domain_admin" class="btn btn-success"><?=$lang['admin']['add'];?></button>
						</div>
					</div>
				</form>
				</small>
			</div>
		</div>
	</div>
</div>

<h4><span class="glyphicon glyphicon-wrench" aria-hidden="true"></span> <?=$lang['admin']['configuration'];?></h4>
<div class="panel-group" id="accordion_config">
<div class="panel panel-default">
<div style="cursor:pointer;" class="panel-heading" data-toggle="collapse" data-parent="#accordion_config" data-target="#collapseRestrictions">
	<span class="accordion-toggle"><?=$lang['admin']['restrictions'];?></span>
</div>
<div id="collapseRestrictions" class="panel-collapse collapse">
<div class="panel-body">
<p class="help-block"><?=$lang['admin']['r_info'];?></p>
<?php
$srr_values_active = return_mailcow_config("srr")['active'];
$srr_values_inactive = return_mailcow_config("srr")['inactive'];
?>
	<form class="form-horizontal" id="srr_form" role="form" method="post">
		<div class="form-group">
			<label class="control-label col-sm-2" for="location"><?=$lang['admin']['rr'];?></label>
			<div class="col-sm-10">
				<ul id="srr-sortable-active list-group">
					<li class="ui-state-default list-group-item list-group-item-success list-heading">&lrarr; <?=$lang['admin']['r_active'];?></li>
<?php
foreach($srr_values_active as $srr_value) {
	if (!in_array($srr_value, $GLOBALS['VALID_SRR']))  {
?>
					<li class="ui-state-default ui-state-disabled list-group-item" data-value="<?php echo $srr_value; ?>"><?php echo $srr_value; ?></li>
<?php
	}
	else {
?>
					<li class="ui-state-default list-group-item" data-value="<?php echo $srr_value; ?>"><?php echo $srr_value; ?></li>
<?php
	}
}
?>
				</ul>
				<ul id="srr-sortable-inactive list-group">
					<li class="ui-state-default list-group-item list-group-item-warning list-heading">&lrarr; <?=$lang['admin']['r_inactive'];?></li>
<?php
foreach($srr_values_inactive as $srr_value) {
?>
					<li class="ui-state-default list-group-item" data-value="<?php echo $srr_value; ?>"><?php echo $srr_value; ?></li>
<?php
}
?>
				</ul>
			</div>
		</div>
		<div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
				<button type="submit" name="srr" class="btn btn-success"><?=$lang['admin']['save'];?></button>
				<button type="submit" name="reset-srr" class="btn btn-primary"><?=$lang['admin']['reset_defaults'];?></button>
			</div>
		</div>
	</form>
<?php
$ssr_values_active = return_mailcow_config("ssr")['active'];
$ssr_values_inactive = return_mailcow_config("ssr")['inactive'];
?>
	<form class="form-horizontal" id="ssr_form" role="form" method="post">
		<div class="form-group">
			<label class="control-label col-sm-2" for="location"><?=$lang['admin']['sr'];?></label>
			<div class="col-sm-10">
				<ul id="ssr-sortable-active list-group">
					<li class="ui-state-default list-group-item list-group-item-success list-heading">&lrarr; <?=$lang['admin']['r_active'];?></li>
<?php
foreach($ssr_values_active as $ssr_value) {
	if (!in_array($ssr_value, $GLOBALS['VALID_SSR']))  {
?>
					<li class="ui-state-default ui-state-disabled list-group-item" data-value="<?php echo $ssr_value; ?>"><?php echo $ssr_value; ?></li>
<?php
	}
	else {
?>
					<li class="ui-state-default list-group-item" data-value="<?php echo $ssr_value; ?>"><?php echo $ssr_value; ?></li>
<?php
	}
}
?>
				</ul>
				<ul id="ssr-sortable-inactive list-group">
					<li class="ui-state-default list-group-item list-group-item-warning list-heading">&lrarr; <?=$lang['admin']['r_inactive'];?></li>
<?php
foreach($ssr_values_inactive as $ssr_value) {
?>
					<li class="ui-state-default list-group-item" data-value="<?php echo $ssr_value; ?>"><?php echo $ssr_value; ?></li>
<?php
}
?>
				</ul>
			</div>
		</div>
		<div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
				<button type="submit" name="ssr" class="btn btn-success"><?=$lang['admin']['save'];?></button>
				<button type="submit" name="reset-ssr" class="btn btn-primary"><?=$lang['admin']['reset_defaults'];?></button>
			</div>
		</div>
	</form>
</div>
</div>
</div>


<div class="panel panel-default">
<div style="cursor:pointer;" class="panel-heading" data-toggle="collapse" data-parent="#accordion_config" data-target="#collapsePubFolders">
	<span class="accordion-toggle"><?=$lang['admin']['public_folders'];?></span>
</div>
<div id="collapsePubFolders" class="panel-collapse collapse">
<div class="panel-body">
<p><?=$lang['admin']['public_folders_text'];?></p>
<form class="form-horizontal" role="form" method="post">
	<div class="form-group">
		<div class="col-sm-offset-4 col-sm-8">
			<div class="checkbox">
			<label><input type="checkbox" name="use_public_folder" <?=return_mailcow_config("public_folder_status");?>> <?=$lang['admin']['public_folder_enable'];?></label>
			</div>
			<small><?=$lang['admin']['public_folder_enable_text'];?></small>
		</div>
	</div>
	<div class="form-group">
		<label class="control-label col-sm-4" for="location"><?=$lang['admin']['public_folder_name'];?>:</label>
		<div class="col-sm-8">
		<input type="text" class="form-control" name="public_folder_name" id="public_folder_name" value="<?=htmlspecialchars(return_mailcow_config("public_folder_name"));?>">
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-4 col-sm-8">
			<div class="checkbox">
			<label><input type="checkbox" name="public_folder_pvt" <?=return_mailcow_config("public_folder_pvt");?>> <?=$lang['admin']['public_folder_pusf'];?></label>
			</div>
			<small><?=$lang['admin']['public_folder_pusf_text'];?></small>
		</div>
	</div>
	<div class="form-group">
	<input type="hidden" name="trigger_public_folder">
		<div class="col-sm-8">
			<button type="submit" class="btn btn-success"><?=$lang['admin']['save'];?></button>
		</div>
	</div>
</form>
</div>
</div>
</div>

<div class="panel panel-default">
<div style="cursor:pointer;" class="panel-heading" data-toggle="collapse" data-parent="#accordion_config" data-target="#collapsePrivacy">
	<span class="accordion-toggle"><?=$lang['admin']['privacy'];?></span>
</div>
<div id="collapsePrivacy" class="panel-collapse collapse">
<div class="panel-body">
<p><?=$lang['admin']['privacy_text'];?></p>
<form class="form-horizontal" role="form" method="post">
	<div class="form-group">
		<div class="col-sm-8">
			<div class="checkbox">
				<label><input name="anonymize" type="checkbox" <?=return_mailcow_config("anonymize");?>> <?=$lang['admin']['privacy_anon_mail'];?></label>
			</div>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-8">
			<button type="submit" name="trigger_anonymize" class="btn btn-success"><?=$lang['admin']['save'];?></button>
		</div>
	</div>
</form>
</div>
</div>
</div>

<div class="panel panel-default">
<div style="cursor:pointer;" class="panel-heading" data-toggle="collapse" data-parent="#accordion_config" data-target="#collapseDKIM">
	<span class="accordion-toggle"><?=$lang['admin']['dkim_keys'];?></span>
</div>
<div id="collapseDKIM" class="panel-collapse collapse">
<div class="panel-body">
	<?php
	$dnstxt_folder	= scandir($GLOBALS["MC_ODKIM_TXT"]);
	$dnstxt_files	= array_diff($dnstxt_folder, array('.', '..'));
	foreach($dnstxt_files as $file) {
		$str = file_get_contents($GLOBALS["MC_ODKIM_TXT"]."/".$file);
		$str = preg_replace('/\r|\t|\n/', '', $str);
		preg_match('/\(.*\)/im', $str, $matches);
		if(isset($matches[0])) {
			$str = str_replace(array(' ', '"', '(', ')'), '', $matches[0]);
		}
	?>
		<div class="row">
			<div class="col-xs-2">
				<p>Domain: <strong><?=htmlspecialchars(explode("_", $file)[1]);?></strong> (<?=htmlspecialchars(explode("_", $file)[0]);?>._domainkey)</p>
			</div>
			<div class="col-xs-9">
				<pre><?=$str;?></pre>
			</div>
			<div class="col-xs-1">
				<form class="form-inline" role="form" method="post">
				<a href="#" onclick="$(this).closest('form').submit()"><span class="glyphicon glyphicon-remove-circle"></span></a>
				<input type="hidden" name="delete_dkim_record" value="<?=htmlspecialchars($file);?>">
				</form>
			</div>
		</div>
	<?php
	}
	?>
	<legend><?=$lang['admin']['dkim_add_key'];?></legend>
	<form class="form-inline" role="form" method="post">
		<div class="form-group">
			<label for="dkim_domain">Domain</label>
			<input class="form-control" id="dkim_domain" name="dkim_domain" placeholder="example.org" required>
		</div>
		<div class="form-group">
			<label for="dkim_selector">Selector</label>
			<input class="form-control" id="dkim_selector" name="dkim_selector" value="default" required>
		</div>
		<div class="form-group">
			<select class="form-control" id="dkim_key_size" name="dkim_key_size" title="<?=$lang['admin']['dkim_key_length'];?>" required>
				<option>1024</option>
				<option>2048</option>
			</select>
		</div>
		<button type="submit" name="add_dkim_record" class="btn btn-success"><span class="glyphicon glyphicon-plus"></span> <?=$lang['admin']['add'];?></button>
	</form>
</div>
</div>
</div>

<div class="panel panel-default">
<div style="cursor:pointer;" class="panel-heading" data-toggle="collapse" data-parent="#accordion_config" data-target="#collapseMsgSize">
	<span class="accordion-toggle"><?=$lang['admin']['msg_size'];?></span>
</div>
<div id="collapseMsgSize" class="panel-collapse collapse">
<div class="panel-body">
<form class="form-inline" method="post">
	<p><?=$lang['admin']['msg_size_limit'];?>: <strong><?=intval(return_mailcow_config("maxmsgsize"));?> MB</strong></p>
	<p><?=$lang['admin']['msg_size_limit_details'];?></p>
	<div class="form-group">
		<input type="number" class="form-control" id="maxmsgsize" name="maxmsgsize" placeholder="in MB" min="1" max="250" required>
	</div>
	<button type="submit" class="btn btn-success"><?=$lang['admin']['save'];?></button>
</form>
</div>
</div>
</div>

</div>

<h4><span class="glyphicon glyphicon-dashboard" aria-hidden="true"></span> <?=$lang['admin']['maintenance'];?></h4>
<div class="panel-group" id="accordion_maint">
<div class="panel panel-default">
	<div style="cursor:pointer;" class="panel-heading" data-toggle="collapse" data-parent="#accordion_maint" data-target="#collapseSysinfo">
		<span class="accordion-toggle"><?=$lang['admin']['sys_info'];?></span>
	</div>
	<div id="collapseSysinfo" class="panel-collapse collapse">
	<div class="panel-body">
		<legend><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> About Server</legend>
		<div class="row">
			<div class="col-md-6">
				<blockquote>
					<strong>Uptime:</strong> <?=sys_info('uptime')['days'].' <i>day(s)</i>, '.sys_info('uptime')['hours'].' <i>hour(s)</i>, '.sys_info('uptime')['minutes'].' <i>minute(s)</i> '?>
				</blockquote>
			</div>
			<div class="col-md-6">
				<blockquote>
					<strong>Hostname:</strong> <?=gethostname()?>
				</blockquote>
			</div>
			<div class="col-md-6">
				<blockquote>
					<strong>mailcow version:</strong> <?=file_get_contents("/etc/mailcow_version")?>
				</blockquote>
			</div>
		</div>
		<div class="row">
			<div class="col-md-6">
				<legend><span class="glyphicon glyphicon-hdd" data-toggle="tooltip" title="/var/vmail" aria-hidden="true"></span> Disk <?=formatBytes(disk_total_space('/var/vmail')-disk_free_space("/var/vmail"));?> / <?=formatBytes(disk_total_space('/var/vmail'))?></legend>
				<div class="progress">
				  <div class="progress-bar progress-bar-info progress-bar-striped" role="progressbar" aria-valuenow="<?php echo sys_info('vmail_percentage');?>"
				  aria-valuemin="0" aria-valuemax="100" style="width:<?php echo sys_info('vmail_percentage');?>%">
				  </div>
				</div>
			</div>
			<div class="col-md-6">
				<legend><span class="glyphicon glyphicon-dashboard" aria-hidden="true"></span> RAM <?=formatBytes(sys_info('ram')['used']);?> / <?=formatBytes(sys_info('ram')['total']);?></legend>
				<div class="progress">
				  <div class="progress-bar progress-bar-info progress-bar-striped" role="progressbar" aria-valuenow="<?php echo sys_info('ram')['used_percent'];?>"
				  aria-valuemin="0" aria-valuemax="100" style="width:<?=sys_info('ram')['used_percent'];?>%">
				  </div>
				</div>
			</div>
		</div>
 	  	<legend><span class="glyphicon glyphicon-stats" aria-hidden="true"></span> CPU <?=sys_info('cpu');?>%</legend>
				<div class="progress">
				  <div class="progress-bar progress-bar-info progress-bar-striped" role="progressbar" aria-valuenow="<?php echo sys_info('cpu');?>"
				  aria-valuemin="0" aria-valuemax="100" style="width:<?=sys_info('cpu');?>%">
				  </div>
				</div>
		<legend>Postqueue</legend>
			<pre><?php echo sys_info("mailq");?></pre>
		<legend>Pflogsumm <code>/var/log/mail.log</code></legend>
			<textarea rows="20" style="font-family:monospace;font-size:9pt;width:100%;"><?php echo sys_info("pflog");?></textarea>
			<p><span class="glyphicon glyphicon-time" aria-hidden="true"></span> <?=round(abs(date('U') - filemtime($PFLOG)) / 60,0). " min.";?></p>
			<form method="post">
				<div class="form-group">
					<input type="hidden" name="pflog_renew" value="1">
					<button type="submit" class="btn btn-default"><span class="glyphicon glyphicon-refresh" aria-hidden="true"></span> Pflogsumm</button>
				</div>
			</form>
		<legend>Mailgraph</legend>
			<?=sys_info("mailgraph");?>
	</div>
	</div>
</div>
</div>
</div> <!-- /container -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js" integrity="sha384-YWP9O4NjmcGo4oEJFXvvYSEzuHIvey+LbXkBNJ1Kd0yfugEZN9NCQNpRYBVC1RvA" crossorigin="anonymous"></script>
<script src="js/sorttable.js"></script>
<script src="js/admin.js"></script>
<?php
require_once("inc/footer.inc.php");
} else {
	header('Location: /');
	exit();
}
?>
