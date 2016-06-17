<?php
function sha512c($password) {
	$password = escapeshellarg($password);
	exec('/usr/bin/doveadm pw -s SHA512-CRYPT -p '.$password, $hash, $return);
	if ($return != "0") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'Cannot create password hash'
		);
		return false;
	}
	return $hash[0];
}
function hasDomainAccess($link, $username, $role, $domain) {
	if (!filter_var($username, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
		return false;
	}

	if (!is_valid_domain_name($domain)) {
		return false;
	}

	$username	= mysqli_real_escape_string($link, $username);
	$domain		= mysqli_real_escape_string($link, $domain);

	$qstring = "SELECT `domain` FROM `domain_admins`
		WHERE (
			`active`='1'
			AND `username`='".$username."'
			AND `domain`='".$domain."'
		)
		OR 'admin'='".$role."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results != 0 && !empty($num_results)) {
		return true;
	}
	return false;	
}
function check_login($link, $user, $pass) {
	if (!filter_var($user, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $user))) {
		return false;
	}
	if (!strpos(shell_exec("file --mime-encoding /usr/bin/doveadm"), "binary")) {
		return false;
	}
	$user = strtolower(trim($user));
	$pass = escapeshellarg($pass);
	$result = mysqli_query($link, "SELECT `password` FROM `admin`
		WHERE `superadmin`='1'
		AND `username`='".$user."'");
	while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
		$row = "'".$row[0]."'";
		exec("echo ".$pass." | doveadm pw -s SHA512-CRYPT -t ".$row, $out, $return);
		if (strpos($out[0], "verified") !== false && $return == "0") {
			unset($_SESSION['ldelay']);
			return "admin";
		}
	}
	$result = mysqli_query($link, "SELECT `password` FROM `admin`
		WHERE `superadmin`='0'
		AND `active`='1'
		AND `username`='".$user."'");
	while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
		$row = "'".$row[0]."'";
		exec("echo ".$pass." | doveadm pw -s SHA512-CRYPT -t ".$row, $out, $return);
		if (strpos($out[0], "verified") !== false && $return == "0") {
			unset($_SESSION['ldelay']);
			return "domainadmin";
		}
	}
	$result = mysqli_query($link, "SELECT `password` FROM `mailbox`
		WHERE `active`='1'
		AND `username`='".$user."'");
	while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
		$row = "'".$row[0]."'";
		exec("echo ".$pass." | doveadm pw -s SHA512-CRYPT -t ".$row, $out, $return);
		if (strpos($out[0], "verified") !== false && $return == "0") {
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
			return shell_exec("sudo /usr/local/sbin/mc_pfset get-srr");
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
					'msg' => 'Invalid max. message size'
				);
				break;
			}
			$v = escapeshellarg($new_size);
			exec('sudo /usr/local/sbin/mc_msg_size '.$new_size, $out, $return);
			if ($return != "0") {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => $lang['admin']['site_not_found']
				);
				break;
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
				file_put_contents($GLOBALS["MC_ANON_HEADERS"], $template);
			} else {
				file_put_contents($GLOBALS["MC_ANON_HEADERS"], "");
			}
			break;
		case "public_folder":
			if (!ctype_alnum(str_replace("/", "", $v['public_folder_name'])))
			{
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'Public folder name must not be empty'
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
			if (isset($v['use_public_folder']) && $v['use_public_folder'] == "on")	{
				file_put_contents($GLOBALS["MC_PUB_FOLDER"], $template);
			}
			else {
				file_put_contents($GLOBALS["MC_PUB_FOLDER"], "");
			}
			break;
		case "srr":
			$srr_parameters = "";
			$valid_srr = array(
				"reject_invalid_helo_hostname",
				"reject_unknown_helo_hostname",
				"reject_unknown_reverse_client_hostname",
				"reject_unknown_client_hostname",
				"reject_non_fqdn_helo_hostname",
				"z1_greylisting"
				);
			$srr = (array_keys($v));
			foreach ($srr as $restriction) {
				if (in_array($restriction, $valid_srr)) {
					$srr_parameters .= $restriction." ";
				}
			}
			exec('sudo /usr/local/sbin/mc_pfset set-srr "'.$srr_parameters.'"', $out, $return);
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
			'msg' => $lang['success']['set_rr']
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
			exec('sudo /usr/local/sbin/mc_dkim_ctrl del '.$selector.' '.$domain, $out, $return);
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
			exec('sudo /usr/local/sbin/mc_dkim_ctrl add '.$selector.' '.$domain, $out, $return);
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
		case "ram":
			$return['total'] = filter_var(shell_exec("free -b | grep Mem | awk '{print $2}'"), FILTER_SANITIZE_NUMBER_FLOAT);
			$return['used'] = filter_var(shell_exec("free -b | grep Mem | awk '{print $3}'"), FILTER_SANITIZE_NUMBER_FLOAT);
			$return['free'] = filter_var(shell_exec("free -b | grep Mem | awk '{print $4}'"), FILTER_SANITIZE_NUMBER_FLOAT);
			$return['used_percent'] = round(shell_exec("free -b | grep Mem | awk '{print $3/$2 * 100.0}'"));
			return $return;
			break;
		case "vmail_percentage":
			$df = disk_free_space("/var/vmail");
			$dt = disk_total_space("/var/vmail");
			$du = $dt - $df;
			return sprintf('%.2f',($du / $dt) * 100);
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
				$imageData = base64_encode(file_get_contents($image));
				if (empty($imageData)) {
					$return .='<img class="img-responsive" alt="not-yet-available" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAG3UlEQVRYR8WXC2yT1xXH/+dcx0loRkMhULZuA2WxAwjStVsYiZ06IYUOClRbVaGJ0m0MCaEpqOs00anVgErtNq1ZH5PWVRUIOtZ2LevGUOkjbydMbKM8x+wQKKWsCYSUFvLAj++c6fvAxo4NDtKkWbIsfd+55/+79/7PudeE//OHblR/A8A11TNnwIiXVSYqMZPggsA6MTQy/vCSffuGbyTnmAFafZ56JXxfhb7JjOJsIgSJQrhTmV4eKYq/smh3TyQXTE6A5pryKkX8WaPma4lklkivIToiRGcYahF0ggJeAXsYcHI6MYYfD3SENxOg1wK5JkBrIODSWO+Tyvjx5aQyAOIXGLL9ro5j/24NzCpikanCljGWDPiDPeferSotyTeubwP0Q4XOtEUJeJdhragJ9vRnX7UsT1sD0wog+X+EYokCQkAjjGujZUW+ZGBWiyWL2XBZ6lAB+gnaBPDW2mDovXaf9yGL9GkGTQBwnAl339UR/mC0XMYK2Cbz+707GLgPis8Avd+QHIzBNDKwIteeOrNW/J3YWhOP531ijOxUYI4KPpCCvHn1zUfOpObIAGitKd8A1Z8RcMEC17oopgLzVyi+MBbxRIxjSOU16oq8CSu/DUCFQto6gsfmbwDkalxK1hafp4KU9gmDifReUTptFB0g3Hwj4mmxhB9ITN8G0wFmTCJQQyAYej4rQJvf+44CC4j0xZg7/hMzkrefGNNTE06uX4zB7qMYPpW+naagEJ9fthynX98KleQEISJxMFUzYTqUXhXo+XyhL/u6whevmPRy+ia/d44BDhIwzLCmCZnHVNGQKn7rPfeh/NGnEP3kHA6sewjDp044r23x2b94AcVfnYu+t/6E8C8fS4Mg4NDZW8N3TO717lFCJYCHa4PhZ9IAWn2eX4HoERG8xHmux8mKfqhgdwJgysJlmPHTnwN02TYORMNKRM72JsUTsVkhFA8oqQHoFRHaP78rdEcaQJu//F927Sq0HooKIno6kXDCnfNQ0bg5KZ54Hj0/gJGPTuLmOXdmWOTU9hdx4neNyecKvF0QH/pWhG86D0Z+1IpPWbjn+FlnOnZTgRW/CIGVL0Ofi5jCN0G8MDGa89yYtelZTKyuHZMXL/X9x1kd+zfxEcGlyHiruGDQNBHgg9KS2s7QLgfAcT/RAbth1AbDX2mqLvvYME9NVRsrxGXxB3Gp7+NMWMOz1dIGgq5O+MABaK4pq2HldruBBDrDc5ury2LM7BqdwYF44jlMrApkXYnrisNpqfNJtZ5AjxJoUyAYsvvNVQAA/6gNhiub/WVRBueNVkl1ezaC6EA/DqxbmVGiiVhi1KlFC0C6HkQbaztCGxyA9pry2aJ6SEROzu86Nr3Z5/2ICbeliuQSTxrzSnUkSjRtG0lmivAjIKxSxbq6zvBzDsCeebcVjrhuGrKPzVhh/nj3cPQ1kC5ODB6r+PUg7P5SNFxUfHHcYDuAeSS6KNDVvTt5FrT5vQftQ8N+ocylgCbbZfHtX0dF4xaQK90WttGGPzyOW+b6M3bk5JbfwP4mlx+y0yW8IsIyALBL3VRS3xwaSAK0+D1P2uZQ4GVx08OuqJ5SYFwiwSR/vVOKZIzzKFFq9r6PLtHTb2xDz/NPAXr1HkJKS5UwEdAtAO2tDYa+YedJArQGPOVi0VEDianRUrV4rQ2UOrVJNXdj1sZnEOnvSyu11BLNJm4LBoKhqna/d7+zysDaQDD82zQApyFVe/8MxjJS/OHiSNHqcYWD/2TCjFQIuwSHTnRn1LkNMWXBUvS+tSNt5nYDchmqFGglFC8RcNYdH5pW9bfTIxkATb5yj4usw84ZQLqcYd5XlU4FJo+pBY4KEkCJsJzYvE+WtU+B8apYVdcZ3nzVG6MGtfg8P7LPAQFGQHKPiukjsXaNvoLlAhKRQUO0Mp7v3kMjkaA9XqC76oLdS1MvqVkvpS1+7zYCHrQhGFiZJ3gnRvKEENZma1AZMITdYNNgWVG3gdkJoJRAR43m+fydh8+nxmcFsG/EFO/dqoTv2MEKbIlZ8fVuN7kh5rsWdDFZdDszCq68F1V0E+l7JLSt/1PXoZKJVgOpbrQrSVSOWMoLF3SFMw6Ia17LFaB2f/l6S6xN9rlgNxIBtgLYTmbq3v6SNi3p90ywjNtE3Zc+tf+EtFR7vUS4X4A1iU4qkDciUV21aG/PhWzblvOPiX1Tcgl+rYy6ZAKRITCFCHQGkLiAb2HAk2pWgfYQeH1dMLTjen7JCZAY3Oz3VDLoe6K4d/Q5keLoC0rUrMDvz00J/eWB12HlMuuYAVITtdeUftFS4yHCJAUbqPUZGTreX9J9bCyiOU2Yi/p/+f6/yPUmTii6UZAAAAAASUVORK5CYII=" /><p>Not enough data</p>';
				}
				else {
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
	shell_exec("sudo /usr/local/sbin/mc_pflog_renew");
}
function dovecot_reload() {
	shell_exec("sudo /usr/sbin/dovecot reload");
}
function mailbox_add_domain($link, $postarray) {
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$domain				= idn_to_ascii(mysqli_real_escape_string($link, strtolower(trim($postarray['domain']))));
	$description		= mysqli_real_escape_string($link, $postarray['description']);
	$aliases			= mysqli_real_escape_string($link, $postarray['aliases']);
	$mailboxes			= mysqli_real_escape_string($link, $postarray['mailboxes']);
	$maxquota			= mysqli_real_escape_string($link, $postarray['maxquota']);
	$quota				= mysqli_real_escape_string($link, $postarray['quota']);

	if ($maxquota > $quota) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_exceeds_domain_quota'])
		);
		return false;
	}

	isset($postarray['active']) ? $active = '1' : $active = '0';
	isset($postarray['relay_all_recipients']) ? $relay_all_recipients = '1' : $relay_all_recipients = '0';
	isset($postarray['backupmx']) ? $backupmx = '1' : $backupmx = '0';
	isset($postarray['relay_all_recipients']) ? $backupmx = '1' : true;

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

	$InsertDomain = "INSERT INTO `domain` (`domain`, `description`, `aliases`, `mailboxes`, `maxquota`, `quota`, `transport`, `backupmx`, `created`, `modified`, `active`, `relay_all_recipients`)
		VALUES ('".$domain."', '".$description."', '".$aliases."', '".$mailboxes."', '".$maxquota."', '".$quota."', 'virtual', '".$backupmx."', NOW(), NOW(), '".$active."', '".$relay_all_recipients."')";
	if (!mysqli_query($link, $InsertDomain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}

	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_added'], htmlspecialchars($domain))
	);
}
function mailbox_add_alias($link, $postarray) {
	global $lang;
	$addresses		= array_map('trim', preg_split( "/( |,|;|\n)/", $postarray['address']));
	$gotos			= array_map('trim', preg_split( "/( |,|;|\n)/", $postarray['goto']));
	isset($postarray['active']) ? $active = '1' : $active = '0';

	if (empty($addresses)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_empty'])
		);
		return false;
	}

	if (empty($gotos)) {
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

		if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['access_denied'])
			);
			return false;
		}

		$address = mysqli_real_escape_string($link, $address);

		$qstring = "SELECT `address` FROM `alias`
			WHERE `address`='".$address."'";
		$qresult = mysqli_query($link, $qstring);
		$num_results = mysqli_num_rows($qresult);
		if ($num_results != 0) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['is_alias_or_mailbox'], htmlspecialchars($address))
			);
			return false;
		}

		$qstring = "SELECT `address` FROM `spamalias`
			WHERE address='".$address."'";
		$qresult = mysqli_query($link, $qstring);
		$num_results = mysqli_num_rows($qresult);
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
			$goto = mysqli_real_escape_string($link, $goto);
		}
		$gotos = array_filter($gotos);
		$goto = implode(",", $gotos);

		if (!filter_var($address, FILTER_VALIDATE_EMAIL) === true) {
			$InsertAliasQuery = "INSERT INTO `alias` (`address`, `goto`, `domain`, `created`, `modified`, `active`)
				VALUES ('@".$domain."', '".$goto."', '".$domain."', NOW(), NOW(), '".$active."')";
		}
		else {
			$InsertAliasQuery = "INSERT INTO `alias` (`address`, `goto`, `domain`, `created`, `modified`, `active`)
				VALUES ('".$address."', '".$goto."', '".$domain."', NOW(), NOW(), '".$active."')";
		}

		if (!mysqli_query($link, $InsertAliasQuery)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.mysqli_error($link)
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_added'])
	);
}
function mailbox_add_alias_domain($link, $postarray) {
	global $lang;
	isset($postarray['active']) ? $active = '1' : $active = '0';

	if (!is_valid_domain_name($alias_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_domain_invalid'])
		);
		return false;
	}

	if (!is_valid_domain_name($target_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['target_domain_invalid'])
		);
		return false;
	}

	foreach (array($alias_domain, $target_domain) as $domain) {
		if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['access_denied'])
			);
			return false;
		}
	}

	if ($alias_domain == $target_domain) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['aliasd_targetd_identical'])
		);
		return false;
	}

	$alias_domain	= mysqli_real_escape_string($link, strtolower(trim($postarray['alias_domain'])));
	$target_domain	= mysqli_real_escape_string($link, strtolower(trim($postarray['target_domain'])));

	$qstring = "SELECT `domain` FROM `domain`
		WHERE `domain`='".$target_domain."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results == 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['targetd_not_found'])
		);
		return false;
	}

	$qstring = "SELECT `domain` FROM `domain`
		WHERE `domain`='".$alias_domain."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results == 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['aliasd_not_found'])
		);
		return false;
	}

	$qstring = "SELECT alias_domain FROM alias_domain
		WHERE `alias_domain`='".$alias_domain."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['aliasd_exists'])
		);
		return false;
	}

	$InsertAliasDomainQuery = "INSERT INTO `alias_domain` (`alias_domain`, `target_domain`, `created`, `modified`, `active`)
		VALUES ('".$alias_domain."', '".$target_domain."', NOW(), NOW(), '".$active."')";
	if (!mysqli_query($link, $InsertAliasDomainQuery)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['aliasd_added'], htmlspecialchars($alias_domain))
	);
}
function mailbox_add_mailbox($link, $postarray) {
	global $lang;
	$username		= strtolower(trim($postarray['local_part'])).'@'.strtolower(trim($postarray['domain']));
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_invalid'])
		);
		return false;
	}
	if (empty($local_part)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_invalid'])
		);
		return false;
	}
	$domain			= mysqli_real_escape_string($link, strtolower(trim($postarray['domain'])));
	$password		= mysqli_real_escape_string($link, $postarray['password']);
	$password2		= mysqli_real_escape_string($link, $postarray['password2']);
	$local_part		= mysqli_real_escape_string($link, strtolower(trim($postarray['local_part'])));
	$username		= mysqli_real_escape_string($link, $username);
	$name			= mysqli_real_escape_string($link, $postarray['name']);
	$quota_m		= mysqli_real_escape_string($link, $postarray['quota']);

	if (empty($name)) {
		$name = $local_part;
	}
	else {
		$name = utf8_decode($name);
	}

	isset($postarray['active']) ? $active = '1' : $active = '0';

	$quota_b		= ($quota_m * 1048576);
	$maildir		= $domain."/".$local_part."/";
	$username		= $local_part.'@'.$domain;

	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}

	$DomainData = mysqli_fetch_assoc(mysqli_query($link,
		"SELECT `mailboxes`, `maxquota`, `quota` FROM `domain`
			WHERE domain='".$domain."'"));
	$MailboxData = mysqli_fetch_assoc(mysqli_query($link,
		"SELECT 
			count(*) as count,
			coalesce(round(sum(quota)/1048576), 0) as quota
				FROM `mailbox`
					WHERE domain='".$domain."'"));

	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	$qstring = "SELECT `local_part` FROM `mailbox` WHERE local_part='".$local_part."' and domain='".$domain."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['object_exists'], htmlspecialchars($username))
		);
		return false;
	}

	$qstring = "SELECT `address` FROM `alias` WHERE address='".$username."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['is_alias'], htmlspecialchars($username))
		);
		return false;
	}

	$qstring = "SELECT `address` FROM `spamalias` WHERE address='".$username."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
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
		$password_hashed = sha512c($password);
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

	$qstring = "SELECT `domain` FROM `domain`
		WHERE `domain`='".$domain."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results == 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_not_found'])
		);
		return false;
	}

	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_invalid'])
		);
		return false;
	}

	if ($quota_m > $DomainData['maxquota']) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_exceeded'], $quota_m, $DomainData['maxquota'])
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

	$create_user_array = array(
		"INSERT INTO `mailbox` (`username`, `password`, `name`, `maildir`, `quota`, `local_part`, `domain`, `created`, `modified`, `active`) 
			VALUES ('".$username."', '".$password_hashed."', '".$name."', '$maildir', '".$quota_b."', '$local_part', '".$domain."', now(), now(), '".$active."')",
		"INSERT INTO `quota2` (`username`, `bytes`, `messages`)
			VALUES ('".$username."', '0', '0')",
		"INSERT INTO `alias` (`address`, `goto`, `domain`, `created`, `modified`, `active`)
			VALUES ('".$username."', '".$username."', '".$domain."', now(), now(), '".$active."')"
	);

	foreach ($create_user_array as $create_user) {
		if (!mysqli_query($link, $create_user)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.mysqli_error($link)
			);
			return false;
		}
	}

	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_added'], htmlspecialchars($username))
	);
}
function mailbox_edit_alias($link, $postarray) {
	global $lang;
	$address	= mysqli_real_escape_string($link, $postarray['address']);
	$domain		= idn_to_ascii(substr(strstr($address, '@'), 1));
	$local_part	= strstr($address, '@', true);
	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
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
	$mystring = "UPDATE `alias` SET `goto`='".$goto."', `active`='".$active."' WHERE `address`='".$address."'";
	if (!mysqli_query($link, $mystring)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_modified'], htmlspecialchars($address))
	);
}
function mailbox_edit_domain($link, $postarray) {
	global $lang;
	$domain			= mysqli_real_escape_string($link, $postarray['domain']);
	$description	= mysqli_real_escape_string($link, $postarray['description']);

	$aliases		= filter_var($postarray['aliases'], FILTER_SANITIZE_NUMBER_FLOAT);
	$mailboxes		= filter_var($postarray['mailboxes'], FILTER_SANITIZE_NUMBER_FLOAT);
	$maxquota		= filter_var($postarray['maxquota'], FILTER_SANITIZE_NUMBER_FLOAT);
	$quota			= filter_var($postarray['quota'], FILTER_SANITIZE_NUMBER_FLOAT);

	isset($postarray['relay_all_recipients']) ? $relay_all_recipients = '1' : $relay_all_recipients = '0';
	isset($postarray['backupmx']) ? $backupmx = '1' : $backupmx = '0';
	isset($postarray['relay_all_recipients']) ? $backupmx = '1' : true;
	isset($postarray['active']) ? $active = '1' : $active = '0';

	$MailboxData = mysqli_fetch_assoc(mysqli_query($link,
			"SELECT 
				count(*) AS count,
				max(coalesce(round(quota/1048576), 0)) AS maxquota,
				coalesce(round(sum(quota)/1048576), 0) AS quota
					FROM `mailbox`
						WHERE domain='".$domain."'"));
	$AliasData = mysqli_fetch_assoc(mysqli_query($link, 
			"SELECT count(*) AS count FROM `alias`
				WHERE domain='".$domain."'
				AND address NOT IN (
					SELECT `username` FROM `mailbox`
				)"));
	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
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
			'msg' => sprintf($lang['danger']['maxquota_in_use'], $MailboxData['maxquota'])
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
	$UpdateDomainData = "UPDATE `domain` SET 
		`modified`=now(),
		`relay_all_recipients`='".$relay_all_recipients."',
		`backupmx`='".$backupmx."',
		`active`='".$active."',
		`quota`='".$quota."',
		`maxquota`='".$maxquota."',
		`mailboxes`='".$mailboxes."',
		`aliases`='".$aliases."',
		`description`='".$description."'
			WHERE domain='".$domain."'";
	if (!mysqli_query($link, $UpdateDomainData)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_modified'], htmlspecialchars($domain))
	);
}
function mailbox_edit_domainadmin($link, $postarray) {
	global $lang;
	$username		= mysqli_real_escape_string($link, $postarray['username']);
	$password		= mysqli_real_escape_string($link, $postarray['password']);
	$password2		= mysqli_real_escape_string($link, $postarray['password2']);
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
	};
	if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$DeleteDomainAdminsData = "DELETE FROM `domain_admins` WHERE username='".$username."'";
	if (!mysqli_query($link, $DeleteDomainAdminsData)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}
	foreach ($postarray['domain'] as $domain) {
		$InsertDomainAdminsData = "INSERT INTO `domain_admins` (`username`, `domain`, `created`, `active`)
			VALUES ('".$username."', '".$domain."', now(), '".$active."')";
		if (!mysqli_query($link, $InsertDomainAdminsData)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.mysqli_error($link)
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
		$password_hashed = sha512c($password);
		$UpdateAdminData = "UPDATE admin SET modified=now(), active='".$active."', password='".$password_hashed."' WHERE username='".$username."';";
	}
	else {
		$UpdateAdminData = "UPDATE admin SET modified=now(), active='".$active."' where username='".$username."'";
	}
	if (!mysqli_query($link, $UpdateAdminData)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_admin_modified'], htmlspecialchars($username))
	);
}
function mailbox_edit_mailbox($link, $postarray) {
	global $lang;
	isset($postarray['active']) ? $active = '1' : $active = '0';
	$quota_m		= mysqli_real_escape_string($link, $postarray['quota']);
	$quota_b		= $quota_m*1048576;
	$username		= mysqli_real_escape_string($link, $postarray['username']);
	$name			= mysqli_real_escape_string($link, $postarray['name']);
	$password		= $postarray['password'];
	$password2		= $postarray['password2'];
	$MailboxData1	= mysqli_fetch_assoc(mysqli_query($link,
		"SELECT `domain`
			FROM `mailbox`
				WHERE username='".$username."'"));
	$MailboxData2	= mysqli_fetch_assoc(mysqli_query($link,
		"SELECT 
			coalesce(round(sum(quota)/1048576), 0) as quota_m_now 
				FROM `mailbox`
					WHERE username='".$username."'"));
	$MailboxData3	= mysqli_fetch_assoc(mysqli_query($link,
		"SELECT 
			coalesce(round(sum(quota)/1048576), 0) as quota_m_in_use
				FROM `mailbox`
					WHERE domain='".$MailboxData1['domain']."'"));
	$DomainData		= mysqli_fetch_assoc(mysqli_query($link,
		"SELECT `quota`, `maxquota`
			FROM `domain`
				WHERE domain='".$MailboxData1['domain']."'"));
	
	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $MailboxData1['domain'])) {
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
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if ($quota_m > $DomainData['maxquota']) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_exceeded'], $quota_m, $DomainData['maxquota'])
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
	$DeleteSenderAclData = "DELETE FROM sender_acl
		WHERE logged_in_as='".$username."';";
	if (!mysqli_query($link, $DeleteSenderAclData)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
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
			$InsertSenderAclData = "INSERT INTO `sender_acl`
				(`send_as`, `logged_in_as`)
					VALUES ('".$sender_acl."', '".$username."')";
			if (!mysqli_query($link, $InsertSenderAclData)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.mysqli_error($link)
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
		$password_hashed = sha512c($password);
		$UpdateMailboxArray = array(
			"UPDATE `alias` SET
				`modified`=NOW(),
				`active`='".$active."'
					WHERE `address`='".$username."'",
			"UPDATE mailbox SET
				`modified`=NOW(),
				`active`='".$active."',
				`password`='".$password_hashed."',
				`name`='".utf8_decode($name)."',
				`quota`='".$quota_b."'
					WHERE username='".$username."'"
		);
		foreach ($UpdateMailboxArray as $UpdateUserData) {
			if (!mysqli_query($link, $UpdateUserData)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.mysqli_error($link)
				);
				return false;
			}
		}
		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['mailbox_modified'], $username)
		);
		return true;
	}
	$UpdateMailboxArray = array(
		"UPDATE `alias` SET
			`modified`=NOW(),
			`active`='".$active."'
				WHERE address='".$username."'",
		"UPDATE `mailbox` SET
			`modified`=NOW(),
			`active`='".$active."',
			`name`='".utf8_decode($name)."',
			`quota`='".$quota_b."'
				WHERE `username`='".$username."'"
	);
	foreach ($UpdateMailboxArray as $UpdateUserData) {
		if (!mysqli_query($link, $UpdateUserData)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.mysqli_error($link)
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function mailbox_delete_domain($link, $domain) {
	global $lang;
	$domain 	= mysqli_real_escape_string($link, strtolower(trim($domain)));
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
	$MailboxString = "SELECT `username` FROM `mailbox`
		WHERE domain='".$domain."';";
	$MailboxResult = mysqli_query($link, $MailboxString)
		OR die(mysqli_error($link));
	$MailboxCount = mysqli_num_rows($MailboxResult);
	if ($MailboxCount != 0 || !empty($MailboxCount)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_not_empty'])
		);
		return false;
	}
	$DeleteDomainArray = array(
		"DELETE FROM domain WHERE domain='".$domain."';",
		"DELETE FROM domain_admins WHERE domain='".$domain."';",
		"DELETE FROM alias WHERE domain='".$domain."';",
		"DELETE FROM sender_acl WHERE logged_in_as LIKE '%@".$domain."';",
		"DELETE FROM quota2 WHERE username LIKE '%@".$domain."';",
		"DELETE FROM alias_domain WHERE target_domain='".$domain."';",
		"DELETE FROM mailbox WHERE domain='".$domain."';",
		"DELETE FROM userpref WHERE username LIKE '%@".$domain."';",
		"DELETE FROM spamalias WHERE address LIKE '%@".$domain."';",
		"DELETE FROM fugluconfig WHERE scope LIKE '%@".$domain."';"
	);
	foreach ($DeleteDomainArray as $DeleteDomainData) {
		if (!mysqli_query($link, $DeleteDomainData)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.mysqli_error($link)
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_removed'], htmlspecialchars($domain))
	);
	return true;
}
function mailbox_delete_alias($link, $postarray) {
	global $lang;
	$address = mysqli_real_escape_string($link, $postarray['address']);
	$local_part = strstr($address, '@', true);
	$domain = substr(strrchr($address, "@"), 1);
	$gotos = mysqli_fetch_assoc(mysqli_query($link, "SELECT `goto` FROM `alias` WHERE `address`='".$address."'"));
	$goto_array = explode(',', $gotos);
	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$mystring = "DELETE FROM `alias` WHERE `address`='".$address."' AND `address` NOT IN (SELECT `username` FROM `mailbox`)";
	if (!mysqli_query($link, $mystring)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_removed'], htmlspecialchars($address))
	);
}
function mailbox_delete_alias_domain($link, $postarray) {
	global $lang;
	$alias_domain = mysqli_real_escape_string($link, $postarray['alias_domain']);
	if (!is_valid_domain_name($alias_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}
	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $alias_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$DeleteAliasDomain = "DELETE FROM `alias_domain`
		WHERE alias_domain='".$alias_domain."'";
	if (!mysqli_query($link, $DeleteAliasDomain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_domain_removed'], htmlspecialchars($alias_domain))
	);
}
function mailbox_delete_mailbox($link, $postarray) {
	global $lang;
	$username	= mysqli_real_escape_string($link, $postarray['username']);
	$domain		= substr(strrchr($username, "@"), 1);
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$DeleteMailboxArray = array(
		"DELETE FROM `alias` 
			WHERE `goto`='".$username."'",
		"UPDATE `alias` SET
			`goto`=REPLACE(`goto`, ',".$username.",', ',')",
		"UPDATE `alias` SET
			`goto`=REPLACE(`goto`, ',".$username."', '')",
		"UPDATE `alias` SET
			`goto`=REPLACE(`goto`, '".$username.",', '')",
		"DELETE FROM `quota2`
			WHERE `username`='".$username."'",
		"DELETE FROM `mailbox`
			WHERE `username`='".$username."'",
		"DELETE FROM `sender_acl`
			WHERE `logged_in_as`='".$username."'",
		"DELETE FROM `spamalias`
			WHERE `goto`='".$username."'",
		"DELETE FROM `fugluconfig`
			WHERE `scope`='".$username."'",
		"DELETE FROM `userpref`
			WHERE `username`='".$username."'"
	);
	foreach ($DeleteMailboxArray as $DeleteMailbox) {
		if (!mysqli_query($link, $DeleteMailbox)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.mysqli_error($link)
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_removed'], htmlspecialchars($username))
	);
}
function set_admin_account($link, $postarray) {
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$name			= mysqli_real_escape_string($link, $postarray['admin_user']);
	$name_now		= mysqli_real_escape_string($link, $postarray['admin_user_now']);
	$password		= mysqli_real_escape_string($link, $postarray['admin_pass']);
	$password2		= mysqli_real_escape_string($link, $postarray['admin_pass2']);

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
	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = sha512c($password);
		$UpdateAdmin = "UPDATE `admin` SET 
			`modified`=NOW(),
			`password`='".$password_hashed."',
			`username`='".$name."'
				WHERE `username`='".$name_now."'";
		if (!mysqli_query($link, $UpdateAdmin)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.mysqli_error($link)
			);
			return false;
		}
	}
	else {
		$UpdateAdmin = "UPDATE `admin` SET 
			`modified`=NOW(),
			`username`='".$name."'
				WHERE `username`='".$name_now."'";
		if (!mysqli_query($link, $UpdateAdmin)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.mysqli_error($link)
			);
			return false;
		}
	}
	$UpdateDomainAdmins = "UPDATE `domain_admins` SET
		`username`='".$name."',
		`domain`='ALL'
			WHERE username='".$name_now."'";
	if (!mysqli_query($link, $UpdateDomainAdmins)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['admin_modified'])
	);
}
function set_time_limited_aliases($link, $postarray) {
	global $lang;
	$username = $_SESSION['mailcow_cc_username'];
	$domain = substr($username, strpos($username, '@'));
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
			$letters = 'abcefghijklmnopqrstuvwxyz1234567890';
			$random_name = substr(str_shuffle($letters), 0, 16);
			$SelectArray = array(
				"SELECT `username` FROM `mailbox`
					WHERE `username`='".$random_name.$domain."'",
				"SELECT `address` FROM `spamalias`
					WHERE `address`='".$random_name.$domain."'",
				"SELECT `address` FROM `alias`
					WHERE `address`='".$random_name.$domain."'",
			);
			foreach ($SelectArray as $SelectQuery) {
				$SelectCount = mysqli_query($link, $SelectQuery)
					OR die(mysqli_error($link));
				if (mysqli_num_rows($SelectCount) == 0) {
					continue;
				}
				else {
					$_SESSION['return'] = array(
						'type' => 'warning',
						'msg' => sprintf($lang['warning']['spam_alias_temp_error'])
					);
					return false;
				}
			}
			$SelectSpamalias = "SELECT `goto` FROM `spamalias`
				WHERE `goto`='".$username."'";
			$SpamaliasResult = mysqli_query($link, $SelectSpamalias)
				OR die(mysqli_error($link));
			$SpamaliasCount = mysqli_num_rows($SpamaliasResult);
			if ($SpamaliasCount == 20) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['spam_alias_max_exceeded'])
				);
				return false;
			}
			$InsertSpamalias = "INSERT INTO `spamalias`
				(`address`, `goto`, `validity`) VALUES
					('".$random_name.$domain."', '".$username."', DATE_ADD(NOW(), INTERVAL ".$postarray["validity"]." HOUR));";
			if (!mysqli_query($link, $InsertSpamalias)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.mysqli_error($link)
				);
				return false;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
		case "delete":
			$DeleteSpamalias = "DELETE FROM spamalias
				WHERE goto='".$username."'";
			if (!mysqli_query($link, $DeleteSpamalias)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.mysqli_error($link)
				);
				return false;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
		case "extend":
			$UpdateSpamalias = "UPDATE `spamalias` SET
				`validity`=DATE_ADD(validity, INTERVAL 1 HOUR)
					WHERE `goto`='".$username."' 
						AND `validity` >= NOW()";
			if (!mysqli_query($link, $UpdateSpamalias)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.mysqli_error($link)
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
function set_user_account($link, $postarray) {
	global $lang;
	$username			= $_SESSION['mailcow_cc_username'];
	$password_old		= $postarray['user_old_pass'];
	isset($postarray['togglePwNew']) ? $pwnew_active = '1' : $pwnew_active = '0';

	if (isset($pwnew_active) && $pwnew_active == "1") {
		$password_new	= $postarray['user_new_pass'];
		$password_new2	= $postarray['user_new_pass2'];
	}

	if (!check_login($link, $username, $password_old) == "user") {
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
			$password_hashed = sha512c($password_new);
			$UpdateMailboxQuery = "UPDATE mailbox SET modified=NOW(), password='".$password_hashed."' WHERE username='".$username."';";
			if (!mysqli_query($link, $UpdateMailboxQuery)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.mysqli_error($link)
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
function add_domain_admin($link, $postarray) {
	global $lang;
	$username		= mysqli_real_escape_string($link, strtolower(trim($postarray['username'])));
	$password		= mysqli_real_escape_string($link, $postarray['password']);
	$password2		= mysqli_real_escape_string($link, $postarray['password2']);
	isset($postarray['active']) ? $active = '1' : $active = '0';
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
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
	$SelectQueryArray = array(
		"SELECT `username` FROM `mailbox`
			WHERE `username`='".$username."'",
		"SELECT `username` FROM `admin`
			WHERE `username`='".$username."'",
		"SELECT `username` FROM `domain_admins`
			WHERE `username`='".$username."'"
	);
	foreach ($SelectQueryArray as $SelectQuery) {
		$SelectResult = mysqli_query($link, $SelectQuery)
			OR die(mysqli_error($link));
		$SelectCount = mysqli_num_rows($SelectResult);
		if ($SelectCount != 0 || !empty($SelectCount)) {
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
		$password_hashed = sha512c($password);
		$DeleteArray = array(
			"DELETE FROM `domain_admins`
				WHERE `username`='".$username."'",
			"DELETE FROM `admin`
				WHERE `username`='".$username."'",
		);
		foreach ($DeleteArray as $DeleteQuery) {
			if (!mysqli_query($link, $DeleteQuery)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.mysqli_error($link)
				);
				return false;
			}
		}
		foreach ($postarray['domain'] as $domain) {
			$domain = mysqli_real_escape_string($link, $domain);
			if (!is_valid_domain_name($domain)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['domain_invalid'])
				);
				return false;
			}
			$InsertDomainAdminQuery = "INSERT INTO `domain_admins` (`username`, `domain`, `created`, `active`)
				VALUES ('".$username."', '".$domain."', now(), '".$active."')";
			if (!mysqli_query($link, $InsertDomainAdminQuery)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.mysqli_error($link)
				);
				return false;
			}
		}
		$InsertAdminQuery = "INSERT INTO `admin` (`username`, `password`, `superadmin`, `created`, `modified`, `active`)
			VALUES ('".$username."', '".$password_hashed."', '0', now(), now(), '".$active."')";
		if (!mysqli_query($link, $InsertAdminQuery)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.mysqli_error($link)
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
function delete_domain_admin($link, $postarray) {
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$username = mysqli_real_escape_string($link, $postarray['username']);
	if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$DeleteDomainArray = array(
		"DELETE FROM `domain_admins` 
			WHERE `username`='".$username."'",
		"DELETE FROM `admin` 
			WHERE `username`='".$username."'"
	);
	foreach ($DeleteDomainArray as $DeleteDomainQuery) {
		if (!mysqli_query($link, $DeleteDomainQuery)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.mysqli_error($link)
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_admin_removed'], htmlspecialchars($username))
	);
}
function get_spam_score($link, $username) {
	$default		= "5, 15";
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		return $default;
	}
	$username		= mysqli_real_escape_string($link, $username);
	$SelectQuery = "SELECT * FROM `userpref`, `fugluconfig`
		WHERE `username`='".$username."'
		AND `scope`='".$username."'";
	$SelectResult = mysqli_query($link, $SelectQuery)
		OR die(mysqli_error($link));
	$SelectCount = mysqli_num_rows($SelectResult);
	if ($SelectCount == 0 || empty($SelectCount)) {
		return $default;
	}
	else {
		$FugluconfigData = mysqli_fetch_assoc(mysqli_query($link,
			"SELECT `value` FROM `fugluconfig`
				WHERE `option`='highspamlevel'
				AND `scope`='".$username."';"));
		$UserprefData = mysqli_fetch_assoc(mysqli_query($link,
			"SELECT `value` FROM `userpref`
				WHERE `preference`='required_hits'
				AND `username`='".$username."';"));
		return $UserprefData['value'].', '.$FugluconfigData['value'];
	}
}
function set_whitelist($link, $postarray) {
	global $lang;
	$whitelist_from	= mysqli_real_escape_string($link, $postarray['whitelist_from']);
	$username	= $_SESSION['mailcow_cc_username'];
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$username = mysqli_real_escape_string($link, $username);
	if (!ctype_alnum(str_replace(array('@', '.', '-', '*'), '', $whitelist_from))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['whitelist_from_invalid'])
		);
		return false;
	}

	$SelectQuery = "SELECT `username` FROM `userpref`
			WHERE `preference`='whitelist_from'
				AND `username`='".$username."'
				AND `value`='".$whitelist_from."'";
	$SelectCount = mysqli_query($link, $SelectQuery)
		OR die(mysqli_error($link));
	if (mysqli_num_rows($SelectCount) != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['whitelist_exists'])
		);
		return false;
	}

	$insertWhitelist = "INSERT INTO `userpref` (`username`, `preference` ,`value`)
			VALUES ('".$username."', 'whitelist_from', '".$whitelist_from."')";
	if (!mysqli_query($link, $insertWhitelist)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function delete_whitelist($link, $postarray) {
	global $lang;
	$username	= $_SESSION['mailcow_cc_username'];
	$prefid		= mysqli_real_escape_string($link, $postarray['wlid']);
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$username	= mysqli_real_escape_string($link, $username);
	if (!is_numeric($prefid)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['whitelist_from_invalid'])
		);
		return false;
	}
	$deleteWhitelist = "DELETE FROM `userpref` WHERE `username`='".$username."' AND `prefid`='".$prefid."'";
	if (!mysqli_query($link, $deleteWhitelist)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function set_blacklist($link, $postarray) {
	global $lang;
	$username		= $_SESSION['mailcow_cc_username'];
	$blacklist_from	= mysqli_real_escape_string($link, $postarray['blacklist_from']);
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$username	= mysqli_real_escape_string($link, $username);
	if (!ctype_alnum(str_replace(array('@', '.', '-', '*'), '', $blacklist_from))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['blacklist_from_invalid'])
		);
		return false;
	}

	$SelectQuery = "SELECT `username` FROM `userpref`
			WHERE `preference`='blacklist_from'
				AND `username`='".$username."'
				AND `value`='".$blacklist_from."'";
	$SelectCount = mysqli_query($link, $SelectQuery)
		OR die(mysqli_error($link));
	if (mysqli_num_rows($SelectCount) != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['blacklist_exists'])
		);
		return false;
	}

	$insertBlacklist = "INSERT INTO `userpref` (`username`, `preference` ,`value`)
			VALUES ('".$username."', 'blacklist_from', '".$blacklist_from."')";
	if (!mysqli_query($link, $insertBlacklist)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function delete_blacklist($link, $postarray) {
	global $lang;
	$username	= $_SESSION['mailcow_cc_username'];
	$prefid		= mysqli_real_escape_string($link, $postarray['wlid']);
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$username	= mysqli_real_escape_string($link, $username);
	if (!is_numeric($prefid)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['blacklist_from_invalid'])
		);
		return false;
	}
	$deleteBlacklist = "DELETE FROM `userpref` WHERE `username`='".$username."' AND `prefid`='".$prefid."'";
	if (!mysqli_query($link, $deleteBlacklist)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function set_spam_score($link, $postarray) {
	global $lang;
	$username		= $_SESSION['mailcow_cc_username'];
	$lowspamlevel	= explode(',', mysqli_real_escape_string($link, $postarray['score']))[0];
	$highspamlevel	= explode(',', mysqli_real_escape_string($link, $postarray['score']))[1];
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$username		= mysqli_real_escape_string($link, $username);
	if (!is_numeric($lowspamlevel) || !is_numeric($highspamlevel)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$DeleteSpamScoreArray = array(
		"DELETE FROM `fugluconfig` 
			WHERE `scope`='".$username."'
			AND (
				`option`='highspamlevel'
				OR `option`='lowspamlevel'
			)",
		"DELETE FROM `userpref`
			WHERE `username`='".$username."'
			AND preference='required_hits'"
	);
	foreach ($DeleteSpamScoreArray as $DeleteSpamScoreQuery) {
		if (!mysqli_query($link, $DeleteSpamScoreQuery)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.mysqli_error($link)
			);
			return false;
		}
	}
	$InsertSpamScoreArray = array(
		"INSERT INTO `fugluconfig` (`scope`, `section`, `option`, `value`)
			VALUES ('".$username."', 'SAPlugin', 'highspamlevel', '".$highspamlevel."')",
		"INSERT INTO `userpref` (`username`, `preference` ,`value`)
			VALUES ('".$username."', 'required_hits', '".$lowspamlevel."')"
	);
	foreach ($InsertSpamScoreArray as $InsertSpamScoreQuery) {
		if (!mysqli_query($link, $InsertSpamScoreQuery)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.mysqli_error($link)
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function set_tls_policy($link, $postarray) {
	global $lang;
	isset($postarray['tls_in']) ? $tls_in = '1' : $tls_in = '0';
	isset($postarray['tls_out']) ? $tls_out = '1' : $tls_out = '0';
	$username		= $_SESSION['mailcow_cc_username'];
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$username		= mysqli_real_escape_string($link, $username);
	$UpdateTlsPolicyQuery = "UPDATE `mailbox` SET tls_enforce_out='".$tls_out."', tls_enforce_in='".$tls_in."' WHERE `username`='".$username."'";
	if (!mysqli_query($link, $UpdateTlsPolicyQuery)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL Fehler: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function get_tls_policy($link, $username) {
	global $lang;
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$username = mysqli_real_escape_string($link, $username);
	$SelectQuery = "SELECT `tls_enforce_out`, `tls_enforce_in` FROM `mailbox` WHERE `username`='".$username."'";
	$SelectResult = mysqli_query($link, $SelectQuery)
		OR die(mysqli_error($link));
	$SelectData = mysqli_fetch_assoc($SelectResult);
	return $SelectData;
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
