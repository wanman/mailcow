textb() { echo $(tput bold)${1}$(tput sgr0); }
greenb() { echo $(tput bold)$(tput setaf 2)${1}$(tput sgr0); }
redb() { echo $(tput bold)$(tput setaf 1)${1}$(tput sgr0); }
yellowb() { echo $(tput bold)$(tput setaf 3)${1}$(tput sgr0); }
pinkb() { echo $(tput bold)$(tput setaf 5)${1}$(tput sgr0); }

usage() {
	echo "fufix install script command-line parameters."
	echo $(textb "Do not append any parameters to run fufix in default mode.")
	echo "
	--help | -h
		Print this text

	--upgrade-from-file installer.log | -u installer.log
		Upgrade using a previous \"installer.log\" file
	"
}

genpasswd() {
    count=0
    while [ $count -lt 3 ]
    do
        pw_valid=$(tr -cd A-Za-z0-9 < /dev/urandom | fold -w24 | head -n1)
        count=$(grep -o "[0-9]" <<< $pw_valid | wc -l)
    done
    echo $pw_valid
}

returnwait() {
		echo "$(greenb [OK]) - Task $(textb "$1") completed"
		echo "----------------------------------------------"
		if [[ $inst_unattended != "yes" ]]; then
			read -p "$(yellowb !) Press ENTER to continue with task $(textb "$2") (CTRL-C to abort) "
		fi
		echo "$(pinkb [RUNNING]) - Task $(textb "$2") started, please wait..."
}

is_ipv6() {
    # Thanks to https://github.com/mutax
    INPUT="$@"
    O=""
    while [ "$O" != "$INPUT" ]; do
        O="$INPUT"
        INPUT="$( sed  's|:\([0-9a-f]\{3\}\):|:0\1:|g' <<< "$INPUT" )"
        INPUT="$( sed  's|:\([0-9a-f]\{3\}\)$|:0\1|g'  <<< "$INPUT")"
        INPUT="$( sed  's|^\([0-9a-f]\{3\}\):|0\1:|g'  <<< "$INPUT" )"
        INPUT="$( sed  's|:\([0-9a-f]\{2\}\):|:00\1:|g' <<< "$INPUT")"
        INPUT="$( sed  's|:\([0-9a-f]\{2\}\)$|:00\1|g'  <<< "$INPUT")"
        INPUT="$( sed  's|^\([0-9a-f]\{2\}\):|00\1:|g'  <<< "$INPUT")"
        INPUT="$( sed  's|:\([0-9a-f]\):|:000\1:|g'  <<< "$INPUT")"
        INPUT="$( sed  's|:\([0-9a-f]\)$|:000\1|g'   <<< "$INPUT")"
        INPUT="$( sed  's|^\([0-9a-f]\):|000\1:|g'   <<< "$INPUT")"
    done

    grep -qs "::" <<< "$INPUT"
    if [ "$?" -eq 0 ]; then
        GRPS="$(sed  's|[0-9a-f]||g' <<< "$INPUT" | wc -m)"
        ((GRPS--)) # carriage return
        ((MISSING=8-GRPS))
        for ((i=0;i<$MISSING;i++)); do
            ZEROES="$ZEROES:0000"
        done
        INPUT="$( sed  's|\(.\)::\(.\)|\1'$ZEROES':\2|g'   <<< "$INPUT")"
        INPUT="$( sed  's|\(.\)::$|\1'$ZEROES':0000|g'   <<< "$INPUT")"
        INPUT="$( sed  's|^::\(.\)|'$ZEROES':0000:\1|g;s|^:||g'   <<< "$INPUT")"
    fi

    if [ $(echo $INPUT | wc -m) != 40 ]; then
        return 1
    else
        return 0
    fi
}

checksystem() {
	if [[ $(grep MemTotal /proc/meminfo | awk '{print $2}') -lt 500000 ]]; then
		echo "$(yellowb [WARN]) - At least 500MB of memory is highly recommended"
		read -p "Press ENTER to skip this warning or CTRL-C to cancel the process"
	fi
}

checkports() {
	if [[ -z $(which nc) ]]; then
		echo "$(redb [ERR]) - Please install $(textb netcat) before running this script"
		exit 1
	fi
	for port in 25 143 465 587 993 995
	do
		if [[ $(nc -z localhost $port; echo $?) -eq 0 ]]; then
			echo "$(redb [ERR]) - An application is blocking the installation on Port $(textb $port)"
			# Wait until finished to list all blocked ports.
			blocked_port=1
		fi
	done
	[[ $blocked_port -eq 1 ]] && exit 1
	if [[ $(nc -z localhost 3306; echo $?) -eq 0 ]] && [[ $(mysql --defaults-file=/etc/mysql/debian.cnf -e ""; echo $?) -ne 0 ]]; then
		echo "$(redb [ERR]) - No useable MySQL instance found (/etc/mysql/debian.cnf missing?)"
		exit 1
	elif [[ $(nc -z localhost 3306; echo $?) -eq 0 ]] && [[ $(mysql --defaults-file=/etc/mysql/debian.cnf -e ""; echo $?) -eq 0 ]]; then
		echo "$(textb [INFO]) - Useable MySQL instance found, will not re-configure MySQL"
		mysql_useable=1
		my_rootpw="not changed"
	fi
}

checkconfig() {
    if [[ $conf_done = "no" ]]; then
        echo "$(redb [ERR]) - Error in configuration file"
        echo "Is \"conf_done\" set to \"yes\"?"
        echo
        exit 1
    fi
	if [[ ${#cert_country} -ne 2 ]]; then
        echo "$(redb [ERR]) - Country code must consist of exactly two characters (DE/US/UK etc.)"
        exit 1
    fi
	if [[ $conf_httpd != "nginx" && $conf_httpd != "apache2" ]]; then
		echo "$(redb [ERR]) - \"conf_httpd\" is neither nginx nor apache2"
		exit 1
	elif [[ $conf_httpd = "apache2" && -z $(apt-cache show apache2 | grep Version | grep "2.4") ]]; then
		echo "$(redb [ERR]) - Unable to install Apache 2.4, please use Nginx or upgrade your distribution"
        exit 1
	fi
    for var in sys_hostname sys_domain sys_timezone my_postfixdb my_postfixuser my_postfixpass my_rootpw my_rcuser my_rcpass my_rcdb pfadmin_adminuser pfadmin_adminpass cert_country cert_state cert_city cert_org
    do
        if [[ -z ${!var} ]]; then
            echo "$(redb [ERR]) - Parameter $var must not be empty."
            echo
            exit 1
        fi
    done
    pass_count=$(grep -o "[0-9]" <<< $pfadmin_adminpass | wc -l)
    pass_chars=$(echo $pfadmin_adminpass | egrep "^.{8,255}" | \
	egrep "[ABCDEFGHIJKLMNOPQRSTUVXYZ]" | \
	egrep "[abcdefghijklmnopqrstuvxyz"] | \
	egrep "[0-9]")
    if [[ $pass_count -lt 2 || -z $pass_chars ]]; then
		echo "$(redb [ERR]) - Postfixadmin password does not meet password policy requirements."
		echo
		exit 1
	fi
	if [[ $inst_debug == "yes" ]]; then
		set -x
	fi
}

installtask() {
	case $1 in
		environment)
			getpublicipv4=$(wget -q4O- icanhazip.com)
			if [[ $getpublicipv4 =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
				cat > /etc/hosts<<'EOF'
127.0.0.1 localhost
::1 localhost ip6-localhost ip6-loopback
ff02::1 ip6-allnodes
ff02::2 ip6-allrouters
EOF
				echo $getpublicipv4 $sys_hostname.$sys_domain $sys_hostname >> /etc/hosts
				echo $sys_hostname.$sys_domain > /etc/mailname
				getpublicipv6=$(wget -t2 -T1 -q6O- icanhazip.com)
					if is_ipv6 $getpublicipv6; then
						echo $getpublicipv6 $sys_hostname.$sys_domain $sys_hostname >> /etc/hosts
					fi
			else
				echo "$(redb [ERR]) - Cannot set your hostname"
				exit 1
			fi
			echo "$(textb [INFO]) - Setting your hostname..."
			if [[ -f /lib/systemd/systemd ]]; then
				hostnamectl set-hostname $sys_hostname
			else
				echo $sys_hostname > /etc/hostname
				hostname $sys_hostname > /dev/null 2>&1
				service hostname.sh start > /dev/null 2>&1
			fi
			if [[ -f /usr/share/zoneinfo/$sys_timezone ]] ; then
				echo $sys_timezone > /etc/timezone
				dpkg-reconfigure -f noninteractive tzdata > /dev/null 2>&1
				if [ "$?" -ne "0" ]; then
					echo "$(redb [ERR]) - Timezone configuration failed: dpkg returned exit code != 0"
					exit 1
				fi
			else
				echo "$(redb [ERR]) - Cannot set your timezone: timezone is unknown"
				exit 1
			fi
			;;
		installpackages)
			echo "$(textb [INFO]) - Installing prerequisites..."
			apt-get -y update > /dev/null ; apt-get -y install lsb-release dbus whiptail apt-utils ssl-cert > /dev/null 2>&1
			/usr/sbin/make-ssl-cert generate-default-snakeoil --force-overwrite
			dist_codename=$(lsb_release -cs)
			# Detect and edit repos
			if [[ $dist_codename == "wheezy" ]] && [[ -z $(grep -E "^deb(.*)wheezy-backports(.*)" /etc/apt/sources.list) ]]; then
				echo "$(textb [INFO]) - Enabling wheezy-backports..."
				echo -e "\ndeb http://http.debian.net/debian wheezy-backports main" >> /etc/apt/sources.list
				apt-get -y update >/dev/null
			fi
			if [[ ! -z $(grep -E "^deb(.*)wheezy-backports(.*)" /etc/apt/sources.list) ]]; then
				echo "$(textb [INFO]) - Installing jq and python-magic from wheezy-backports..."
				apt-get -y update >/dev/null ; apt-get -y install jq python-magic -t wheezy-backports >/dev/null
			fi
            if [[ $conf_httpd == "apache2" ]]; then
				echo "$(textb [INFO]) - Installing Apache2 and components..."
				apt-get -y install apache2 apache2-utils >/dev/null
			elif [[ $conf_httpd == "nginx" ]]; then
				echo "$(textb [INFO]) - Installing Nginx..."
				apt-get -y install nginx-extras >/dev/null
			fi
			echo "$(textb [INFO]) - Installing packages unattended, please stand by, errors will be reported."
			apt-get -y update >/dev/null
DEBIAN_FRONTEND=noninteractive apt-get --force-yes -y install jq dnsutils python-sqlalchemy python-beautifulsoup python-setuptools \
python-magic libmail-spf-perl libmail-dkim-perl openssl php-auth-sasl php-http-request php-mail php-mail-mime php-mail-mimedecode php-net-dime php-net-smtp \
php-net-socket php-net-url php-pear php-soap php5 php5-cli php5-common php5-curl php5-fpm php5-gd php5-imap php-apc subversion \
php5-intl php5-mcrypt php5-mysql php5-sqlite libawl-php php5-xmlrpc mysql-client mysql-server mailutils \
postfix-mysql postfix-pcre spamassassin spamc sudo bzip2 curl mpack opendkim opendkim-tools unzip clamav-daemon \
fetchmail liblockfile-simple-perl libdbi-perl libmime-base64-urlsafe-perl libtest-tempdir-perl liblogger-syslog-perl bsd-mailx >/dev/null
			if [ "$?" -ne "0" ]; then
				echo "$(redb [ERR]) - Package installation failed"
				exit 1
			fi
			update-alternatives --set mailx /usr/bin/bsd-mailx --quiet > /dev/null 2>&1
			mkdir -p /etc/dovecot/private/
			cp /etc/ssl/certs/ssl-cert-snakeoil.pem /etc/dovecot/dovecot.pem
			cp /etc/ssl/private/ssl-cert-snakeoil.key /etc/dovecot/dovecot.key
			cp /etc/ssl/certs/ssl-cert-snakeoil.pem /etc/dovecot/private/dovecot.pem
			cp /etc/ssl/private/ssl-cert-snakeoil.key /etc/dovecot/private/dovecot.key
			if [[ ! -z $(grep wheezy-backports /etc/apt/sources.list) ]]; then
				echo "$(textb [INFO]) - Installing Dovecot from wheezy-backports..."
DEBIAN_FRONTEND=noninteractive apt-get --force-yes -y install dovecot-common dovecot-core dovecot-imapd dovecot-lmtpd dovecot-managesieved dovecot-sieve dovecot-mysql dovecot-pop3d -t wheezy-backports >/dev/null
			else
DEBIAN_FRONTEND=noninteractive apt-get --force-yes -y install dovecot-common dovecot-core dovecot-imapd dovecot-lmtpd dovecot-managesieved dovecot-sieve dovecot-mysql dovecot-pop3d >/dev/null
			fi
			;;
		ssl)
			rm /etc/ssl/mail/* 2> /dev/null
			mkdir /etc/ssl/mail 2> /dev/null
			openssl req -new -newkey rsa:4096 -sha256 -days 1095 -nodes -x509 -subj "/C=$cert_country/ST=$cert_state/L=$cert_city/O=$cert_org/CN=$sys_hostname.$sys_domain" -keyout /etc/ssl/mail/mail.key  -out /etc/ssl/mail/mail.crt
			chmod 600 /etc/ssl/mail/mail.key
			cp /etc/ssl/mail/mail.crt /usr/local/share/ca-certificates/
			update-ca-certificates
			;;
		mysql)
			if [[ $mysql_useable -ne 1 ]]; then
				mysql --defaults-file=/etc/mysql/debian.cnf -e "UPDATE mysql.user SET Password=PASSWORD('$my_rootpw') WHERE USER='root'; FLUSH PRIVILEGES;"
			fi
			mysql --defaults-file=/etc/mysql/debian.cnf -e "DROP DATABASE IF EXISTS $my_postfixdb; DROP DATABASE IF EXISTS $my_rcdb;"
			mysql --defaults-file=/etc/mysql/debian.cnf -e "CREATE DATABASE $my_postfixdb; GRANT ALL PRIVILEGES ON $my_postfixdb.* TO '$my_postfixuser'@'localhost' IDENTIFIED BY '$my_postfixpass';"
			mysql --defaults-file=/etc/mysql/debian.cnf -e "CREATE DATABASE $my_rcdb; GRANT ALL PRIVILEGES ON $my_rcdb.* TO '$my_rcuser'@'localhost' IDENTIFIED BY '$my_rcpass';"
			mysql --defaults-file=/etc/mysql/debian.cnf -e "GRANT SELECT ON $my_postfixdb.* TO 'vmail'@'localhost'; FLUSH PRIVILEGES;"
			;;
		postfix)
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
			sed -i "s/FUFIX_HOST.FUFIX_DOMAIN/$sys_hostname.$sys_domain/g" /etc/postfix/* 2> /dev/null
			sed -i "s/FUFIX_DOMAIN/$sys_domain/g" /etc/postfix/* 2> /dev/null
			sed -i "s/my_postfixpass/$my_postfixpass/g" /etc/postfix/sql/*
			sed -i "s/my_postfixuser/$my_postfixuser/g" /etc/postfix/sql/*
			sed -i "s/my_postfixdb/$my_postfixdb/g" /etc/postfix/sql/*
			postmap /etc/postfix/fufix_sender_access
			chown www-data: /etc/postfix/fufix_*
			sed -i "/%www-data/d" /etc/sudoers 2> /dev/null
			sed -i "/%vmail/d" /etc/sudoers 2> /dev/null
			echo '%www-data ALL=(ALL) NOPASSWD: /usr/sbin/postfix reload, /usr/local/bin/opendkim-keycontrol, /usr/local/bin/fufix_msg_size, /usr/bin/tail * /opt/vfilter/log/vfilter.log' >> /etc/sudoers
			echo '%vmail ALL=(ALL) NOPASSWD: /usr/bin/spamc*' >> /etc/sudoers
			;;
		dovecot)
			[[ -z $(grep fs.inotify.max_user_instances /etc/sysctl.conf) ]] && echo "fs.inotify.max_user_instances=1024" >> /etc/sysctl.conf
			sysctl -p > /dev/null
			rm -rf /etc/dovecot/*
			cp -R dovecot/conf/*.conf /etc/dovecot/
			userdel vmail 2> /dev/null
			groupadd -g 5000 vmail
			useradd -g vmail -u 5000 vmail -d /var/vmail
			chown root:dovecot "/etc/dovecot/dovecot-dict-sql.conf"; chmod 640 "/etc/dovecot/dovecot-dict-sql.conf"
			chown root:vmail "/etc/dovecot/dovecot-mysql.conf"; chmod 640 "/etc/dovecot/dovecot-mysql.conf"
			chown root:root "/etc/dovecot/dovecot.conf"; chmod 644 "/etc/dovecot/dovecot.conf"
			sed -i "s/FUFIX_HOST.FUFIX_DOMAIN/$sys_hostname.$sys_domain/g" /etc/dovecot/*
			sed -i "s/FUFIX_DOMAIN/$sys_domain/g" /etc/dovecot/*
			sed -i "s/my_postfixpass/$my_postfixpass/g" /etc/dovecot/*
			sed -i "s/my_postfixuser/$my_postfixuser/g" /etc/dovecot/*
			sed -i "s/my_postfixdb/$my_postfixdb/g" /etc/dovecot/*
			mkdir /etc/dovecot/conf.d 2> /dev/null
			mkdir -p /var/vmail/sieve
			cp dovecot/conf/spam-global.sieve /var/vmail/sieve/spam-global.sieve
			touch /var/vmail/sieve/default.sieve
			install -m 755 misc/fufix_msg_size /usr/local/bin/fufix_msg_size
			sievec /var/vmail/sieve/spam-global.sieve
			chown -R vmail:vmail /var/vmail
			install -m 755 dovecot/conf/doverecalcq /etc/cron.daily/
			;;
		vfilter)
			mkdir -p /opt/vfilter 2> /dev/null
			install -m 755 vfilter/vfilter.sh /opt/vfilter/vfilter.sh
			install -m 644 vfilter/replies /opt/vfilter/replies
			install -m 600 vfilter/vfilter.conf /opt/vfilter/vfilter.conf
			install -m 755 -d /opt/vfilter/clamav_positives
			chown -R vmail:vmail /opt/vfilter
			;;
		clamav)
			usermod -a -G clamav vmail
			# A second freshclam process indicates freshclam is already updating
			# First process is freshclam in daemon mode
			if [[ $(pgrep freshclam | wc -l) -lt 2 ]]; then
				freshclam
			fi
            ;;
		opendkim)
			echo 'SOCKET="inet:10040@localhost"' > /etc/default/opendkim
			mkdir -p /etc/opendkim/{keyfiles,dnstxt} 2> /dev/null
			touch /etc/opendkim/{KeyTable,SigningTable}
			install -m 755 opendkim/conf/opendkim-keycontrol /usr/local/bin/
			install -m 644 opendkim/conf/opendkim.conf /etc/opendkim.conf
			;;
		spamassassin)
			cp spamassassin/conf/local.cf /etc/spamassassin/local.cf
			sed -i '/^OPTIONS=/s/=.*/="--create-prefs --max-children 5 --helper-home-dir"/' /etc/default/spamassassin
			sed -i '/^CRON=/s/=.*/="1"/' /etc/default/spamassassin
			sed -i '/^ENABLED=/s/=.*/="1"/' /etc/default/spamassassin
			# Thanks to mf3hd@GitHub
			[[ -z $(grep RANDOM_DELAY /etc/crontab) ]] && sed -i '/SHELL/a RANDOM_DELAY=30' /etc/crontab
			install -m 755 spamassassin/conf/spamlearn /etc/cron.daily/spamlearn
			install -m 755 spamassassin/conf/spamassassin_heinlein /etc/cron.daily/spamassassin_heinlein
			;;
		webserver)
			if [[ $conf_httpd == "nginx" ]]; then
				rm /etc/nginx/sites-enabled/000-0-fufix 2>/dev/null
				cp webserver/nginx/conf/sites-available/fufix /etc/nginx/sites-available/
				ln -s /etc/nginx/sites-available/fufix /etc/nginx/sites-enabled/000-0-fufix
				[[ ! -z $(grep "client_max_body_size" /etc/nginx/nginx.conf) ]] && \
					sed -i "/client_max_body_size/c\ \ \ \ \ \ \ \ client_max_body_size 25M;" /etc/nginx/nginx.conf || \
					sed -i "/http {/a\ \ \ \ \ \ \ \ client_max_body_size 25M;" /etc/nginx/nginx.conf
				sed -i "s/_;/$sys_hostname.$sys_domain;/g" /etc/nginx/sites-available/fufix
			elif [[ $conf_httpd == "apache2" ]]; then
				rm /etc/apache2/sites-enabled/000-0-fufix 2>/dev/null
				cp webserver/apache2/conf/sites-available/fufix /etc/apache2/sites-available/
				ln -s /etc/apache2/sites-available/fufix /etc/apache2/sites-enabled/000-0-fufix.conf
				sed -i "s/\"\*\"/\"$sys_hostname.$sys_domain\"/g" /etc/apache2/sites-available/fufix
                sed -i "s/\"autoconfig.domain.tld\"/\"autoconfig.$sys_domain\"/g" /etc/apache2/sites-available/fufix
				a2enmod rewrite ssl proxy > /dev/null 2>&1
			fi
			cp php5-fpm/conf/pool/mail.conf /etc/php5/fpm/pool.d/mail.conf
			cp php5-fpm/conf/php-fpm.conf /etc/php5/fpm/php-fpm.conf
			mkdir /var/lib/php5/sessions 2> /dev/null
			chown -R www-data:www-data /var/lib/php5/sessions
			sed -i "/date.timezone/c\php_admin_value[date.timezone] = $sys_timezone" /etc/php5/fpm/pool.d/mail.conf
			;;
		postfixadmin)
			rm -rf /var/www/mail 2> /dev/null
			tar xf pfadmin/inst/$postfixadmin_revision.tar -C pfadmin/inst/
			mkdir -p /var/www/mail/pfadmin /var/run/fetchmail /etc/mail/postfixadmin 2> /dev/null
			cp -R webserver/htdocs/{fcc,index.php,robots.txt,autoconfig.xml} /var/www/mail/
			touch /var/www/{VT_API_KEY,VT_ENABLE,VT_ENABLE_UPLOAD}
			mv pfadmin/inst/$postfixadmin_revision/* /var/www/mail/pfadmin/
			install -m 755 /var/www/mail/pfadmin/ADDITIONS/fetchmail.pl /usr/local/bin/fetchmail.pl
			install -m 644 pfadmin/conf/config.local.php /var/www/mail/pfadmin/config.local.php
			install -m 644 pfadmin/conf/fetchmail.conf /etc/mail/postfixadmin/fetchmail.conf
			install -m 644 pfadmin/conf/pfadminfetchmail /etc/cron.d/pfadminfetchmail
            sed -i "s/fufix_sub/$sys_hostname/g" /var/www/mail/autoconfig.xml
			sed -i "s/my_postfixpass/$my_postfixpass/g" /var/www/mail/pfadmin/config.local.php /etc/mail/postfixadmin/fetchmail.conf
			sed -i "s/my_postfixuser/$my_postfixuser/g" /var/www/mail/pfadmin/config.local.php /etc/mail/postfixadmin/fetchmail.conf
			sed -i "s/my_postfixdb/$my_postfixdb/g" /var/www/mail/pfadmin/config.local.php /etc/mail/postfixadmin/fetchmail.conf
			sed -i "s/domain.tld/$sys_domain/g" /var/www/mail/pfadmin/config.local.php /etc/mail/postfixadmin/fetchmail.conf
			sed -i "s/change-this-to-your.domain.tld/$sys_domain/g" /var/www/mail/pfadmin/config.inc.php
			chown -R www-data: /var/www/ ; chown -R vmail: /var/run/fetchmail
			rm -rf pfadmin/inst/$postfixadmin_revision
			[[ -z $(grep fetchmail /etc/rc.local) ]] && sed -i '/^exit 0/i\test -d /var/run/fetchmail || install -m 755 -o vmail -g vmail -d /var/run/fetchmail/' /etc/rc.local
			;;
		roundcube)
			mkdir -p /var/www/mail/rc
			tar xf roundcube/inst/$roundcube_version.tar -C roundcube/inst/
			mv roundcube/inst/$roundcube_version/* /var/www/mail/rc/
			cp -R roundcube/conf/* /var/www/mail/rc/
			sed -i "s/my_postfixuser/$my_postfixuser/g" /var/www/mail/rc/plugins/password/config.inc.php
			sed -i "s/my_postfixpass/$my_postfixpass/g" /var/www/mail/rc/plugins/password/config.inc.php
			sed -i "s/my_postfixdb/$my_postfixdb/g" /var/www/mail/rc/plugins/password/config.inc.php
			sed -i "s/my_rcuser/$my_rcuser/g" /var/www/mail/rc/config/config.inc.php
			sed -i "s/my_rcpass/$my_rcpass/g" /var/www/mail/rc/config/config.inc.php
			sed -i "s/my_rcdb/$my_rcdb/g" /var/www/mail/rc/config/config.inc.php
			conf_rcdeskey=$(genpasswd)
			sed -i "s/conf_rcdeskey/$conf_rcdeskey/g" /var/www/mail/rc/config/config.inc.php
			sed -i "s/fufix_dfhost/$sys_hostname.$sys_domain/g" /var/www/mail/rc/config/config.inc.php
			sed -i "s/fufix_smtpsrv/$sys_hostname.$sys_domain/g" /var/www/mail/rc/config/config.inc.php
			chown -R www-data: /var/www/
			if [[ $(mysql --defaults-file=/etc/mysql/debian.cnf -s -N -e "use $my_rcdb; show tables;" | wc -l) -lt 5 ]]; then
				mysql -u $my_rcuser -p$my_rcpass $my_rcdb < /var/www/mail/rc/SQL/mysql.initial.sql
			fi
			rm -rf roundcube/inst/$roundcube_version
			rm -rf /var/www/mail/rc/installer/
			;;
		fail2ban)
			tar xf fail2ban/inst/$fail2ban_version.tar -C fail2ban/inst/
			rm -rf /etc/fail2ban/ 2> /dev/null
			(cd fail2ban/inst/$fail2ban_version ; python setup.py -q install 2> /dev/null)
			if [[ -f /lib/systemd/systemd ]]; then
				mkdir -p /var/run/fail2ban
				cp fail2ban/conf/fail2ban.service /lib/systemd/system/fail2ban.service
				systemctl enable fail2ban
			else
				cp fail2ban/conf/fail2ban.init /etc/init.d/fail2ban
				chmod +x /etc/init.d/fail2ban
				update-rc.d fail2ban defaults
			fi
			cp fail2ban/conf/jail.local /etc/fail2ban/jail.local
			rm -rf fail2ban/inst/$fail2ban_version
			;;
		rsyslogd)
			sed "s/*.*;auth,authpriv.none/*.*;auth,mail.none,authpriv.none/" -i /etc/rsyslog.conf
			;;
		restartservices)
			[[ -f /lib/systemd/systemd ]] && echo "$(textb [INFO]) - Restarting services, this may take a few seconds..."
			for var in fail2ban rsyslog $conf_httpd php5-fpm spamassassin mysql dovecot postfix opendkim clamav-daemon
			do
				service $var stop
				sleep 1.5
				service $var start
			done
			;;
		checkdns)
			if [[ -z $(dig -x $getpublicipv4 @8.8.8.8 | grep -i $sys_domain) ]]; then
				echo "$(yellowb [WARN]) - Remember to setup a PTR record: $getpublicipv4 does not point to $sys_domain (checked by Google DNS)" | tee -a installer.log
			fi
			if [[ -z $(dig $sys_hostname.$sys_domain @8.8.8.8 | grep -i $getpublicipv4) ]]; then
				echo "$(yellowb [WARN]) - Remember to setup A + MX records! (checked by Google DNS)" | tee -a installer.log
			else
				if [[ -z $(dig mx $sys_domain @8.8.8.8 | grep -i $sys_hostname.$sys_domain) ]] && [[ -z $(dig mx $sys_hostname.$sys_domain @8.8.8.8 | grep -i $sys_hostname.$sys_domain) ]]; then
					echo "$(yellowb [WARN]) - Remember to setup a MX record pointing to this server (checked by Google DNS)" | tee -a installer.log
				fi
			fi
			if [[ -z $(dig $sys_domain txt @8.8.8.8 | grep -i spf) ]]; then
				echo "$(textb [HINT]) - You may want to setup a TXT record for SPF (checked by Google DNS)" | tee -a installer.log
			fi
			if [[ ! -z $(host dbltest.com.dbl.spamhaus.org | grep NXDOMAIN) || ! -z $(cat /etc/resolv.conf | grep '^nameserver 8.8.') ]]; then
				echo "$(redb [CRIT]) - You either use Google DNS service or another blocked DNS provider for blacklist lookups. Consider using another DNS server for better spam detection." | tee -a installer.log
			fi
			;;
		setupsuperadmin)
			sed -i 's/E_ALL/E_ALL ^ E_NOTICE/g' /var/www/mail/pfadmin/scripts/postfixadmin-cli.php
			(cd /var/www/mail/pfadmin ; php setup.php 2> 1&>2 /dev/null)
			php /var/www/mail/pfadmin/scripts/postfixadmin-cli.php admin add $pfadmin_adminuser --password $pfadmin_adminpass --password2 $pfadmin_adminpass --superadmin
			;;
	esac
}
upgradetask() {
	if [[ ! -f /etc/fufix_version || -z $(cat /etc/fufix_version | grep -E "0.7|0.8|0.9") ]]; then
		echo "$(redb [ERR]) - Upgrade not supported"
		return 1
	fi
	if [[ ! -z $(which apache2) ]]; then
		conf_httpd="apache2"
	elif [[ ! -z $(which nginx) ]]; then
		conf_httpd="nginx"
	else
		conf_httpd="nginx"
	fi
	echo "$(textb [INFO]) - Installing prerequisites..."
	apt-get -y update > /dev/null ; apt-get -y install lsb-release > /dev/null 2>&1
	sys_hostname=$(hostname)
	sys_domain=$(hostname -d)
	sys_timezone=$(cat /etc/timezone)
	timestamp=$(date +%Y%m%d_%H%M%S)
	readconf=( $(php -f misc/readconf.php) )
	my_postfixuser=${readconf[0]}
	my_postfixpass=${readconf[1]}
	my_postfixdb=${readconf[2]}
	old_des_key_rc=${readconf[3]}
	my_rcuser=${readconf[4]}
	my_rcpass=${readconf[5]}
	my_rcdb=${readconf[6]}

	for var in conf_httpd sys_hostname sys_domain sys_timezone my_postfixdb my_postfixuser my_postfixpass my_rcuser my_rcpass my_rcdb
	do
		if [[ -z ${!var} ]]; then
			echo "$(redb [ERR]) - Could not gather required information, upgrade failed..."
			echo
			exit 1
		fi
	done
	echo -e "\nThe following configuration was detected:"
	echo "
$(textb "Hostname")        $sys_hostname
$(textb "Domain")          $sys_domain
$(textb "FQDN")            $sys_hostname.$sys_domain
$(textb "Timezone")        $sys_timezone
$(textb "Postfix MySQL")   ${my_postfixuser}:${my_postfixpass}/${my_postfixdb}
$(textb "Roundcube MySQL") ${my_rcuser}:${my_rcpass}/${my_rcdb}
$(textb "Web server")      ${conf_httpd^}

--------------------------------------------------------
THIS UPGRADE WILL RESET SOME OF YOUR CONFIGURATION FILES
--------------------------------------------------------
A backup will be stored in ./before_upgrade_$timestamp
--------------------------------------------------------
"
	read -p "Press ENTER to continue or CTRL-C to cancel the upgrade process"
	echo -en "Creating backups in ./before_upgrade_$timestamp... \t"
		mkdir before_upgrade_$timestamp
		cp -R /var/www/mail/ before_upgrade_$timestamp/mail_wwwroot
		mysqldump --defaults-file=/etc/mysql/debian.cnf --all-databases > backup_all_databases.sql 2>/dev/null
		cp -R /etc/{postfix,dovecot,spamassassin,fail2ban,$conf_httpd,mysql,php5} before_upgrade_$timestamp/
    echo -e "$(greenb "[OK]")"
	echo -en "\nStopping services, this may take a few seconds... \t\t"
	for var in fail2ban rsyslog $conf_httpd php5-fpm spamassassin dovecot postfix opendkim clamav-daemon
	do
		service $var stop > /dev/null 2>&1
	done
	echo -e "$(greenb "[OK]")"
    echo "Update CA certificate store (self-signed only)..."
	if [[ ! -z $(openssl x509 -issuer -in /etc/ssl/mail/mail.crt | grep $sys_hostname.$sys_domain ) ]]; then
		cp /etc/ssl/mail/mail.crt /usr/local/share/ca-certificates/
		update-ca-certificates
		returnwait "Update CA certificate store" "Package installation"
	else
		returnwait "Update CA certificate store (skipped)" "Package installation"
	fi

	installtask installpackages
    returnwait "Package installation" "Postfix configuration"

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
	returnwait "Webserver configuration" "Postfixadmin configuration"

	installtask postfixadmin
	returnwait "Postfixadmin configuration" "Roundcube configuration"

	installtask roundcube
	sed -i "s/conf_rcdeskey/$old_des_key_rc/g" /var/www/mail/rc/config/config.inc.php
	/var/www/mail/rc/bin/updatedb.sh --package=roundcube --dir=/var/www/mail/rc/SQL
	returnwait "Roundcube configuration" "OpenDKIM configuration"

	installtask opendkim
	returnwait "OpenDKIM configuration" "Fail2ban configuration"

	installtask fail2ban
	returnwait "Fail2ban configuration" "Restarting services"

	installtask restartservices
	returnwait "Restarting services" "Finish upgrade"
	mysql --defaults-file=/etc/mysql/debian.cnf -e "GRANT SELECT ON $my_postfixdb.* TO 'vmail'@'localhost'; FLUSH PRIVILEGES;"
	echo Done.
	echo
	echo "\"installer.log\" file updated."
	return 0
}
