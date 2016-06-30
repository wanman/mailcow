<?php
if (isset($_POST["login_user"]) && isset($_POST["pass_user"])) {
	$login_user = strtolower(trim($_POST["login_user"]));
	$as = check_login($link, $login_user, $_POST["pass_user"]);
	if ($as == "admin") {
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "admin";
		header("Location: /admin.php");
	}
	elseif ($as == "domainadmin") {
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "domainadmin";
		header("Location: /mailbox.php");
	}
	elseif ($as == "user") {
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "user";
		header("Location: /user.php");
	}
	else {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => $lang['danger']['login_failed']
		);
	}
}
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
	if (isset($_POST["trigger_set_admin"])) {
		$_SESSION['last_expanded'] = "collapseAdmin";
		set_admin_account($link, $_POST);
	}
	if (isset($_POST["trigger_public_folder"])) {
		$_SESSION['last_expanded'] = "collapsePubFolders";
		set_mailcow_config("public_folder", $_POST);
		dovecot_reload();
	}
	if (isset($_POST["pflog_renew"])) {
		$_SESSION['last_expanded'] = "collapseSysinfo";
		pflog_renew();
	}
	if (isset($_POST["srr"])) {
		$_SESSION['last_expanded'] = "collapseRestrictions";
		set_mailcow_config("srr", $_POST);
		postfix_reload();
	}
	if (isset($_POST["ssr"])) {
		$_SESSION['last_expanded'] = "collapseRestrictions";
		set_mailcow_config("ssr", $_POST);
		postfix_reload();
	}
	if (isset($_GET["del"])) {
		$_SESSION['last_expanded'] = "collapseDKIM";
		opendkim_table("delete", $_GET["del"]);
	}
	if (isset($_POST["maxmsgsize"])) {
		$_SESSION['last_expanded'] = "collapseMsgSize";
		set_mailcow_config("maxmsgsize", $_POST["maxmsgsize"]);
	}
	if (isset($_POST["dkim_selector"])) {
		$_SESSION['last_expanded'] = "collapseDKIM";
		opendkim_table("add", $_POST["dkim_selector"] . "_" . $_POST["dkim_domain"]);
	}
	if (isset($_POST["trigger_anonymize"])) {
		isset($_POST['anonymize']) ? $anonymize = 'on' : $anonymize = '';
		$_SESSION['last_expanded'] = "collapsePrivacy";
		set_mailcow_config("anonymize", $anonymize);
		postfix_reload();
	}
	if (isset($_POST["trigger_add_domain_admin"])) {
		$_SESSION['last_expanded'] = "collapseDomAdmins";
		add_domain_admin($link, $_POST);
	}
	if (isset($_POST["trigger_delete_domain_admin"])) {
		$_SESSION['last_expanded'] = "collapseDomAdmins";
		delete_domain_admin($link, $_POST);
	}
}
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "user") {
	if (isset($_POST["trigger_set_user_account"])) {
		$_SESSION['last_expanded'] = "collapseUserDetails";
		set_user_account($link, $_POST);
	}
	if (isset($_POST["trigger_set_spam_score"])) {
		$_SESSION['last_expanded'] = "collapseSpamFilter";
		set_spam_score($link, $_POST);
	}
	if (isset($_POST["trigger_set_whitelist"])) {
		$_SESSION['last_expanded'] = "collapseSpamFilter";
		set_whitelist($link, $_POST);
	}
	if (isset($_POST["trigger_delete_whitelist"])) {
		$_SESSION['last_expanded'] = "collapseSpamFilter";
		delete_whitelist($link, $_POST);
	}
	if (isset($_POST["trigger_set_blacklist"])) {
		$_SESSION['last_expanded'] = "collapseSpamFilter";
		set_blacklist($link, $_POST);
	}
	if (isset($_POST["trigger_delete_blacklist"])) {
		$_SESSION['last_expanded'] = "collapseSpamFilter";
		delete_blacklist($link, $_POST);
	}
	if (isset($_POST["trigger_set_tls_policy"])) {
		$_SESSION['last_expanded'] = "collapseTlsPolicy";
		set_tls_policy($link, $_POST);
	}
	if (isset($_POST["trigger_set_time_limited_aliases"])) {
		$_SESSION['last_expanded'] = "collapseSpamAlias";
		set_time_limited_aliases($link, $_POST);
	}
}
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
	if (isset($_POST["trigger_mailbox_action"])) {
		switch ($_POST["trigger_mailbox_action"]) {
			case "adddomain":
				mailbox_add_domain($link, $_POST);
			break;
			case "addalias":
				mailbox_add_alias($link, $_POST);
			break;
			case "editalias":
				mailbox_edit_alias($link, $_POST);
			break;
			case "addaliasdomain":
				mailbox_add_alias_domain($link, $_POST);
			break;
			case "addmailbox":
				mailbox_add_mailbox($link, $_POST);
			break;
			case "editdomain":
				mailbox_edit_domain($link, $_POST);
			break;
			case "editmailbox":
				mailbox_edit_mailbox($link, $_POST);
			break;
			case "editdomainadmin":
				mailbox_edit_domainadmin($link, $_POST);
			break;
			case "deletedomain":
				mailbox_delete_domain($link, $_POST);
			break;
			case "deletealias":
				mailbox_delete_alias($link, $_POST);
			break;
			case "deletealiasdomain":
				mailbox_delete_alias_domain($link, $_POST);
			break;
			case "editaliasdomain":
				mailbox_edit_alias_domain($link, $_POST);
			break;
			case "deletemailbox":
				mailbox_delete_mailbox($link, $_POST);
			break;
		}
	}
}
?>
