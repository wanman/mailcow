<?php
$IP=$_SERVER['SERVER_ADDR'];
if ( false === filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ) {
	$IP="YOUR.IP.V.4";
}
$fufix_anonymize_headers = "/etc/postfix/fufix_anonymize_headers.pcre";
$fufix_reject_attachments = "/etc/postfix/fufix_reject_attachments.regex";
$fufix_sender_access = "/etc/postfix/fufix_sender_access";
$fufix_opendkim_dnstxt_folder = "/etc/opendkim/dnstxt";
$VT_API_KEY = "/var/www/VT_API_KEY";
$VT_ENABLE_UPLOAD = "/var/www/VT_ENABLE_UPLOAD";
$MYHOSTNAME=exec("/usr/sbin/postconf -h myhostname");
$MYHOSTNAME_0=explode(".", exec("/usr/sbin/postconf -h myhostname"))[0];
$MYHOSTNAME_1=explode(".", exec("/usr/sbin/postconf -h myhostname"))[1];
$MYHOSTNAME_2=explode(".", exec("/usr/sbin/postconf -h myhostname"))[2];
?>
