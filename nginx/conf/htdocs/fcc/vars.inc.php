<?php
$mailname = $line = file('/etc/mailname')[0];
$mailname = str_replace(array("\n", "\r"), '', $mailname);
$fufix_anonymize_headers = "/etc/postfix/fufix_anonymize_headers.pcre";
$fufix_reject_attachments = "/etc/postfix/fufix_reject_attachments.regex";
$fufix_sender_access = "/etc/postfix/fufix_sender_access";
$fufix_opendkim_dnstxt_folder = "/etc/opendkim/dnstxt";
$VT_API_KEY = "/var/www/VT_API_KEY";
?>
