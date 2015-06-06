<?php
$database_host = "localhost";
$database_user = "my_postfixuser";
$database_pass = "my_postfixpass";
$database_name = "my_postfixdb";
// if NAT or IPv6
if (isset($_SERVER['SERVER_ADDR'])) {
	$IP=$_SERVER['SERVER_ADDR'];
}
else {
	$IP="";
}
if (!filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
	$IP="YOUR.IP.V.4";
}
elseif (!filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
	$IP="YOUR.IP.V.4";
}
$mailcow_anonymize_headers = "/etc/postfix/mailcow_anonymize_headers.pcre";
$mailcow_reject_attachments = "/etc/postfix/mailcow_reject_attachments.regex";
$mailcow_sender_access = "/etc/postfix/mailcow_sender_access";
$mc_mailbox_backup = "/var/www/MAILBOX_BACKUP";
$mailcow_opendkim_dnstxt_folder = "/etc/opendkim/dnstxt";
$VT_API_KEY = "/var/www/VT_API_KEY";
$VT_ENABLE = "/var/www/VT_ENABLE";
$CAV_ENABLE = "/var/www/CAV_ENABLE";
$VT_ENABLE_UPLOAD = "/var/www/VT_ENABLE_UPLOAD";
$MYHOSTNAME=exec("/usr/sbin/postconf -h myhostname");
$MYHOSTNAME_0=explode(".", exec("/usr/sbin/postconf -h myhostname"))[0];
$MYHOSTNAME_1=explode(".", exec("/usr/sbin/postconf -h myhostname"))[1];
$MYHOSTNAME_2=explode(".", exec("/usr/sbin/postconf -h myhostname"))[2];
?>
