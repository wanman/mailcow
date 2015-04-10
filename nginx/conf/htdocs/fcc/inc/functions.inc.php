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
function return_fufix_config($s) {
	switch ($s) {
		case "extlist":
			$read_mime_check = file($GLOBALS["fufix_reject_attachments"])[0];
			preg_match('#\((.*?)\)#', $read_mime_check, $match);
			return $match[1];
			break;
		case "vtenable":
			$read_mime_check = file($GLOBALS["fufix_reject_attachments"])[0];
			if (strpos($read_mime_check,'FILTER') !== false) { return "checked"; } else { return false; }
			break;
		case "anonymize":
			$state = file_get_contents($GLOBALS["fufix_anonymize_headers"]);
			if (!empty($state)) { return "checked"; } else { return false; }
			break;
		case "vtupload":
			$state = file_get_contents($GLOBALS["VT_ENABLE_UPLOAD"]);
			if (empty($state)) { return "checked"; } else { return false; }
			break;
		case "vtapikey":
			return file_get_contents($GLOBALS["VT_API_KEY"]);
			break;
		case "senderaccess":
			$state = file($GLOBALS["fufix_sender_access"]);
			foreach ($state as $each) {
				$each_expl = explode(" ", $each);
				echo $each_expl[0], "\n";
			}
			break;
		case "maxmsgsize":
			return shell_exec("echo $(( $(/usr/sbin/postconf -h message_size_limit) / 1048576 ))");
			break;
	}
}
function set_fufix_config($s, $v = "", $vext = "") {
	switch ($s) {
		case "vtupload":
			if ($v != "1") {
				file_put_contents($GLOBALS["VT_ENABLE_UPLOAD"], "");
			}
			else {
				file_put_contents($GLOBALS["VT_ENABLE_UPLOAD"], "1");
			}
			break;
		case "maxmsgsize":
			shell_exec("sudo /usr/local/bin/fufix_msg_size $v");
			break;
		case "vtapikey":
			file_put_contents($GLOBALS["VT_API_KEY"], $v);
			break;
		case "extlist":
			if ($vext == "reject") {
				foreach (explode("|", $v) as $each_ext) { if (!ctype_alnum($each_ext) || strlen($each_ext) >= 10 ) { return false; } }
				file_put_contents($GLOBALS["fufix_reject_attachments"], "/name=[^>]*\.($v)/     REJECT     Dangerous files are prohibited on this server.".PHP_EOL);
			} elseif ($vext == "filter") {
				foreach (explode("|", $v) as $each_ext) { if (!ctype_alnum($each_ext) || strlen($each_ext) >= 10 ) { return false; } }
				file_put_contents($GLOBALS["fufix_reject_attachments"], "/name=[^>]*\.($v)/     FILTER     vfilter:dummy".PHP_EOL);
			}
			break;
		case "anonymize":
			$template = '/^\s*(Received: from)[^\n]*(.*)/ REPLACE $1 [127.0.0.1] (localhost [127.0.0.1])$2
/^\s*User-Agent/        IGNORE
/^\s*X-Enigmail/        IGNORE
/^\s*X-Mailer/          IGNORE
/^\s*X-Originating-IP/  IGNORE
		';
			if ($v == "on") {
				file_put_contents($GLOBALS["fufix_anonymize_headers"], $template);
			} else {
				file_put_contents($GLOBALS["fufix_anonymize_headers"], "");
			}
			break;
		case "senderaccess":
			file_put_contents($GLOBALS["fufix_sender_access"], "");
			foreach(preg_split("/((\r?\n)|(\r\n?))/", $v) as $each) {
				if ($each != "" && preg_match("/^[a-zA-Z0-9-\ .@]+$/", $each)) {
					file_put_contents($GLOBALS["fufix_sender_access"], "$each REJECT Sender not allowed".PHP_EOL, FILE_APPEND);
				}
			}
			$sender_map = $GLOBALS["fufix_sender_access"];
			shell_exec("/usr/sbin/postmap $sender_map");
			break;
	}
}
function opendkim_table($action = "show", $which = "") {
	switch ($action) {
		case "show":
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
			break;
		case "delete":
			if(!ctype_alnum(str_replace(array("_", "-", "."), "", $which))) {
				return false;
			}
			$selector = explode("_", $which)[0];
			$domain = explode("_", $which)[1];
			shell_exec("sudo /usr/local/bin/opendkim-keycontrol del $selector $domain");
			break;
		case "add":
			$selector = explode("_", $which)[0];
			$domain = explode("_", $which)[1];
			if(!ctype_alnum($selector) || !ctype_alnum(str_replace(array("-", "."), "", $domain))) {
				return false;
			}
			shell_exec("sudo /usr/local/bin/opendkim-keycontrol add $selector $domain");
			break;
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
		case "vfilterlog":
			$output = shell_exec("sudo -u vmail /usr/bin/tail /opt/vfilter/log/vfilter.log");
			if ($output != NULL) {
				return $output;
			}
			else {
				return "none";
			}
			break;
	}
}
function postfix_reload() {
	shell_exec("sudo /usr/sbin/postfix reload");
}
if (isset($link)) { mysqli_close($link); }
?>
