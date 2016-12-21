<?php
function hash_password($password) {
	$salt_str = bin2hex(openssl_random_pseudo_bytes(8));
	if ($GLOBALS['HASHING'] == "SHA512-CRYPT") {
		return "{SHA512-CRYPT}".crypt($password, '$6$'.$salt_str.'$');
	}
	else {
		return "{SSHA256}".base64_encode(hash('sha256', $password.$salt_str, true).$salt_str);
	}
}
function hasDomainAccess($username, $role, $domain) {
	global $pdo;
	if (!filter_var($username, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
		return false;
	}

	if (!is_valid_domain_name($domain)) {
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `domain` FROM `domain_admins`
			WHERE (
				`active`='1'
				AND `username` = :username
				AND `domain` = :domain
			)
			OR 'admin' = :role");
		$stmt->execute(array(':username' => $username, ':domain' => $domain, ':role' => $role));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	} catch(PDOException $e) {
		error_log($e);
		return false;
	}
	if ($num_results != 0 && !empty($num_results)) {
		return true;
	}
	return false;
}
function doveadm_authenticate($hash, $algorithm, $password) {
	$descr = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$pipes = array();
	$process = proc_open("/usr/bin/doveadm pw -s ".$algorithm." -t '".$hash."'", $descr, $pipes);
	if (is_resource($process)) {
		fputs($pipes[0], $password);
		fclose($pipes[0]);
		while ($f = fgets($pipes[1])) {
			if (preg_match('/(verified)/', $f)) {
				proc_close($process);
				return true;
			}
			return false;
		}
		fclose($pipes[1]);
		while ($f = fgets($pipes[2])) {
			proc_close($process);
			return false;
		}
		fclose($pipes[2]);
		proc_close($process);
	}
	return false;
}
function check_login($user, $pass) {
	global $pdo;
	if (!filter_var($user, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $user))) {
		return false;
	}
	if (!strpos(shell_exec("file --mime-encoding /usr/bin/doveadm"), "binary")) {
		return false;
	}
	$user = strtolower(trim($user));
	$stmt = $pdo->prepare("SELECT `password` FROM `admin`
			WHERE `superadmin` = '1'
			AND `username` = :user");
	$stmt->execute(array(':user' => $user));
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $row) {
		if (doveadm_authenticate($row['password'], $GLOBALS['HASHING'], $pass) !== false) {
			unset($_SESSION['ldelay']);
			return "admin";
		}
	}
	$stmt = $pdo->prepare("SELECT `password` FROM `admin`
			WHERE `superadmin` = '0'
			AND `active`='1'
			AND `username` = :user");
	$stmt->execute(array(':user' => $user));
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $row) {
		if (doveadm_authenticate($row['password'], $GLOBALS['HASHING'], $pass) !== false) {
			unset($_SESSION['ldelay']);
			return "domainadmin";
		}
	}
	$stmt = $pdo->prepare("SELECT `password` FROM `mailbox`
			WHERE `active`='1'
			AND `username` = :user");
	$stmt->execute(array(':user' => $user));
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $row) {
		if (doveadm_authenticate($row['password'], $GLOBALS['HASHING'], $pass) !== false) {
			unset($_SESSION['ldelay']);
			return "user";
		}
	}
	if (!isset($_SESSION['ldelay'])) {
		$_SESSION['ldelay'] = "0";
	}
	elseif (!isset($_SESSION['mailcow_cc_username'])) {
		$_SESSION['ldelay'] = $_SESSION['ldelay']+0.5;
	}
	sleep($_SESSION['ldelay']);
}
function formatBytes($size, $precision = 2) {
	if(!is_numeric($size)) {
		return "0";
	}
	$base = log($size, 1024);
	$suffixes = array(' Byte', ' KiB', ' MiB', ' GiB', ' TiB');
	if ($size == "0") {
		return "0";
	}
	return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
}
function return_mailcow_config($s) {
	switch ($s) {
		case "anonymize":
			$state = file_get_contents($GLOBALS["MC_ANON_HEADERS"]);
			return !empty($state) ? "checked" : null;
			break;
		case "public_folder_status":
			$state = file_get_contents($GLOBALS["MC_PUB_FOLDER"]);
			return !empty($state) ? "checked" : null;
			break;
		case "public_folder_name":
			$state = file_get_contents($GLOBALS["MC_PUB_FOLDER"]);
			if (!empty($state)) {
				$name = explode(";;", $state)[1];
				return (!empty($name)) ? $name : null;
			}
			break;
		case "public_folder_pvt":
			$state = file_get_contents($GLOBALS["MC_PUB_FOLDER"]);
			if (!empty($state)) {
				$PVT = explode(";;", $state)[3];
				return ($PVT == "on") ? "checked" : null;
			}
			break;
		case "srr":
			// Clean array*, hide Postfix warnings by redirecting stderr to null
			// *Split into array by " ", "," or "\n", trim whitespaces, remove empty values, recreate index
			$srr_active = array_values(array_filter(array_map('trim', preg_split( "/( |,|\n)/", shell_exec("/usr/sbin/postconf -h smtpd_recipient_restrictions 2> /dev/null")))));
			for($i = 0; $i < count($srr_active); ++$i) {
				// Merge table value with previous element in array
				if (preg_match('/:/', $srr_active[$i])) {
					$table_value = $srr_active[$i];
					$srr_active[$i-1] = $srr_active[$i-1].' '.$srr_active[$i];
					unset($srr_active[$i]);
				}
			}
			$srr['inactive'] = array_diff($GLOBALS["VALID_SRR"], $srr_active);
			$srr['active'] = $srr_active;
			return $srr;
			break;
		case "ssr":
			// Clean array, hide Postfix warnings by redirecting stderr to null
			$ssr_active = array_values(array_filter(array_map('trim', preg_split( "/( |,|\n)/", shell_exec("/usr/sbin/postconf -h smtpd_sender_restrictions 2> /dev/null")))));
			for($i = 0; $i < count($ssr_active); ++$i) {
				// Merge table value with previous element in array
				if (preg_match('/:/', $ssr_active[$i])) {
					$table_value = $ssr_active[$i];
					$ssr_active[$i-1] = $ssr_active[$i-1].' '.$ssr_active[$i];
					unset($ssr_active[$i]);
				}
			}
			$ssr['inactive'] = array_diff($GLOBALS["VALID_SSR"], $ssr_active);
			$ssr['active'] = $ssr_active;
			return $ssr;
			break;
		case "maxmsgsize":
			return shell_exec("echo $(( $(/usr/sbin/postconf -h message_size_limit) / 1048576 ))");
			break;
	}
}
function set_mailcow_config($s, $v = '') {
	global $lang;
	switch ($s) {
		case "maxmsgsize":
			if (!ctype_alnum($v)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => $lang['admin']['invalid_max_msg_size']
				);
				break;
			}
			$new_size = escapeshellarg($v);
			exec('sudo /usr/local/sbin/mailcow-set-message-limit '.$new_size, $out, $return);
			if ($return != "0") {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => $lang['admin']['site_not_found']
				);
				break;
			}
			break;
		case "anonymize":
			if ($v == "on") {
 				$template = file_get_contents($GLOBALS["MC_ANON_HEADERS"].".template");
				file_put_contents($GLOBALS["MC_ANON_HEADERS"], $template);
			} else {
				file_put_contents($GLOBALS["MC_ANON_HEADERS"], "");
			}
			break;
		case "public_folder":
			if (!ctype_alnum(str_replace("/", "", $v['public_folder_name']))) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => $lang['admin']['public_folder_empty']
				);
				break;
			}
			if (isset($v['public_folder_pvt']) && $v['public_folder_pvt'] == "on") {
				$PVT = ':INDEXPVT=~/public';
			}
			else {
				$PVT = '';
			}
			$template = '# ;;'.$v['public_folder_name'].';;
# ;;'.$v['public_folder_pvt'].';;
namespace {
  type = public
  separator = /
  prefix = Public/
  location = maildir:/var/vmail/public'.$PVT.'
  subscriptions = no
  mailbox '.$v['public_folder_name'].' {
    auto = subscribe
  }
}';
			if (isset($v['use_public_folder']) && $v['use_public_folder'] == "on") {
				file_put_contents($GLOBALS["MC_PUB_FOLDER"], $template);
			}
			else {
				file_put_contents($GLOBALS["MC_PUB_FOLDER"], "");
			}
			break;
		case "srr":
			exec('sudo /usr/sbin/postconf -e smtpd_recipient_restrictions='.escapeshellarg($v['srr_value']), $out, $return);
			if ($return != "0") {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => $lang['admin']['set_rr_failed']
				);
				break;
			}
			break;
		case "ssr":
			exec('sudo /usr/sbin/postconf -e smtpd_sender_restrictions='.escapeshellarg($v['ssr_value']), $out, $return);
			if ($return != "0") {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => $lang['admin']['set_rr_failed']
				);
				break;
			}
			break;
		case "reset-ssr":
			exec('sudo /usr/sbin/postconf -e smtpd_sender_restrictions="reject_authenticated_sender_login_mismatch, permit_mynetworks, reject_sender_login_mismatch, permit_sasl_authenticated, reject_unlisted_sender, reject_unknown_sender_domain"', $out, $return);
			if ($return != "0") {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => $lang['admin']['set_rr_failed']
				);
				break;
			}
			break;
		case "reset-srr":
			exec('sudo /usr/sbin/postconf -e smtpd_recipient_restrictions="check_recipient_access proxy:mysql:/etc/postfix/sql/mysql_tls_enforce_in_policy.cf permit_sasl_authenticated, permit_mynetworks, reject_invalid_helo_hostname, reject_unknown_reverse_client_hostname, reject_unauth_destination"', $out, $return);
			if ($return != "0") {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => $lang['admin']['set_rr_failed']
				);
				break;
			}
			break;
	}
	if (!isset($_SESSION['return'])) {
		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => $lang['success']['changes_general']
		);
	}
}
function opendkim_table($action, $which = "") {
	global $lang;
	switch ($action) {
		case "delete":
			$selector	= explode("_", $which)[0];
			$domain		= explode("_", $which)[1];
			if (!ctype_alnum($selector) || !is_valid_domain_name($domain)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['dkim_domain_or_sel_invalid'])
				);
				break;
			}
			$selector	= escapeshellarg($selector);
			$domain		= escapeshellarg($domain);
			exec('sudo /usr/local/sbin/mailcow-dkim-tool del '.$selector.' '.$domain, $out, $return);
			if ($return != "0") {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['dkim_remove_failed'])
				);
				break;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['dkim_removed'])
			);
			break;
		case "add":
			$selector	= trim($which['dkim_selector']);
			$domain		= trim($which['dkim_domain']);
			$key_length	= trim($which['dkim_key_size']);
			if (!is_numeric($key_length)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['dkim_key_length_invalid'])
				);
				break;
			}
			if (!ctype_alnum($selector) || !is_valid_domain_name($domain)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['dkim_domain_or_sel_invalid'])
				);
				break;
			}
			$selector	= escapeshellarg($selector);
			$domain		= escapeshellarg($domain);
			exec('sudo /usr/local/sbin/mailcow-dkim-tool add '.$selector.' '.$domain.' '.$key_length, $out, $return);
			if ($return != "0") {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['dkim_add_failed'])
				);
				break;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['dkim_added'])
			);
			break;
	}
}
function sys_info($what) {
	switch ($what) {
		case "uptime":
			$ut = strtok(exec("cat /proc/uptime"), ".");
			$uptime['days'] = sprintf("%2d", ($ut/(3600*24)));
			$uptime['hours'] = sprintf("%2d", (($ut%(3600*24))/3600));
			$uptime['minutes'] = sprintf("%2d", ($ut%(3600*24)%3600)/60);
			$uptime['seconds'] = sprintf("%2d", ($ut%(3600*24)%3600)%60);
			return $uptime;
			break;
		case "ram":
			$return['total']		= filter_var(shell_exec("free -b | grep Mem | awk '{print $2}'"), FILTER_SANITIZE_NUMBER_FLOAT);
			$return['used']			= filter_var(shell_exec("free -b | grep Mem | awk '{print $3}'"), FILTER_SANITIZE_NUMBER_FLOAT);
			$return['free']			= filter_var(shell_exec("free -b | grep Mem | awk '{print $4}'"), FILTER_SANITIZE_NUMBER_FLOAT);
			$return['used_percent']	= round(shell_exec("free -b | grep Mem | awk '{print $3/$2 * 100.0}'"));
			return $return;
			break;
		case "vmail_percentage":
			$df = disk_free_space("/var/vmail");
			$dt = disk_total_space("/var/vmail");
			$du = $dt - $df;
			return sprintf('%.2f',($du / $dt) * 100);
			break;
    		case "cpu":
		      	$exec_loads = sys_getloadavg();
		      	$exec_cores = trim(shell_exec("grep -P '^processor' /proc/cpuinfo|wc -l"));
		      	$cpu = round($exec_loads[1]/($exec_cores + 1)*100, 0);
		      	return $cpu;
		      	break;
		case "pflog":
			$pflog_content = file_get_contents($GLOBALS['PFLOG']);
			if (!file_exists($GLOBALS['PFLOG'])) {
				return "none";
			}
			else {
				return file_get_contents($GLOBALS['PFLOG']);
			}
			break;
		case "mailgraph":
			$imageurls = array("0-n", "1-n", "2-n", "3-n");
			$return = "";
			foreach ($imageurls as $image) {
				$image = 'http://localhost:81/mailgraph.cgi?'.$image;
				$headers = get_headers($image);
				$return_code = substr($headers[0], 9, 3);
				if ($return_code >= 400) {
					$return .='<img class="img-responsive" alt="not-yet-available" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAG3UlEQVRYR8WXC2yT1xXH/+dcx0loRkMhULZuA2WxAwjStVsYiZ06IYUOClRbVaGJ0m0MCaEpqOs00anVgErtNq1ZH5PWVRUIOtZ2LevGUOkjbydMbKM8x+wQKKWsCYSUFvLAj++c6fvAxo4NDtKkWbIsfd+55/+79/7PudeE//OHblR/A8A11TNnwIiXVSYqMZPggsA6MTQy/vCSffuGbyTnmAFafZ56JXxfhb7JjOJsIgSJQrhTmV4eKYq/smh3TyQXTE6A5pryKkX8WaPma4lklkivIToiRGcYahF0ggJeAXsYcHI6MYYfD3SENxOg1wK5JkBrIODSWO+Tyvjx5aQyAOIXGLL9ro5j/24NzCpikanCljGWDPiDPeferSotyTeubwP0Q4XOtEUJeJdhragJ9vRnX7UsT1sD0wog+X+EYokCQkAjjGujZUW+ZGBWiyWL2XBZ6lAB+gnaBPDW2mDovXaf9yGL9GkGTQBwnAl339UR/mC0XMYK2Cbz+707GLgPis8Avd+QHIzBNDKwIteeOrNW/J3YWhOP531ijOxUYI4KPpCCvHn1zUfOpObIAGitKd8A1Z8RcMEC17oopgLzVyi+MBbxRIxjSOU16oq8CSu/DUCFQto6gsfmbwDkalxK1hafp4KU9gmDifReUTptFB0g3Hwj4mmxhB9ITN8G0wFmTCJQQyAYej4rQJvf+44CC4j0xZg7/hMzkrefGNNTE06uX4zB7qMYPpW+naagEJ9fthynX98KleQEISJxMFUzYTqUXhXo+XyhL/u6whevmPRy+ia/d44BDhIwzLCmCZnHVNGQKn7rPfeh/NGnEP3kHA6sewjDp044r23x2b94AcVfnYu+t/6E8C8fS4Mg4NDZW8N3TO717lFCJYCHa4PhZ9IAWn2eX4HoERG8xHmux8mKfqhgdwJgysJlmPHTnwN02TYORMNKRM72JsUTsVkhFA8oqQHoFRHaP78rdEcaQJu//F927Sq0HooKIno6kXDCnfNQ0bg5KZ54Hj0/gJGPTuLmOXdmWOTU9hdx4neNyecKvF0QH/pWhG86D0Z+1IpPWbjn+FlnOnZTgRW/CIGVL0Ofi5jCN0G8MDGa89yYtelZTKyuHZMXL/X9x1kd+zfxEcGlyHiruGDQNBHgg9KS2s7QLgfAcT/RAbth1AbDX2mqLvvYME9NVRsrxGXxB3Gp7+NMWMOz1dIGgq5O+MABaK4pq2HldruBBDrDc5ury2LM7BqdwYF44jlMrApkXYnrisNpqfNJtZ5AjxJoUyAYsvvNVQAA/6gNhiub/WVRBueNVkl1ezaC6EA/DqxbmVGiiVhi1KlFC0C6HkQbaztCGxyA9pry2aJ6SEROzu86Nr3Z5/2ICbeliuQSTxrzSnUkSjRtG0lmivAjIKxSxbq6zvBzDsCeebcVjrhuGrKPzVhh/nj3cPQ1kC5ODB6r+PUg7P5SNFxUfHHcYDuAeSS6KNDVvTt5FrT5vQftQ8N+ocylgCbbZfHtX0dF4xaQK90WttGGPzyOW+b6M3bk5JbfwP4mlx+y0yW8IsIyALBL3VRS3xwaSAK0+D1P2uZQ4GVx08OuqJ5SYFwiwSR/vVOKZIzzKFFq9r6PLtHTb2xDz/NPAXr1HkJKS5UwEdAtAO2tDYa+YedJArQGPOVi0VEDianRUrV4rQ2UOrVJNXdj1sZnEOnvSyu11BLNJm4LBoKhqna/d7+zysDaQDD82zQApyFVe/8MxjJS/OHiSNHqcYWD/2TCjFQIuwSHTnRn1LkNMWXBUvS+tSNt5nYDchmqFGglFC8RcNYdH5pW9bfTIxkATb5yj4usw84ZQLqcYd5XlU4FJo+pBY4KEkCJsJzYvE+WtU+B8apYVdcZ3nzVG6MGtfg8P7LPAQFGQHKPiukjsXaNvoLlAhKRQUO0Mp7v3kMjkaA9XqC76oLdS1MvqVkvpS1+7zYCHrQhGFiZJ3gnRvKEENZma1AZMITdYNNgWVG3gdkJoJRAR43m+fydh8+nxmcFsG/EFO/dqoTv2MEKbIlZ8fVuN7kh5rsWdDFZdDszCq68F1V0E+l7JLSt/1PXoZKJVgOpbrQrSVSOWMoLF3SFMw6Ia17LFaB2f/l6S6xN9rlgNxIBtgLYTmbq3v6SNi3p90ywjNtE3Zc+tf+EtFR7vUS4X4A1iU4qkDciUV21aG/PhWzblvOPiX1Tcgl+rYy6ZAKRITCFCHQGkLiAb2HAk2pWgfYQeH1dMLTjen7JCZAY3Oz3VDLoe6K4d/Q5keLoC0rUrMDvz00J/eWB12HlMuuYAVITtdeUftFS4yHCJAUbqPUZGTreX9J9bCyiOU2Yi/p/+f6/yPUmTii6UZAAAAAASUVORK5CYII=" /><p>Not enough data</p>';
				}
				else {
					$imageData = base64_encode(file_get_contents($image));
					$return .='<img class="img-responsive" alt="'.$image.'" src="data:image/png;base64,'.$imageData.'" />';
				}
			}
			return $return;
			break;
		case "mailq":
			return shell_exec("mailq");
			break;
	}
}
function postfix_reload() {
	shell_exec("sudo /usr/sbin/postfix reload");
}
function pflog_renew() {
	shell_exec("sudo /usr/local/sbin/mailcow-renew-pflogsumm");
}
function dovecot_reload() {
	shell_exec("sudo /usr/sbin/dovecot reload");
}
function mailbox_add_domain($postarray) {
	global $pdo;
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$domain				= idn_to_ascii(strtolower(trim($postarray['domain'])));
	$description		= $postarray['description'];
	$aliases			= $postarray['aliases'];
	$mailboxes			= $postarray['mailboxes'];
	$maxquota			= $postarray['maxquota'];
	$quota				= $postarray['quota'];

	if ($maxquota > $quota) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_exceeds_domain_quota'])
		);
		return false;
	}

	isset($postarray['active'])					? $active = '1' : $active = '0';
	isset($postarray['relay_all_recipients'])	? $relay_all_recipients = '1' : $relay_all_recipients = '0';
	isset($postarray['backupmx'])				? $backupmx = '1' : $backupmx = '0';
	isset($postarray['relay_all_recipients'])	? $backupmx = '1' : true;

	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}

	foreach (array($quota, $maxquota, $mailboxes, $aliases) as $data) {
		if (!is_numeric($data)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['object_is_not_numeric'], htmlspecialchars($data))
			);
			return false;
		}
	}

	try {
		$stmt = $pdo->prepare("SELECT `domain` FROM `domain`
			WHERE `domain` = :domain");
		$stmt->execute(array(':domain' => $domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_exists'], htmlspecialchars($domain))
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("INSERT INTO `domain` (`domain`, `description`, `aliases`, `mailboxes`, `maxquota`, `quota`, `transport`, `backupmx`, `created`, `modified`, `active`, `relay_all_recipients`)
			VALUES (:domain, :description, :aliases, :mailboxes, :maxquota, :quota, 'virtual', :backupmx, :created, :modified, :active, :relay_all_recipients)");
		$stmt->execute(array(
			':domain' => $domain,
			':description' => $description,
			':aliases' => $aliases,
			':mailboxes' => $mailboxes,
			':maxquota' => $maxquota,
			':quota' => $quota,
			':backupmx' => $backupmx,
			':active' => $active,
			':created' => date('Y-m-d H:i:s'),
			':modified' => date('Y-m-d H:i:s'),
			':relay_all_recipients' => $relay_all_recipients
		));
		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['domain_added'], htmlspecialchars($domain))
		);
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
}
function mailbox_add_alias($postarray) {
	global $lang;
	global $pdo;
	$addresses		= array_map('trim', preg_split( "/( |,|;|\n)/", $postarray['address']));
	$gotos			= array_map('trim', preg_split( "/( |,|;|\n)/", $postarray['goto']));
	isset($postarray['active']) ? $active = '1' : $active = '0';
	if (empty($addresses[0])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_empty'])
		);
		return false;
	}

	if (empty($gotos[0])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['goto_empty'])
		);
		return false;
	}

	foreach ($addresses as $address) {
		if (empty($address)) {
			continue;
		}

		$domain			= idn_to_ascii(substr(strstr($address, '@'), 1));
		$local_part		= strstr($address, '@', true);
		$address		= $local_part.'@'.$domain;

		if ((!filter_var($address, FILTER_VALIDATE_EMAIL) === true) && !empty($local_part)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['alias_invalid'])
			);
			return false;
		}

		if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['access_denied'])
			);
			return false;
		}

		try {
			$stmt = $pdo->prepare("SELECT `address` FROM `alias`
				WHERE `address`= :address");
			$stmt->execute(array(':address' => $address));
			$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
		}
		catch(PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
		if ($num_results != 0) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['is_alias_or_mailbox'], htmlspecialchars($address))
			);
			return false;
		}

		try {
			$stmt = $pdo->prepare("SELECT `address` FROM `spamalias`
				WHERE `address`= :address");
			$stmt->execute(array(':address' => $address));
			$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
		}
		catch(PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
		if ($num_results != 0) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['is_spam_alias'], htmlspecialchars($address))
			);
			return false;
		}

		foreach ($gotos as &$goto) {
			if (empty($goto)) {
				continue;
			}

			$goto_domain		= idn_to_ascii(substr(strstr($goto, '@'), 1));
			$goto_local_part	= strstr($goto, '@', true);
			$goto				= $goto_local_part.'@'.$goto_domain;

			if (!filter_var($goto, FILTER_VALIDATE_EMAIL) === true) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['goto_invalid'])
				);
				return false;
			}
			if ($goto == $address) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['alias_goto_identical'])
				);
				return false;
			}
		}

		$gotos = array_filter($gotos);
		$goto = implode(",", $gotos);

		try {
			$stmt = $pdo->prepare("INSERT INTO `alias` (`address`, `goto`, `domain`, `created`, `modified`, `active`)
				VALUES (:address, :goto, :domain, :created, :modified, :active)");

			if (!filter_var($address, FILTER_VALIDATE_EMAIL) === true) {
				$stmt->execute(array(
					':address' => '@'.$domain,
					':goto' => $goto,
					':domain' => $domain,
					':created' => date('Y-m-d H:i:s'),
					':modified' => date('Y-m-d H:i:s'),
					':active' => $active
				));
			}
			else {
				$stmt->execute(array(
					':address' => $address,
					':goto' => $goto,
					':domain' => $domain,
					':created' => date('Y-m-d H:i:s'),
					':modified' => date('Y-m-d H:i:s'),
					':active' => $active
				));
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['alias_added'])
			);
		}
		catch (PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_added'])
	);
}
function mailbox_add_alias_domain($postarray) {
	global $lang;
	global $pdo;
	isset($postarray['active']) ? $active = '1' : $active = '0';

	if (!is_valid_domain_name($postarray['alias_domain'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_domain_invalid'])
		);
		return false;
	}

	if (!is_valid_domain_name($postarray['target_domain'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['target_domain_invalid'])
		);
		return false;
	}

	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $postarray['target_domain'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	if ($postarray['alias_domain'] == $postarray['target_domain']) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['aliasd_targetd_identical'])
		);
		return false;
	}

	$alias_domain	= strtolower(trim($postarray['alias_domain']));
	$target_domain	= strtolower(trim($postarray['target_domain']));

	try {
		$stmt = $pdo->prepare("SELECT `domain` FROM `domain`
			WHERE `domain`= :target_domain");
		$stmt->execute(array(':target_domain' => $target_domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if ($num_results == 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['targetd_not_found'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain` WHERE `alias_domain`= :alias_domain
			UNION
			SELECT `alias_domain` FROM `alias_domain` WHERE `alias_domain`= :alias_domain_in_domain");
		$stmt->execute(array(':alias_domain' => $alias_domain, ':alias_domain_in_domain' => $alias_domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['aliasd_exists'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("INSERT INTO `alias_domain` (`alias_domain`, `target_domain`, `created`, `modified`, `active`)
			VALUES (:alias_domain, :target_domain, :created, :modified, :active)");
		$stmt->execute(array(
			':alias_domain' => $alias_domain,
			':target_domain' => $target_domain,
			':created' => date('Y-m-d H:i:s'),
			':modified' => date('Y-m-d H:i:s'),
			':active' => $active
		));
		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['aliasd_added'], htmlspecialchars($alias_domain))
		);
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}

}
function mailbox_edit_alias_domain($postarray) {
	global $lang;
	global $pdo;
	isset($postarray['active']) ? $active = '1' : $active = '0';
	$alias_domain		= idn_to_ascii($postarray['alias_domain']);
	$alias_domain		= strtolower(trim($alias_domain));
	$alias_domain_now	= strtolower(trim($postarray['alias_domain_now']));
	if (!is_valid_domain_name($alias_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_domain_invalid'])
		);
		return false;
	}

	if (!is_valid_domain_name($alias_domain_now)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_domain_invalid'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain`
				WHERE `alias_domain`= :alias_domain_now");
		$stmt->execute(array(':alias_domain_now' => $alias_domain_now));
		$DomainData = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $DomainData['target_domain'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain`
		WHERE `target_domain`= :alias_domain");
		$stmt->execute(array(':alias_domain' => $alias_domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['aliasd_targetd_identical'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("UPDATE `alias_domain` SET `alias_domain` = :alias_domain, `active` = :active WHERE `alias_domain` = :alias_domain_now");
		$stmt->execute(array(
			':alias_domain' => $alias_domain,
			':alias_domain_now' => $alias_domain_now,
			':active' => $active
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}

	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['aliasd_modified'], htmlspecialchars($alias_domain))
	);
}
function mailbox_add_mailbox($postarray) {
	global $pdo;
	global $lang;
	$username = strtolower(trim($postarray['local_part'])).'@'.strtolower(trim($postarray['domain']));
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_invalid'])
		);
		return false;
	}
	if (empty($postarray['local_part'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_invalid'])
		);
		return false;
	}
	$domain			= strtolower(trim($postarray['domain']));
	$password		= $postarray['password'];
	$password2		= $postarray['password2'];
	$local_part		= strtolower(trim($postarray['local_part']));
	$name			= $postarray['name'];
	$quota_m		= $postarray['quota'];

	if (empty($name)) {
		$name = $local_part;
	}
	else {
		$name = utf8_decode($name);
	}

	isset($postarray['active']) ? $active = '1' : $active = '0';

	$quota_b		= ($quota_m * 1048576);
	$maildir		= $domain."/".$local_part."/";

	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `mailboxes`, `maxquota`, `quota` FROM `domain`
			WHERE `domain` = :domain");
		$stmt->execute(array(':domain' => $domain));
		$DomainData = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT 
			COUNT(*) as count,
			COALESCE(ROUND(SUM(`quota`)/1048576), 0) as `quota`
				FROM `mailbox`
					WHERE `domain` = :domain");
		$stmt->execute(array(':domain' => $domain));
		$MailboxData = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}

	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `local_part` FROM `mailbox` WHERE `local_part` = :local_part and `domain`= :domain");
		$stmt->execute(array(':local_part' => $local_part, ':domain' => $domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['object_exists'], htmlspecialchars($username))
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE address= :username");
		$stmt->execute(array(':username' => $username));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['is_alias'], htmlspecialchars($username))
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `address` FROM `spamalias` WHERE `address`= :username");
		$stmt->execute(array(':username' => $username));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['is_spam_alias'], htmlspecialchars($username))
		);
		return false;
	}

	if (!is_numeric($quota_m) || $quota_m == "0") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['quota_not_0_not_numeric'])
		);
		return false;
	}

	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = hash_password($password);
	}
	else {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['password_empty'])
		);
		return false;
	}

	if ($MailboxData['count'] >= $DomainData['mailboxes']) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['max_mailbox_exceeded'], $MailboxData['count'], $DomainData['mailboxes'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `domain` FROM `domain` WHERE `domain`= :domain");
		$stmt->execute(array(':domain' => $domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if ($num_results == 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => $lang['danger']['domain_not_found']
		);
		return false;
	}

	if ($quota_m > $DomainData['maxquota']) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_exceeded'], $DomainData['maxquota'])
		);
		return false;
	}

	if (($MailboxData['quota'] + $quota_m) > $DomainData['quota']) {
		$quota_left_m = ($DomainData['quota'] - $MailboxData['quota']);
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_left_exceeded'], $quota_left_m)
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("INSERT INTO `mailbox` (`username`, `password`, `name`, `maildir`, `quota`, `local_part`, `domain`, `created`, `modified`, `active`) 
			VALUES (:username, :password_hashed, :name, :maildir, :quota_b, :local_part, :domain, :created, :modified, :active)");
		$stmt->execute(array(
			':username' => $username,
			':password_hashed' => $password_hashed,
			':name' => $name,
			':maildir' => $maildir,
			':quota_b' => $quota_b,
			':local_part' => $local_part,
			':domain' => $domain,
			':created' => date('Y-m-d H:i:s'),
			':modified' => date('Y-m-d H:i:s'),
			':active' => $active
		));

		$stmt = $pdo->prepare("INSERT INTO `quota2` (`username`, `bytes`, `messages`)
			VALUES (:username, '0', '0')");
		$stmt->execute(array(':username' => $username));

		$stmt = $pdo->prepare("INSERT INTO `alias` (`address`, `goto`, `domain`, `created`, `modified`, `active`)
			VALUES (:username1, :username2, :domain, :created, :modified, :active)");
		$stmt->execute(array(
			':username1' => $username,
			':username2' => $username,
			':domain' => $domain,
			':created' => date('Y-m-d H:i:s'),
			':modified' => date('Y-m-d H:i:s'),
			':active' => $active
		));

		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['mailbox_added'], htmlspecialchars($username))
		);
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
}
function mailbox_edit_alias($postarray) {
	global $lang;
	global $pdo;
	$address	= $postarray['address'];
	$domain		= idn_to_ascii(substr(strstr($address, '@'), 1));
	$local_part	= strstr($address, '@', true);
	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if (empty($postarray['goto'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['goto_empty'])
		);
		return false;
	}
	$gotos = array_map('trim', preg_split( "/( |,|;|\n)/", $postarray['goto']));
	foreach ($gotos as &$goto) {
		if (empty($goto)) {
			continue;
		}
		if (!filter_var($goto, FILTER_VALIDATE_EMAIL)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' =>sprintf($lang['danger']['goto_invalid'])
			);
			return false;
		}
		if ($goto == $address) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['alias_goto_identical'])
			);
			return false;
		}
	}
	$gotos = array_filter($gotos);
	$goto = implode(",", $gotos);
	isset($postarray['active']) ? $active = '1' : $active = '0';
	if ((!filter_var($address, FILTER_VALIDATE_EMAIL) === true) && !empty($local_part)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_invalid'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("UPDATE `alias` SET `goto` = :goto, `active`= :active WHERE `address` = :address");
		$stmt->execute(array(
			':goto' => $goto,
			':active' => $active,
			':address' => $address
		));
		$_SESSION['return'] = array(
			'type' => 'success',
		'msg' => sprintf($lang['success']['alias_modified'], htmlspecialchars($address))
		);
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
}
function mailbox_edit_domain($postarray) {
	global $lang;
	global $pdo;
	$domain			= $postarray['domain'];
	$description	= $postarray['description'];

	$aliases		= filter_var($postarray['aliases'], FILTER_SANITIZE_NUMBER_FLOAT);
	$mailboxes		= filter_var($postarray['mailboxes'], FILTER_SANITIZE_NUMBER_FLOAT);
	$maxquota		= filter_var($postarray['maxquota'], FILTER_SANITIZE_NUMBER_FLOAT);
	$quota			= filter_var($postarray['quota'], FILTER_SANITIZE_NUMBER_FLOAT);

	isset($postarray['relay_all_recipients']) ? $relay_all_recipients = '1' : $relay_all_recipients = '0';
	isset($postarray['backupmx']) ? $backupmx = '1' : $backupmx = '0';
	isset($postarray['relay_all_recipients']) ? $backupmx = '1' : true;
	isset($postarray['active']) ? $active = '1' : $active = '0';

	try {
		$stmt = $pdo->prepare("SELECT 
				COUNT(*) AS count,
				MAX(COALESCE(ROUND(`quota`/1048576), 0)) AS `maxquota`,
				COALESCE(ROUND(SUM(`quota`)/1048576), 0) AS `quota`
					FROM `mailbox`
						WHERE domain= :domain");
		$stmt->execute(array(':domain' => $domain));
		$MailboxData = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}


	try {
		$stmt = $pdo->prepare("SELECT COUNT(*) AS `count` FROM `alias`
				WHERE domain = :domain
				AND address NOT IN (
					SELECT `username` FROM `mailbox`
				)");
		$stmt->execute(array(':domain' => $domain));
		$AliasData = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}

	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	
	if ($maxquota > $quota) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_exceeds_domain_quota'])
		);
		return false;
	}
	
	if ($MailboxData['maxquota'] > $maxquota) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['max_quota_in_use'], $MailboxData['maxquota'])
		);
		return false;
	}
	
	if ($MailboxData['quota'] > $quota) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_quota_m_in_use'], $MailboxData['quota'])
		);
		return false;
	}
	
	if ($MailboxData['count'] > $mailboxes) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailboxes_in_use'], $MailboxData['count'])
		);
		return false;
	}
	
	if ($AliasData['count'] > $aliases) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['aliases_in_use'], $AliasData['count'])
		);
		return false;
	}

	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("UPDATE `domain` SET 
		`modified`= :modified,
		`relay_all_recipients` = :relay_all_recipients,
		`backupmx` = :backupmx,
		`active` = :active,
		`quota` = :quota,
		`maxquota` = :maxquota,
		`mailboxes` = :mailboxes,
		`aliases` = :aliases,
		`description` = :description
			WHERE `domain` = :domain");
		$stmt->execute(array(
			':relay_all_recipients' => $relay_all_recipients,
			':backupmx' => $backupmx,
			':active' => $active,
			':quota' => $quota,
			':maxquota' => $maxquota,
			':mailboxes' => $mailboxes,
			':aliases' => $aliases,
			':modified' => date('Y-m-d H:i:s'),
			':description' => $description,
			':domain' => $domain
		));
		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['domain_modified'], htmlspecialchars($domain))
		);
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}

}
function edit_domain_admin($postarray) {
	global $lang;
	global $pdo;
	$username		= $postarray['username'];
	$password		= $postarray['password'];
	$password2		= $postarray['password2'];
	isset($postarray['active']) ? $active = '1' : $active = '0';

	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	
	foreach ($postarray['domain'] as $domain) {
		if (!is_valid_domain_name($domain)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['domain_invalid'])
			);
			return false;
		}
	}

	if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("DELETE FROM `domain_admins` WHERE `username` = :username");
		$stmt->execute(array(
			':username' => $username,
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}

	foreach ($postarray['domain'] as $domain) {
		try {
			$stmt = $pdo->prepare("INSERT INTO `domain_admins` (`username`, `domain`, `created`, `active`)
				VALUES (:username, :domain, :created, :active)");
			$stmt->execute(array(
				':username' => $username,
				':domain' => $domain,
				':created' => date('Y-m-d H:i:s'),
				':active' => $active
			));
		}
		catch (PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}

	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = hash_password($password);
		try {
			$stmt = $pdo->prepare("UPDATE `admin` SET `modified` = :modified, `active` = :active, `password` = :password_hashed WHERE `username` = :username");
			$stmt->execute(array(
				':password_hashed' => $password_hashed,
				':username' => $username,
				':modified' => date('Y-m-d H:i:s'),
				':active' => $active
			));
		}
		catch (PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}
	else {
		try {
			$stmt = $pdo->prepare("UPDATE `admin` SET `modified` = :modified, `active` = :active WHERE `username` = :username");
			$stmt->execute(array(
				':username' => $username,
				':modified' => date('Y-m-d H:i:s'),
				':active' => $active
			));
		}
		catch (PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}

	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_admin_modified'], htmlspecialchars($username))
	);
}
function mailbox_edit_mailbox($postarray) {
	global $lang;
	global $pdo;
	isset($postarray['active']) ? $active = '1' : $active = '0';
	if (!filter_var($postarray['username'], FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$quota_m		= $postarray['quota'];
	$quota_b		= $quota_m*1048576;
	$username		= $postarray['username'];
	$name			= $postarray['name'];
	$password		= $postarray['password'];
	$password2		= $postarray['password2'];

	try {
		$stmt = $pdo->prepare("SELECT `domain`
			FROM `mailbox`
				WHERE username = :username");
		$stmt->execute(array(':username' => $username));
		$MailboxData1 = $stmt->fetch(PDO::FETCH_ASSOC);

		$stmt = $pdo->prepare("SELECT 
			COALESCE(ROUND(SUM(`quota`)/1048576), 0) as `quota_m_now`
				FROM `mailbox`
					WHERE `username` = :username");
		$stmt->execute(array(':username' => $username));
		$MailboxData2 = $stmt->fetch(PDO::FETCH_ASSOC);

		$stmt = $pdo->prepare("SELECT 
			COALESCE(ROUND(SUM(`quota`)/1048576), 0) as `quota_m_in_use`
				FROM `mailbox`
					WHERE `domain` = :domain");
		$stmt->execute(array(':domain' => $MailboxData1['domain']));
		$MailboxData3 = $stmt->fetch(PDO::FETCH_ASSOC);

		$stmt = $pdo->prepare("SELECT `quota`, `maxquota`
			FROM `domain`
				WHERE `domain` = :domain");
		$stmt->execute(array(':domain' => $MailboxData1['domain']));
		$DomainData = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}

	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $MailboxData1['domain'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if (!is_numeric($quota_m) || $quota_m == "0") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['quota_not_0_not_numeric'], htmlspecialchars($quota_m))
		);
		return false;
	}
	if ($quota_m > $DomainData['maxquota']) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_exceeded'], $DomainData['maxquota'])
		);
		return false;
	}
	if (($MailboxData3['quota_m_in_use'] - $MailboxData2['quota_m_now'] + $quota_m) > $DomainData['quota']) {
		$quota_left_m = ($DomainData['quota'] - $MailboxData3['quota_m_in_use'] + $MailboxData2['quota_m_now']);
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_left_exceeded'], $quota_left_m)
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("DELETE FROM `sender_acl` WHERE `logged_in_as` = :username");
		$stmt->execute(array(
			':username' => $username
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if (isset($postarray['sender_acl']) && is_array($postarray['sender_acl'])) {
		foreach ($postarray['sender_acl'] as $sender_acl) {
			if (!filter_var($sender_acl, FILTER_VALIDATE_EMAIL) && 
				!is_valid_domain_name(str_replace('@', '', $sender_acl))) {
					$_SESSION['return'] = array(
						'type' => 'danger',
						'msg' => sprintf($lang['danger']['sender_acl_invalid'])
					);
					return false;
			}
		}
		foreach ($postarray['sender_acl'] as $sender_acl) {
			try {
				$stmt = $pdo->prepare("INSERT INTO `sender_acl` (`send_as`, `logged_in_as`)
					VALUES (:sender_acl, :username)");
				$stmt->execute(array(
					':sender_acl' => $sender_acl,
					':username' => $username
				));
			}
			catch (PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
		}
	}
	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = hash_password($password);
		try {
			$stmt = $pdo->prepare("UPDATE `alias` SET
					`modified` = :modified,
					`active` = :active
						WHERE `address` = :address");
			$stmt->execute(array(
				':address' => $username,
				':modified' => date('Y-m-d H:i:s'),
				':active' => $active
			));
			$stmt = $pdo->prepare("UPDATE `mailbox` SET
					`modified` = :modified,
					`active` = :active,
					`password` = :password_hashed,
					`name`= :name,
					`quota` = :quota_b
						WHERE `username` = :username");
			$stmt->execute(array(
				':modified' => date('Y-m-d H:i:s'),
				':password_hashed' => $password_hashed,
				':active' => $active,
				':name' => utf8_decode($name),
				':quota_b' => $quota_b,
				':username' => $username
			));
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], $username)
			);
			return true;
		}
		catch (PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}
	try {
		$stmt = $pdo->prepare("UPDATE `alias` SET
				`modified` = :modified,
				`active` = :active
					WHERE `address` = :address");
		$stmt->execute(array(
			':address' => $username,
			':modified' => date('Y-m-d H:i:s'),
			':active' => $active
		));
		$stmt = $pdo->prepare("UPDATE `mailbox` SET
				`modified` = :modified,
				`active` = :active,
				`name`= :name,
				`quota` = :quota_b
					WHERE `username` = :username");
		$stmt->execute(array(
			':active' => $active,
			':modified' => date('Y-m-d H:i:s'),
			':name' => utf8_decode($name),
			':quota_b' => $quota_b,
			':username' => $username
		));
		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['mailbox_modified'], $username)
		);
		return true;
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
}
function mailbox_delete_domain($postarray) {
	global $lang;
	global $pdo;
	$domain = $postarray['domain'];
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}
	$domain	= strtolower(trim($domain));


	try {
		$stmt = $pdo->prepare("SELECT `username` FROM `mailbox`
			WHERE `domain` = :domain");
		$stmt->execute(array(':domain' => $domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if ($num_results != 0 || !empty($num_results)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_not_empty'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("DELETE FROM `domain` WHERE `domain` = :domain");
		$stmt->execute(array(
			':domain' => $domain,
		));
		$stmt = $pdo->prepare("DELETE FROM `domain_admins` WHERE `domain` = :domain");
		$stmt->execute(array(
			':domain' => $domain,
		));
		$stmt = $pdo->prepare("DELETE FROM `alias` WHERE `domain` = :domain");
		$stmt->execute(array(
			':domain' => $domain,
		));
		$stmt = $pdo->prepare("DELETE FROM `alias_domain` WHERE `target_domain` = :domain");
		$stmt->execute(array(
			':domain' => $domain,
		));
		$stmt = $pdo->prepare("DELETE FROM `mailbox` WHERE `domain` = :domain");
		$stmt->execute(array(
			':domain' => $domain,
		));
		$stmt = $pdo->prepare("DELETE FROM `sender_acl` WHERE `logged_in_as` LIKE :domain");
		$stmt->execute(array(
			':domain' => '%@'.$domain,
		));
		$stmt = $pdo->prepare("DELETE FROM `quota2` WHERE `username` = :domain");
		$stmt->execute(array(
			':domain' => '%@'.$domain,
		));
		$stmt = $pdo->prepare("DELETE FROM `userpref` WHERE `username` = :domain");
		$stmt->execute(array(
			':domain' => '%@'.$domain,
		));
		$stmt = $pdo->prepare("DELETE FROM `spamalias` WHERE `address` = :domain");
		$stmt->execute(array(
			':domain' => '%@'.$domain,
		));
		$stmt = $pdo->prepare("DELETE FROM `fugluconfig` WHERE `scope` = :domain");
		$stmt->execute(array(
			':domain' => '%@'.$domain,
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_removed'], htmlspecialchars($domain))
	);
	return true;
}
function mailbox_delete_alias($postarray) {
	global $lang;
	global $pdo;
	$address		= $postarray['address'];
	$local_part		= strstr($address, '@', true);
	$domain			= substr(strrchr($address, "@"), 1);
	try {
		$stmt = $pdo->prepare("SELECT `goto` FROM `alias` WHERE `address` = :address");
		$stmt->execute(array(':address' => $address));
		$gotos = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$goto_array = explode(',', $gotos['goto']);

	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("DELETE FROM `alias` WHERE `address` = :address AND `address` NOT IN (SELECT `username` FROM `mailbox`)");
		$stmt->execute(array(
			':address' => $address
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_removed'], htmlspecialchars($address))
	);

}
function mailbox_delete_alias_domain($postarray) {
	global $lang;
	global $pdo;
	if (!is_valid_domain_name($postarray['alias_domain'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}
	$alias_domain = $postarray['alias_domain'];
	try {
		$stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain`
				WHERE `alias_domain`= :alias_domain");
		$stmt->execute(array(':alias_domain' => $alias_domain));
		$DomainData = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}

	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $DomainData['target_domain'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("DELETE FROM `alias_domain` WHERE `alias_domain` = :alias_domain");
		$stmt->execute(array(
			':alias_domain' => $alias_domain,
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_domain_removed'], htmlspecialchars($alias_domain))
	);
}
function mailbox_delete_mailbox($postarray) {
	global $lang;
	global $pdo;
	$domain		= substr(strrchr($postarray['username'], "@"), 1);
	$username	= $postarray['username'];
	if (!filter_var($postarray['username'], FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("DELETE FROM `alias` WHERE `goto` = :username");
		$stmt->execute(array(
			':username' => $username
		));
		$stmt = $pdo->prepare("DELETE FROM `quota2` WHERE `username` = :username");
		$stmt->execute(array(
			':username' => $username
		));
		$stmt = $pdo->prepare("DELETE FROM `mailbox` WHERE `username` = :username");
		$stmt->execute(array(
			':username' => $username
		));
		$stmt = $pdo->prepare("DELETE FROM `sender_acl` WHERE `logged_in_as` = :username");
		$stmt->execute(array(
			':username' => $username
		));
		$stmt = $pdo->prepare("DELETE FROM `spamalias` WHERE `goto` = :username");
		$stmt->execute(array(
			':username' => $username
		));
		$stmt = $pdo->prepare("DELETE FROM `fugluconfig` WHERE `scope` = :username");
		$stmt->execute(array(
			':username' => $username
		));
		$stmt = $pdo->prepare("DELETE FROM `userpref` WHERE `username` = :username");
		$stmt->execute(array(
			':username' => $username
		));
		$stmt = $pdo->prepare("SELECT `address`, `goto` FROM `alias`
				WHERE `goto` LIKE :username");
		$stmt->execute(array(':username' => '%'.$username.'%'));
		$GotoData = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($GotoData as $gotos) {
			$goto_exploded = explode(',', $gotos['goto']);
			if (($key = array_search($username, $goto_exploded)) !== false) {
				unset($goto_exploded[$key]);
			}
			$gotos_rebuild = implode(',', $goto_exploded);
			$stmt = $pdo->prepare("UPDATE `alias` SET `goto` = :goto WHERE `address` = :address");
			$stmt->execute(array(
				':goto' => $gotos_rebuild,
				':address' => $gotos['address']
			));
		}
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_removed'], htmlspecialchars($username))
	);
}
function set_admin_account($postarray) {
	global $lang;
	global $pdo;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$name		= $postarray['admin_user'];
	$name_now	= $postarray['admin_user_now'];

	if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $name)) || empty ($name)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $name_now)) || empty ($name_now)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if (!empty($postarray['admin_pass']) && !empty($postarray['admin_pass2'])) {
		if ($postarray['admin_pass'] != $postarray['admin_pass2']) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = hash_password($postarray['admin_pass']);
		try {
			$stmt = $pdo->prepare("UPDATE `admin` SET 
				`modified` = :modified,
				`password` = :password_hashed,
				`username` = :name
					WHERE `username` = :username");
			$stmt->execute(array(
				':password_hashed' => $password_hashed,
				':modified' => date('Y-m-d H:i:s'),
				':name' => $name,
				':username' => $name_now
			));
		}
		catch (PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}
	else {
		try {
			$stmt = $pdo->prepare("UPDATE `admin` SET 
				`modified` = :modified,
				`username` = :name
					WHERE `username` = :name_now");
			$stmt->execute(array(
				':name' => $name,
				':modified' => date('Y-m-d H:i:s'),
				':name_now' => $name_now
			));
		}
		catch (PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}
	try {
		$stmt = $pdo->prepare("UPDATE `domain_admins` SET 
			`domain` = :domain,
			`username` = :name
				WHERE `username` = :name_now");
		$stmt->execute(array(
			':domain' => 'ALL',
			':name' => $name,
			':name_now' => $name_now
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['admin_modified'])
	);
}
function set_time_limited_aliases($postarray) {
	global $lang;
	global $pdo;
	$username	= $_SESSION['mailcow_cc_username'];
	$domain		= substr($username, strpos($username, '@'));
	if (($_SESSION['mailcow_cc_role'] != "user" &&
		$_SESSION['mailcow_cc_role'] != "domainadmin") || 
			empty($username) ||
			empty($domain)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['access_denied'])
				);
				return false;
	}
	switch ($postarray["trigger_set_time_limited_aliases"]) {
		case "generate":
			if (!is_numeric($postarray["validity"]) || $postarray["validity"] > 672) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['validity_missing'])
				);
				return false;
			}
			$validity = strtotime("+".$postarray["validity"]." hour"); 
			$letters = 'abcefghijklmnopqrstuvwxyz1234567890';
			$random_name = substr(str_shuffle($letters), 0, 24);
			try {
				$stmt = $pdo->prepare("INSERT INTO `spamalias` (`address`, `goto`, `validity`) VALUES
					(:address, :goto, :validity)");
				$stmt->execute(array(
					':address' => $random_name.$domain,
					':goto' => $username,
					':validity' => $validity
				));
			}
			catch (PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
		case "delete":
			try {
				$stmt = $pdo->prepare("DELETE FROM `spamalias` WHERE `goto` = :username");
				$stmt->execute(array(
					':username' => $username
				));
			}
			catch (PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}	
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
		case "extend":
			try {
				$stmt = $pdo->prepare("UPDATE `spamalias` SET `validity` = (`validity` + 3600)
					WHERE `goto` = :username 
						AND `validity` >= :validity");
				$stmt->execute(array(
					':username' => $username,
					':validity' => time(),
				));
			}
			catch (PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
	}
}
function set_user_account($postarray) {
	global $lang;
	global $pdo;
	$username			= $_SESSION['mailcow_cc_username'];
	$password_old		= $postarray['user_old_pass'];
	isset($postarray['togglePwNew']) ? $pwnew_active = '1' : $pwnew_active = '0';

	if (isset($pwnew_active) && $pwnew_active == "1") {
		$password_new	= $postarray['user_new_pass'];
		$password_new2	= $postarray['user_new_pass2'];
	}

	if (!check_login($username, $password_old) == "user") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	if ($_SESSION['mailcow_cc_role'] != "user" &&
		$_SESSION['mailcow_cc_role'] != "domainadmin") {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['access_denied'])
			);
			return false;
	}

	if (isset($password_new) && isset($password_new2)) {
		if (!empty($password_new2) && !empty($password_new)) {
			if ($password_new2 != $password_new) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['password_mismatch'])
				);
				return false;
			}
			if (strlen($password_new) < "6" ||
				!preg_match('/[A-Za-z]/', $password_new) ||
				!preg_match('/[0-9]/', $password_new)) {
					$_SESSION['return'] = array(
						'type' => 'danger',
						'msg' => sprintf($lang['danger']['password_complexity'])
					);
					return false;
			}
			$password_hashed = hash_password($password_new);
			try {
				$stmt = $pdo->prepare("UPDATE `mailbox` SET `modified` = :modified, `password` = :password_hashed WHERE `username` = :username");
				$stmt->execute(array(
					':password_hashed' => $password_hashed,
					':modified' => date('Y-m-d H:i:s'),
					':username' => $username
				));
			}
			catch (PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function add_domain_admin($postarray) {
	global $lang;
	global $pdo;
	$username		= strtolower(trim($postarray['username']));
	$password		= $postarray['password'];
	$password2		= $postarray['password2'];
	isset($postarray['active']) ? $active = '1' : $active = '0';
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if (empty($postarray['domain'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}
	if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $username)) || empty ($username)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("SELECT `username` FROM `mailbox`
			WHERE `username` = :username");
		$stmt->execute(array(':username' => $username));
		$num_results[] = count($stmt->fetchAll(PDO::FETCH_ASSOC));
		
		$stmt = $pdo->prepare("SELECT `username` FROM `admin`
			WHERE `username` = :username");
		$stmt->execute(array(':username' => $username));
		$num_results[] = count($stmt->fetchAll(PDO::FETCH_ASSOC));
		
		$stmt = $pdo->prepare("SELECT `username` FROM `domain_admins`
			WHERE `username` = :username");
		$stmt->execute(array(':username' => $username));
		$num_results[] = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	foreach ($num_results as $num_results_each) {
		if ($num_results_each != 0) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['object_exists'], htmlspecialchars($username))
			);
			return false;
		}
	}
	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = hash_password($password);
		foreach ($postarray['domain'] as $domain) {
			if (!is_valid_domain_name($domain)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['domain_invalid'])
				);
				return false;
			}
			try {
				$stmt = $pdo->prepare("INSERT INTO `domain_admins` (`username`, `domain`, `created`, `active`)
						VALUES (:username, :domain, :created, :active)");
				$stmt->execute(array(
					':username' => $username,
					':domain' => $domain,
					':created' => date('Y-m-d H:i:s'),
					':active' => $active
				));
			}
			catch (PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
		}
		try {
			$stmt = $pdo->prepare("INSERT INTO `admin` (`username`, `password`, `superadmin`, `created`, `modified`, `active`)
				VALUES (:username, :password_hashed, '0', :created, :modified, :active)");
			$stmt->execute(array(
				':username' => $username,
				':password_hashed' => $password_hashed,
				':created' => date('Y-m-d H:i:s'),
				':modified' => date('Y-m-d H:i:s'),
				':active' => $active
			));
		}
		catch (PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}
	else {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['password_empty'])
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_admin_added'], htmlspecialchars($username))
	);
}
function delete_domain_admin($postarray) {
	global $pdo;
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$username = $postarray['username'];
	if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("DELETE FROM `domain_admins` WHERE `username` = :username");
		$stmt->execute(array(
			':username' => $username,
		));
		$stmt = $pdo->prepare("DELETE FROM `admin` WHERE `username` = :username");
		$stmt->execute(array(
			':username' => $username,
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_admin_removed'], htmlspecialchars($username))
	);
}
function get_spam_score($username) {
	global $pdo;
	$default = "5, 15";
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		return $default;
	}
	try {
		$stmt = $pdo->prepare("SELECT * FROM `userpref`, `fugluconfig`
			WHERE `username` = :username
			AND `scope`= :scope");
		$stmt->execute(array(':username' => $username, ':scope' => $username));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if ($num_results == 0 || empty ($num_results)) {
		return $default;
	}
	else {
		try {
			$stmt = $pdo->prepare("SELECT `value` FROM `fugluconfig`
					WHERE `option` = 'highspamlevel'
					AND `scope` = :username");
			$stmt->execute(array(':username' => $username));
			$FugluconfigData = $stmt->fetch(PDO::FETCH_ASSOC);

			$stmt = $pdo->prepare("SELECT `value` FROM `userpref`
				WHERE `preference` = 'required_hits'
				AND `username` = :username");
			$stmt->execute(array(':username' => $username));
			$UserprefData = $stmt->fetch(PDO::FETCH_ASSOC);
			return $UserprefData['value'].', '.$FugluconfigData['value'];
		}
		catch(PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}
}
function set_whitelist($postarray) {
	global $lang;
	global $pdo;
	$username	= $_SESSION['mailcow_cc_username'];
	$whitelist_from	= trim(strtolower($postarray['whitelist_from']));
	$whitelist_from = preg_replace("/\.\*/", "*", $whitelist_from);
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if (!ctype_alnum(str_replace(array('@', '.', '-', '*'), '', $whitelist_from))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['whitelist_from_invalid'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("SELECT `username` FROM `userpref`
			WHERE `preference` = 'whitelist_from'
				AND `username` = :username
				AND `value` = :whitelist_from");
		$stmt->execute(array(':username' => $username, ':whitelist_from' => $whitelist_from));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['whitelist_exists'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("INSERT INTO `userpref` (`username`, `preference` ,`value`)
			VALUES (:username, 'whitelist_from', :whitelist_from)");
		$stmt->execute(array(
			':username' => $username,
			':whitelist_from' => $whitelist_from
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function delete_whitelist($postarray) {
	global $lang;
	global $pdo;
	$username	= $_SESSION['mailcow_cc_username'];
	$prefid		= $postarray['wlid'];
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if (!is_numeric($prefid)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['whitelist_from_invalid'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("DELETE FROM `userpref` WHERE `username` = :username AND `prefid` = :prefid");
		$stmt->execute(array(
			':username' => $username,
			':prefid' => $prefid
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function set_blacklist($postarray) {
	global $lang;
	global $pdo;
	$username		= $_SESSION['mailcow_cc_username'];
	$blacklist_from	= trim(strtolower($postarray['blacklist_from']));
	$blacklist_from = preg_replace("/\.\*/", "*", $blacklist_from);
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if (!ctype_alnum(str_replace(array('@', '.', '-', '*'), '', $blacklist_from))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['blacklist_from_invalid'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("SELECT `username` FROM `userpref`
			WHERE `preference` = 'blacklist_from'
				AND `username` = :username
				AND `value` = :blacklist_from");
		$stmt->execute(array(':username' => $username, ':blacklist_from' => $blacklist_from));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['blacklist_exists'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("INSERT INTO `userpref` (`username`, `preference` ,`value`)
			VALUES (:username, 'blacklist_from', :blacklist_from)");
		$stmt->execute(array(
			':username' => $username,
			':blacklist_from' => $blacklist_from
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function delete_blacklist($postarray) {
	global $lang;
	global $pdo;
	$username	= $_SESSION['mailcow_cc_username'];
	$prefid		= $postarray['blid'];
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if (!is_numeric($prefid)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['blacklist_from_invalid'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("DELETE FROM `userpref` WHERE `username` = :username AND `prefid` = :prefid");
		$stmt->execute(array(
			':username' => $username,
			':prefid' => $prefid
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function set_spam_score($postarray) {
	global $lang;
	global $pdo;
	$username		= $_SESSION['mailcow_cc_username'];
	$lowspamlevel	= explode(',', $postarray['score'])[0];
	$highspamlevel	= explode(',', $postarray['score'])[1];
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if (!is_numeric($lowspamlevel) || !is_numeric($highspamlevel)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("DELETE FROM `fugluconfig` WHERE `scope` = :username
			AND (
				`option`='highspamlevel'
				OR `option`='lowspamlevel'
			)");
		$stmt->execute(array(
			':username' => $username
		));
		$stmt = $pdo->prepare("DELETE FROM `userpref` WHERE `username` = :username
			AND preference='required_hits'");
		$stmt->execute(array(
			':username' => $username
		));
		$stmt = $pdo->prepare("INSERT INTO `fugluconfig` (`scope`, `section`, `option`, `value`)
			VALUES (:username, 'SAPlugin', 'highspamlevel', :highspamlevel)");
		$stmt->execute(array(
			':username' => $username,
			':highspamlevel' => $highspamlevel
		));
		$stmt = $pdo->prepare("INSERT INTO `userpref` (`username`, `preference` ,`value`)
			VALUES (:username, 'required_hits', :lowspamlevel)");
		$stmt->execute(array(
			':username' => $username,
			':lowspamlevel' => $lowspamlevel
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function set_tls_policy($postarray) {
	global $lang;
	global $pdo;
	isset($postarray['tls_in']) ? $tls_in = '1' : $tls_in = '0';
	isset($postarray['tls_out']) ? $tls_out = '1' : $tls_out = '0';
	$username = $_SESSION['mailcow_cc_username'];
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("UPDATE `mailbox` SET `tls_enforce_out` = :tls_out, `tls_enforce_in` = :tls_in WHERE `username` = :username");
		$stmt->execute(array(
			':tls_out' => $tls_out,
			':tls_in' => $tls_in,
			':username' => $username
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function get_tls_policy($username) {
	global $lang;
	global $pdo;
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("SELECT `tls_enforce_out`, `tls_enforce_in` FROM `mailbox` WHERE `username` = :username");
		$stmt->execute(array(':username' => $username));
		$TLSData = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	return $TLSData;
}
function remaining_specs($domain, $object = null, $js = null) {
	// left_m	without object given	= MiB left in domain
	// left_m	with object given		= Max. MiB we can assign to given object
	// limit_m							= Domain limit in MiB
	// left_c							= Mailboxes we can create depending on domain quota
	global $pdo;
	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		return false;
	}
	try {
		$stmt = $pdo->prepare("SELECT `mailboxes`, `maxquota`, `quota` FROM `domain` WHERE `domain` = :domain");
		$stmt->execute(array(':domain' => $domain));
		$DomainData			= $stmt->fetch(PDO::FETCH_ASSOC);

		$stmt = $pdo->prepare("SELECT COUNT(*) AS `count`, COALESCE(ROUND(SUM(`quota`)/1048576), 0) as `in_use_m` FROM `mailbox` WHERE `domain` = :domain AND `username` != :object");
		$stmt->execute(array(':domain' => $domain, ':object' => $object));
		$MailboxDataDomain	= $stmt->fetch(PDO::FETCH_ASSOC);

		$quota_left_m	= $DomainData['quota']		- $MailboxDataDomain['in_use_m'];
		$mboxs_left		= $DomainData['mailboxes']	- $MailboxDataDomain['count'];

		if ($quota_left_m > $DomainData['maxquota']) {
			$quota_left_m = $DomainData['maxquota'];
		}

	}
	catch (PDOException $e) {
		return false;
	}
	if (is_numeric($quota_left_m)) {
		$spec['left_m']		= $quota_left_m;
		$spec['limit_m']	= $DomainData['maxquota'];
	}
	if (is_numeric($mboxs_left)) {
		$spec['left_c']		= $mboxs_left;
	}
	if (!empty($js)) {
		echo $quota_left_m;
		exit;
	}
	return $spec;
}
function get_sender_acl_handles($mailbox, $which) {
	global $pdo;
	if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
		return false;
	}
	switch ($which) {
		case "preselected":
			try {
				$stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE `goto` = :goto AND `address` NOT LIKE '@%'");
				$stmt->execute(array(':goto' => $mailbox));
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				return $rows;
			}
			catch(PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
			break;
		case "selected":
			try {
				$stmt = $pdo->prepare("SELECT `send_as` FROM `sender_acl` WHERE `logged_in_as` = :logged_in_as");
				$stmt->execute(array(':logged_in_as' => $mailbox));
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				return $rows;
			}
			catch(PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
			break;
		case "unselected-domains":
			try {
				if ($_SESSION['mailcow_cc_role'] == "admin"  ) {
					$stmt = $pdo->prepare("SELECT DISTINCT `domain` FROM `domain`
						WHERE `domain` NOT IN (
							SELECT REPLACE(`send_as`, '@', '') FROM `sender_acl` 
								WHERE `logged_in_as` = :logged_in_as)
						AND	`domain` NOT IN (
								SELECT REPLACE(`address`, '@', '') FROM `alias` 
									WHERE `goto` = :goto)");
					$stmt->execute(array(
						':logged_in_as' => $mailbox,
						':goto' => $mailbox,
					));
				}
				else {
					$stmt = $pdo->prepare("SELECT DISTINCT `domain` FROM `domain_admins`
						WHERE `username` = :username
							AND `domain` != 'ALL'
							AND	`domain` NOT IN (
								SELECT REPLACE(`send_as`, '@', '') FROM `sender_acl` 
									WHERE `logged_in_as` = :logged_in_as)");
					$stmt->execute(array(
						':logged_in_as' => $mailbox,
						':username' => $_SESSION['mailcow_cc_username']
					));
				}
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				return $rows;
			}
			catch(PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
			break;
		case "unselected-addresses":
			try {
				if ($_SESSION['mailcow_cc_role'] == "admin"  ) {
					$stmt = $pdo->prepare("SELECT `address` FROM `alias`
						WHERE `goto` != :goto
							AND `address` NOT IN (
								SELECT `send_as` FROM `sender_acl` 
									WHERE `logged_in_as` = :logged_in_as)");
					$stmt->execute(array(
						':logged_in_as' => $mailbox,
						':goto' => $mailbox
					));
				}
				else {
					$stmt = $pdo->prepare("SELECT `address` FROM `alias`
						WHERE `goto` != :goto
							AND `domain` IN (
								SELECT `domain` FROM `domain_admins`
									WHERE `username` = :username)
							AND `address` NOT IN (
								SELECT `send_as` FROM `sender_acl` 
									WHERE `logged_in_as` = :logged_in_as)");
					$stmt->execute(array(
						':logged_in_as' => $mailbox,
						':goto' => $mailbox,
						':username' => $_SESSION['mailcow_cc_username']
					));
				}
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				return $rows;
			}
			catch(PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
			break;
	}
	return false;
}
function is_valid_domain_name($domain_name) { 
	if (empty($domain_name)) {
		return false;
	}
	$domain_name = idn_to_ascii($domain_name);
	return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name)
		   && preg_match("/^.{1,253}$/", $domain_name)
		   && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name));
}
?>
