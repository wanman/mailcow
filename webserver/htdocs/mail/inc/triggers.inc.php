<?php
if (isset($_POST["login_user"]) && isset($_POST["pass_user"])) {
	$login_user = strtolower(trim($_POST["login_user"]));
	$as = check_login($login_user, $_POST["pass_user"]);
	if ($as == "admin" && "domainadmin" == $_POST["login_role"]) {
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "admin";
		if(isset($_POST["remember_user"]) && $_POST["remember_user"]) {
			setcookie("admin", $login_user, time() + (86400 * 5));
		}
		header("Location: /admin.php");
	}
	elseif ($as == "domainadmin" && "domainadmin" == $_POST["login_role"]) {
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "domainadmin";
		if(isset($_POST["remember_user"]) && $_POST["remember_user"]) {
			setcookie("admin", $login_user, time() + (86400 * 5));
		}
		header("Location: /mailbox.php");
	}
	elseif ($as == "user" && "mailboxuser" == $_POST["login_role"]) {
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "user";
		if(isset($_POST["remember_user"]) && $_POST["remember_user"]) {
			setcookie("user", $login_user, time() + (86400 * 5));
		}
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
		set_admin_account($_POST);
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
	if (isset($_POST["reset-srr"])) {
		$_SESSION['last_expanded'] = "collapseRestrictions";
		set_mailcow_config("reset-srr");
		postfix_reload();
	}
	if (isset($_POST["reset-ssr"])) {
		$_SESSION['last_expanded'] = "collapseRestrictions";
		set_mailcow_config("reset-ssr");
		postfix_reload();
	}
	if (isset($_POST["delete_dkim_record"])) {
		$_SESSION['last_expanded'] = "collapseDKIM";
		opendkim_table("delete", $_POST["delete_dkim_record"]);
	}
	if (isset($_POST["maxmsgsize"])) {
		$_SESSION['last_expanded'] = "collapseMsgSize";
		set_mailcow_config("maxmsgsize", $_POST["maxmsgsize"]);
	}
	if (isset($_POST["add_dkim_record"])) {
		$_SESSION['last_expanded'] = "collapseDKIM";
		opendkim_table("add", $_POST);
	}
	if (isset($_POST["trigger_anonymize"])) {
		isset($_POST['anonymize']) ? $anonymize = 'on' : $anonymize = '';
		$_SESSION['last_expanded'] = "collapsePrivacy";
		set_mailcow_config("anonymize", $anonymize);
		postfix_reload();
	}
	if (isset($_POST["trigger_add_domain_admin"])) {
		$_SESSION['last_expanded'] = "collapseDomAdmins";
		add_domain_admin($_POST);
	}
	if (isset($_POST["trigger_delete_domain_admin"])) {
		$_SESSION['last_expanded'] = "collapseDomAdmins";
		delete_domain_admin($_POST);
	}
	if (isset($_POST["trigger_edit_domain_admin"])) {
		$_SESSION['last_expanded'] = "collapseDomAdmins";
		edit_domain_admin($_POST);
	}
}
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "user") {
	if (isset($_POST["trigger_set_user_account"])) {
		$_SESSION['last_expanded'] = "collapseUserDetails";
		set_user_account($_POST);
	}
	if (isset($_POST["trigger_set_spam_score"])) {
		$_SESSION['last_expanded'] = "collapseSpamFilter";
		set_spam_score($_POST);
	}
	if (isset($_POST["trigger_set_whitelist"])) {
		$_SESSION['last_expanded'] = "collapseSpamFilter";
		set_whitelist($_POST);
	}
	if (isset($_POST["trigger_delete_whitelist"])) {
		$_SESSION['last_expanded'] = "collapseSpamFilter";
		delete_whitelist($_POST);
	}
	if (isset($_POST["trigger_set_blacklist"])) {
		$_SESSION['last_expanded'] = "collapseSpamFilter";
		set_blacklist($_POST);
	}
	if (isset($_POST["trigger_delete_blacklist"])) {
		$_SESSION['last_expanded'] = "collapseSpamFilter";
		delete_blacklist($_POST);
	}
	if (isset($_POST["trigger_set_tls_policy"])) {
		$_SESSION['last_expanded'] = "collapseTlsPolicy";
		set_tls_policy($_POST);
	}
	if (isset($_POST["trigger_set_time_limited_aliases"])) {
		$_SESSION['last_expanded'] = "collapseSpamAlias";
		set_time_limited_aliases($_POST);
	}
}
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
	if (isset($_GET["js"])) {
		switch ($_GET["js"]) {
			case "remaining_specs":
				remaining_specs($_GET['domain'], $_GET['object'], "y");
			break;
		}
	}
	if (isset($_POST["trigger_mailbox_action"])) {
		switch ($_POST["trigger_mailbox_action"]) {
			case "adddomain":
				mailbox_add_domain($_POST);
			break;
			case "addalias":
				mailbox_add_alias($_POST);
			break;
			case "editalias":
				mailbox_edit_alias($_POST);
			break;
			case "addaliasdomain":
				mailbox_add_alias_domain($_POST);
			break;
			case "addmailbox":
				mailbox_add_mailbox($_POST);
			break;
			case "editdomain":
				mailbox_edit_domain($_POST);
			break;
			case "editmailbox":
				mailbox_edit_mailbox($_POST);
			break;
			case "deletedomain":
				mailbox_delete_domain($_POST);
			break;
			case "deletealias":
				mailbox_delete_alias($_POST);
			break;
			case "deletealiasdomain":
				mailbox_delete_alias_domain($_POST);
			break;
			case "editaliasdomain":
				mailbox_edit_alias_domain($_POST);
			break;
			case "deletemailbox":
				mailbox_delete_mailbox($_POST);
			break;
		}
	}
}
?>
