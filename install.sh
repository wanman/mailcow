#!/bin/bash
if [ "$EUID" -ne 0 ]
  then echo "Please run as root"
  exit
fi

cat includes/banner
source includes/versions
source includes/functions.sh

echo $fufix_banner

case $1 in
	"-u" | "--upgrade" )
		upgradetask
		echo $fufix_version > /etc/fufix_version
echo --------------------------------- >> installer.log
echo UPGRADE to $fufix_version on $(date) >> installer.log
echo --------------------------------- >> installer.log
echo Fail2ban version: $fail2ban_version >> installer.log
echo Postfixadmin Revision: $postfixadmin_revision >> installer.log
echo Roundcube version: $roundcube_version >> installer.log
echo --------------------------------- >> installer.log
		exit 0
		;;
	"-h" | "--help" )
		usage
		exit 0
        ;;
esac

checksystem
checkports
source configuration
checkconfig

echo "    $(textb "Hostname")        $sys_hostname
    $(textb "Domain")          $sys_domain
    $(textb "FQDN")            $sys_hostname.$sys_domain
    $(textb "Timezone")        $sys_timezone
    $(textb "Postfix MySQL")   ${my_postfixuser}:hidden/${my_postfixdb}
    $(textb "Roundcube MySQL") ${my_rcuser}:hidden/${my_rcdb}
    $(textb "Postfixadmin")    ${pfadmin_adminuser}
"

returnwait "Reading configuration" "System environment"

echo --------------------------------- > installer.log
echo MySQL Postfix database: $my_postfixdb >> installer.log
echo MySQL Postfix username: $my_postfixuser >> installer.log
echo MySQL Postfix password: $my_postfixpass >> installer.log
echo --------------------------------- >> installer.log
echo MySQL Roundcube database: $my_rcdb >> installer.log
echo MySQL Roundcube username: $my_rcuser >> installer.log
echo MySQL Roundcube password: $my_rcpass >> installer.log
echo --------------------------------- >> installer.log
echo Only set when MySQL was not available >> installer.log
echo MySQL root password: $my_rootpw >> installer.log
echo --------------------------------- >> installer.log
echo Postfixadmin Administrator >> installer.log
echo Username: $pfadmin_adminuser >> installer.log
echo Password: $pfadmin_adminpass >> installer.log
echo --------------------------------- >> installer.log
echo FQDN: $sys_hostname.$sys_domain >> installer.log
echo Timezone: $sys_timezone >> installer.log
echo --------------------------------- >> installer.log
echo Fail2ban version: $fail2ban_version >> installer.log
echo Postfixadmin Revision: $postfixadmin_revision >> installer.log
echo Roundcube version: $roundcube_version >> installer.log
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
returnwait "Dovecot configuration" "vfilter configuration"

installtask vfilter
returnwait "vfilter configuration" "ClamAV configuration"

installtask clamav
returnwait "ClamAV configuration" "Spamassassin configuration"

installtask spamassassin
returnwait "Spamassassin configuration" "Webserver configuration"

installtask webserver
returnwait "Nginx configuration" "Postfixadmin configuration"

installtask postfixadmin
returnwait "Postfixadmin configuration" "Roundcube configuration"

installtask roundcube
returnwait "Roundcube configuration" "Rsyslogd configuration"

installtask rsyslogd
returnwait "Rsyslogd configuration" "Fail2ban configuration"

installtask fail2ban
returnwait "Fail2ban configuration" "OpenDKIM configuration"

installtask opendkim
returnwait "OpenDKIM configuration" "Restarting services"

installtask restartservices
returnwait "Restarting services" "Completing Postfixadmin setup"

installtask setupsuperadmin
returnwait "Completing Postfixadmin setup" "Checking DNS settings"

installtask checkdns
returnwait "Checking DNS settings" "Finish installation"

echo $fufix_version > /etc/fufix_version
chmod 600 installer.log
echo
echo "`tput setaf 2`Finished installation`tput sgr0`"
echo "Logged credentials and further information to file `tput bold`installer.log`tput sgr0`."
echo
echo "Next steps:"
echo " * Backup installer.log to a safe place and delete it"
echo " * Open \"https://$sys_hostname.$sys_domain\" and login to Postfixadmin as Postfix Administrator to create a domain and a mailbox."
echo " * Please do not use Port 25 in your mail client, use Port 587 instead."
echo
