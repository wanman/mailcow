<?php
$config = array();
$config['db_dsnw'] = 'mysql://my_rcuser:my_rcpass@localhost/my_rcdb';
$config['default_host'] = '127.0.0.1';
$config['smtp_server'] = '127.0.0.1';
$config['smtp_port'] = 10029;
$config['smtp_user'] = '%u';
$config['smtp_pass'] = '%p';
$config['support_url'] = '';
$config['product_name'] = $_SERVER['HTTP_HOST'];
$config['des_key'] = 'conf_rcdeskey';
$config['plugins'] = array(
    'archive',
    'zipdownload',
	'managesieve',
	'password',
	'attachment_reminder',
	'new_user_dialog',
);
$config['skin'] = 'larry';
$config['login_autocomplete'] = 2;
$config['imap_cache'] = 'apc';
$config['username_domain'] = '%d';

