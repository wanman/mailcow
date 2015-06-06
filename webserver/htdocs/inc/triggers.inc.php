<?php
if (isset($_POST["login_user"]) && isset($_POST["pass_user"])) {
	if (check_login($link, $_POST["login_user"], $_POST["pass_user"]) == "admin") {
		$_SESSION['fufix_cc_loggedin'] = "yes";
		$_SESSION['fufix_cc_username'] = $_POST["login_user"];
		$_SESSION['fufix_cc_role'] = "admin";
		header("Location: admin.php");
	}
	elseif (check_login($link, $_POST["login_user"], $_POST["pass_user"]) == "domainadmin") {
		$_SESSION['fufix_cc_loggedin'] = "yes";
		$_SESSION['fufix_cc_username'] = $_POST["login_user"];
		$_SESSION['fufix_cc_role'] = "domainadmin";
		header("Location: mailbox.php");

	}
}
if (isset($_SESSION['fufix_cc_loggedin']) && $_SESSION['fufix_cc_loggedin'] == "yes" && $_SESSION['fufix_cc_role'] == "admin") {
	if (isset($_POST["admin_user"])) {
		set_admin_account($link, $_POST);
	}
	if (isset($_POST["use_backup"])) {
		set_fufix_config("backup", $_POST);
	}
	if (isset($_GET["del"])) {
		opendkim_table("delete", $_GET["del"]);
	}
	if (isset($_GET["av_dl"])) {
		dl_clamav_positives();
	}
	if (file_exists("/tmp/clamav_positives.zip")) {
		unlink("/tmp/clamav_positives.zip");
	}
	if (isset($_POST["vtapikey"]) && ctype_alnum($_POST["vtapikey"])) {
		set_fufix_config("vtapikey", $_POST["vtapikey"]);
	}
	if (isset($_POST["maxmsgsize"]) && ctype_alnum($_POST["maxmsgsize"])) {
		set_fufix_config("maxmsgsize", $_POST["maxmsgsize"]);
	}
	if (isset($_POST["sender"])) {
		set_fufix_config("senderaccess", $_POST["sender"]);
		postfix_reload();
	}
	if (isset($_POST["dkim_selector"])) {
		opendkim_table("add", $_POST["dkim_selector"] . "_" . $_POST["dkim_domain"]);
	}
	if (isset($_POST["ext"])) {
		if (isset($_POST["vfilter"]) && $_POST["vfilter"] == "filter") {
			set_fufix_config("extlist", $_POST["ext"], "filter");
		} else {
			set_fufix_config("extlist", $_POST["ext"], "reject");
		}
		if (isset($_POST["virustotalcheckonly"]) && $_POST["virustotalcheckonly"] == "on") {
			set_fufix_config("vtupload", "0");
		} else {
			set_fufix_config("vtupload", "1");
		}
		if (isset($_POST["virustotalenable"]) && $_POST["virustotalenable"] == "on") {
			set_fufix_config("vtenable", "1");
		} else {
			set_fufix_config("vtenable", "0");
		}
		if (isset($_POST["clamavenable"]) && $_POST["clamavenable"] == "on") {
			set_fufix_config("cavenable", "1");
		} else {
			set_fufix_config("cavenable", "0");
		}
		postfix_reload();
	}
	if (isset($_POST["anonymize_"])) {
		if (!isset($_POST["anonymize"])) { $_POST["anonymize"] = ""; }
		set_fufix_config("anonymize", $_POST["anonymize"]);
		postfix_reload();
	}
	if (isset($_POST["mailboxaction"])) {
		switch ($_POST["mailboxaction"]) {
			case "deletedomainadmin":
				delete_domain_admin($link, $_POST);
			break;
			case "adddomainadmin":
				add_domain_admin($link, $_POST);
			break;
		}
	}
}
if (isset($_SESSION['fufix_cc_loggedin']) && $_SESSION['fufix_cc_loggedin'] == "yes" && ($_SESSION['fufix_cc_role'] == "domainadmin" || $_SESSION['fufix_cc_role'] == "admin")) {
	if (isset($_POST["mailboxaction"])) {
		switch ($_POST["mailboxaction"]) {
			case "adddomain":
				mailbox_add_domain($link, $_POST);
			break;
			case "addalias":
				mailbox_add_alias($link, $_POST);
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
			case "deletemailbox":
				mailbox_delete_mailbox($link, $_POST);
			break;
		}
	}
}
if (isset($_POST["logout"])) {
	$_SESSION['fufix_cc_loggedin'] = "no";
	session_destroy();
}
?>

