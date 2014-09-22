#!/bin/bash
if [ "$EUID" -ne 0 ]
  then echo "Please run as root"
  exit
fi

cat includes/banner
source includes/versions
source includes/functions.sh
source configuration

echo $fufix_banner

checkconfig
checkports

echo "`tput bold`Please review your configuration`tput sgr0`
(Passwords will not be displayed)
----------------------------------------------
FQDN: $sys_hostname.$sys_domain
Timezone: $sys_timezone

Postfix MySQL database name: $my_postfixdb
Postfix MySQL database user: $my_postfixuser

Postfixadmin Superuser: $pfadmin_adminuser
----------------------------------------------
"
read -p "Press ENTER to continue or CTRL+C to cancel installation"

echo --------------------------------- > installer.log
echo MySQL database name: $my_postfixdb >> installer.log
echo MySQL username $my_postfixuser >> installer.log
echo MySQL password $my_postfixpass >> installer.log
echo MySQL root password: $my_rootpw >> installer.log
echo --------------------------------- >> installer.log
echo Postfixadmin Superuser >> installer.log
echo Username: $pfadmin_adminuser >> installer.log
echo Password: $pfadmin_adminpass >> installer.log
echo --------------------------------- >> installer.log
echo FQDN: $sys_hostname.$sys_domain >> installer.log
echo Timezone: $sys_timezone >> installer.log
echo --------------------------------- >> installer.log
echo FuGlu version: $fuglu_version >> installer.log
echo Fail2ban version: $fail2ban_version >> installer.log
echo Postfixadmin Revision: $postfixadmin_revision >> installer.log
echo --------------------------------- >> installer.log


installtask environment
returnwait "System environment" "Package installation"

installtask installpackages
returnwait "Package installation" "Self-signed certificate"

installtask ssl
returnwait "Self-signed certificate" "MySQL configuration"

installtask mysql
returnwait "MySQL configuration" "FuGlu setup"

installtask fuglu
returnwait "FuGlu setup" "Postfix configuration"

installtask postfix
returnwait "Postfix configuration" "Dovecot configuration"

installtask dovecot
returnwait "Dovecot configuration" "ClamAV configuration"

installtask clamav
returnwait "ClamAV configuration" "Spamassassin configuration"

installtask spamassassin
returnwait "Spamassassin configuration" "Nginx configuration"

installtask webserver
returnwait "Nginx configuration" "Postfixadmin configuration"

installtask postfixadmin
returnwait "Postfixadmin configuration" "Fail2ban configuration"

installtask fail2ban
returnwait "Fail2ban configuration" "Rsyslogd configuration"

installtask rsyslogd
returnwait "Rsyslogd configuration" "Restarting services"

installtask restartservices
returnwait "Restarting services" "Completing Postfixadmin setup"

installtask setupsuperadmin
returnwait "Completing Postfixadmin setup" "Checking DNS settings"

installtask checkdns
returnwait "Checking DNS settings" "Finish installation"

chmod 600 installer.log
echo
echo "`tput setaf 2`Finished installation`tput sgr0`"
echo "Logged credentials and further information to file `tput bold`installer.log`tput sgr0`."
echo
echo "Next steps:"
echo " * Backup installer.log to a safe place and delete it"
echo " * Open \"https://$sys_hostname.$sys_domain\", select \"System Settings\" and login as Postfixadmin Superuser to create a mailbox."
echo
echo "Configure your mail client as follows:"
echo "  - Incoming: IMAP, server \"$sys_hostname.$sys_domain\", Port 143, STARTTLS, Password/Normal"
echo "    You can use SSL instead of STARTTLS on Port 993 if you like to."
echo "  - Outgoing: SMTP, server \"$sys_hostname.$sys_domain\", Port 587, STARTTLS, Password/Normal"
echo "Use your full mail adress (your.username@$sys_domain) as username for incoming and outgoing mail."
