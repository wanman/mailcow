<?php
$database_host = "my_dbhost";
$database_user = "my_mailcowuser";
$database_pass = "my_mailcowpass";
$database_name = "my_mailcowdb";
$IP=$_SERVER['SERVER_ADDR'];
$MC_ANON_HEADERS = "/etc/postfix/mailcow_anonymize_headers.pcre";
$MC_PUB_FOLDER = "/etc/dovecot/mailcow_public_folder.conf";
$MC_ODKIM_TXT = "/etc/opendkim/dnstxt";
$MC_MBOX_BACKUP_ENV = "/var/mailcow/mailbox_backup_env";
$PFLOG = "/var/mailcow/log/pflogsumm.log";
$MYHOSTNAME = exec("/usr/sbin/postconf -h myhostname");
$MYHOSTNAME_0 = explode(".", exec("/usr/sbin/postconf -h myhostname"))[0];
$MYHOSTNAME_1 = explode(".", exec("/usr/sbin/postconf -h myhostname"))[1];
$MYHOSTNAME_2 = explode(".", exec("/usr/sbin/postconf -h myhostname"))[2];
$DAV_SUBDOMAIN = "dav";
$PASS_SCHEME = "SHA512-CRYPT";
?>
