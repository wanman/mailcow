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
function return_fufix_config($s) {
	switch ($s) {
		case "extlist":
			$read_mime_check = file($GLOBALS["fufix_reject_attachments"])[0];
			preg_match('#\((.*?)\)#', $read_mime_check, $match);
			return $match[1];
			break;
		case "vfilter":
			$read_mime_check = file($GLOBALS["fufix_reject_attachments"])[0];
			if (strpos($read_mime_check,'FILTER') !== false) { return "checked"; } else { return false; }
			break;
		case "anonymize":
			$state = file_get_contents($GLOBALS["fufix_anonymize_headers"]);
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
		case "vtenable":
			if ($v != "1") {
				file_put_contents($GLOBALS["VT_ENABLE"], "");
			}
			else {
				file_put_contents($GLOBALS["VT_ENABLE"], "1");
			}
			break;
		case "cavenable":
			if ($v != "1") {
				file_put_contents($GLOBALS["CAV_ENABLE"], "");
			}
			else {
				file_put_contents($GLOBALS["CAV_ENABLE"], "1");
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
			$sender_array = array_keys(array_flip(preg_split("/((\r?\n)|(\r\n?))/", $v)));
			foreach($sender_array as $each) {
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
	if ($_SESSION['fufix_cc_role'] != "admin") {
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
	
	if (!mysqli_result(mysqli_query($link, "SELECT domain FROM domain WHERE domain='$domain' AND (domain NOT IN (SELECT domain from domain_admins WHERE username='$logged_in_as') OR 'admin'!='$logged_in_role')"))) { 
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if (!ctype_alnum(str_replace(array('.', '-'), '', $domain)) || empty ($domain)) {
		header("Location: do.php?event=".base64_encode("Domain name invalid"));
		die("Domain name invalid");
	}
	if (!ctype_alnum($local_part) || empty ($local_part)) {
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
		$password = escapeshellcmd($password);
		exec("/usr/bin/doveadm pw -s SHA512-CRYPT -p $password", $hash, $return);
		$password = $hash[0];
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
	$mystring = "INSERT INTO mailbox (username, password, name, maildir, quota, local_part, domain, created, modified, active)
		VALUES ('$username', '$password', '$name', '$maildir', '$quota_b', '$local_part', '$domain', now(), now(), '$active')";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	$mystring = "INSERT INTO quota2 (username, bytes, messages)
		VALUES ('$username', '', '')";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	$mystring = "INSERT INTO alias (address, goto, domain, created, modified, active)
		VALUES ('$username', '$username', '$domain', now(), now(), '$active')";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
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
	if (mysqli_query($link, $mystring)) {
		header('Location: do.php?return=success');
	}
}
function mailbox_edit_domainadmin($link, $postarray) {
	if (empty($_POST['domain'])) {
		header("Location: do.php?event=".base64_encode("Please assign a domain"));
		die("Please assign a domain");
	}
	if ($_SESSION['fufix_cc_role'] != "admin") {
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
		$password = escapeshellcmd($password);
		exec("/usr/bin/doveadm pw -s SHA512-CRYPT -p $password", $hash, $return);
		$password = $hash[0];
		if ($return != "0") {
			header("Location: do.php?event=".base64_encode("Error creating password hash"));
			die("Error creating password hash");	
		}
		$mystring = "UPDATE mailbox SET modified=now(), active='$active', password='$password', name='$name', quota='$quota_b' WHERE username='$username'";
		if (!mysqli_query($link, $mystring)) {
			header("Location: do.php?event=".base64_encode("MySQL query failed"));
			die("MySQL query failed");
		}
		else {
			header('Location: do.php?return=success');
		}
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
	if ($_SESSION['fufix_cc_role'] != "admin") {
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	if (!ctype_alnum(str_replace(array('.', '-'), '', $domain)) || empty ($domain)) {
		header("Location: do.php?event=".base64_encode("Domain name invalid"));
		die("Domain name invalid");
	}
	$mystring = "DELETE FROM quota2 WHERE username IN (SELECT username FROM mailbox WHERE domain='$domain')";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	foreach (array("domain", "alias", "mailbox", "domain_admins") as $deletefrom) {
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
	$mystring = "DELETE FROM alias WHERE goto='$username'";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	$arraydeletefrom = array("quota2", "mailbox");
	foreach ($arraydeletefrom as $deletefrom) {
		$mystring = "DELETE FROM $deletefrom WHERE username='$username'";
		if (!mysqli_query($link, $mystring)) {
			header("Location: do.php?event=".base64_encode("MySQL query failed"));
			die("MySQL query failed");
		}
	}
	header('Location: do.php?return=success');
}
function set_admin_account($link, $postarray) {
	$name = mysqli_real_escape_string($link, $_POST['admin_user']);
	$name_now = mysqli_real_escape_string($link, $_POST['admin_user_now']);
	$password = mysqli_real_escape_string($link, $_POST['admin_pass']);
	$password2 = mysqli_real_escape_string($link, $_POST['admin_pass2']);
	if ($_SESSION['fufix_cc_role'] != "admin") {
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
	$mystring = "UPDATE domain_admins SET username='$name', domain='all' WHERE username='$name_now'";
	if (!mysqli_query($link, $mystring)) {
		header("Location: do.php?event=".base64_encode("MySQL query failed"));
		die("MySQL query failed");
	}
	header('Location: do.php?return=success');
}
function add_domain_admin($link, $postarray) {
	if ($_SESSION['fufix_cc_role'] != "admin") {
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
	if ($_SESSION['fufix_cc_role'] != "admin") {
		header("Location: do.php?event=".base64_encode("Permission denied"));
		die("Permission denied");
	}
	$username = mysqli_real_escape_string($link, $_POST['username']);
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $username))) {
		header("Location: do.php?event=".base64_encode("Invalid username"));
		die("Invalid username");
	}
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
	header('Location: do.php?return=success');
}
?>
