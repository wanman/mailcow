<?php
error_reporting(E_ERROR);
require_once "/var/www/mail/rc/config/config.inc.php";
require_once "/var/www/mail/inc/vars.inc.php";

echo $database_user, PHP_EOL;
echo $database_pass, PHP_EOL;
echo $database_name, PHP_EOL;

echo $config["des_key"], PHP_EOL;
echo parse_url($config["db_dsnw"])[user], PHP_EOL;
echo parse_url($config["db_dsnw"])[pass], PHP_EOL;
echo substr(parse_url($config["db_dsnw"])[path], 1), PHP_EOL;
?>

