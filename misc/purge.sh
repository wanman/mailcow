#!/bin/bash

echo '
###############
# # WARNING # #
###############
# Use with caution!
# Open and review this script before running it!
# Web server + web root, MySQL server + databases as well as
# your mail directory (/var/vmail) will not be removed.
###############
'
read -p "Type \"confirm\" to continue: " confirminput
[[ $confirminput == "confirm" ]] || exit 0
echo "Please wait..."
service fail2ban stop
service rsyslog stop
service clamav-daemon stop
service clamav-freshclam stop
service spamassassin stop
service dovecot stop
service postfix stop
update-rc.d -f fail2ban remove
systemctl disable fail2ban
rm /lib/systemd/system/fail2ban.service
# dovecot purge fails at first
apt-get -y purge php5 python-sqlalchemy python-beautifulsoup python-setuptools \
python-magic php-auth-sasl php-http-request php-mail php-mail-mime php-mail-mimedecode php-net-dime php-net-smtp \
php-net-socket php-net-url php-pear php-soap php5 php5-cli php5-common php5-curl php5-fpm php5-gd php5-imap subversion \
php5-intl php5-mcrypt php5-mysql php5-sqlite dovecot-common dovecot-core \
dovecot-imapd dovecot-lmtpd dovecot-managesieved dovecot-sieve dovecot-mysql dovecot-pop3d postfix \
postfix-mysql postfix-pcre clamav clamav-base clamav-daemon clamav-freshclam spamassassin curl mpack
apt-get -y purge dovecot-imapd dovecot-lmtpd dovecot-managesieved dovecot-pop3d dovecot-sieve
apt-get -y autoremove --purge
apt-get -y purge dovecot-imapd dovecot-lmtpd dovecot-managesieved dovecot-pop3d dovecot-sieve
apt-get -y autoremove --purge
killall -u vmail
userdel vmail
rm -rf /etc/ssl/mail/
rm -rf /etc/spamassassin/
rm -rf /etc/clamav/
rm -rf /etc/dovecot/
rm -rf /etc/postfix/
rm -rf /etc/fail2ban/
rm -rf /etc/sudoers*
rm -rf /etc/php5/
rm -rf /etc/mail/postfixadmin
rm -rf /var/www/mail
rm -rf /var/run/fetchmail
rm -rf /usr/local/lib/python2.7/dist-packages/fail2ban-*
rm -f /usr/local/bin/fail2ban*
rm -f /etc/init.d/fail2ban
rm -rf /var/run/fail2ban/
rm -f /var/log/fail2ban.log
cat /dev/null > /var/log/mail.warn
cat /dev/null > /var/log/mail.err
cat /dev/null > /var/log/mail.info
cat /dev/null > /var/log/mail.log
rm -rf /var/lib/fail2ban/
rm -rf /var/lib/dovecot/
rm -f /var/log/mail*1
rm -f /var/log/mail*gz
rm -rf /var/log/clamav/
rm -rf /opt/vfilter/
rm -f /etc/cron.d/pfadminfetchmail
rm -f /etc/cron.daily/spam*
rm -rf /etc/opendkim*
rm -f /usr/local/bin/fufix_msg_size
