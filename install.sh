#!/bin/bash
genpasswd() {
	local l=$1
       	[ "$l" == "" ] && l=16
      	tr -cd '[:alnum:]' < /dev/urandom | fold -w${l} | head -n1
}

[[ ! -z `ss -lnt | awk '$1 == "LISTEN" && $4 ~ ":25" || $4 ~ ":143" || $4 ~ ":993" || $4 ~ ":587" || $4 ~ ":485" || $4 ~ ":80" || $4 ~ ":443" || $4 ~ ":995"'` ]] && { echo "please remove any mail and web services before running this script"; exit 1; }

########### CONFIG START ###########
sys_hostname="mail"
sys_domain="domain.tld"
sys_timezone="Europe/Berlin"

my_postfixdb="postfixdb"
my_postfixuser="postfix"
my_postfixpass=`genpasswd 20`
my_rootpw=`genpasswd 20`

cert_country="DE"
cert_state="NRW"
cert_city="DUS"
cert_org="MAIL"
############ CONFIG END ############
#### do not edit any line below ####

# log generated passwords
echo ---------- > installer.log
echo Postfix password: $my_postfixpass >> installer.log
echo MySQL root password: $my_rootpw >> installer.log
echo ---------- >> installer.log

# set hostname
function systemenvironment {
getpublicip=`wget -q4O- ip.appspot.com`
if [[ $getpublicip =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
cat > /etc/hosts<<'EOF'
127.0.0.1 localhost
::1 localhost ip6-localhost ip6-loopback
ff02::1 ip6-allnodes
ff02::2 ip6-allrouters
EOF
echo $getpublicip $sys_hostname.$sys_domain $sys_hostname >> /etc/hosts
echo $sys_hostname > /etc/hostname
# need to trigger this pseudo service now
service hostname.sh start
else
echo "cannot set your hostname: cannot resolve ip.appspot.com";
fi
if [[ -f /usr/share/zoneinfo/$sys_timezone ]] ; then
echo $sys_timezone > /etc/timezone
dpkg-reconfigure -f noninteractive tzdata
else
echo "cannot set your timezone: timezone is unknown";
fi
if [[ -z `dig -x $getpublicip @8.8.8.8 | grep -i $sys_hostname.$sys_domain` ]]; then
echo "remember to setup a ptr record: $getpublicip does not point to $sys_hostname.$sys_domain (google dns)"
fi
}

# installation
function installpackages {
DEBIAN_FRONTEND=noninteractive apt-get --force-yes -y install dnsutils python-sqlalchemy python-beautifulsoup python-setuptools \
python-magic openssl php-auth-sasl php-http-request php-mail php-mail-mime php-mail-mimedecode php-net-dime php-net-smtp \
php-net-socket php-net-url php-pear php-soap php5 php5-cli php5-common php5-curl php5-fpm php5-gd php5-imap subversion \
php5-intl php5-mcrypt php5-mysql php5-sqlite mysql-client mysql-server nginx dovecot-common dovecot-core mailutils \
dovecot-imapd dovecot-lmtpd dovecot-managesieved dovecot-sieve dovecot-mysql dovecot-pop3d postfix \
postfix-mysql postfix-pcre clamav clamav-base clamav-daemon clamav-freshclam spamassassin fail2ban
unset DEBIAN_FRONTEND
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
mkdir /var/log/fuglu
rm /tmp/fuglu_control.sock
chown nobody:nogroup /var/log/fuglu
git clone https://github.com/gryphius/fuglu.git fuglu_git
cd fuglu_git/fuglu
python setup.py install
cd ../../
find /etc/fuglu -type f -name '*.dist' -print0 | xargs -0 rename 's/.dist$//'
sed -i '/^group=/s/=.*/=nogroup/' /etc/fuglu/fuglu.conf
cp fuglu_git/fuglu/scripts/startscripts/debian/7/fuglu /etc/init.d/fuglu
chmod +x /etc/init.d/fuglu
update-rc.d fuglu defaults
}

# postfix
function postfixconfig {
cp -R postfix/* /etc/postfix/
chown root:postfix "/etc/postfix/sql/mysql_virtual_alias_domain_catchall_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_alias_domain_catchall_maps.cf"
chown root:postfix "/etc/postfix/sql/mysql_virtual_alias_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_alias_maps.cf"
chown root:postfix "/etc/postfix/sql/mysql_virtual_alias_domain_mailbox_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_alias_domain_mailbox_maps.cf"
chown root:postfix "/etc/postfix/sql/mysql_virtual_mailbox_limit_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_mailbox_limit_maps.cf"
chown root:postfix "/etc/postfix/sql/mysql_virtual_mailbox_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_mailbox_maps.cf"
chown root:postfix "/etc/postfix/sql/mysql_virtual_alias_domain_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_alias_domain_maps.cf"
chown root:postfix "/etc/postfix/sql/mysql_virtual_domains_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_domains_maps.cf"
chown root:root "/etc/postfix/filter_default"; chmod 644 "/etc/postfix/filter_default"
chown root:root "/etc/postfix/master.cf"; chmod 644 "/etc/postfix/master.cf"
chown root:root "/etc/postfix/filter_trusted"; chmod 644 "/etc/postfix/filter_trusted"
chown root:root "/etc/postfix/main.cf"; chmod 644 "/etc/postfix/main.cf"
sed -i "s/mail.domain.tld/$sys_hostname.$sys_domain/g" /etc/postfix/*
sed -i "s/my_postfixpass/$my_postfixpass/g" /etc/postfix/sql/*
sed -i "s/my_postfixuser/$my_postfixuser/g" /etc/postfix/sql/*
sed -i "s/my_postfixdb/$my_postfixdb/g" /etc/postfix/sql/*
}

# dovecot
function dovecotconfig {
rm -rf /etc/dovecot/*
cp -R dovecot/* /etc/dovecot/
chown root:dovecot "/etc/dovecot/dovecot-dict-sql.conf"; chmod 640 "/etc/dovecot/dovecot-dict-sql.conf"
chown root:vmail "/etc/dovecot/dovecot-mysql.conf"; chmod 640 "/etc/dovecot/dovecot-mysql.conf"
chown root:root "/etc/dovecot/dovecot.conf"; chmod 644 "/etc/dovecot/dovecot.conf"
sed -i "s/mail.domain.tld/$sys_hostname.$sys_domain/g" /etc/dovecot/*
sed -i "s/my_postfixpass/$my_postfixpass/g" /etc/dovecot/*
sed -i "s/my_postfixuser/$my_postfixuser/g" /etc/dovecot/*
sed -i "s/my_postfixdb/$my_postfixdb/g" /etc/dovecot/*
groupadd -g 5000 vmail
useradd -g vmail -u 5000 vmail -d /var/vmail
mkdir -p /var/vmail/sieve
cp dovecot/spam-global.sieve /var/vmail/sieve/spam-global.sieve
cp dovecot/default.sieve /var/vmail/sieve/default.sieve
sievec /var/vmail/sieve/spam-global.sieve
chown -R vmail:vmail /var/vmail
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
service clamav-freshclam start
service clamav-daemon start
}

# spamassassin
function spamassassinconfig {
sed -i '/rewrite_header/c\rewrite_header Subject [SPAM]' /etc/spamassassin/local.cf
sed -i '/report_safe/c\report_safe 2' /etc/spamassassin/local.cf
sed -i '/^OPTIONS=/s/=.*/="--create-prefs --max-children 5 --helper-home-dir --username debian-spamd"/' /etc/default/spamassassin
sed -i '/^CRON=/s/=.*/="1"/' /etc/default/spamassassin
sed -i '/^ENABLED=/s/=.*/="1"/' /etc/default/spamassassin
}

# nginx, php5
function websrvconfig {
rm -rf /etc/php5/fpm/pool.d/*
rm -rf /etc/nginx/{sites-available,sites-enabled}/*
cp nginx/sites-available/mail /etc/nginx/sites-available/mail
ln -s /etc/nginx/sites-available/mail /etc/nginx/sites-enabled/mail
cp php5-fpm/mail.conf /etc/php5/fpm/pool.d/mail.conf
sed -i "/date.timezone/c\php_admin_value[date.timezone] = $sys_timezone" /etc/php5/fpm/pool.d/mail.conf
sed -i '/server_tokens/c\server_tokens off;' /etc/nginx/nginx.conf
}

# pfadmin
function pfadminconfig {
rm -rf /usr/share/nginx/mail
mkdir -p /usr/share/nginx/mail
svn co http://svn.code.sf.net/p/postfixadmin/code/trunk /usr/share/nginx/mail/pfadmin
cp pfadmin/config.local.php /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/my_postfixpass/$my_postfixpass/g" /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/my_postfixuser/$my_postfixuser/g" /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/my_postfixdb/$my_postfixdb/g" /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/domain.tld/$sys_domain/g" /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/change-this-to-your.domain.tld/$sys_domain/g" /usr/share/nginx/mail/pfadmin/config.inc.php
chown -R www-data: /usr/share/nginx/
}

# fail2ban
function fail2banconfig {
cp fail2ban/jail.local /etc/fail2ban/jail.local
cp fail2ban/filter.d/sasl.conf /etc/fail2ban/filter.d/sasl.conf
cp fail2ban/filter.d/dovecot-pop3imap.conf /etc/fail2ban/filter.d/dovecot-pop3imap.conf
}

# rsyslogd
function rsyslogdconfig {
sed "s/*.*;auth,authpriv.none/*.*;auth,mail.none,authpriv.none/" -i /etc/rsyslog.conf
}

function restartservices {
service fail2ban stop; service fail2ban stop;
service nginx stop; service nginx start;
service php5-fpm stop; service php5-fpm start;
service clamav-daemon stop; service clamav-daemon start;
service clamav-freshclam stop; service clamav-freshclam start;
service spamassassin stop; service spamassassin start;
service fuglu restart; service fuglu start;
service dovecot stop; service dovecot start;
service postfix stop; service postfix start;
}


read -p "Press [ENTER] to setup your system environment..."
systemenvironment
read -p "Press [ENTER] to install the required packages (fuglu will be installed from git)..."
installpackages
read -p "Press [ENTER] to create a self-signed certificate..."
selfsignedcert
read -p "Press [ENTER] to setup mysql..."
mysqlconfiguration
read -p "Press [ENTER] to install and setup fuglu..."
fuglusetup
read -p "Press [ENTER] to setup postfix..."
postfixconfig
read -p "Press [ENTER] to setup dovecot..."
dovecotconfig
read -p "Press [ENTER] to setup clamav..."
clamavconfig
read -p "Press [ENTER] to setup spamassassin..."
spamassassinconfig
read -p "Press [ENTER] to setup nginx and php5..."
websrvconfig
read -p "Press [ENTER] to setup postfixadmin..."
pfadminconfig
read -p "Press [ENTER] to setup fail2ban..."
fail2banconfig
read -p "Press [ENTER] to setup rsyslogd..."
rsyslogdconfig
read -p "Press [ENTER] to restart all depending services..."
restartservices
