<?php
require_once "inc/header.inc.php";

if ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin") {
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
?>
<div class="container">
	<div class="row">
		<div class="col-md-12">
		<?php
		$hasDomainQuery = mysqli_query($link,
			"SELECT `domain` FROM `domain_admins` 
				WHERE (
					username='".$_SESSION['mailcow_cc_username']."'
					AND active='1'
				)
				OR 'admin'='".$_SESSION['mailcow_cc_role']."';");
		if (mysqli_num_rows($hasDomainQuery) == "0"):
		?>
			<div class="alert alert-danger"><?=sprintf($lang['mailbox']['customer_has_no_domain'], $_SESSION['mailcow_cc_username']);?></div>
		<?php
		endif;
		?>
			<div class="panel panel-default">
				<div class="panel-heading">
				<h3 class="panel-title"><?=$lang['mailbox']['domains'];?> <span class="badge" id="numRowsDomain"></span></h3>
				<div class="pull-right">
					<span class="clickable filter" data-toggle="tooltip" title="<?=$lang['mailbox']['filter_table'];?>" data-container="body">
						<i class="glyphicon glyphicon-filter"></i>
					</span>
				<?php
				if ($_SESSION['mailcow_cc_role'] == "admin"):
				?>
					<a href="/add.php?domain"><span class="glyphicon glyphicon-plus"></span></a>
				<?php
				endif;
				?>
				</div>
				</div>
				<div class="panel-body">
					<input type="text" class="form-control" id="domaintable-filter" data-action="filter" data-filters="#domaintable" placeholder="Filter" />
				</div>
				<div class="table-responsive">
				<table class="table table-striped" id="domaintable">
					<thead>
						<tr>
							<th><?=$lang['mailbox']['domain'];?></th>
							<th><?=$lang['mailbox']['aliases'];?></th>
							<th><?=$lang['mailbox']['mailboxes'];?></th>
							<th><?=$lang['mailbox']['mailbox_quota'];?></th>
							<th><?=$lang['mailbox']['domain_quota'];?></th>
							<?php
							if ($_SESSION['mailcow_cc_role'] == "admin"):
							?>
								<th><?=$lang['mailbox']['backup_mx'];?></th>
							<?php
							endif;
							?>
							<th><?=$lang['mailbox']['active'];?></th>
							<th><?=$lang['mailbox']['action'];?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					$result = mysqli_query($link,
						"SELECT 
							`domain`,
							`aliases`,
							`mailboxes`, 
							`maxquota` * 1048576 AS `maxquota`,
							`quota` * 1048576 AS `quota`,
							CASE `backupmx` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `backupmx`,
							CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
								FROM domain WHERE
									domain IN (
										SELECT domain FROM domain_admins WHERE username='".$_SESSION['mailcow_cc_username']."' AND active='1'
									)
									OR 'admin'='".$_SESSION['mailcow_cc_role']."'");
					while ($row = mysqli_fetch_array($result)):
					$AliasData		= mysqli_fetch_assoc(mysqli_query($link,
						"SELECT COUNT(*) as count FROM `alias`
							WHERE `domain`='".$row['domain']."'
							AND `address` NOT IN (
								SELECT `username` FROM `mailbox`)"));
					$MailboxData	= mysqli_fetch_assoc(mysqli_query($link,
						"SELECT 
							COUNT(*) AS count,
							COALESCE(SUM(`quota`)) AS quota
								FROM `mailbox`
									WHERE `domain`='".$row['domain']."'"));
					?>
						<tr>
							<td><?=$row['domain'];?></td>
							<td><?=$AliasData['count'];?> / <?=$row['aliases'];?></td>
							<td><?=$MailboxData['count'];?> / <?=$row['mailboxes'];?></td>
							<td><?=formatBytes($row['maxquota'], 2);?></td>
							<td><?=formatBytes($MailboxData['quota'], 2);?> / <?=formatBytes($row['quota']);?></td>
							<?php
							if ($_SESSION['mailcow_cc_role'] == "admin"):
							?>
								<td><?=$row['backupmx'];?></td>
							<?php
							endif;
							?>
							<td><?=$row['active'];?></td>
							<?php
							if ($_SESSION['mailcow_cc_role'] == "admin"):
							?>
								<td><a href="/delete.php?domain=<?=urlencode($row['domain']);?>"><?=$lang['mailbox']['remove'];?></a> | <a href="/edit.php?domain=<?=urlencode($row['domain']);?>"><?=$lang['mailbox']['edit'];?></a></td>
							<?php
							else:
							?>
								<td><a href="/edit.php?domain=<?=urlencode($row['domain']);?>"><?=$lang['mailbox']['view'];?></a></td>
							<?php
							endif;
							endwhile;
							?>
						</tr>
					</tbody>
				</table>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['mailbox']['domain_aliases'];?> <span class="badge" id="numRowsDomainAlias"></span></h3>
					<div class="pull-right">
						<span class="clickable filter" data-toggle="tooltip" title="<?=$lang['mailbox']['filter_table'];?>" data-container="body">
							<i class="glyphicon glyphicon-filter"></i>
						</span>
						<a href="/add.php?aliasdomain"><span class="glyphicon glyphicon-plus"></span></a>
					</div>
				</div>
				<div class="panel-body">
					<input type="text" class="form-control" id="domainaliastable-filter" data-action="filter" data-filters="#domainaliastable" placeholder="Filter" />
				</div>
				<div class="table-responsive">
				<table class="table table-striped" id="domainaliastable">
					<thead>
						<tr>
							<th><?=$lang['mailbox']['alias'];?></th>
							<th><?=$lang['mailbox']['target_domain'];?></th>
							<th><?=$lang['mailbox']['active'];?></th>
							<th><?=$lang['mailbox']['action'];?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					$result = mysqli_query($link, "
						SELECT 
							`alias_domain`,
							`target_domain`,
							CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
								FROM `alias_domain`
									WHERE `target_domain` IN (
										SELECT `domain` FROM `domain_admins`
											WHERE `username`='".$_SESSION['mailcow_cc_username']."' 
											AND `active`='1'
									)
									OR 'admin'='".$_SESSION['mailcow_cc_role']."'");
					while ($row = mysqli_fetch_array($result)):
					?>
						<tr>
							<td><?=$row['alias_domain'];?></td>
							<td><?=$row['target_domain'];?></td>
							<td><?=$row['active'];?></td>
							<td><a href="/delete.php?aliasdomain=<?=$row['alias_domain'];?>"><?=$lang['mailbox']['remove'];?></a></td>
						</tr>
					<?php
					endwhile;
					?>
					</tbody>
				</table>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['mailbox']['mailboxes'];?> <span class="badge" id="numRowsMailbox"></span></h3>
					<div class="pull-right">
						<span class="clickable filter" data-toggle="tooltip" title="<?=$lang['mailbox']['filter_table'];?>" data-container="body">
							<i class="glyphicon glyphicon-filter"></i>
						</span>
						<a href="/add.php?mailbox"><span class="glyphicon glyphicon-plus"></span></a>
					</div>
				</div>
				<div class="panel-body">
					<input type="text" class="form-control" id="mailboxtable-filter" data-action="filter" data-filters="#mailboxtable" placeholder="Filter" />
				</div>
				<div class="table-responsive">
				<table class="table table-striped" id="mailboxtable">
					<thead>
						<tr>
							<th><?=$lang['mailbox']['username'];?></th>
							<th><?=$lang['mailbox']['fname'];?></th>
							<th><?=$lang['mailbox']['domain'];?></th>
							<th><?=$lang['mailbox']['quota'];?></th>
							<th><?=$lang['mailbox']['in_use'];?></th>
							<th><?=$lang['mailbox']['msg_num'];?></th>
							<th><?=$lang['mailbox']['active'];?></th>
							<th><?=$lang['mailbox']['action'];?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$result = mysqli_query($link, "SELECT
							`domain`.`backupmx`,
							`mailbox`.`username`,
							`mailbox`.`name`,
							CASE `mailbox`.`active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
							`mailbox`.`domain`,
							`mailbox`.`quota`,
							`quota2`.`bytes`,
							`quota2`.`messages`
								FROM mailbox, quota2, domain
									WHERE (`mailbox`.`username` = `quota2`.`username`)
									AND (`domain`.`domain` = `mailbox`.`domain`)
									AND (`mailbox`.`domain` IN (
										SELECT `domain` FROM `domain_admins`
											WHERE `username`='".$_SESSION['mailcow_cc_username']."'
												AND `active`='1'
											)
											OR 'admin'='".$_SESSION['mailcow_cc_role']."')");
						while ($row = mysqli_fetch_array($result)):
						?>
						<tr>
							<?php
							if ($row['backupmx'] == "0"):
							?>
								<td><?=$row['username'];?></td>
							<?php
							else:
							?>
								<td><span data-toggle="tooltip" title="Relayed"><i class="glyphicon glyphicon-forward"></i> <?=$row['username'];?></span></td>
							<?php
							endif;
							?>
							<td><?=utf8_encode($row['name']);?></td>
							<td><?=$row['domain'];?></td>
							<td><?=formatBytes($row['bytes'], 2);?> / <?=formatBytes($row['quota'], 2);?></td>
							<td style="min-width:120px;">
								<?php
								$percentInUse = round(($row['bytes'] / $row['quota']) * 100);
								if ($percentInUse >= 90) {
									$pbar = "progress-bar-danger";
								}
								elseif ($percentInUse >= 75) {
									$pbar = "progress-bar-warning";
								}
								else {
									$pbar = "progress-bar-success";
								}
								?>
								<div class="progress">
									<div class="progress-bar <?=$pbar;?>" role="progressbar" aria-valuenow="<?=$percentInUse;?>" aria-valuemin="0" aria-valuemax="100" style="min-width:2em;width: <?=$percentInUse;?>%;">
										<?=$percentInUse;?>%
									</div>
								</div>
							</td>
							<td><?=$row['messages'];?></td>
							<td><?=$row['active'];?></td>
							<td><a href="/delete.php?mailbox=<?=urlencode($row['username']);?>"><?=$lang['mailbox']['remove'];?></a> | 
							<a href="/edit.php?mailbox=<?=urlencode($row['username']);?>"><?=$lang['mailbox']['edit'];?></a></td>
						</tr>
						<?php
						endwhile;
						?>
					</tbody>
				</table>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['mailbox']['aliases'];?> <span class="badge" id="numRowsAlias"></span></h3>
					<div class="pull-right">
						<span class="clickable filter" data-toggle="tooltip" title="<?=$lang['mailbox']['filter_table'];?>" data-container="body">
							<i class="glyphicon glyphicon-filter"></i>
						</span>
						<a href="/add.php?alias"><span class="glyphicon glyphicon-plus"></span></a>
					</div>
				</div>
				<div class="panel-body">
					<input type="text" class="form-control" id="aliastable-filter" data-action="filter" data-filters="#aliastable" placeholder="Filter" />
				</div>
				<div class="table-responsive">
				<table class="table table-striped" id="aliastable">
					<thead>
						<tr>
							<th><?=$lang['mailbox']['alias'];?></th>
							<th><?=$lang['mailbox']['target_address'];?></th>
							<th><?=$lang['mailbox']['domain'];?></th>
							<th><?=$lang['mailbox']['active'];?></th>
							<th><?=$lang['mailbox']['action'];?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					$result = mysqli_query($link, "
						SELECT
							`address`,
							`goto`,
							`domain`,
							CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
								FROM alias
									WHERE (
										address NOT IN (
											SELECT username FROM mailbox
										)
										AND address!=goto
									) AND (domain IN (
										SELECT `domain` FROM `domain_admins`
											WHERE username='".$_SESSION['mailcow_cc_username']."' 
											AND active='1'
										)
										OR 'admin'='".$_SESSION['mailcow_cc_role']."')");
					while ($row = mysqli_fetch_array($result)):
					?>
						<tr>
							<td>
							<?php
							if(!filter_var($row['address'], FILTER_VALIDATE_EMAIL)):
							?>
								<span class="glyphicon glyphicon-pushpin" aria-hidden="true"></span> Catch-all @<?=$row['domain'];?>
							<?php
							else:
								echo $row['address'];
							endif;
							?>
							</td>
							<td>
							<?php
							foreach(explode(",", $row['goto']) as $goto) {
								echo nl2br($goto.PHP_EOL);
							}
							?>
							</td>
							<td><?=$row['domain'];?></td>
							<td><?=$row['active'];?></td>
							<td>
								<a href="/delete.php?alias=<?=urlencode($row['address']);?>"><?=$lang['mailbox']['remove'];?></a> 
								| <a href="/edit.php?alias=<?=urlencode($row['address']);?>"><?=$lang['mailbox']['edit'];?></a>
							</td>
						</tr>
					<?php
					endwhile;
					?>
					</tbody>
				</table>
				</div>
			</div>
		</div>
	</div>
</div> <!-- /container -->
<?php
}
else {
	header('Location: /');
}
require_once("inc/footer.inc.php");
?>
