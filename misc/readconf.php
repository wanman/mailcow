<?php
// No empty spaces to prevent Bash errors
error_reporting(0);
if (file_exists("/var/www/mail/inc/vars.inc.php")) {
include "/var/www/mail/inc/vars.inc.php";
echo $database_host, PHP_EOL;
echo $database_user, PHP_EOL;
echo $database_pass, PHP_EOL;
echo $database_name, PHP_EOL;
}
if (file_exists("/var/www/mail/rc/config/config.inc.php")){
include "/var/www/mail/rc/config/config.inc.php";
echo parse_url($config["db_dsnw"])[user], PHP_EOL;
echo parse_url($config["db_dsnw"])[pass], PHP_EOL;
echo substr(parse_url($config["db_dsnw"])[path], 1), PHP_EOL;
}
?>
