<?php
if (isset($_POST["login_user"]) && isset($_POST["pass_user"])) {
	if (check_login($_POST["login_user"], $_POST["pass_user"], "/var/www/mail/pfadmin/config.local.php") == true) { $_SESSION['fufix_cc_loggedin'] = "yes"; }
}
if (isset($_SESSION['fufix_cc_loggedin']) && $_SESSION['fufix_cc_loggedin'] == "yes") {
	if (isset($_GET["del"])) {
		opendkim_table("delete", $_GET["del"]);
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
		postfix_reload();
	}
	if (isset($_POST["anonymize_"])) {
		if (!isset($_POST["anonymize"])) { $_POST["anonymize"] = ""; }
		set_fufix_config("anonymize", $_POST["anonymize"]);
		postfix_reload();
	}
	if (isset($_POST["logout"])) {
		$_SESSION['fufix_cc_loggedin'] = "no";
	}
}
?>
