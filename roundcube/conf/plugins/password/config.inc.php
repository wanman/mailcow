<?php
$config['password_driver'] = 'sql';
$config['password_db_dsn'] = 'mysql://my_postfixuser:my_postfixpass@localhost/my_postfixdb';
$config['password_query'] = 'UPDATE mailbox SET password=%c WHERE username=%u';
$config['password_dovecotpw'] = '/usr/bin/doveadm pw';
$config['password_dovecotpw_method'] = 'MD5-CRYPT';
$config['password_dovecotpw_with_method'] = false;
$config['password_confirm_current'] = true;
$config['password_minimum_length'] = 8;
$config['password_require_nonalpha'] = false;
$config['password_log'] = false;
$config['password_login_exceptions'] = null;
$config['password_hosts'] = null;
$config['password_force_save'] = false;

