#!/bin/bash
genpasswd() {
	local l=$1
       	[ "$l" == "" ] && l=16
      	tr -cd '[:alnum:]' < /dev/urandom | fold -w${l} | head -n1
}

[[ ! -z `ss -lnt | awk '$1 == "LISTEN" && $4 ~ ":25" || $4 ~ ":143" || $4 ~ ":993" || $4 ~ ":587" || $4 ~ ":485" || $4 ~ ":80" || $4 ~ ":443" || $4 ~ ":995"'` ]] && echo "please remove any mail and web services before running this script"; exit 1;

########### CONFIG START ###########
sys_hostname="mail"
sys_domain="domain.tld"
my_postfixdb="postfixdb"
my_postfixuser="postfix"
my_postfixpass=`genpasswd 20`
my_rootpw=`genpasswd 20`
############ CONFIG END ############
#### do not edit any line below ####

# log generated passwords
echo ---------- > installer.log
echo Postfix password: $my_postfixpass >> installer.log
echo MySQL root password: $my_rootpw >> installer.log
echo ---------- >> installer.log

# set hostname
cat > hosts<<'EOF'
127.0.0.1 localhost
::1 localhost ip6-localhost ip6-loopback
ff02::1 ip6-allnodes
ff02::2 ip6-allrouters
EOF
echo `wget -q4O- ip.appspot.com` $sys_hostname.$sys_domain $sys_hostname >> hosts
echo $sys_hostname > /etc/hostname
service hostname.sh start

# installation
DEBIAN_FRONTEND=noninteractive apt-get --force-yes -y install python-sqlalchemy python-beautifulsoup python-setuptools \
python-magic openssl php-auth-sasl php-http-request php-mail php-mail-mime php-mail-mimedecode php-net-dime php-net-smtp \
php-net-socket php-net-url php-pear php-soap php5 php5-cli php5-common php5-curl php5-fpm php5-gd php5-imap svn \
php5-intl php5-mcrypt php5-mysql php5-sqlite mysql-client mysql-server nginx dovecot-common dovecot-core mailutils \
dovecot-imapd dovecot-lmtpd dovecot-managesieved dovecot-sieve dovecot-mysql dovecot-pop3d postfix \
postfix-mysql postfix-pcre clamav clamav-base clamav-daemon clamav-freshclam spamassassin fail2ban

# certificate
mkdir /etc/ssl/mail
openssl req -new -newkey rsa:4096 -days 1095 -nodes -x509 -subj "/C=DE/ST=SESI/L=SESI/O=SESI/CN=$sys_hostname.$sys_domain" -keyout /etc/ssl/mail/mail.key  -out /etc/ssl/mail/mail.crt
chmod 600 /etc/ssl/mail/mail.key

# mysql
mysqladmin -u root password $my_rootpw
mysql --defaults-file=/etc/mysql/debian.cnf -e "CREATE DATABASE $my_postfixdb; CREATE USER '$my_postfixuser'@'localhost' IDENTIFIED BY '$my_postfixpass'; GRANT ALL PRIVILEGES ON `$my_postfixdb` . * TO '$my_postfixuser'@'localhost';"

# fuglu
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

# postfix
cp -R postfix/* /etc/postfix2/
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


# dovecot
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
cp misc/spam-global.sieve_dovecot /var/vmail/sieve/spam-global.sieve
cp misc/default.sieve_dovecot /var/vmail/sieve/default.sieve
sievec /var/vmail/sieve/spam-global.sieve
chown -R vmail:vmail /var/vmail

# clamv
service clamav-daemon stop
service clamav-freshclam stop
freshclam
echo TCPSocket 3310 >> /etc/clamav/clamd.conf
echo TCPAddr 127.0.0.1 >> /etc/clamav/clamd.conf
service clamav-freshclam start
service clamav-daemon start

# spamassassin
sed -i '/rewrite_header/c\rewrite_header Subject [SPAM]' /etc/spamassassin/local.cf
sed -i '/report_safe/c\report_safe 2' /etc/spamassassin/local.cf
sed -i '/^OPTIONS=/s/=.*/="--create-prefs --max-children 5 --helper-home-dir --username debian-spamd"/' /etc/default/spamassassin
sed -i '/^CRON=/s/=.*/="1"/' /etc/default/spamassassin
sed -i '/^ENABLED=/s/=.*/="1"/' /etc/default/spamassassin
service spamassassin restart

# nginx, php5
rm -rf /etc/php5/fpm/pool.d/*
rm -rf /etc/nginx/{sites-available,sites-enabled}/*
cp misc/mail_nginx /etc/nginx/sites-available/
cp misc/mail.conf_fpm /etc/php5/fpm/pool.d/mail.conf
sed -i '/server_tokens/c\server_tokens off;' /etc/nginx/nginx.conf

# pfadmin
mkdir /usr/share/nginx/mail
svn co http://svn.code.sf.net/p/postfixadmin/code/trunk /usr/share/nginx/mail/pfadmin
cp misc/config.local.php_pfadmin /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/my_postfixpass/$my_postfixpass/g" /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/my_postfixuser/$my_postfixuser/g" /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/my_postfixdb/$my_postfixdb/g" /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/domain.tld/$sys_domain/g" /usr/share/nginx/mail/pfadmin/config.local.php
sed -i "s/change-this-to-your.domain.tld/$sys_domain/g" /usr/share/nginx/mail/pfadmin/config.inc.php
chown -R www-data: /usr/share/nginx/

# fail2ban
cp misc/jail.local_fail2ban /etc/fail2ban/jail.local
cp misc/sasl.conf_fail2ban /etc/fail2ban/filter.d/sasl.conf
cp misc/dovecot-pop3imap.conf_fail2ban /etc/fail2ban/dovecot-pop3imap.conf
service fail2ban restart

# rsyslog
sed "s/*.*;auth,authpriv.none/*.*;auth,mail.none,authpriv.none/" -i /etc/rsyslog.conf
