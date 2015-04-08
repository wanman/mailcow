<?php
error_reporting(E_ERROR);
require_once "/var/www/mail/rc/config/config.inc.php";
require_once "/var/www/mail/pfadmin/config.local.php";

echo $CONF['database_user'], PHP_EOL;
echo $CONF['database_password'], PHP_EOL;
echo $CONF['database_name'], PHP_EOL;

echo $config["db_dsnw"], PHP_EOL;
echo $config["des_key"], PHP_EOL;
?>

