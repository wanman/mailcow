<?php
function check_login($user, $pass, $pfconfig) {
        if(!filter_var($user, FILTER_VALIDATE_EMAIL)) {
                return false;
        }
        $pass = escapeshellcmd($pass);
        include_once($pfconfig);
        $link = mysql_connect('localhost', $CONF['database_user'], $CONF['database_password']);
        mysql_select_db($CONF['database_name']);
        $result = mysql_query("select password from admin where superadmin=1 and username='$user'");
        while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
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
function get_fufix_reject_attachments_toggle() {
    $read_mime_check = file($GLOBALS["fufix_reject_attachments"])[0];
        if (strpos($read_mime_check,'FILTER') !== false) {
            echo "checked";
        } else {
                echo "";
        }
}
function get_fufix_reject_attachments() {
        $read_mime_check = file($GLOBALS["fufix_reject_attachments"])[0];
        preg_match('#\((.*?)\)#', $read_mime_check, $match);
        echo $match[1];
}
function get_fufix_anonymize_toggle() {
        $state = file_get_contents($GLOBALS["fufix_anonymize_headers"]);
        if (!empty($state)) { echo "checked"; } else { return 1; }
}
function get_fufix_sender_access() {
        $state = file($GLOBALS["fufix_sender_access"]);
        foreach ($state as $each) {
                $each_expl = explode("     ", $each);
                echo $each_expl[0], "\n";
        }
}
function set_fufix_sender_access($what) {
        file_put_contents($GLOBALS["fufix_sender_access"], "");
        foreach(preg_split("/((\r?\n)|(\r\n?))/", $what) as $each) {
                if ($each != "" && preg_match("/^[a-zA-Z0-9-\ .@]+$/", $each)) {
                        file_put_contents($GLOBALS["fufix_sender_access"], "$each     REJECT     Sender not allowed".PHP_EOL, FILE_APPEND);
                }
        }
        $sender_map = $GLOBALS["fufix_sender_access"];
        shell_exec("/usr/sbin/postmap $sender_map");
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
?>

