<?php
$CONF['configured'] = true;
$CONF['setup_password'] = 'changeme';
$CONF['default_language'] = 'en';
$CONF['database_user'] = 'my_postfixuser';
$CONF['database_password'] = 'my_postfixpass';
$CONF['database_name'] = 'my_postfixdb';
$CONF['encrypt'] = 'dovecot:SHA512-CRYPT';
$CONF['dovecotpw'] = "/usr/bin/doveadm pw";
$CONF['admin_email'] = 'noreply@domain.tld';
$CONF['default_aliases'] = ''
$CONF['aliases'] = '200';
$CONF['mailboxes'] = '200';
$CONF['maxquota'] = '10240';
$CONF['domain_quota_default'] = '20480';
$CONF['quota_multiplier'] = '1048576';
$CONF['quota'] = 'YES';
$CONF['backup'] = 'YES';
$CONF['fetchmail'] = 'YES';
$CONF['show_footer_text'] = 'NO';
$CONF['used_quotas'] = 'YES';
$CONF['emailcheck_resolve_domain'] = 'NO';
?>
