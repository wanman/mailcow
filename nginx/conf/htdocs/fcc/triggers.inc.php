<?php
if (isset($_POST["login_user"]) && isset($_POST["pass_user"])) {
        if (check_login($_POST["login_user"], $_POST["pass_user"], "/var/www/mail/pfadmin/config.local.php") == true) { $_SESSION['fufix_cc_loggedin'] = "yes"; }
}

if ($_SESSION['fufix_cc_loggedin'] == "yes") {
        if (isset($_POST["vtapikey"]) && ctype_alnum($_POST["vtapikey"])) {
                file_put_contents($VT_API_KEY, $_POST["vtapikey"]);
        }
        if (isset($_POST["sender"])) {
                set_fufix_sender_access($_POST["sender"]);
                postfix_reload();
        }
        if (isset($_POST["ext"])) {
                if (isset($_POST["virustotaltoggle"]) && $_POST["virustotaltoggle"] == "on") {
                        set_fufix_reject_attachments($_POST["ext"], "filter");
                } else {
                        set_fufix_reject_attachments($_POST["ext"], "reject");
                }
                postfix_reload();
        }
        if (isset($_POST["anonymize_"])) {
                if (!isset($_POST["anonymize"])) { $_POST["anonymize"] = ""; }
                set_fufix_anonymize_headers($_POST["anonymize"]);
                postfix_reload();
        }
        if (isset($_POST["logout"])) {
                $_SESSION['fufix_cc_loggedin'] = "no";
        }
        if (isset($_POST["backupdl"])) {
                shell_exec("sudo /bin/tar -cvjf /tmp/backup_vmail.tar.bz2 /var/vmail/");
                $file = '/tmp/backup_vmail.tar.bz2';
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename='.basename($file));
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file));
                readfile($file);
                exit;
        }
}
?>

