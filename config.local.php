<?php
$CONF['configured'] = true;
$CONF['setup_password'] = 'changeme';
$CONF['default_language'] = 'de';
$CONF['database_user'] = 'my_postfixuser';
$CONF['database_password'] = 'my_postfixpass';
$CONF['database_name'] = 'my_postfixdb';
$CONF['admin_email'] = 'mailer@domain.tld';
$CONF['default_aliases'] = array (
    'abuse' => 'abuse@domain.tld',
    'hostmaster' => 'hostmaster@domain.tld',
    'postmaster' => 'postmaster@domain.tld',
    'webmaster' => 'webmaster@domain.tld'
);
$CONF['aliases'] = '10240';
$CONF['mailboxes'] = '10240';
$CONF['maxquota'] = '10240';
$CONF['quota'] = 'YES';
$CONF['backup'] = 'YES';
$CONF['fetchmail'] = 'NO';
$CONF['show_footer_text'] = 'NO';
$CONF['used_quotas'] = 'YES';
?>
