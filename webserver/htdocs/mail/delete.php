<?php
require_once("inc/header.inc.php");
?>
<div class="container">
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['delete']['title'];?></h3>
				</div>
				<div class="panel-body">
<?php
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin"  || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
		// DELETE DOMAIN
		if (isset($_GET["domain"]) &&
			is_valid_domain_name($_GET["domain"]) &&
			!empty($_GET["domain"]) &&
			$_SESSION['mailcow_cc_role'] == "admin") {

			$domain = mysqli_real_escape_string($link, $_GET["domain"]);
			?>
				<div class="alert alert-warning" role="alert"><?=$lang['delete']['remove_domain_warning'];?></div>
				<p><?=$lang['delete']['remove_domain_details'];?></p>
				<form class="form-horizontal" role="form" method="post" action="/mailbox.php">
				<input type="hidden" name="domain" value="<?php echo $domain ?>">
					<div class="form-group">
						<div class="col-sm-offset-1 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="deletedomain" class="btn btn-default btn-sm"><?=$lang['delete']['remove_button'];?></button>
						</div>
					</div>
				</form>
			<?php
		}
		// DELETE ALIAS
		elseif (isset($_GET["alias"]) &&
			(filter_var($_GET["alias"], FILTER_VALIDATE_EMAIL) || is_valid_domain_name(substr(strrchr($_GET["alias"], "@"), 1))) &&
			!empty($_GET["alias"])) {
				$domain = substr(strrchr($_GET["alias"], "@"), 1);
				if (hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
				?>
					<div class="alert alert-warning" role="alert"><?=sprintf($lang['delete']['remove_alias_warning'], $_GET["alias"]);?></div>
					<p><?=$lang['delete']['remove_alias_details'];?></p>
					<form class="form-horizontal" role="form" method="post" action="/mailbox.php">
					<input type="hidden" name="address" value="<?php echo $_GET["alias"] ?>">
						<div class="form-group">
							<div class="col-sm-offset-1 col-sm-10">
								<button type="submit" name="trigger_mailbox_action" value="deletealias" class="btn btn-default btn-sm"><?=$lang['delete']['remove_button'];?></button>
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
		// DELETE ALIAS DOMAIN
		elseif (
			isset($_GET["aliasdomain"]) &&
			is_valid_domain_name($_GET["aliasdomain"]) && 
			!empty($_GET["aliasdomain"])) {
				$alias_domain = mysqli_real_escape_string($link, $_GET["aliasdomain"]);
				if (hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $alias_domain)) {
				?>
					<div class="alert alert-warning" role="alert"><?=$lang['delete']['remove_domainalias_warning'];?></div>
					<form class="form-horizontal" role="form" method="post" action="/mailbox.php">
					<input type="hidden" name="alias_domain" value="<?php echo $alias_domain ?>">
						<div class="form-group">
							<div class="col-sm-offset-1 col-sm-10">
								<button type="submit" name="trigger_mailbox_action" value="deletealiasdomain" class="btn btn-default btn-sm"><?=$lang['delete']['remove_button'];?></button>
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
		// DELETE DOMAIN ADMIN
		elseif (isset($_GET["domainadmin"]) &&
			ctype_alnum(str_replace(array('_', '.', '-'), '', $_GET["domainadmin"])) &&
			!empty($_GET["domainadmin"]) &&
			$_SESSION['mailcow_cc_role'] == "admin") {
				$domain_admin = mysqli_real_escape_string($link, $_GET["domainadmin"]);
				?>
				<div class="alert alert-warning" role="alert"><?=$lang['delete']['remove_domainadmin_warning'];?></div>
				<form class="form-horizontal" role="form" method="post" action="/admin.php">
				<input type="hidden" name="username" value="<?=$domain_admin;?>">
					<div class="form-group">
						<div class="col-sm-offset-1 col-sm-10">
							<button type="submit" name="trigger_delete_domain_admin" class="btn btn-default btn-sm"><?=$lang['delete']['remove_button'];?></button>
						</div>
					</div>
				</form>
				<?php
		}
		// DELETE MAILBOX
		elseif (isset($_GET["mailbox"]) &&
			filter_var($_GET["mailbox"], FILTER_VALIDATE_EMAIL) &&
			!empty($_GET["mailbox"])) {
				$mailbox = mysqli_real_escape_string($link, $_GET["mailbox"]);
				$domain = substr(strrchr($mailbox, "@"), 1);
				if (hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
				?>
					<div class="alert alert-warning" role="alert"><?=$lang['delete']['remove_mailbox_warning'];?></div>
					<p><?=$lang['delete']['remove_mailbox_details'];?></p>
					<form class="form-horizontal" role="form" method="post" action="/mailbox.php">
					<input type="hidden" name="username" value="<?=$mailbox;?>">
						<div class="form-group">
							<div class="col-sm-offset-1 col-sm-10">
								<button type="submit" name="trigger_mailbox_action" value="deletemailbox" class="btn btn-default btn-sm"><?=$lang['delete']['remove_button'];?></button>
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
<a href="<?=$_SESSION['return_to'];?>">&#8592; <?=$lang['delete']['previous'];?></a>
</div> <!-- /container -->
<?php
require_once("inc/footer.inc.php");
?>
