#!/bin/bash
if [ "$EUID" -ne 0 ]
  then echo "Please run as root"
  exit
fi
echo  '
                    .hddddddh
                   `sdddddddd+
        `//:       .dddddddddd-`      -//`
      `+hdddyo/./syyyso++++osyhhs/.:oyddddo`
    `+hddddddddy+:.` `......` `.:ohdddddddddo
    `sddddddh+. `-+shhddddddhys/-` -ohdddddds
     `ydddd+` .+hddddddddddddddddh+. .+ddddy`
       ddy- .oddddddddddddddddddddddo` -ydd
      /dy` :hddddddddddddddddddddddddh- `yd.
    `.hy. -dddddhhhhddddddddddhhhhddddh- .hh.`
/ssyddd/ `yddd+.    `:sddddy/`    ./hddy` +dddyss/
ddddddd` :ddd/  .+o:   yddh.  -++-  -hdd: .ddddddd
ddddddh` /ddh-  /ddh.  oddy   sddo  `hdd/ `ddddddd
ddddddd` :ddh-   .-`.::oyys::``..   `hdd: .ddddddd
/osdddd/ .ydh-      `/oooooo/       `hdy` /ddddso/
   ``+dy. :dh-       `:oooo:        `hd- .hh/``
      /ds` :y-         :oo-         `s- `yd/
       sdy. ..          ..          `` -yds
     `ydddh+`                        `+ddddy`
    `sddddddh+.                    .+hdddddds`
    `+hddddddddy+-`            `:+yddddddddh+`
      `+hddddy+/+ydhso++//++oyhdy+/+yddddh+`
        `++-`     `-dddddddddd-`     `-+o`
                   `sdddddddd+
                    :dddddddh.'
echo
echo

genpasswd() {
# While loop to make sure a password with at least 3 numbers is generated
# Replaced alnum by A-Za-z0-9 due to unexpected behaviour when LC_ALL is not C.
count=0
while [ $count -lt 3 ]
do
pw_valid=$(tr -cd A-Za-z0-9 < /dev/urandom | fold -w22 | head -n1)
count=$(grep -o "[0-9]" <<< $pw_valid | wc -l)
done
echo $pw_valid
}

####################################
########### CONFIG START ###########
####################################

sys_hostname="mail"
sys_domain="domain.com"
sys_timezone="Europe/Berlin"

my_postfixdb="postfixdb"
my_postfixuser="postfix"
my_postfixpass=`genpasswd`
my_rootpw=`genpasswd`

pfadmin_adminuser="pfadmin@$sys_domain"
pfadmin_adminpass=`genpasswd`

cert_country="DE"
cert_state="NRW"
cert_city="DUS"
cert_org="MAIL"

####################################
############ CONFIG END ############
####################################
#### do not edit any line below ####
####################################

[[ ! -z `ss -lnt | awk '$1 == "LISTEN" && $4 ~ ":25" || $4 ~ ":143" || $4 ~ ":993" || $4 ~ ":587" || $4 ~ ":485" || $4 ~ ":80" || $4 ~ ":443" || $4 ~ ":995"'` ]] && { echo "`tput setaf 1``tput bold`Please remove any mail and web services before running this script.`tput sgr0`"; echo "(Dovecot, Postfix, Sendmail, Apache2, Nginx etc.)"; exit 1; }

function returnwait {
echo "`tput setaf 4``tput bold`$1`tput sgr0` - `tput setaf 2``tput bold`[OK]`tput sgr0`";
read -p "Press ENTER to continue or CTRL+C to cancel installation"
}


fuglu_version="0.6.2"
fail2ban_version="0.9.0a2-800"
postfixadmin_revision="1689"

echo "
---------------------------------
Please review your configuration
---------------------------------
Passwords will not be displayed
---------------------------------
FQDN: $sys_hostname.$sys_domain
Timezone: $sys_timezone
---------------------------------
Postfix MySQL database name: $my_postfixdb
Postfix MySQL database user: $my_postfixuser
---------------------------------
Postfixadmin Superuser: $pfadmin_adminuser
---------------------------------"
read -p "Press ENTER to continue or CTRL+C to cancel installation"

# log generated data
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


# set hostname
function systemenvironment {
getpublicipv4=`wget -q4O- ip4.telize.com`
if [[ $getpublicipv4 =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
cat > /etc/hosts<<'EOF'
127.0.0.1 localhost
::1 localhost ip6-localhost ip6-loopback
ff02::1 ip6-allnodes
ff02::2 ip6-allrouters
EOF
echo $getpublicipv4 $sys_hostname.$sys_domain $sys_hostname >> /etc/hosts
echo $sys_hostname > /etc/hostname
# need to trigger this pseudo service now
service hostname.sh start
else
echo "`tput bold`WARNING`tput sgr0`: Cannot set your hostname"
fi
if [[ -f /usr/share/zoneinfo/$sys_timezone ]] ; then
echo $sys_timezone > /etc/timezone
dpkg-reconfigure -f noninteractive tzdata
else
echo "`tput bold`WARNING`tput sgr0`: Cannot set your timezone: timezone is unknown";
fi
}

# installation
function installpackages {
echo "Installing packages unattended, please stand by, errors will be reported."
DEBIAN_FRONTEND=noninteractive apt-get --force-yes -y install dnsutils python-sqlalchemy python-beautifulsoup python-setuptools \
python-magic openssl php-auth-sasl php-http-request php-mail php-mail-mime php-mail-mimedecode php-net-dime php-net-smtp \
php-net-socket php-net-url php-pear php-soap php5 php5-cli php5-common php5-curl php5-fpm php5-gd php5-imap subversion \
php5-intl php5-mcrypt php5-mysql php5-sqlite mysql-client mysql-server nginx dovecot-common dovecot-core mailutils \
dovecot-imapd dovecot-lmtpd dovecot-managesieved dovecot-sieve dovecot-mysql dovecot-pop3d postfix \
postfix-mysql postfix-pcre clamav clamav-base clamav-daemon clamav-freshclam spamassassin  >/dev/null
}

# certificate
function selfsignedcert {
mkdir /etc/ssl/mail
openssl req -new -newkey rsa:4096 -days 1095 -nodes -x509 -subj "/C=$cert_country/ST=$cert_state/L=$cert_city/O=$cert_org/CN=$sys_hostname.$sys_domain" -keyout /etc/ssl/mail/mail.key  -out /etc/ssl/mail/mail.crt
chmod 600 /etc/ssl/mail/mail.key
}

# mysql
function mysqlconfiguration {
mysql --defaults-file=/etc/mysql/debian.cnf -e "SET PASSWORD FOR root@localhost=PASSWORD(''); DROP DATABASE IF EXISTS $my_postfixdb;"
mysqladmin -u root password $my_rootpw
mysql --defaults-file=/etc/mysql/debian.cnf -e "CREATE DATABASE $my_postfixdb; GRANT ALL PRIVILEGES ON $my_postfixdb.* TO '$my_postfixuser'@'localhost' IDENTIFIED BY '$my_postfixpass';"
}

# fuglu
function fuglusetup {
mkdir /var/log/fuglu 2> /dev/null
groupadd fuglu
useradd -g fuglu -s /bin/false fuglu
usermod -a -G debian-spamd fuglu
usermod -a -G clamav fuglu
rm /tmp/fuglu_control.sock 2> /dev/null
chown fuglu:fuglu /var/log/fuglu
tar xf fuglu/inst/$fuglu_version.tar -C fuglu/inst/ 2> /dev/null
(cd fuglu/inst/$fuglu_version ; python setup.py -q install)
cp -R fuglu/conf/* /etc/fuglu/
cp fuglu/inst/$fuglu_version/scripts/startscripts/debian/7/fuglu /etc/init.d/fuglu
chmod +x /etc/init.d/fuglu
update-rc.d fuglu defaults
rm -rf fuglu/inst/$fuglu_version
}

# postfix
function postfixconfig {
cp -R postfix/conf/* /etc/postfix/
chown root:postfix "/etc/postfix/sql/mysql_virtual_alias_domain_catchall_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_alias_domain_catchall_maps.cf"
chown root:postfix "/etc/postfix/sql/mysql_virtual_alias_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_alias_maps.cf"
chown root:postfix "/etc/postfix/sql/mysql_virtual_alias_domain_mailbox_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_alias_domain_mailbox_maps.cf"
chown root:postfix "/etc/postfix/sql/mysql_virtual_mailbox_limit_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_mailbox_limit_maps.cf"
chown root:postfix "/etc/postfix/sql/mysql_virtual_mailbox_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_mailbox_maps.cf"
chown root:postfix "/etc/postfix/sql/mysql_virtual_alias_domain_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_alias_domain_maps.cf"
chown root:postfix "/etc/postfix/sql/mysql_virtual_domains_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_domains_maps.cf"
chown root:root "/etc/postfix/master.cf"; chmod 644 "/etc/postfix/master.cf"
chown root:root "/etc/postfix/main.cf"; chmod 644 "/etc/postfix/main.cf"
sed -i "s/mail.domain.tld/$sys_hostname.$sys_domain/g" /etc/postfix/* 2> /dev/null
sed -i "s/domain.tld/$sys_domain/g" /etc/postfix/* 2> /dev/null
sed -i "s/my_postfixpass/$my_postfixpass/g" /etc/postfix/sql/*
sed -i "s/my_postfixuser/$my_postfixuser/g" /etc/postfix/sql/*
sed -i "s/my_postfixdb/$my_postfixdb/g" /etc/postfix/sql/*
}

# dovecot
function dovecotconfig {
rm -rf /etc/dovecot/* 2> /dev/null
cp -R dovecot/conf/*.conf /etc/dovecot/
groupadd -g 5000 vmail
useradd -g vmail -u 5000 vmail -d /var/vmail
chown root:dovecot "/etc/dovecot/dovecot-dict-sql.conf"; chmod 640 "/etc/dovecot/dovecot-dict-sql.conf"
chown root:vmail "/etc/dovecot/dovecot-mysql.conf"; chmod 640 "/etc/dovecot/dovecot-mysql.conf"
chown root:root "/etc/dovecot/dovecot.conf"; chmod 644 "/etc/dovecot/dovecot.conf"
sed -i "s/mail.domain.tld/$sys_hostname.$sys_domain/g" /etc/dovecot/*
sed -i "s/domain.tld/$sys_domain/g" /etc/dovecot/*
sed -i "s/my_postfixpass/$my_postfixpass/g" /etc/dovecot/*
sed -i "s/my_postfixuser/$my_postfixuser/g" /etc/dovecot/*
sed -i "s/my_postfixdb/$my_postfixdb/g" /etc/dovecot/*
mkdir -p /var/vmail/sieve
cp dovecot/conf/spam-global.sieve /var/vmail/sieve/spam-global.sieve
cp dovecot/conf/default.sieve /var/vmail/sieve/default.sieve
sievec /var/vmail/sieve/spam-global.sieve
chown -R vmail:vmail /var/vmail
cp dovecot/conf/doverecalcq /etc/cron.daily/; chmod 755 /etc/cron.daily/doverecalcq
}

# clamv
function clamavconfig {
service clamav-daemon stop
service clamav-freshclam stop
freshclam
if [[ -z `cat /etc/clamav/clamd.conf | grep -i -e TCPSocket -e TCPAddr` ]]; then
echo TCPSocket 3310 >> /etc/clamav/clamd.conf
echo TCPAddr 127.0.0.1 >> /etc/clamav/clamd.conf
fi
sed -i '/MaxFileSize/c\MaxFileSize 25M' /etc/clamav/clamd.conf
sed -i '/StreamMaxLength/c\StreamMaxLength 25M' /etc/clamav/clamd.conf
service clamav-freshclam start
service clamav-daemon start
}

# spamassassin
function spamassassinconfig {
sed -i '/rewrite_header/c\rewrite_header Subject [SPAM]' /etc/spamassassin/local.cf
sed -i '/report_safe/c\report_safe 2' /etc/spamassassin/local.cf
sed -i '/^OPTIONS=/s/=.*/="--create-prefs --max-children 5 --helper-home-dir --username debian-spamd --socketpath \/var\/run\/spamd.sock --socketowner debian-spamd --socketgroup debian-spamd"/' /etc/default/spamassassin
sed -i '/^CRON=/s/=.*/="1"/' /etc/default/spamassassin
sed -i '/^ENABLED=/s/=.*/="1"/' /etc/default/spamassassin
}

# nginx, php5
function websrvconfig {
rm -rf /etc/php5/fpm/pool.d/* 2> /dev/null
rm -rf /etc/nginx/{sites-available,sites-enabled}/* 2> /dev/null
cp nginx/conf/sites-available/mail /etc/nginx/sites-available/mail
ln -s /etc/nginx/sites-available/mail /etc/nginx/sites-enabled/mail
cp php5-fpm/conf/mail.conf /etc/php5/fpm/pool.d/mail.conf
sed -i "/date.timezone/c\php_admin_value[date.timezone] = $sys_timezone" /etc/php5/fpm/pool.d/mail.conf
sed -i '/server_tokens/c\server_tokens off;' /etc/nginx/nginx.conf
}

# pfadmin
function pfadminconfig {
rm -rf /usr/share/nginx/mail 2> /dev/null
mkdir -p /usr/share/nginx/mail/pfadmin
cp nginx/conf/index.php /usr/share/nginx/mail/
tar xf pfadmin/inst/$postfixadmin_revision.tar -C pfadmin/inst/
mv pfadmin/inst/$postfixadmin_revision/* /usr/share/nginx/mail/pfadmin/
cp pfadmin/conf/config.local.php /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/my_postfixpass/$my_postfixpass/g" /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/my_postfixuser/$my_postfixuser/g" /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/my_postfixdb/$my_postfixdb/g" /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/domain.tld/$sys_domain/g" /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/change-this-to-your.domain.tld/$sys_domain/g" /usr/share/nginx/mail/pfadmin/config.inc.php
chown -R www-data: /usr/share/nginx/
rm -r pfadmin/inst/$postfixadmin_revision
}

# fail2ban
function fail2banconfig {
tar xf fail2ban/inst/$fail2ban_version.tar -C fail2ban/inst/
rm -rf /etc/fail2ban/ 2> /dev/null
(cd fail2ban/inst/$fail2ban_version ; python setup.py -q install 2> /dev/null)
cp fail2ban/conf/fail2ban.init /etc/init.d/fail2ban
cp fail2ban/conf/jail.local /etc/fail2ban/jail.local
chmod +x /etc/init.d/fail2ban
update-rc.d fail2ban defaults
rm -rf fail2ban/inst/$fail2ban_version
}

# rsyslogd
function rsyslogdconfig {
sed "s/*.*;auth,authpriv.none/*.*;auth,mail.none,authpriv.none/" -i /etc/rsyslog.conf
}

# restart services
function restartservices {
service fail2ban stop; service fail2ban start;
service rsyslog stop; service rsyslog start;
service nginx stop; service nginx start;
service php5-fpm stop; service php5-fpm start;
service clamav-daemon stop; service clamav-daemon start;
service clamav-freshclam stop; service clamav-freshclam start;
service spamassassin stop; echo "Sleeping 3 seconds..."; sleep 3; service spamassassin start;
service fuglu stop; sleep 1; service fuglu start;
service mysql stop; service mysql start;
cat /dev/null > /var/log/mail.err
cat /dev/null > /var/log/mail.warn
cat /dev/null > /var/log/mail.log
cat /dev/null > /var/log/mail.info
service dovecot stop; service dovecot start;
service postfix stop; service postfix start;
}

# check dns settings for domain
function checkdns {
if [[ -z `dig -x $getpublicipv4 @8.8.8.8 | grep -i $sys_domain` ]]; then
echo "`tput bold`WARNING`tput sgr0`: Remember to setup a PTR record: $getpublicipv4 does not point to $sys_domain (checked by Google DNS)"
fi
if [[ -z `dig $sys_hostname.$sys_domain @8.8.8.8 | grep -i $getpublicipv4` ]]; then
echo "`tput bold`WARNING`tput sgr0`: Remember to setup an A record for $sys_hostname.$sys_domain pointing to $getpublicipv4 (checked by Google DNS)"
fi
if [[ -z `dig $sys_domain txt @8.8.8.8 | grep -i spf` ]]; then
echo "`tput bold`HINT`tput sgr0`: You may want to setup a TXT record for SPF, see spfwizard.com for further information (checked by Google DNS)"
fi
}

# setup an administrator for postfixadmin
function setupsuperadmin {
# init database silently
wget --quiet --no-check-certificate -O /dev/null https://localhost/pfadmin/setup.php
# creating admin account
php /usr/share/nginx/mail/pfadmin/scripts/postfixadmin-cli.php admin add $pfadmin_adminuser --password $pfadmin_adminpass --password2 $pfadmin_adminpass --superadmin
}

systemenvironment
returnwait "System environment"
installpackages
returnwait "Package installation"
selfsignedcert
returnwait "Self-signed certificate"
mysqlconfiguration
returnwait "MySQL configuration"
fuglusetup
returnwait "FuGlu setup"
postfixconfig
returnwait "Postfix configuration"
dovecotconfig
returnwait "Dovecot configuration"
clamavconfig
returnwait "ClamAV configuration"
spamassassinconfig
returnwait "Spamassassin configuration"
websrvconfig
returnwait "Nginx configuration"
pfadminconfig
returnwait "Postfixadmin configuration"
fail2banconfig
returnwait "Fail2ban configuration"
rsyslogdconfig
returnwait "Rsyslogd configuration"
restartservices
returnwait "Restarting services"
setupsuperadmin
returnwait "Completing Postfixadmin setup"
checkdns
echo
chmod 600 installer.log
echo "LOGGED CREDENTIALS AND FURTHER INFORMATION TO: installer.log - only readable by root"
echo
echo "Visit \"https://$sys_hostname.$sys_domain\", select \"System Settings\" and login as Postfixadmin Superuser to create a mailbox."
echo
