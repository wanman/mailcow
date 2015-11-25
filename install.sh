#!/bin/bash
if [ "$EUID" -ne 0 ]
  then echo "Please run as root"
  exit
fi

cat includes/banner
source includes/versions
source includes/functions.sh

while getopts H:D: par; do
case $par in
    H) sys_hostname="$OPTARG" ;;
    D) sys_domain="$OPTARG" ;;
esac
done

case ${1} in
	"-u" | "--upgrade" | "-uu" | "--upgrade-unattended" )
		[[ ${1} == "-uu" || ${1} == "--upgrade-unattended" ]] && inst_unattended="yes"
		upgradetask
		echo ${mailcow_version} > /etc/mailcow_version
echo --------------------------------- >> installer.log
echo UPGRADE to ${mailcow_version} on $(date) >> installer.log
echo --------------------------------- >> installer.log
echo Fail2ban version: ${fail2ban_version} >> installer.log
echo Roundcube version: ${roundcube_version} >> installer.log
echo FuGlu version: ${fuglu_version} >> installer.log
echo --------------------------------- >> installer.log
		exit 0
		;;
	"-h" | "--help" )
		usage
		exit 0
        ;;
esac

source mailcow.config
checksystem
checkports
checkconfig

echo "    $(textb "Hostname")            ${sys_hostname}
    $(textb "Domain")              ${sys_domain}
    $(textb "FQDN")                ${sys_hostname}.${sys_domain}
    $(textb "Timezone")            ${sys_timezone}
    $(textb "mailcow MySQL")       ${my_mailcowuser}:${my_mailcowpass}@${my_dbhost}/${my_mailcowdb}
    $(textb "Roundcube MySQL")     ${my_rcuser}:${my_rcpass}@${my_dbhost}/${my_rcdb}
    $(textb "mailcow admin user")  ${mailcow_admin_user}
"

returnwait "Reading configuration" "System environment"

echo --------------------------------- > installer.log
echo MySQL database host: ${my_dbhost}  >> installer.log
echo --------------------------------- >> installer.log
echo Use existing database: ${my_useexisting}  >> installer.log
echo --------------------------------- >> installer.log
echo MySQL mailcow database: ${my_mailcowdb} >> installer.log
echo MySQL mailcow username: ${my_mailcowuser} >> installer.log
echo MySQL mailcow password: ${my_mailcowpass} >> installer.log
echo --------------------------------- >> installer.log
echo MySQL Roundcube database: ${my_rcdb} >> installer.log
echo MySQL Roundcube username: ${my_rcuser} >> installer.log
echo MySQL Roundcube password: ${my_rcpass} >> installer.log
echo --------------------------------- >> installer.log
echo Only set when MySQL was not available >> installer.log
echo MySQL root password: ${my_rootpw} >> installer.log
echo --------------------------------- >> installer.log
echo mailcow administrator >> installer.log
echo Username: ${mailcow_admin_user} >> installer.log
echo Password: ${mailcow_admin_pass} >> installer.log
echo --------------------------------- >> installer.log
echo FQDN: ${sys_hostname}.${sys_domain} >> installer.log
echo Timezone: ${sys_timezone} >> installer.log
echo --------------------------------- >> installer.log
echo Web root: https://${sys_hostname}.${sys_domain} >> installer.log
echo DAV web root: https://${httpd_dav_subdomain}.${sys_domain} >> installer.log
echo Autodiscover \(Z-Push\): https://autodiscover.${sys_domain} >> installer.log
echo --------------------------------- >> installer.log
echo Fail2ban version: $fail2ban_version >> installer.log
echo Roundcube version: $roundcube_version >> installer.log
echo FuGlu version: ${fuglu_version} >> installer.log
echo mailcow version: ${mailcow_version} >> installer.log
echo --------------------------------- >> installer.log

installtask environment
returnwait "System environment" "Package installation"

installtask installpackages
returnwait "Package installation" "Self-signed certificate"

installtask ssl
returnwait "Self-signed certificate" "MySQL configuration"

installtask mysql
returnwait "MySQL configuration" "Postfix configuration"

installtask postfix
returnwait "Postfix configuration" "Dovecot configuration"

installtask dovecot
returnwait "Dovecot configuration" "FuGlu configuration"

installtask fuglu
returnwait "FuGlu configuration" "ClamAV configuration"

installtask clamav
returnwait "ClamAV configuration" "Spamassassin configuration"

installtask spamassassin
returnwait "Spamassassin configuration" "Webserver configuration"

installtask webserver
returnwait "Webserver configuration" "Roundcube configuration"

installtask roundcube
returnwait "Roundcube configuration" "Rsyslogd configuration"

installtask rsyslogd
returnwait "Rsyslogd configuration" "Fail2ban configuration"

installtask fail2ban
returnwait "Fail2ban configuration" "OpenDKIM configuration"

installtask opendkim
returnwait "OpenDKIM configuration" "Restarting services"

installtask restartservices
returnwait "Restarting services" "Checking DNS settings"

installtask checkdns
returnwait "Checking DNS settings" "Finish installation"

echo ${mailcow_version} > /etc/mailcow_version
chmod 600 installer.log
echo
echo "`tput setaf 2`Finished installation`tput sgr0`"
echo "Logged credentials and further information to file `tput bold`installer.log`tput sgr0`."
echo
echo "Next steps:"
echo " * Backup installer.log to a safe place and delete it from your server"
echo " * Open \"https://$sys_hostname.$sys_domain\" and login to mailcow control center as $mailcow_admin_user to create a domain and a mailbox. Please use the full URL and not your IP address."
echo " * Please do not use port 25 in your mail client, use port 587 instead."
echo " * Setup SPF records!"
echo " * You may or may not see some information about your domains DNS. SRV records are not necessarily needed. Please see the wiki for help @ https://github.com/andryyy/mailcow/wiki"
echo
