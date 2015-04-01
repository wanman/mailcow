<?php
function check_login($user, $pass, $pfconfig) {
	if(!filter_var($user, FILTER_VALIDATE_EMAIL)) {
		return false;
	}
	$pass = escapeshellcmd($pass);
	include_once($pfconfig);
	$link = mysqli_connect('localhost', $CONF['database_user'], $CONF['database_password'], $CONF['database_name']);
	$result = mysqli_query($link, "select password from admin where superadmin=1 and username='$user'");
	while ($row = mysqli_fetch_array($result, MYSQL_NUM)) {
		$row = "'".$row[0]."'";
		if (strpos(shell_exec("echo $pass | doveadm pw -s SHA512-CRYPT -t $row"), "verified") !== false) {
			return true;
		}
	}
	return false;
}
function postfix_reload() {
	shell_exec("sudo /usr/sbin/postfix reload");
}
function set_fufix_msg_size($MB) {
	shell_exec("sudo /usr/local/bin/fufix_msg_size $MB");
}

function return_fufix_reject_attachments_toggle() {
	$read_mime_check = file($GLOBALS["fufix_reject_attachments"])[0];
	if (strpos($read_mime_check,'FILTER') !== false) { return "checked"; } else { return false; }
}
function return_fufix_reject_attachments() {
	$read_mime_check = file($GLOBALS["fufix_reject_attachments"])[0];
	preg_match('#\((.*?)\)#', $read_mime_check, $match);
	return $match[1];
}
function return_fufix_anonymize_toggle() {
    $state = file_get_contents($GLOBALS["fufix_anonymize_headers"]);
    if (!empty($state)) { return "checked"; } else { return false; }
}
function echo_fufix_opendkim_table() {
	$dnstxt_folder = scandir($GLOBALS["fufix_opendkim_dnstxt_folder"]);
	$dnstxt_files = array_diff($dnstxt_folder, array('.', '..'));
	foreach($dnstxt_files as $file) {
	echo "<div class=\"row\">
		<div class=\"col-xs-2\">
			<p class=\"text-justify\">
			Domain:<br /><strong>", explode("_", $file)[1], "</strong><br />
			Selector:<br /><strong>", explode("_", $file)[0], "</strong><br />
			</p>
		</div>
		<div class=\"col-xs-9\">
			<pre>", file_get_contents($GLOBALS["fufix_opendkim_dnstxt_folder"]."/".$file), "</pre>
		</div>
		<div class=\"col-xs-1\">
			<a href=\"?del=", $file, "\" onclick=\"return confirm('Are you sure?')\"><span class=\"glyphicon glyphicon-remove-circle\"></span></a>
		</div>
	</div>";
	}
}
function echo_fufix_sender_access() {
	$state = file($GLOBALS["fufix_sender_access"]);
	foreach ($state as $each) {
		$each_expl = explode(" ", $each);
		echo $each_expl[0], "\n";
	}
}
function set_fufix_sender_access($what) {
	file_put_contents($GLOBALS["fufix_sender_access"], "");
	foreach(preg_split("/((\r?\n)|(\r\n?))/", $what) as $each) {
		if ($each != "" && preg_match("/^[a-zA-Z0-9-\ .@]+$/", $each)) {
			file_put_contents($GLOBALS["fufix_sender_access"], "$each REJECT Sender not allowed".PHP_EOL, FILE_APPEND);
		}
	}
	$sender_map = $GLOBALS["fufix_sender_access"];
	shell_exec("/usr/sbin/postmap $sender_map");
}
function del_fufix_opendkim_entry($which) {
	if(!ctype_alnum(str_replace(array("_", "-", "."), "", $which))) {
		return false;
	}
	$selector = explode("_", $which)[0];
	$domain = explode("_", $which)[1];
	shell_exec("sudo /usr/local/bin/opendkim-keycontrol del $selector $domain");
}
function add_fufix_opendkim_entry($selector, $domain) {
	if(!ctype_alnum($selector) || !ctype_alnum(str_replace(array("-", "."), "", $domain))) {
		return false;
	}
	shell_exec("sudo /usr/local/bin/opendkim-keycontrol add $selector $domain");
}
function return_vt_enable_upload_toggle() {
	$state = file_get_contents($GLOBALS["VT_ENABLE_UPLOAD"]);
	if (empty($state)) { return "checked"; } else { return false; }
}
function return_vt_filter_log() {
	$output = shell_exec("sudo -u vmail /usr/bin/tail /opt/vfilter/log/vfilter.log");
	if ($output != NULL) {
		return $output;
	}
	else {
		return "none";
	}
}
function set_vt_enable_upload_toggle($value) {
	if ($value != "1") {
		file_put_contents($GLOBALS["VT_ENABLE_UPLOAD"], "");
	}
	else {
		file_put_contents($GLOBALS["VT_ENABLE_UPLOAD"], "1");
	}
}
function set_fufix_reject_attachments($ext, $action) {
	if ($action == "reject") {
		foreach (explode("|", $ext) as $each_ext) { if (!ctype_alnum($each_ext) || strlen($each_ext) >= 10 ) { return false; } }
		file_put_contents($GLOBALS["fufix_reject_attachments"], "/name=[^>]*\.($ext)/     REJECT     Dangerous files are prohibited on this server.".PHP_EOL);
	} elseif ($action == "filter") {
		foreach (explode("|", $ext) as $each_ext) { if (!ctype_alnum($each_ext) || strlen($each_ext) >= 10 ) { return false; } }
		file_put_contents($GLOBALS["fufix_reject_attachments"], "/name=[^>]*\.($ext)/     FILTER     vfilter:dummy".PHP_EOL);
	}
}
function set_fufix_anonymize_headers($toggle) {
	$template = '/^\s*(Received: from)[^\n]*(.*)/ REPLACE $1 [127.0.0.1] (localhost [127.0.0.1])$2
/^\s*User-Agent/        IGNORE
/^\s*X-Enigmail/        IGNORE
/^\s*X-Mailer/          IGNORE
/^\s*X-Originating-IP/  IGNORE
';
	if ($toggle == "on") {
		file_put_contents($GLOBALS["fufix_anonymize_headers"], $template);
	} else {
		file_put_contents($GLOBALS["fufix_anonymize_headers"], "");
	}
}
function echo_sys_info($what) {
	switch ($what) {
	case "ram":
		echo round(`free | grep Mem | awk '{print $3/$2 * 100.0}'`);
		break;
	case "maildisk":
		echo preg_replace('/\D/', '', `df -h /var/vmail/ | tail -n1 | awk {'print $5'}`);
		break;
	case "mailq":
		echo `mailq`;
		break;
	}
}
if (isset($link)) { mysqli_close($link); }
?>


