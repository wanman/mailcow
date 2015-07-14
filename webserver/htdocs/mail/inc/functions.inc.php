<?php
function check_login($link, $user, $pass) {
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $user))) {
		return false;
	}
	$pass = escapeshellcmd($pass);
	$result = mysqli_query($link, "SELECT password FROM admin WHERE superadmin='1' AND username='$user'");
	while ($row = mysqli_fetch_array($result, MYSQL_NUM)) {
		$row = "'".$row[0]."'";
		if (strpos(shell_exec("echo $pass | doveadm pw -s SHA512-CRYPT -t $row"), "verified") !== false) {
			return "admin";
		}
	}
	$result = mysqli_query($link, "SELECT password FROM admin WHERE superadmin='0' AND active='1' AND username='$user'");
	while ($row = mysqli_fetch_array($result, MYSQL_NUM)) {
		$row = "'".$row[0]."'";
		if (strpos(shell_exec("echo $pass | doveadm pw -s SHA512-CRYPT -t $row"), "verified") !== false) {
			return "domainadmin";
		}
	}
	$result = mysqli_query($link, "SELECT password FROM mailbox WHERE active='1' AND username='$user'");
	while ($row = mysqli_fetch_array($result, MYSQL_NUM)) {
		$row = "'".$row[0]."'";
		if (strpos(shell_exec("echo $pass | doveadm pw -s SHA512-CRYPT -t $row"), "verified") !== false) {
			return "user";
		}
	}
	return false;
}
function formatBytes($size, $precision = 2) {
	$base = log($size, 1024);
	$suffixes = array(' Byte', 'k', 'M', 'G', 'T');
	if ($size == "0") {
		return "0";
	}
	return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
}
function mysqli_result($res,$row=0,$col=0) { 
    $numrows = mysqli_num_rows($res); 
    if ($numrows && $row <= ($numrows-1) && $row >=0){
        mysqli_data_seek($res,$row);
        $resrow = (is_numeric($col)) ? mysqli_fetch_row($res) : mysqli_fetch_assoc($res);
        if (isset($resrow[$col])){
            return $resrow[$col];
        }
    }
    return false;
}
function dl_clamav_positives() {
	$files = scandir("/opt/vfilter/clamav_positives");
	$files = array_diff($files, array('.', '..'));
	if (empty($files)) { return false; }
	$zipname = "/tmp/clamav_positives.zip";
	$zip = new ZipArchive;
	$zip->open($zipname, ZipArchive::CREATE);
	foreach ($files as $file) {
		$zip->addFile("/opt/vfilter/clamav_positives/$file", "$file" . ".txt");
	}
	$zip->close();
	header("Content-Disposition: attachment; filename=clamav_positives.zip");
	header("Content-length: " . filesize("/tmp/clamav_positives.zip"));
	header("Pragma: no-cache");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Expires: 0");
	readfile("/tmp/clamav_positives.zip");

}
function return_mailcow_config($s) {
	switch ($s) {
		case "backup_location":
			preg_match("/LOCATION=(.*)/", file_get_contents($GLOBALS['MC_MBOX_BACKUP']) , $result);
			if (!empty($result[1])) { return $result[1]; } else { return "/backup/mail"; }
			break;
		case "backup_runtime":
			preg_match("/RUNTIME=(.*)/", file_get_contents($GLOBALS['MC_MBOX_BACKUP']) , $result);
			if (!empty($result[1])) { return $result[1]; } else { return false; }
			break;
		case "backup_active":
			preg_match("/BACKUP=(.*)/", file_get_contents("/var/www/MAILBOX_BACKUP") , $result);
			if (!empty($result[1])) { return $result[1]; } else { return false; }
			break;
		case "extlist":
			$read_mime_check = file($GLOBALS["mailcow_reject_attachments"])[0];
			preg_match('#\((.*?)\)#', $read_mime_check, $match);
			return $match[1];
			break;
		case "vfilter":
			$read_mime_check = file($GLOBALS["mailcow_reject_attachments"])[0];
			if (strpos($read_mime_check,'FILTER') !== false) { return "checked"; } else { return false; }
			break;
		case "anonymize":
			$state = file_get_contents($GLOBALS["mailcow_anonymize_headers"]);
			if (!empty($state)) { return "checked"; } else { return false; }
			break;
		case "vtenable":
			$state = file_get_contents($GLOBALS["VT_ENABLE"]);
			if (!empty($state)) { return "checked"; } else { return false; }
			break;
		case "cavenable":
			$state = file_get_contents($GLOBALS["CAV_ENABLE"]);
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
			$state = file($GLOBALS["mailcow_sender_access"]);
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
function set_mailcow_config($s, $v = "", $vext = "") {
	switch ($s) {
		case "backup":
			$file="/var/www/MAILBOX_BACKUP";
			if (($v['use_backup'] != "on" && $v['use_backup'] != "") || 
				($v['runtime'] != "hourly" && $v['runtime'] != "daily" && $v['runtime'] != "monthly")) {
					header("Location: do.php?event=".base64_encode("Invalid form data"));
					die("Invalid form data");
			}
			if (!ctype_alnum(str_replace("/", "", $v['location']))) {
				header("Location: do.php?event=".base64_encode("Invalid form data"));
				die("Invalid form data");
			}
			if (!isset($v['use_backup']) || empty($v['use_backup'])) {
				$v['use_backup']="off";
			}
			file_put_contents($file, "BACKUP=".$v['use_backup'].PHP_EOL, LOCK_EX);
			file_put_contents($file, "MBOX=(".PHP_EOL, FILE_APPEND | LOCK_EX);
			foreach ($v['mailboxes'] as $mbox) {
				if (!filter_var($mbox, FILTER_VALIDATE_EMAIL)) {
					header("Location: do.php?event=".base64_encode("Mail address format invalid"));
					die("Mail address format invalid"); 
				}
				file_put_contents($file, $mbox.PHP_EOL, FILE_APPEND | LOCK_EX);
			}
			file_put_contents($file, ")".PHP_EOL.'RUNTIME='.$v['runtime'].PHP_EOL, FILE_APPEND | LOCK_EX);
			file_put_contents($file, "LOCATION=".$v['location'].PHP_EOL, FILE_APPEND | LOCK_EX);
			exec("sudo /usr/local/sbin/mc_inst_cron", $out, $return);
			if ($return != "0") {
				header("Location: do.php?event=".base64_encode("Error setting up cronjob"));
				die("Error setting up cronjob");
			}
			header('Location: do.php?return=success');
			break;
		case "vtupload":
			if ($v != "1") {
				file_put_contents($GLOBALS["VT_ENABLE_UPLOAD"], "");
				header('Location: do.php?return=success');
			}
			else {
				file_put_contents($GLOBALS["VT_ENABLE_UPLOAD"], "1");
				header('Location: do.php?return=success');
			}
			break;
		case "vtenable":
			if ($v != "1") {
				file_put_contents($GLOBALS["VT_ENABLE"], "");
				header('Location: do.php?return=success');
			}
			else {
				file_put_contents($GLOBALS["VT_ENABLE"], "1");
				header('Location: do.php?return=success');
			}
			break;
		case "cavenable":
			if ($v != "1") {
				file_put_contents($GLOBALS["CAV_ENABLE"], "");
				header('Location: do.php?return=success');
			}
			else {
				file_put_contents($GLOBALS["CAV_ENABLE"], "1");
				header('Location: do.php?return=success');
			}
			break;
		case "maxmsgsize":
			exec("sudo /usr/local/sbin/mc_msg_size $v", $out, $return);
			if ($return != "0") {
				header("Location: do.php?event=".base64_encode("Error setting max. message size"));
				die("Error setting max. message size");
			}
			header('Location: do.php?return=success');
			break;
		case "vtapikey":
			file_put_contents($GLOBALS["VT_API_KEY"], $v);
			header('Location: do.php?return=success');
			break;
		case "extlist":
			if ($vext == "reject") {
				foreach (explode("|", $v) as $each_ext) { if (!ctype_alnum($each_ext) || strlen($each_ext) >= 10 ) { return false; } }
				file_put_contents($GLOBALS["mailcow_reject_attachments"], "/name=[^>]*\.($v)/     REJECT     Dangerous files are prohibited on this server.".PHP_EOL);
				header('Location: do.php?return=success');
			} elseif ($vext == "filter") {
				foreach (explode("|", $v) as $each_ext) { if (!ctype_alnum($each_ext) || strlen($each_ext) >= 10 ) { return false; } }
				file_put_contents($GLOBALS["mailcow_reject_attachments"], "/name=[^>]*\.($v)/     FILTER     vfilter:dummy".PHP_EOL);
				header('Location: do.php?return=success');
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
				file_put_contents($GLOBALS["mailcow_anonymize_headers"], $template);
				header('Location: do.php?return=success');
			} else {
				file_put_contents($GLOBALS["mailcow_anonymize_headers"], "");
				header('Location: do.php?return=success');
			}
			break;
		case "senderaccess":
			file_put_contents($GLOBALS["mailcow_sender_access"], "");
			$sender_array = array_keys(array_flip(preg_split("/((\r?\n)|(\r\n?))/", $v)));
			foreach($sender_array as $each) {
				if ($each != "" && preg_match("/^[a-zA-Z0-9-\ .@]+$/", $each)) {
					file_put_contents($GLOBALS["mailcow_sender_access"], "$each REJECT Sender not allowed".PHP_EOL, FILE_APPEND);
				}
			}
			$sender_map = $GLOBALS["mailcow_sender_access"];
			shell_exec("/usr/sbin/postmap $sender_map");
			header('Location: do.php?return=success');
			break;
	}
}
function opendkim_table($action = "show", $which = "") {
	switch ($action) {
		case "show":
			$dnstxt_folder = scandir($GLOBALS["mailcow_opendkim_dnstxt_folder"]);
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
					<pre>", file_get_contents($GLOBALS["mailcow_opendkim_dnstxt_folder"]."/".$file), "</pre>
				</div>
				<div class=\"col-xs-1\">
					<a href=\"?del=", $file, "\" onclick=\"return confirm('Are you sure?')\"><span class=\"glyphicon glyphicon-remove-circle\"></span></a>
				</div>
			</div>";
			}
			break;
		case "delete":
			if(!ctype_alnum(str_replace(array("_", "-", "."), "", $which))) {
				header("Location: do.php?event=".base64_encode("Invalid format"));
				die("Invalid format");
			}
			$selector = explode("_", $which)[0];
			$domain = explode("_", $which)[1];
			exec("sudo /usr/local/bin/mc-dkim-ctrl del $selector $domain", $hash, $return);
			if ($return != "0") {
				header("Location: do.php?event=".base64_encode("Cannot delete domain record. Does it exist?"));
				die("Cannot delete domain record. Does it exist?");
			}
			header('Location: do.php?return=success');
			break;
		case "add":
			$selector = explode("_", $which)[0];
			$domain = explode("_", $which)[1];
			if(!ctype_alnum($selector) || !ctype_alnum(str_replace(array("-", "."), "", $domain))) {
				header("Location: do.php?event=".base64_encode("Invalid format"));
				die("Invalid format");
			}
			exec("sudo /usr/local/bin/mc-dkim-ctrl add $selector $domain", $hash, $return);
			if ($return != "0") {
				header("Location: do.php?event=".base64_encode("Cannot add this domain. Does it already exist?"));
				die("Cannot add this domain. Does it already exist?");
			}
			header('Location: do.php?return=success');
			break;
	}
}
function echo_sys_info($what, $extra="") {
	switch ($what) {
		case "ram":
			echo round(shell_exec('free | grep Mem | awk \'{print $3/$2 * 100.0}\''));
			break;
		case "maildisk":
			echo preg_replace('/\D/', '', shell_exec('df -h /var/vmail/ | tail -n1 | awk {\'print $5\'}'));
			break;
		case "mailq":
			echo shell_exec("mailq");
			break;
		case "positives";
			$pos = glob('/opt/vfilter/clamav_positives/message*', GLOB_BRACE);
			if (empty($pos)) {
				echo "0";
			}
			else {
				echo count($pos);
			}
			break;
		case "vfilterlog":
			if ( is_numeric($extra) && $extra <= 70) {
				$lines = $extra;
			}
			else {
				$lines = "30";
			};
			$output = shell_exec("sudo -u vmail /usr/bin/tail -n $lines /opt/vfilter/log/vfilter.log");
			if ($output != NULL) {
				echo $output;
			}
			else {
				echo "none";
			}
			break;
	}
}
function postfix_reload() {
	shell_exec("sudo /usr/sbin/postfix reload");
}
function mailbox_add_domain($link, $postarray) {
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	$domain = mysqli_real_escape_string($link, $postarray['domain']);
	$description = mysqli_real_escape_string($link, $postarray['description']);
	$aliases = mysqli_real_escape_string($link, $postarray['aliases']);
	$mailboxes = mysqli_real_escape_string($link, $postarray['mailboxes']);
	$maxquota = mysqli_real_escape_string($link, $postarray['maxquota']);
	$quota = mysqli_real_escape_string($link, $postarray['quota']); 
	if ($maxquota > $quota) {
		header("Location: do.php?event=".base64_encode("Max. size per mailbox can not be greater than domain quota"));
		die("Max. size per mailbox can not be greater than domain quota");
	}
	if (isset($postarray['active']) && $postarray['active'] == "on") { $active = "1"; } else { $active = "0"; }
	if (isset($postarray['backupmx']) && $postarray['backupmx'] == "on") { $backupmx = "1"; } else { $backupmx = "0"; }
	if (!ctype_alnum(str_replace(array('.', '-'), '', $domain))) {
		header("Location: do.php?event=".base64_encode("Domain name invalid"));
		die("Domain name invalid");
	}
	foreach (array($quota, $maxquota, $mailboxes, $aliases) as $data) {
		if (!is_numeric($data)) { 
			header("Location: do.php?event=".base64_encode("'$data' is not numeric"));
			die("'$data' is not numeric"); 
		}
	}
	$mystring = "INSERT INTO domain (domain, description, aliases, mailboxes, maxquota, quota, transport, backupmx, created, modified, active)
		VALUES ('$domain', '$description', '$aliases', '$mailboxes', '$maxquota', '$quota', 'virtual', '$backupmx', now(), now(), '$active')";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("Cannot add domain"));
		die("Cannot add domain");
	}
	header('Location: do.php?return=success');
}
function mailbox_add_alias($link, $postarray) {
	$address = mysqli_real_escape_string($link, $_POST['address']);
	$goto = mysqli_real_escape_string($link, $_POST['goto']);
	$domain = substr($address, strpos($address, '@')+1);
	global $logged_in_role;
	global $logged_in_as;
	if (!mysqli_result(mysqli_query($link, "SELECT domain FROM domain WHERE domain='$domain' AND (domain NOT IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'!='$logged_in_role')"))) { 
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if (isset($_POST['active']) && $_POST['active'] == "on") { $active = "1"; } else { $active = "0"; }
	if (!filter_var($address, FILTER_VALIDATE_EMAIL) || !filter_var($goto, FILTER_VALIDATE_EMAIL)) {
		header("Location: do.php?event=".base64_encode("Mail address format invalid"));
		die("Mail address format invalid");
	}
	if (!mysqli_result(mysqli_query($link, "SELECT domain FROM domain WHERE domain='$domain'"))) { 
		header("Location: do.php?event=".base64_encode("Domain $domain not found"));
		die("Domain $domain not found");
	}
	$mystring = "INSERT INTO alias (address, goto, domain, created, modified, active) VALUE ('$address', '$goto', '$domain', now(), now(), '$active')";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	header('Location: do.php?return=success');
}
function mailbox_add_alias_domain($link, $postarray) {
	$alias_domain = mysqli_real_escape_string($link, $_POST['alias_domain']);
	$target_domain = mysqli_real_escape_string($link, $_POST['target_domain']);
	global $logged_in_role;
	global $logged_in_as;
	if (!mysqli_result(mysqli_query($link, "SELECT domain FROM domain WHERE domain='$target_domain' AND (domain NOT IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'!='$logged_in_role')"))) { 
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if (isset($_POST['active']) && $_POST['active'] == "on") { $active = "1"; } else { $active = "0"; }
	if (!ctype_alnum(str_replace(array('.', '-'), '', $alias_domain)) || empty ($alias_domain)) {
		header("Location: do.php?event=".base64_encode("Alias domain name invalid"));
		die("Alias domain name invalid");
	}
	if (!ctype_alnum(str_replace(array('.', '-'), '', $target_domain)) || empty ($target_domain)) {
		header("Location: do.php?event=".base64_encode("Target domain name invalid"));
		die("Target domain name invalid");
	}
	if (!mysqli_result(mysqli_query($link, "SELECT domain FROM domain where domain='$target_domain'"))) { 
		header("Location: do.php?event=".base64_encode("Target domain $target_domain not found"));
		die("Target domain $target_domain not found");
	}
	if (mysqli_result(mysqli_query($link, "SELECT alias_domain FROM alias_domain where alias_domain='$alias_domain'"))) { 
		header("Location: do.php?event=".base64_encode("Alias domain exists"));
		die("Alias domain exists");
	}
	$mystring = "INSERT INTO alias_domain (alias_domain, target_domain, created, modified, active) VALUE ('$alias_domain', '$target_domain', now(), now(), '$active')";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	header('Location: do.php?return=success');
}
function mailbox_add_mailbox($link, $postarray) {
	$password = mysqli_real_escape_string($link, $_POST['password']);
	$password2 = mysqli_real_escape_string($link, $_POST['password2']);
	$domain = mysqli_real_escape_string($link, $_POST['domain']);
	$local_part = mysqli_real_escape_string($link, $_POST['local_part']);
	$name = mysqli_real_escape_string($link, $_POST['name']);
	$default_cal = mysqli_real_escape_string($link, $_POST['default_cal']);
	$default_card = mysqli_real_escape_string($link, $_POST['default_card']);
	$quota_m = mysqli_real_escape_string($link, $_POST['quota']);

	$quota_b = $quota_m*1048576;
	$maildir = $domain."/".$local_part."/";
	$username = $local_part.'@'.$domain;

	$row_from_domain = mysqli_fetch_assoc(mysqli_query($link, "SELECT mailboxes, maxquota, quota FROM domain WHERE domain='$domain'"));
	$row_from_mailbox = mysqli_fetch_assoc(mysqli_query($link, "SELECT count(*) as count, coalesce(round(sum(quota)/1048576), 0) as quota FROM mailbox WHERE domain='$domain'"));

	$num_mailboxes = $row_from_mailbox['count'];
	$quota_m_in_use = $row_from_mailbox['quota'];
	$num_max_mailboxes = $row_from_domain['mailboxes'];
	$maxquota_m = $row_from_domain['maxquota'];
	$domain_quota_m = $row_from_domain['quota'];

	global $logged_in_role;
	global $logged_in_as;
	
	if (empty($default_cal) || empty($default_card)) {
		header("Location: do.php?event=".base64_encode("Calendar and address book cannot be empty"));
		die("Calendar and address book cannot be empty");
	}

	if (!mysqli_result(mysqli_query($link, "SELECT domain FROM domain WHERE domain='$domain' AND (domain NOT IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'!='$logged_in_role')"))) { 
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if (!ctype_alnum(str_replace(array('.', '-'), '', $domain)) || empty ($domain)) {
		header("Location: do.php?event=".base64_encode("Domain name invalid"));
		die("Domain name invalid");
	}
	if (!ctype_alnum(str_replace(array('.', '-'), '', $local_part) || empty ($local_part)) {
		header("Location: do.php?event=".base64_encode("Mailbox alias must be alphanumeric"));
		die("Mailbox alias must be alphanumeric");
	}
	if (!is_numeric($quota_m)) { 
		header("Location: do.php?event=".base64_encode("Quota is not numeric"));
		die("Quota is not numeric"); 
	}
	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) { 
			header("Location: do.php?event=".base64_encode("Password mismatch"));
			die("Password mismatch");
		}
		$prep_password = escapeshellcmd($password);
		exec("/usr/bin/doveadm pw -s SHA512-CRYPT -p $prep_password", $hash, $return);
		$password_sha512c = $hash[0];
		if ($return != "0") {
			header("Location: do.php?event=".base64_encode("Error creating password hash"));
			die("Error creating password hash");
		}
	}
	else {
		header("Location: do.php?event=".base64_encode("Password cannot be empty"));
		die("Password cannot be empty");
	}
	if ($num_mailboxes >= $num_max_mailboxes) {
		header("Location: do.php?event=".base64_encode("Mailbox quota exceeded ($num_mailboxes of $num_max_mailboxes)"));
		die("Mailbox quota exceeded ($num_mailboxes of $num_max_mailboxes)");
	}
	if (!mysqli_result(mysqli_query($link, "SELECT domain FROM domain where domain='$domain'"))) {
		header("Location: do.php?event=".base64_encode("Domain $domain not found"));
		die("Domain $domain not found");
	}
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		header("Location: do.php?event=".base64_encode("Mail address is invalid"));
		die("Mail address is invalid"); 
	}
	if ($quota_m > $maxquota_m) {
		header("Location: do.php?event=".base64_encode("Quota over max. quota limit ($maxquota_m M)"));
		die("Quota over max. quota limit ($maxquota_m M)"); 
	}
	if (($quota_m_in_use+$quota_m) > $domain_quota_m) {
		$quota_left_m = ($domain_quota_m - $quota_m_in_use);
		header("Location: do.php?event=".base64_encode("Quota exceeds quota left ($quota_left_m M)"));
		die("Quota exceeds quota left ($quota_left_m M)");
	}
	if (isset($_POST['active']) && $_POST['active'] == "on") { $active = "1"; } else { $active = "0"; }
	$create_user = "INSERT INTO mailbox (username, password, name, maildir, quota, local_part, domain, created, modified, active) 
			VALUES ('$username', '$password_sha512c', '$name', '$maildir', '$quota_b', '$local_part', '$domain', now(), now(), '$active');";
	$create_user .= "INSERT INTO quota2 (username, bytes, messages)
			VALUES ('$username', '', '');";
	$create_user .= "INSERT INTO alias (address, goto, domain, created, modified, active)
			VALUES ('$username', '$username', '$domain', now(), now(), '$active');";
	$create_user .= "INSERT INTO users (username, digesta1)
			VALUES('$username', MD5(CONCAT('$username', ':SabreDAV:', '$password')));";
	$create_user .= "INSERT INTO principals (uri,email,displayname) 
			VALUES ('principals/$username', '$username', '$name');";
	$create_user .= "INSERT INTO principals (uri,email,displayname) 
			VALUES ('principals/$username/calendar-proxy-read', null, null);";
	$create_user .= "INSERT INTO principals (uri,email,displayname)
			VALUES ('principals/$username/calendar-proxy-write', null, null);";
	$create_user .= "INSERT INTO addressbooks (principaluri, displayname, uri, description, synctoken) 
			VALUES ('principals/$username','$default_card','default','','1');";
	$create_user .= "INSERT INTO calendars (principaluri, displayname, uri, description, components, transparent) 
			VALUES ('principals/$username','$default_cal','default','','VEVENT,VTODO', '0');";	
	if (!mysqli_multi_query($link, $create_user)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	while ($link->next_result()) {
		if (!$link->more_results()) break;
	}
	header('Location: do.php?return=success');
}
function mailbox_edit_domain($link, $postarray) {
	$domain = mysqli_real_escape_string($link, $_POST['domain']);
	$description = mysqli_real_escape_string($link, $_POST['description']);
	$aliases = mysqli_real_escape_string($link, $_POST['aliases']);
	$mailboxes = mysqli_real_escape_string($link, $_POST['mailboxes']);
	$maxquota = mysqli_real_escape_string($link, $_POST['maxquota']);
	$quota = mysqli_real_escape_string($link, $_POST['quota']);

	$row_from_mailbox = mysqli_fetch_assoc(mysqli_query($link, "SELECT count(*) as count, max(coalesce(round(quota/1048576), 0)) as maxquota, coalesce(round(sum(quota)/1048576), 0) as quota FROM mailbox WHERE domain='$domain'"));
	$maxquota_in_use = $row_from_mailbox['maxquota'];
	$domain_quota_m_in_use = $row_from_mailbox['quota'];
	$mailboxes_in_use = $row_from_mailbox['count'];
	$aliases_in_use = mysqli_result(mysqli_query($link, "SELECT count(*) FROM alias WHERE domain='$domain' and address NOT IN (SELECT username FROM mailbox)"));

	global $logged_in_role;
	global $logged_in_as;

	if (!mysqli_result(mysqli_query($link, "SELECT domain FROM domain WHERE domain='$domain' AND (domain NOT IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'!='$logged_in_role')"))) { 
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}

	$numeric_array = array($aliases, $mailboxes, $maxquota, $quota);
	foreach ($numeric_array as $numeric) {
		if (!is_numeric($mailboxes)) {
			header("Location: do.php?event=".base64_encode("$numeric must be numeric"));
			die("$numeric must be numeric");
		}
	}
	if (!ctype_alnum(str_replace(array('.', '-'), '', $domain)) || empty ($domain)) {
		header("Location: do.php?event=".base64_encode("Domain name invalid"));
		die("Domain name invalid");
	}
	if ($maxquota > $quota) {
		header("Location: do.php?event=".base64_encode("Max. size per mailbox can not be greater than domain quota"));
		die("Max. size per mailbox can not be greater than domain quota");
	}
	if ($maxquota_in_use > $maxquota) {
		header("Location: do.php?event=".base64_encode("Max. size per mailbox must be greater than or equal to $maxquota_in_use"));
		die("Max. quota per mailbox must be greater than or equal to $maxquota_in_use");
	}
	if ($domain_quota_m_in_use > $quota) {
		header("Location: do.php?event=".base64_encode("Domain quota must be greater than or equal to $domain_quota_m_in_use"));
		die("Max. quota must be greater than or equal to $domain_quota_m_in_use");
	}
	if ($mailboxes_in_use > $mailboxes) {
		header("Location: do.php?event=".base64_encode("Max. mailboxes must be greater than or equal to $mailboxes_in_use"));
		die("Max. mailboxes must be greater than or equal to $mailboxes_in_use");
	}
	if ($aliases_in_use > $aliases) {
		header("Location: do.php?event=".base64_encode("Max. aliases must be greater than or equal to $aliases_in_use"));
		die("Max. aliases must be greater than or equal to $aliases_in_use");
	}
	if (isset($_POST['active']) && $_POST['active'] == "on") { $active = "1"; } else { $active = "0"; }
	if (isset($_POST['backupmx']) && $_POST['backupmx'] == "on") { $backupmx = "1"; } else { $backupmx = "0"; }
	$mystring = "UPDATE domain SET modified=now(), backupmx='$backupmx', active='$active', quota='$quota', maxquota='$maxquota', mailboxes='$mailboxes', aliases='$aliases', description='$description' WHERE domain='$domain'";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed"); 
	}
	header('Location: do.php?return=success');
}
function mailbox_edit_domainadmin($link, $postarray) {
	if (empty($_POST['domain'])) {
		header("Location: do.php?event=".base64_encode("Please assign a domain"));
		die("Please assign a domain");
	}
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	array_walk($_POST['domain'], function(&$string) use ($link) { 
		$string = mysqli_real_escape_string($link, $string);
	});
	$username = mysqli_real_escape_string($link, $_POST['username']);
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $username))) {
		header("Location: do.php?event=".base64_encode("Invalid username"));
		die("Invalid username");
	}
	if (isset($_POST['active']) && $_POST['active'] == "on") { $active = "1"; } else { $active = "0"; }
	$mystring = "DELETE FROM domain_admins WHERE username='$username'";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed"); 
	}
	foreach ($_POST['domain'] as $domain) {
		$mystring = "INSERT INTO domain_admins (username, domain, created, active) VALUES ('$username', '$domain', now(), '$active')";
		if (!mysqli_query($link, $mystring)) {
			header("Location: do.php?event=".base64_encode("MySQL query failed"));
			die("MySQL query failed");
		}
	}
	$mystring = "UPDATE admin SET modified=now(), active='$active' where username='$username'";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	header('Location: do.php?return=success');
}
function mailbox_edit_mailbox($link, $postarray) {
	$quota_m = mysqli_real_escape_string($link, $_POST['quota']);
	$quota_b = $quota_m*1048576;
	$username = mysqli_real_escape_string($link, $_POST['username']);
	$name = mysqli_real_escape_string($link, $_POST['name']);
	$password = mysqli_real_escape_string($link, $_POST['password']);
	$password2 = mysqli_real_escape_string($link, $_POST['password2']);
	if (!is_numeric($quota_m)) { 
		header("Location: do.php?event=".base64_encode("Quota not numeric"));
		die("Quota not numeric"); 
	}
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $username))) {
		header("Location: do.php?event=".base64_encode("Invalid username"));
		die("Invalid username");
	}
	$domain = mysqli_result(mysqli_query($link, "SELECT domain FROM mailbox WHERE username='$username'"));
	$quota_m_now = mysqli_result(mysqli_query($link, "SELECT coalesce(round(sum(quota)/1048576), 0) as quota FROM mailbox WHERE username='$username'"));
	$quota_m_in_use = mysqli_result(mysqli_query($link, "SELECT coalesce(round(sum(quota)/1048576), 0) as quota FROM mailbox WHERE domain='$domain'"));
	$row_from_domain = mysqli_fetch_assoc(mysqli_query($link, "SELECT quota, maxquota FROM domain WHERE domain='$domain'"));
	$maxquota_m = $row_from_domain['maxquota'];
	$domain_quota_m = $row_from_domain['quota'];
	global $logged_in_role;
	global $logged_in_as;
	if (!mysqli_result(mysqli_query($link, "SELECT domain FROM domain WHERE domain='$domain' AND (domain NOT IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'!='$logged_in_role')"))) { 
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if ($quota_m > $maxquota_m) {
		header("Location: do.php?event=".base64_encode("Quota over max. quota limit ($maxquota_m M)"));
		die("Quota over max. quota limit ($maxquota_m M)"); 
	}
	if (($quota_m_in_use-$quota_m_now+$quota_m) > $domain_quota_m) {
		$quota_left_m = ($domain_quota_m - $quota_m_in_use + $quota_m_now);
		header("Location: do.php?event=".base64_encode("Quota exceeds quota left (max. $quota_left_m M)"));
		die("Quota exceeds quota left (max. $quota_left_m M)");
	}
	if (isset($_POST['active']) && $_POST['active'] == "on") { $active = "1"; }	else { $active = "0"; }
	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			header("Location: do.php?event=".base64_encode("Password mismatch"));
			die("Password mismatch");
		}
		$prep_password = escapeshellcmd($password);
		exec("/usr/bin/doveadm pw -s SHA512-CRYPT -p $prep_password", $hash, $return);
		$password_sha512c = $hash[0];
		if ($return != "0") {
			header("Location: do.php?event=".base64_encode("Error creating password hash"));
			die("Error creating password hash");	
		}
		$update_user = "UPDATE mailbox SET modified=now(), active='$active', password='$password_sha512c', name='$name', quota='$quota_b' WHERE username='$username';";
		$update_user .= "UPDATE users SET digesta1=MD5(CONCAT('$username', ':SabreDAV:', '$password')) WHERE username='$username';";
		if (!mysqli_multi_query($link, $update_user)) {
			header("Location: do.php?event=".base64_encode("MySQL query failed"));
			die("MySQL query failed");
		}
		while ($link->next_result()) {
			if (!$link->more_results()) break;
		}
		header('Location: do.php?return=success');
	}
	$mystring = "UPDATE mailbox SET modified=now(), active='$active', name='$name', quota='$quota_b' WHERE username='$username'";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	else {
		header('Location: do.php?return=success');
	}
}
function mailbox_delete_domain($link, $postarray) {
	$domain = mysqli_real_escape_string($link, $_POST['domain']);
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if (!ctype_alnum(str_replace(array('.', '-'), '', $domain)) || empty ($domain)) {
		header("Location: do.php?event=".base64_encode("Domain name invalid"));
		die("Domain name invalid");
	}
	$mystring = "SELECT username FROM mailbox WHERE domain='$domain';";
	if (!mysqli_query($link, $mystring) || !empty(mysqli_result(mysqli_query($link, $mystring)))) {
		header("Location: do.php?event=".base64_encode("Domain is not empty! Please delete mailboxes first."));
		die("Domain is not empty! Please delete mailboxes first.");
	}
	foreach (array("domain", "alias", "domain_admins") as $deletefrom) {
		$mystring = "DELETE FROM $deletefrom WHERE domain='$domain'";
		if (!mysqli_query($link, $mystring)) {
			header("Location: do.php?event=".base64_encode("MySQL query failed"));
			die("MySQL query failed");
		}
	}
	$mystring = "DELETE FROM alias_domain WHERE target_domain='$domain'";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	header('Location: do.php?return=success');
}
function mailbox_delete_alias($link, $postarray) {
	$address = mysqli_real_escape_string($link, $_POST['address']);
	global $logged_in_role;
	global $logged_in_as;
	if (!mysqli_result(mysqli_query($link, "SELECT domain FROM alias WHERE address='$address' AND (domain NOT IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'!='$logged_in_role')"))) { 
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
		header("Location: do.php?event=".base64_encode("Mail address invalid"));
		die("Mail address invalid"); 
	}
	$mystring = "DELETE FROM alias WHERE address='$address' AND address NOT IN (SELECT username FROM mailbox)";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	header('Location: do.php?return=success');
}
function mailbox_delete_alias_domain($link, $postarray) {
	$alias_domain = mysqli_real_escape_string($link, $_POST['alias_domain']);
	global $logged_in_role;
	global $logged_in_as;
	if (!mysqli_result(mysqli_query($link, "SELECT target_domain FROM alias_domain WHERE alias_domain='$alias_domain' AND (target_domain NOT IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'!='$logged_in_role')"))) { 
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if (!ctype_alnum(str_replace(array('.', '-'), '', $alias_domain))) {
		header("Location: do.php?event=".base64_encode("Domain name invalid"));
		die("Domain name invalid");
	}
	$mystring = "DELETE FROM alias_domain WHERE alias_domain='$alias_domain'";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	header('Location: do.php?return=success');
}
function mailbox_delete_mailbox($link, $postarray) {
	$username = mysqli_real_escape_string($link, $_POST['username']);
	global $logged_in_role;
	global $logged_in_as;
	if (!mysqli_result(mysqli_query($link, "SELECT domain FROM mailbox WHERE username='$username' AND (domain NOT IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'!='$logged_in_role')"))) { 
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		header("Location: do.php?event=".base64_encode("Mail address invalid"));
		die("Mail address invalid"); 
	}
	$delete_user = "DELETE FROM alias WHERE goto='$username';";
	$delete_user .= "DELETE FROM quota2 WHERE username='$username';";
	$delete_user .= "DELETE FROM calendarobjects WHERE calendarid IN (SELECT id from calendars where principaluri='principals/$username');";
	$delete_user .= "DELETE FROM cards WHERE addressbookid IN (SELECT id from calendars where principaluri='principals/$username');";
	$delete_user .= "DELETE FROM mailbox WHERE username='$username';";
	$delete_user .= "DELETE FROM users WHERE username='$username';";
	$delete_user .= "DELETE FROM principals WHERE uri='principals/$username';";
	$delete_user .= "DELETE FROM principals WHERE uri='principals/$username/calendar-proxy-read';";
	$delete_user .= "DELETE FROM principals WHERE uri='principals/$username/calendar-proxy-write';";
	$delete_user .= "DELETE FROM addressbooks WHERE principaluri='principals/$username';";
	$delete_user .= "DELETE FROM calendars WHERE principaluri='principals/$username';";
	if (!mysqli_multi_query($link, $delete_user)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	while ($link->next_result()) {
		if (!$link->more_results()) break;
	}
	header('Location: do.php?return=success');
}
function set_admin_account($link, $postarray) {
	$name = mysqli_real_escape_string($link, $_POST['admin_user']);
	$name_now = mysqli_real_escape_string($link, $_POST['admin_user_now']);
	$password = mysqli_real_escape_string($link, $_POST['admin_pass']);
	$password2 = mysqli_real_escape_string($link, $_POST['admin_pass2']);
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $name)) || empty ($name)) {
		header("Location: do.php?event=".base64_encode("Invalid username"));
		die("Invalid username");
	}
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $name_now)) || empty ($name_now)) {
		header("Location: do.php?event=".base64_encode("Invalid username"));
		die("Invalid username");
	}
	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			header("Location: do.php?event=".base64_encode("Password mismatch"));
			die("Password mismatch");
		}
		$password = escapeshellcmd($password);
		exec("/usr/bin/doveadm pw -s SHA512-CRYPT -p $password", $hash, $return);
		$password = $hash[0];
		if ($return != "0") {
			header("Location: do.php?event=".base64_encode("Error creating password hash"));
			die("Error creating password hash");	
		}
		$mystring = "UPDATE admin SET modified=now(), password='$password', username='$name' WHERE username='$name_now'";
		if (!mysqli_query($link, $mystring)) {
			header("Location: do.php?event=".base64_encode("MySQL query failed"));
			die("MySQL query failed");
		}
	}
	else {
		$mystring = "UPDATE admin SET modified=now(), username='$name' WHERE username='$name_now'";
		if (!mysqli_query($link, $mystring)) {
			header("Location: do.php?event=".base64_encode("MySQL query failed"));
			die("MySQL query failed");
		}
	}
	$mystring = "UPDATE domain_admins SET username='$name', domain='ALL' WHERE username='$name_now'";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	header('Location: do.php?return=success');
}
function set_user_account($link, $postarray) {
	$name_now = mysqli_real_escape_string($link, $_POST['user_now']);
	$password_old = mysqli_real_escape_string($link, $_POST['user_old_pass']);
	$password_new = mysqli_real_escape_string($link, $_POST['user_new_pass']);
	$password_new2 = mysqli_real_escape_string($link, $_POST['user_new_pass']);
	
	if ($_SESSION['mailcow_cc_role'] != "user") {
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $name_now)) || empty ($name_now)) {
		header("Location: do.php?event=".base64_encode("Invalid username"));
		die("Invalid username");
	}
	if (!empty($password_new2) && !empty($password_new)) {
		if ($password_new2 != $password_new) {
			header("Location: do.php?event=".base64_encode("Password mismatch"));
			die("Password mismatch");
		}
		if (!check_login($link, $name_now, $password_old) == "user") {
			header("Location: do.php?event=".base64_encode("Current password incorrect"));
			die("Current password incorrect");	
		}
		$prep_password = escapeshellcmd($password_new);
		exec("/usr/bin/doveadm pw -s SHA512-CRYPT -p $prep_password", $hash, $return);
		if ($return != "0") {
			header("Location: do.php?event=".base64_encode("Error creating password hash"));
			die("Error creating password hash");	
		}
		$password_sha512c = $hash[0];
		$update_user = "UPDATE mailbox SET password='$password_sha512c' WHERE username='$name_now';";
		$update_user .= "UPDATE users SET digesta1=MD5(CONCAT('$name_now', ':SabreDAV:', '$password_new')) WHERE username='$name_now';";
		if (!mysqli_multi_query($link, $update_user)) {
			header("Location: do.php?event=".base64_encode("MySQL query failed"));
			die("MySQL query failed");
		}
		while ($link->next_result()) {
			if (!$link->more_results()) break;
		}
	}
	else {
		header("Location: do.php?event=".base64_encode("Password cannot be empty"));
		die("Password cannot be empty");
	}
	header('Location: do.php?return=success');
}
function set_fetch_mail($link, $postarray) {
	global $logged_in_as;
	$logged_in_as = escapeshellcmd($logged_in_as); 
	$imap_host = explode(":", escapeshellcmd($_POST['imap_host']))[0];
	$imap_port = explode(":", escapeshellcmd($_POST['imap_host']))[1];
	$imap_username = escapeshellcmd($_POST['imap_username']);
	$imap_password = escapeshellcmd($_POST['imap_password']);
	$imap_enc = escapeshellcmd($_POST['imap_enc']);
	$imap_exclude = explode(",", str_replace(array(', ', ' , ', ' ,'), ',', escapeshellcmd($_POST['imap_exclude'])));
	if ($_SESSION['mailcow_cc_role'] != "user") {
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if ($imap_enc != "/ssl" && $imap_enc != "/tls" && $imap_enc != "none") {
		header("Location: do.php?event=".base64_encode("Invalid encryption mechanism"));
		die("Invalid encryption mechanism");
	} 
	if ($imap_enc == "none") {
		$imap_enc = "";
	}
	if (!is_numeric($imap_port) || empty ($imap_port)) {
		header("Location: do.php?event=".base64_encode("Invalid port"));
		die("Invalid Port");
	}
	if (!ctype_alnum(str_replace(array('@', '.', '-', '\\', '/'), '', $imap_username)) || empty ($imap_username)) {
		header("Location: do.php?event=".base64_encode("Invalid username"));
		die("Invalid username");
	}
	if (!ctype_alnum(str_replace(array(', ', ' , ', ' ,', ' '), '', escapeshellcmd($_POST['imap_exclude']))) && !empty($_POST['imap_exclude'])) {
		header("Location: do.php?event=".base64_encode("Invalid excludes definied"));
		die("Invalid excludes definied");
	}
	if (!$imap = imap_open("{".$imap_host.":".$imap_port."/imap/novalidate-cert".$imap_enc."}", $imap_username, $imap_password, OP_HALFOPEN, 1)) {
		header("Location: do.php?event=".base64_encode("Cannot connect to IMAP server"));
		die("Cannot connect to IMAP server");
	}
	if ($imap_enc == "none") {
		$imap_enc = "";
	}
	elseif ($imap_enc == "/ssl") {
		$imap_enc = "imaps";
	}
	elseif ($imap_enc == "/tls") {
		$imap_enc = "starttls";
	}
	if(count($imap_exclude) > 1) {
		foreach ($imap_exclude as $each_exclude) {
			$exclude_parameter .= "-x ".$each_exclude."* ";
		}
	}
	ini_set('max_execution_time', 3600);
	exec('sudo /usr/bin/doveadm -o imapc_port='.$imap_port.' -o imapc_ssl='.$imap_enc.' \
	-o imapc_host='.$imap_host.' \
	-o imapc_user='.$imap_username.' \
	-o imapc_password='.$imap_password.' \
	-o imapc_ssl_verify=no \
	-o ssl_client_ca_dir=/etc/ssl/certs \
	-o imapc_features="rfc822.size fetch-headers" \
	-o mail_prefetch_count=20 sync -1 \
	-x "Shared*" -x "Public*" -x "Archives*" '.$exclude_parameter.' \
	-R -U -u '.$logged_in_as.' imapc:', $out, $return);
	if ($return == "2") {
		exec('sudo /usr/bin/doveadm quota recalc -A', $out, $return);
	}
	if ($return != "0") {
		header("Location: do.php?event=".base64_encode("Died with exit code $return"));
		die("Died with exit code $return");
	}
	header('Location: do.php?return=success');
}
function add_domain_admin($link, $postarray) {
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if (empty($_POST['domain'])) {
		header("Location: do.php?event=".base64_encode("Please assign a domain"));
		die("Please assign a domain");
	}
	$username = mysqli_real_escape_string($link, $_POST['username']);
	$password = mysqli_real_escape_string($link, $_POST['password']);
	$password2 = mysqli_real_escape_string($link, $_POST['password2']);
	array_walk($_POST['domain'], function(&$string) use ($link) { 
		$string = mysqli_real_escape_string($link, $string);
	});
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $username)) || empty ($username)) {
		header("Location: do.php?event=".base64_encode("Invalid username"));
		die("Invalid username");
	}
	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			header("Location: do.php?event=".base64_encode("Password mismatch"));
			die("Password mismatch");
		}
		if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $username))) {
			header("Location: do.php?event=".base64_encode("Invalid username"));
			die("Invalid username");
		}
		$password = escapeshellcmd($password);
		exec("/usr/bin/doveadm pw -s SHA512-CRYPT -p $password", $hash, $return);
		$password = $hash[0];
		if ($return != "0") {
			header("Location: do.php?event=".base64_encode("Error creating password hash"));
			die("Error creating password hash");	
		}
		if (isset($_POST['active']) && $_POST['active'] == "on") { $active = "1"; } else { $active = "0"; }
		$mystring = "DELETE FROM domain_admins WHERE username='$username'";
		if (!mysqli_query($link, $mystring)) {
			header("Location: do.php?event=".base64_encode("MySQL query failed"));
			die("MySQL query failed"); 
		}
		$mystring = "DELETE FROM admin WHERE username='$username'";
		if (!mysqli_query($link, $mystring)) {
			header("Location: do.php?event=".base64_encode("MySQL query failed"));
			die("MySQL query failed"); 
		}
		foreach ($_POST['domain'] as $domain) {
			$mystring = "INSERT INTO domain_admins (username, domain, created, active) VALUES ('$username', '$domain', now(), '$active')";
			if (!mysqli_query($link, $mystring)) {
				header("Location: do.php?event=".base64_encode("MySQL query failed"));
				die("MySQL query failed");
			}
		}
		$mystring = "INSERT INTO admin (username, password, superadmin, created, modified, active) VALUES ('$username', '$password', '0', now(), now(), '$active')";
		if (!mysqli_query($link, $mystring)) {
			header("Location: do.php?event=".base64_encode("MySQL query failed"));
			die("MySQL query failed");
		}
	}
	else {
		header("Location: do.php?event=".base64_encode("Password must not be empty"));
		die("Password must not be empty");
	}
	header('Location: do.php?return=success');
}
function delete_domain_admin($link, $postarray) {
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	$username = mysqli_real_escape_string($link, $_POST['username']);
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $username))) {
		header("Location: do.php?event=".base64_encode("Invalid username"));
		die("Invalid username");
	}
	$delete_domain = "DELETE FROM domain_admins WHERE username='$username';";
	$delete_domain .= "DELETE FROM admin WHERE username='$username';";
	if (!mysqli_multi_query($link, $delete_domain)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	while ($link->next_result()) {
		if (!$link->more_results()) break;
	}
	header('Location: do.php?return=success');
}
?>

