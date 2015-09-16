textb() { echo $(tput bold)${1}$(tput sgr0); }
greenb() { echo $(tput bold)$(tput setaf 2)${1}$(tput sgr0); }
redb() { echo $(tput bold)$(tput setaf 1)${1}$(tput sgr0); }
yellowb() { echo $(tput bold)$(tput setaf 3)${1}$(tput sgr0); }
pinkb() { echo $(tput bold)$(tput setaf 5)${1}$(tput sgr0); }

usage() {
	echo "mailcow install script command-line parameters."
	echo $(textb "Do not append any parameters to run mailcow in default mode.")
	echo "
	--help | -h
		Print this text

	--upgrade | -u
		Upgrade mailcow to a newer version

	--upgrade-unattended | -uu
		Upgrade mailcow to a newer version unattended
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
	if [[ $(grep MemTotal /proc/meminfo | awk '{print $2}') -lt 600000 ]]; then
		echo "$(yellowb [WARN]) - At least ~600MB of memory is highly recommended"
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
	if [[ -z $(which mysql) ]];then
		echo "$(textb [INFO]) - Installing prerequisites for port checks"
		apt-get -y update > /dev/null ; apt-get -y install mysql-client > /dev/null 2>&1
	fi
	if [[ $(nc -z $my_dbhost 3306; echo $?) -eq 0 ]] && [[ $(mysql --host ${my_dbhost} -u root -p${my_rootpw} -e ""; echo $?) -ne 0 ]]; then
		echo "$(redb [ERR]) - Cannot connect to SQL database server at ${my_dbhost} with given root password"
		exit 1
	elif [[ $(nc -z $my_dbhost 3306; echo $?) -eq 0 ]] && [[ $(mysql --host ${my_dbhost} -u root -p${my_rootpw} -e ""; echo $?) -eq 0 ]]; then
		if [[ -z $(mysql --host ${my_dbhost} -u root -p${my_rootpw} -e "SHOW GRANTS" | grep "WITH GRANT OPTION") ]]; then
			echo "$(redb [ERR]) - SQL root user is missing GRANT OPTION"
			exit 1
		fi
		echo "$(textb [INFO]) - Successfully connected to SQL server at ${my_dbhost}"
		echo
		if [[ $my_dbhost == "localhost" || $my_dbhost == "127.0.0.1" ]] && [[ -z $(mysql -V | grep -i "mariadb") && $my_usemariadb == "yes" ]]; then
			echo "$(redb [ERR]) - Found MySQL server but \"my_usemariadb\" is \"yes\""
			exit 1
		elif [[ $my_dbhost == "localhost" || $my_dbhost == "127.0.0.1" ]] && [[ ! -z $(mysql -V | grep -i "mariadb") && $my_usemariadb != "yes" ]]; then
			echo "$(redb [ERR]) - Found MariaDB server but \"my_usemariadb\" is not \"yes\""
			exit 1
		fi
		mysql_useable=1
	fi
}

checkconfig() {
	if [[ ${#cert_country} -ne 2 ]]; then
		echo "$(redb [ERR]) - Country code must consist of exactly two characters (DE/US/UK etc.)"
		exit 1
	fi
	if [[ ${httpd_platform} != "nginx" && ${httpd_platform} != "apache2" ]]; then
		echo "$(redb [ERR]) - \"httpd_platform\" is neither nginx nor apache2"
		exit 1
	elif [[ ${httpd_platform} = "apache2" && -z $(apt-cache show apache2 | grep Version | grep "2.4") ]]; then
		echo "$(redb [ERR]) - Unable to install Apache 2.4, please use Nginx or upgrade your distribution"
		exit 1
	fi
	if [[ ${httpd_dav_subdomain} == ${sys_hostname} ]]; then
		echo "$(redb [ERR]) - \"httpd_dav_subdomain\" must not be \"sys_hostname\""
		exit 1
	fi
	for var in sys_hostname sys_domain sys_timezone my_dbhost my_mailcowdb my_mailcowuser my_mailcowpass my_rootpw my_rcuser my_rcpass my_rcdb mailcow_admin_user mailcow_admin_pass cert_country cert_state cert_city cert_org
	do
		if [[ -z ${!var} ]]; then
			echo "$(redb [ERR]) - Parameter $var must not be empty."
			echo
			exit 1
		fi
	done
	pass_count=$(grep -o "[0-9]" <<< $mailcow_admin_pass | wc -l)
	pass_chars=$(echo $mailcow_admin_pass | egrep "^.{8,255}" | \
	egrep "[ABCDEFGHIJKLMNOPQRSTUVXYZ]" | \
	egrep "[abcdefghijklmnopqrstuvxyz"] | \
	egrep "[0-9]")
	if [[ $pass_count -lt 2 || -z $pass_chars ]]; then
		echo "$(redb [ERR]) - mailcow administrator password does not meet password policy requirements (8 char., 2 num., UPPER- + lowercase)"
		echo
		exit 1
	fi
	if [[ $inst_debug == "yes" ]]; then
		set -x
	fi
	if [[ -z $(which rsyslogd) ]]; then
		echo "$(redb [ERR]) - Please install rsyslogd first"
		echo
		exit 1
	fi
}

installtask() {
	case $1 in
		environment)
			getpublicipv4=$(wget -t1 -T10 -q4O- icanhazip.com)
			if [[ ${getpublicipv4} =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
				cat > /etc/hosts<<'EOF'
127.0.0.1 localhost
::1 localhost ip6-localhost ip6-loopback
ff02::1 ip6-allnodes
ff02::2 ip6-allrouters
EOF
				echo ${getpublicipv4} ${sys_hostname}.${sys_domain} ${sys_hostname} >> /etc/hosts
				echo ${sys_hostname}.${sys_domain} > /etc/mailname
				getpublicipv6=$(wget -t2 -T1 -q6O- icanhazip.com)
					if is_ipv6 $getpublicipv6; then
						echo $getpublicipv6 ${sys_hostname}.${sys_domain} ${sys_hostname} >> /etc/hosts
					fi
			else
				echo "$(redb [ERR]) - Cannot set your hostname"
				exit 1
			fi
			echo "$(textb [INFO]) - Setting your hostname..."
			if [[ -f /lib/systemd/systemd ]]; then
				if [[ -z $(dpkg --get-selections | grep -E "^dbus.*install$") ]]; then
					apt-get update -y > /dev/null 2>&1 && apt-get -y install dbus > /dev/null 2>&1
				fi
				hostnamectl set-hostname ${sys_hostname}
			else
				echo ${sys_hostname} > /etc/hostname
				hostname ${sys_hostname} > /dev/null 2>&1
				service hostname.sh start > /dev/null 2>&1
			fi
			if [[ -f /usr/share/zoneinfo/${sys_timezone} ]] ; then
				echo ${sys_timezone} > /etc/timezone
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
			apt-get -y update > /dev/null ; apt-get -y install lsb-release whiptail apt-utils ssl-cert > /dev/null 2>&1
        		dist_codename=$(lsb_release -cs)
			dist_id=$(lsb_release -is)
			if [[ $dist_id == "Debian" ]]; then
				apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 7638D0442B90D010 > /dev/null 2>&1
				apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 8B48AD6246925553 > /dev/null 2>&1
			fi
			/usr/sbin/make-ssl-cert generate-default-snakeoil --force-overwrite
			# Detect and edit repos
			if [[ $dist_codename == "wheezy" ]] && [[ -z $(grep -E "^deb(.*)wheezy-backports(.*)" /etc/apt/sources.list) ]]; then
				echo "$(textb [INFO]) - Enabling wheezy-backports..."
				echo -e "\ndeb http://http.debian.net/debian wheezy-backports main" >> /etc/apt/sources.list
				apt-get -y update >/dev/null
			fi
			if [[ ! -z $(grep -E "^deb(.*)wheezy-backports(.*)" /etc/apt/sources.list) ]]; then
				echo "$(textb [INFO]) - Installing jq from wheezy-backports..."
				apt-get -y update >/dev/null ; apt-get -y --force-yes install jq -t wheezy-backports >/dev/null
			fi
			if [[ ${httpd_platform} == "apache2" ]]; then
				if [[ $dist_codename == "trusty" ]]; then
					echo "$(textb [INFO]) - Adding ondrej/apache2 repository..."
					echo "deb http://ppa.launchpad.net/ondrej/apache2/ubuntu trusty main" > /etc/apt/sources.list.d/ondrej.list
					apt-key adv --keyserver keyserver.ubuntu.com --recv E5267A6C > /dev/null 2>&1
					apt-get -y update >/dev/null
				fi
				webserver_backend="apache2 apache2-utils libapache2-mod-php5"
			elif [[ ${httpd_platform} == "nginx" ]]; then
				webserver_backend="nginx-extras php5-fpm"
			fi
			echo "$(textb [INFO]) - Installing packages unattended, please stand by, errors will be reported."
			if [[ $(lsb_release -is) == "Ubuntu" ]]; then
				echo "$(yellowb [WARN]) - You are running Ubuntu. The installation will not fail, though you may see a lot of output until the installation is finished."
			fi
			apt-get -y update >/dev/null
			if [[ $my_dbhost == "localhost" || $my_dbhost == "127.0.0.1" ]] && [[ $my_upgradetask != "yes" ]]; then
				if [[ $my_usemariadb == "yes" ]]; then
					database_backend="mariadb-client mariadb-server"
				else
					database_backend="mysql-client mysql-server"
				fi
			else
				database_backend=""
			fi
DEBIAN_FRONTEND=noninteractive apt-get --force-yes -y install zip jq dnsutils python-setuptools libmail-spf-perl libmail-dkim-perl \
openssl php-auth-sasl php-http-request php-mail php-mail-mime php-mail-mimedecode php-net-dime php-net-smtp \
php-net-socket php-net-url php-pear php-soap php5 php5-cli php5-common php5-curl php5-gd php5-imap php-apc subversion \
php5-intl php5-mcrypt php5-mysql php5-sqlite libawl-php php5-xmlrpc ${database_backend} ${webserver_backend} mailutils pyzor razor \
postfix postfix-mysql postfix-pcre postgrey pflogsumm spamassassin spamc sudo bzip2 curl mpack opendkim opendkim-tools unzip clamav-daemon \
fetchmail liblockfile-simple-perl libdbi-perl libmime-base64-urlsafe-perl libtest-tempdir-perl liblogger-syslog-perl bsd-mailx > /dev/null
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
            mkdir /etc/ssl/mail 2> /dev/null
			rm /etc/ssl/mail/* 2> /dev/null
			echo "$(textb [INFO]) - Generating 2048 bit DH parameters, this may take a while, please wait..."
			openssl dhparam -out /etc/ssl/mail/dhparams.pem 2048 2> /dev/null
			openssl req -new -newkey rsa:4096 -sha256 -days 1095 -nodes -x509 -subj "/C=$cert_country/ST=$cert_state/L=$cert_city/O=$cert_org/CN=${sys_hostname}.${sys_domain}" -keyout /etc/ssl/mail/mail.key  -out /etc/ssl/mail/mail.crt
			chmod 600 /etc/ssl/mail/mail.key
			cp /etc/ssl/mail/mail.crt /usr/local/share/ca-certificates/
			update-ca-certificates
			;;
		mysql)
			if [[ $mysql_useable -ne 1 ]]; then
				mysql --defaults-file=/etc/mysql/debian.cnf -e "UPDATE mysql.user SET Password=PASSWORD('$my_rootpw') WHERE USER='root'; FLUSH PRIVILEGES;"
			fi
			mysql --host ${my_dbhost} -u root -p${my_rootpw} -e "DROP DATABASE IF EXISTS $my_mailcowdb; DROP DATABASE IF EXISTS $my_rcdb;"
			mysql --host ${my_dbhost} -u root -p${my_rootpw} -e "CREATE DATABASE $my_mailcowdb; GRANT ALL PRIVILEGES ON $my_mailcowdb.* TO '$my_mailcowuser'@'%' IDENTIFIED BY '$my_mailcowpass';"
			mysql --host ${my_dbhost} -u root -p${my_rootpw} -e "CREATE DATABASE $my_rcdb; GRANT ALL PRIVILEGES ON $my_rcdb.* TO '$my_rcuser'@'%' IDENTIFIED BY '$my_rcpass';"
			mysql --host ${my_dbhost} -u root -p${my_rootpw} -e "GRANT SELECT ON $my_mailcowdb.* TO 'vmail'@'%'; FLUSH PRIVILEGES;"
			;;
		postfix)
			cp -R postfix/conf/* /etc/postfix/
			chown root:postfix "/etc/postfix/sql"; chmod 750 "/etc/postfix/sql"
			chown root:postfix "/etc/postfix/sql/mysql_virtual_alias_domain_catchall_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_alias_domain_catchall_maps.cf"
			chown root:postfix "/etc/postfix/sql/mysql_virtual_alias_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_alias_maps.cf"
			chown root:postfix "/etc/postfix/sql/mysql_virtual_alias_domain_mailbox_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_alias_domain_mailbox_maps.cf"
			chown root:postfix "/etc/postfix/sql/mysql_virtual_mailbox_limit_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_mailbox_limit_maps.cf"
			chown root:postfix "/etc/postfix/sql/mysql_virtual_mailbox_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_mailbox_maps.cf"
			chown root:postfix "/etc/postfix/sql/mysql_virtual_mxdomain_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_mxdomain_maps.cf"
			chown root:postfix "/etc/postfix/sql/mysql_virtual_alias_domain_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_alias_domain_maps.cf"
			chown root:postfix "/etc/postfix/sql/mysql_virtual_spamalias_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_spamalias_maps.cf"
			chown root:postfix "/etc/postfix/sql/mysql_virtual_domains_maps.cf"; chmod 640 "/etc/postfix/sql/mysql_virtual_domains_maps.cf"
			chown root:root "/etc/postfix/master.cf"; chmod 644 "/etc/postfix/master.cf"
			chown root:root "/etc/postfix/main.cf"; chmod 644 "/etc/postfix/main.cf"
			sed -i "s/MAILCOW_HOST.MAILCOW_DOMAIN/${sys_hostname}.${sys_domain}/g" /etc/postfix/* 2> /dev/null
			cp misc/mc_clean_spam_aliases /etc/cron.daily/mc_clean_spam_aliases
			cp misc/mc_pfset /usr/local/sbin/mc_pfset
			cp misc/mc_pflog_renew /usr/local/sbin/mc_pflog_renew
			chmod +x /usr/local/sbin/mc_pfset /usr/local/sbin/mc_pflog_renew
			chmod 700 /etc/cron.daily/mc_clean_spam_aliases
			sed -i "s/MAILCOW_DOMAIN/${sys_domain}/g" /etc/postfix/* 2> /dev/null
			sed -i "s/my_mailcowpass/$my_mailcowpass/g" /etc/postfix/sql/* /etc/cron.daily/mc_clean_spam_aliases
			sed -i "s/my_mailcowuser/$my_mailcowuser/g" /etc/postfix/sql/* /etc/cron.daily/mc_clean_spam_aliases
			sed -i "s/my_mailcowdb/$my_mailcowdb/g" /etc/postfix/sql/* /etc/cron.daily/mc_clean_spam_aliases
			sed -i "s/my_dbhost/$my_dbhost/g" /etc/postfix/sql/* /etc/cron.daily/mc_clean_spam_aliases
			postmap /etc/postfix/mailcow_sender_access
			chown www-data: /etc/postfix/mailcow_*
			chmod 755 /var/spool/
			sed -i "/%www-data/d" /etc/sudoers 2> /dev/null
			sed -i "/%vmail/d" /etc/sudoers 2> /dev/null
			echo '%www-data ALL=(ALL) NOPASSWD: /usr/bin/doveadm * sync *, /usr/local/sbin/mc_pfset *, /usr/bin/doveadm quota recalc -A, /usr/sbin/dovecot reload, /usr/sbin/postfix reload, /usr/local/sbin/mc_dkim_ctrl, /usr/local/sbin/mc_msg_size, /usr/local/sbin/mc_pflog_renew, /usr/local/sbin/mc_inst_cron, /usr/bin/tail * /opt/vfilter/log/vfilter.log' >> /etc/sudoers
			echo '%vmail ALL=(ALL) NOPASSWD: /usr/bin/spamc*' >> /etc/sudoers
			;;
		dovecot)
			[[ -z $(grep fs.inotify.max_user_instances /etc/sysctl.conf) ]] && echo "fs.inotify.max_user_instances=1024" >> /etc/sysctl.conf
			sysctl -p > /dev/null
			if [[ -f /lib/systemd/systemd ]]; then
				systemctl disable dovecot.socket  > /dev/null 2>&1
			fi
			rm -rf /etc/dovecot/*
			cp -R dovecot/conf/*.conf /etc/dovecot/
			userdel vmail 2> /dev/null
			groupdel vmail 2> /dev/null
			groupadd -g 5000 vmail
			useradd -g vmail -u 5000 vmail -d /var/vmail
			chmod 755 "/etc/dovecot/"
			chown root:dovecot "/etc/dovecot/dovecot-dict-sql.conf"; chmod 640 "/etc/dovecot/dovecot-dict-sql.conf"
			chown root:vmail "/etc/dovecot/dovecot-mysql.conf"; chmod 640 "/etc/dovecot/dovecot-mysql.conf"
			chown root:root "/etc/dovecot/dovecot.conf"; chmod 644 "/etc/dovecot/dovecot.conf"
			chown www-data:www-data "/etc/dovecot/mailcow_public_folder.conf"; chmod 644 "/etc/dovecot/mailcow_public_folder.conf"
			sed -i "s/MAILCOW_HOST.MAILCOW_DOMAIN/${sys_hostname}.${sys_domain}/g" /etc/dovecot/*
			sed -i "s/MAILCOW_DOMAIN/${sys_domain}/g" /etc/dovecot/*
			sed -i "s/my_mailcowpass/$my_mailcowpass/g" /etc/dovecot/*
			sed -i "s/my_mailcowuser/$my_mailcowuser/g" /etc/dovecot/*
			sed -i "s/my_mailcowdb/$my_mailcowdb/g" /etc/dovecot/*
			sed -i "s/my_dbhost/$my_dbhost/g" /etc/dovecot/*
			mkdir /etc/dovecot/conf.d 2> /dev/null
			mkdir -p /var/vmail/sieve 2> /dev/null
			mkdir -p /var/vmail/public 2> /dev/null
			if [ ! -f /var/vmail/public/dovecot-acl ]; then
				echo "anyone lrwstipekxa" > /var/vmail/public/dovecot-acl
			fi
			cp dovecot/conf/global.sieve /var/vmail/sieve/global.sieve
			touch /var/vmail/sieve/default.sieve
			install -m 755 misc/mc_msg_size /usr/local/sbin/mc_msg_size
			sievec /var/vmail/sieve/global.sieve
			chown -R vmail:vmail /var/vmail
			install -m 755 dovecot/conf/doverecalcq /etc/cron.daily/
			;;
		vfilter)
			mkdir -p /opt/vfilter 2> /dev/null
			install -m 755 vfilter/vfilter.sh /opt/vfilter/vfilter.sh
			install -m 644 vfilter/replies /opt/vfilter/replies
			install -m 600 vfilter/vfilter.conf /opt/vfilter/vfilter.conf
			sed -i "s/my_dbhost/$my_dbhost/g" /opt/vfilter/vfilter.conf
			install -m 755 -d /opt/vfilter/clamav_positives
			chown -R vmail:vmail /opt/vfilter
			;;
		clamav)
			usermod -a -G vmail clamav 2> /dev/null
			service clamav-freshclam stop > /dev/null 2>&1
			killall freshclam 2> /dev/null
			rm -f /var/lib/clamav/* 2> /dev/null
			sed -i '/DatabaseMirror/d' /etc/clamav/freshclam.conf
			echo "DatabaseMirror clamav.netcologne.de
DatabaseMirror clamav.internet24.eu
DatabaseMirror clamav.inode.at" >> /etc/clamav/freshclam.conf
			if [[ -f /etc/apparmor.d/usr.sbin.clamd || -f /etc/apparmor.d/local/usr.sbin.clamd ]]; then
				rm /etc/apparmor.d/usr.sbin.clamd > /dev/null 2>&1
				rm /etc/apparmor.d/local/usr.sbin.clamd > /dev/null 2>&1
				service apparmor restart > /dev/null 2>&1
			fi
			cp -f clamav/clamav-unofficial-sigs.sh /usr/local/bin/clamav-unofficial-sigs.sh
			chmod +x /usr/local/bin/clamav-unofficial-sigs.sh
			cp -f clamav/clamav-unofficial-sigs.conf /etc/clamav-unofficial-sigs.conf
			cp -f clamav/clamav-unofficial-sigs.8 /usr/share/man/man8/clamav-unofficial-sigs.8
			cp -f clamav/clamav-unofficial-sigs-cron /etc/cron.d/clamav-unofficial-sigs-cron
			cp -f clamav/clamav-unofficial-sigs-logrotate /etc/logrotate.d/clamav-unofficial-sigs-logrotate
			mkdir -p /var/log/clamav-unofficial-sigs 2> /dev/null
			freshclam 2> /dev/null
			;;
		opendkim)
			echo 'SOCKET="inet:10040@localhost"' > /etc/default/opendkim
			mkdir -p /etc/opendkim/{keyfiles,dnstxt} 2> /dev/null
			touch /etc/opendkim/{KeyTable,SigningTable}
			install -m 755 misc/mc_dkim_ctrl /usr/local/sbin/
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
			# Thanks to mf3hd@GitHub, again!
			chmod g+s /etc/spamassassin
			chown -R debian-spamd: /etc/spamassassin
			razor-admin -create -home /etc/razor -conf=/etc/razor/razor-agent.conf
			razor-admin -discover -home /etc/razor
			razor-admin -register -home /etc/razor
			su debian-spamd -c "pyzor --homedir /etc/mail/spamassassin/.pyzor discover 2> /dev/null"
			su debian-spamd -c "sa-update 2> /dev/null"
			if [[ -f /lib/systemd/systemd ]]; then
				systemctl enable spamassassin
			fi
			;;
		webserver)
			mkdir -p /var/www/ 2> /dev/null
			if [[ ${httpd_platform} == "nginx" ]]; then
				rm /etc/nginx/sites-enabled/{000-0-mailcow,000-0-fufix} 2>/dev/null
				cp webserver/nginx/conf/sites-available/mailcow /etc/nginx/sites-available/
				cp webserver/php5-fpm/conf/pool/mail.conf /etc/php5/fpm/pool.d/mail.conf
				cp webserver/php5-fpm/conf/php-fpm.conf /etc/php5/fpm/php-fpm.conf
				sed -i "/date.timezone/c\php_admin_value[date.timezone] = ${sys_timezone}" /etc/php5/fpm/pool.d/mail.conf
				ln -s /etc/nginx/sites-available/mailcow /etc/nginx/sites-enabled/000-0-mailcow 2>/dev/null
				[[ ! -z $(grep "client_max_body_size" /etc/nginx/nginx.conf) ]] && \
					sed -i "/client_max_body_size/c\ \ \ \ \ \ \ \ client_max_body_size 25M;" /etc/nginx/nginx.conf || \
					sed -i "/http {/a\ \ \ \ \ \ \ \ client_max_body_size 25M;" /etc/nginx/nginx.conf
				[[ ! -z $(grep "server_names_hash_bucket_size" /etc/nginx/nginx.conf) ]] && \
					sed -i "/server_names_hash_bucket_size/c\ \ \ \ \ \ \ \ server_names_hash_bucket_size 64;" /etc/nginx/nginx.conf || \
					sed -i "/http {/a\ \ \ \ \ \ \ \ server_names_hash_bucket_size 64;" /etc/nginx/nginx.conf
				sed -i "s/MAILCOW_HOST.MAILCOW_DOMAIN;/${sys_hostname}.${sys_domain};/g" /etc/nginx/sites-available/mailcow
				sed -i "s/MAILCOW_DAV_HOST.MAILCOW_DOMAIN;/${httpd_dav_subdomain}.${sys_domain};/g" /etc/nginx/sites-available/mailcow
				sed -i "s/MAILCOW_DOMAIN;/${sys_domain};/g" /etc/nginx/sites-available/mailcow
			elif [[ ${httpd_platform} == "apache2" ]]; then
				rm /etc/apache2/sites-enabled/{000-0-mailcow,000-0-fufix} 2>/dev/null
				cp webserver/apache2/conf/sites-available/mailcow /etc/apache2/sites-available/
				ln -s /etc/apache2/sites-available/mailcow /etc/apache2/sites-enabled/000-0-mailcow.conf 2>/dev/null
				sed -i "s/\"\MAILCOW_HOST.MAILCOW_DOMAIN\"/\"${sys_hostname}.${sys_domain}\"/g" /etc/apache2/sites-available/mailcow
				sed -i "s/\"\MAILCOW_DAV_HOST.MAILCOW_DOMAIN\"/\"${httpd_dav_subdomain}.${sys_domain}\"/g" /etc/apache2/sites-available/mailcow
				sed -i "s/\"autoconfig.MAILCOW_DOMAIN\"/\"autoconfig.${sys_domain}\"/g" /etc/apache2/sites-available/mailcow
				sed -i "s/MAILCOW_DOMAIN\"/${sys_domain}\"/g" /etc/apache2/sites-available/mailcow
				 sed -i "/date.timezone/c\php_value date.timezone ${sys_timezone}" /etc/apache2/sites-available/mailcow
				a2enmod rewrite ssl > /dev/null 2>&1
			fi
			mkdir /var/lib/php5/sessions 2> /dev/null
			chown -R www-data:www-data /var/lib/php5/sessions
			install -m 755 misc/mc_inst_cron /usr/local/sbin/mc_inst_cron
			cp -R webserver/htdocs/{mail,dav} /var/www/
			tar xf /var/www/dav/vendor.tar -C /var/www/dav/ ; rm /var/www/dav/vendor.tar
			find /var/www/{dav,mail} -type d -exec chmod 755 {} \;
			find /var/www/{dav,mail} -type f -exec chmod 644 {} \;
			sed -i "/date_default_timezone_set/c\date_default_timezone_set('${sys_timezone}');" /var/www/dav/server.php
			touch /var/www/{VT_API_KEY,VT_ENABLE,VT_ENABLE_UPLOAD,CAV_ENABLE,MAILBOX_BACKUP}
			cp misc/mc_resetadmin /usr/local/sbin/mc_resetadmin ; chmod 700 /usr/local/sbin/mc_resetadmin
			sed -i "s/mailcow_sub/${sys_hostname}/g" /var/www/mail/autoconfig.xml
			sed -i "s/my_dbhost/$my_dbhost/g" /var/www/mail/inc/vars.inc.php /var/www/dav/server.php /usr/local/sbin/mc_resetadmin
			sed -i "s/my_mailcowpass/$my_mailcowpass/g" /var/www/mail/inc/vars.inc.php /var/www/dav/server.php /usr/local/sbin/mc_resetadmin
			sed -i "s/my_mailcowuser/$my_mailcowuser/g" /var/www/mail/inc/vars.inc.php /var/www/dav/server.php /usr/local/sbin/mc_resetadmin
			sed -i "s/my_mailcowdb/$my_mailcowdb/g" /var/www/mail/inc/vars.inc.php /var/www/dav/server.php /usr/local/sbin/mc_resetadmin
			sed -i "s/httpd_dav_subdomain/$httpd_dav_subdomain/g" /var/www/mail/inc/vars.inc.php
			chown -R www-data: /var/www/{mail,dav,VT_API_KEY,VT_ENABLE,VT_ENABLE_UPLOAD,CAV_ENABLE,MAILBOX_BACKUP} /var/lib/php5/sessions
			chown www-data: /var/www/
			mysql --host ${my_dbhost} -u ${my_mailcowuser} -p${my_mailcowpass} ${my_mailcowdb} < webserver/htdocs/init.sql
			if [[ -z $(mysql --host ${my_dbhost} -u ${my_mailcowuser} -p${my_mailcowpass} ${my_mailcowdb} -e "SHOW INDEX FROM propertystorage WHERE KEY_NAME = 'path_property';" -N -B) ]]; then
				mysql --host ${my_dbhost} -u ${my_mailcowuser} -p${my_mailcowpass} ${my_mailcowdb} -e "CREATE UNIQUE INDEX path_property ON propertystorage (path(600), name(100));" -N -B
			fi
			if [[ $(mysql --host ${my_dbhost} -u ${my_mailcowuser} -p${my_mailcowpass} ${my_mailcowdb} -s -N -e "SELECT * FROM admin;" | wc -l) -lt 1 ]]; then
				mailcow_admin_pass_hashed=$(doveadm pw -s SHA512-CRYPT -p $mailcow_admin_pass)
				mysql --host ${my_dbhost} -u ${my_mailcowuser} -p${my_mailcowpass} ${my_mailcowdb} -e "INSERT INTO admin VALUES ('$mailcow_admin_user','$mailcow_admin_pass_hashed',1,now(),now(),1);"
				mysql --host ${my_dbhost} -u ${my_mailcowuser} -p${my_mailcowpass} ${my_mailcowdb} -e "INSERT INTO domain_admins (username, domain, created, active) VALUES ('$mailcow_admin_user', 'ALL', now(), '1');"
			else
				echo "$(textb [INFO]) - At least one administrator exists, will not create another mailcow administrator"
			fi
			# Cleaning up old files
			sed -i '/test -d /var/run/fetchmail/d' /etc/rc.local > /dev/null 2>&1
			rm /etc/cron.d/pfadminfetchmail > /dev/null 2>&1
			rm /etc/mail/postfixadmin/fetchmail.conf > /dev/null 2>&1
			rm /usr/local/bin/fetchmail.pl > /dev/null 2>&1
			;;
		roundcube)
			mkdir -p /var/www/mail/rc
			tar xf roundcube/inst/${roundcube_version}.tar -C roundcube/inst/
			cp -R roundcube/inst/${roundcube_version}/* /var/www/mail/rc/
			if [[ $my_upgradetask != "yes" ]]; then
				cp -R roundcube/conf/* /var/www/mail/rc/
				sed -i "s/my_mailcowuser/$my_mailcowuser/g" /var/www/mail/rc/plugins/password/config.inc.php
				sed -i "s/my_mailcowpass/$my_mailcowpass/g" /var/www/mail/rc/plugins/password/config.inc.php
				sed -i "s/my_mailcowdb/$my_mailcowdb/g" /var/www/mail/rc/plugins/password/config.inc.php
				sed -i "s/my_dbhost/$my_dbhost/g" /var/www/mail/rc/config/config.inc.php
				sed -i "s/my_rcuser/$my_rcuser/g" /var/www/mail/rc/config/config.inc.php
				sed -i "s/my_rcpass/$my_rcpass/g" /var/www/mail/rc/config/config.inc.php
				sed -i "s/my_rcdb/$my_rcdb/g" /var/www/mail/rc/config/config.inc.php
				sed -i "s/conf_rcdeskey/$(genpasswd)/g" /var/www/mail/rc/config/config.inc.php
				sed -i "s/MAILCOW_HOST.MAILCOW_DOMAIN/${sys_hostname}.${sys_domain}/g" /var/www/mail/rc/config/config.inc.php
				mysql --host ${my_dbhost} -u ${my_rcuser} -p${my_rcpass} ${my_rcdb} < /var/www/mail/rc/SQL/mysql.initial.sql
			else
				chmod +x roundcube/inst/${roundcube_version}/bin/installto.sh
				roundcube/inst/${roundcube_version}/bin/installto.sh /var/www/mail/rc
			fi
			chown -R www-data: /var/www/
			rm -rf roundcube/inst/${roundcube_version}
			rm -rf /var/www/mail/rc/installer/
			;;
		rsyslogd)
			if [[ -d /etc/rsyslog.d ]]; then
				rm /etc/rsyslog.d/10-fufix > /dev/null 2>&1
				cp rsyslog/conf/10-mailcow /etc/rsyslog.d/
				service rsyslog restart > /dev/null 2>&1
				postlog -p warn dummy > /dev/null 2>&1
				postlog -p info dummy > /dev/null 2>&1
				postlog -p err dummy > /dev/null 2>&1
			fi
			;;
		fail2ban)
			tar xf fail2ban/inst/${fail2ban_version}.tar -C fail2ban/inst/
			rm -rf /etc/fail2ban/ 2> /dev/null
			(cd fail2ban/inst/${fail2ban_version} ; python setup.py -q install 2> /dev/null)
			if [[ -f /lib/systemd/systemd ]]; then
				mkdir -p /var/run/fail2ban
				cp fail2ban/conf/fail2ban.service /lib/systemd/system/fail2ban.service
				systemctl enable fail2ban
			else
				cp fail2ban/conf/fail2ban.init /etc/init.d/fail2ban
				chmod +x /etc/init.d/fail2ban
				update-rc.d fail2ban defaults
			fi
			if [[ ! -f /var/log/mail.warn ]]; then
				touch /var/log/mail.warn
			fi
			if [[ ! -f /etc/fail2ban/jail.local ]]; then
				cp fail2ban/conf/jail.local /etc/fail2ban/jail.local
			fi
			cp fail2ban/conf/jail.d/*.conf /etc/fail2ban/jail.d/
			rm -rf fail2ban/inst/${fail2ban_version}
			[[ -z $(grep fail2ban /etc/rc.local) ]] && sed -i '/^exit 0/i\test -d /var/run/fail2ban || install -m 755 -d /var/run/fail2ban/' /etc/rc.local
			mkdir /var/run/fail2ban/ 2> /dev/null
			;;
		restartservices)
			[[ -f /lib/systemd/systemd ]] && echo "$(textb [INFO]) - Restarting services, this may take a few seconds..."
			if [[ ${httpd_platform} == "nginx" ]]; then
				fpm="php5-fpm"
			else
				fpm=""
			fi
			for var in fail2ban rsyslog ${httpd_platform} ${fpm} spamassassin dovecot postfix opendkim clamav-daemon
			do
				service $var stop
				sleep 1.5
				service $var start
			done
			;;
		checkdns)
			if [[ -z $(dig -x ${getpublicipv4} @8.8.8.8 | grep -i ${sys_domain}) ]]; then
				echo "$(yellowb [WARN]) - Remember to setup a PTR record: ${getpublicipv4} does not point to ${sys_domain} (checked by Google DNS)" | tee -a installer.log
			fi
			for srv in _carddavs _caldavs _imap _imaps _submission _pop3 _pop3s
			do
				if [[ -z $(dig srv ${srv}._tcp.${sys_domain} @8.8.8.8 +short) ]]; then
					echo "$(textb [INFO]) - Cannot find SRV record \"${srv}._tcp.${sys_domain}\""
				fi
			done
			if [[ -z $(dig ${sys_hostname}.${sys_domain} @8.8.8.8 | grep -i ${getpublicipv4}) ]]; then
				echo "$(yellowb [WARN]) - Remember to setup A + MX records! (checked by Google DNS)" | tee -a installer.log
			else
				if [[ -z $(dig mx ${sys_domain} @8.8.8.8 | grep -i ${sys_hostname}.${sys_domain}) ]] && [[ -z $(dig mx ${sys_hostname}.${sys_domain} @8.8.8.8 | grep -i ${sys_hostname}.${sys_domain}) ]]; then
					echo "$(yellowb [WARN]) - Remember to setup a MX record pointing to this server (checked by Google DNS)" | tee -a installer.log
				fi
			fi
			if [[ -z $(dig ${sys_domain} txt @8.8.8.8 | grep -i spf) ]]; then
				echo "$(textb [HINT]) - You may want to setup a TXT record for SPF (checked by Google DNS)" | tee -a installer.log
			fi
			if [[ ! -z $(host dbltest.com.dbl.spamhaus.org | grep NXDOMAIN) || ! -z $(cat /etc/resolv.conf | grep -E '^nameserver 8.8.|^nameserver 208.67.2') ]]; then
				echo "$(redb [CRIT]) - You either use OpenDNS, Google DNS or another blocked DNS provider for blacklist lookups. Consider using another DNS server for better spam detection." | tee -a installer.log
			fi
			;;
	esac
}
upgradetask() {
	if [[ ! -f /etc/mailcow_version && ! -f /etc/fufix_version ]]; then
		echo "$(redb [ERR]) - mailcow is not installed"
		exit 1
	fi
	if [[ -z $(cat /etc/{fufix_version,mailcow_version} 2> /dev/null | grep -E "0.9|0.10|0.11|0.12") ]]; then
		echo "$(redb [ERR]) - Upgrade not supported"
		exit 1
	fi
	if [[ ! -z $(which apache2) && ! -z $(apache2 -v | grep "2.4") ]]; then
		httpd_platform="apache2"
	elif [[ ! -z $(which nginx) ]]; then
		httpd_platform="nginx"
	else
		echo "$(pinkb [NOTICE]) - Falling back to Nginx: Apache 2.4 was not available!"
		httpd_platform="nginx"
	fi
	echo "$(textb [INFO]) - Checking for upgrade prerequisites and collecting system information..."
	if [[ -z $(which lsb_release) ]]; then
		apt-get -y update > /dev/null ; apt-get -y install lsb-release > /dev/null 2>&1
	fi
	sys_hostname=$(hostname)
	sys_domain=$(hostname -d)
	sys_timezone=$(cat /etc/timezone)
	timestamp=$(date +%Y%m%d_%H%M%S)
	readconf=( $(php -f misc/readconf.php) )
	my_dbhost=${readconf[0]}
	my_mailcowuser=${readconf[1]}
	my_mailcowpass=${readconf[2]}
	my_mailcowdb=${readconf[3]}
	old_des_key_rc=${readconf[4]}
	my_rcuser=${readconf[5]}
	my_rcpass=${readconf[6]}
	my_rcdb=${readconf[7]}
	httpd_dav_subdomain=${readconf[8]}
	[[ -z $my_dbhost ]] && my_dbhost="localhost"
	my_upgradetask="yes"
	for var in httpd_platform httpd_dav_subdomain sys_hostname sys_domain sys_timezone my_dbhost my_mailcowdb my_mailcowuser my_mailcowpass my_rcuser my_rcpass my_rcdb
	do
		if [[ -z ${!var} ]]; then
			echo "$(redb [ERR]) - Could not gather required information: \"${var}\" empty, upgrade failed..."
			echo
			exit 1
		fi
	done
	echo -e "\nThe following configuration was detected:"
	echo "
$(textb "Hostname")        ${sys_hostname}
$(textb "Domain")          ${sys_domain}
$(textb "FQDN")            ${sys_hostname}.${sys_domain}
$(textb "Timezone")        ${sys_timezone}
$(textb "mailcow MySQL")   ${my_mailcowuser}:${my_mailcowpass}@${my_dbhost}/${my_mailcowdb}
$(textb "Roundcube MySQL") ${my_rcuser}:${my_rcpass}@${my_dbhost}/${my_rcdb}
$(textb "Web server")      ${httpd_platform^}
$(textb "Web root")        https://${sys_hostname}.${sys_domain}
$(textb "DAV web root")    https://${httpd_dav_subdomain}.${sys_domain}

--------------------------------------------------------
THIS UPGRADE WILL RESET SOME OF YOUR CONFIGURATION FILES
--------------------------------------------------------
A backup will be stored in ./before_upgrade_$timestamp
--------------------------------------------------------
"
	if [[ $inst_unattended != "yes" ]]; then
		read -p "Press ENTER to continue or CTRL-C to cancel the upgrade process"
	fi
	echo -en "Creating backups in ./before_upgrade_$timestamp... \t"
	mkdir before_upgrade_$timestamp
	cp -R /var/www/mail/ before_upgrade_$timestamp/mail_wwwroot
	mysqldump -u ${my_mailcowuser} -p${my_mailcowpass} ${my_mailcowdb} > backup_mailcow_db.sql 2>/dev/null
	mysqldump -u ${my_rcuser} -p${my_rcpass} ${my_rcdb} > backup_roundcube_db.sql 2>/dev/null
	cp -R /etc/{postfix,dovecot,spamassassin,fail2ban,${httpd_platform},mysql,php5,clamav} before_upgrade_$timestamp/
	echo -e "$(greenb "[OK]")"
	echo -en "\nStopping services, this may take a few seconds... \t\t"
	if [[ ${httpd_platform} == "nginx" ]]; then
		fpm="php5-fpm"
	else
		fpm=""
	fi
	for var in fail2ban rsyslog ${httpd_platform} ${fpm} spamassassin dovecot postfix opendkim clamav-daemon
	do
		service $var stop > /dev/null 2>&1
	done
	echo -e "$(greenb "[OK]")"
	if [[ ! -z $(openssl x509 -issuer -in /etc/ssl/mail/mail.crt | grep ${sys_hostname}.${sys_domain} ) ]]; then
		echo "$(textb [INFO]) - Update CA certificate store (self-signed only)..."
		cp /etc/ssl/mail/mail.crt /usr/local/share/ca-certificates/
		update-ca-certificates
	fi
	if [[ ! -f /etc/ssl/mail/dhparams.pem ]]; then
		echo "$(textb [INFO]) - Generating 2048 bit DH parameters, this may take a while, please wait..."
		openssl dhparam -out /etc/ssl/mail/dhparams.pem 2048 2> /dev/null
	fi

	echo "Starting task \"Package installation\"..."
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
	rm -rf /var/lib/php5/sessions/*
	returnwait "Webserver configuration" "Roundcube configuration"

	installtask roundcube
	returnwait "Roundcube configuration" "OpenDKIM configuration"

	installtask opendkim
	returnwait "OpenDKIM configuration" "Rsyslogd configuration"

	installtask rsyslogd
	returnwait "Rsyslogd configuration" "Fail2ban configuration"

	installtask fail2ban
	# restore user configuration (*.local)
	cp before_upgrade_$timestamp/fail2ban/*.local /etc/fail2ban/
	cp before_upgrade_$timestamp/fail2ban/action.d/*.local /etc/fail2ban/action.d/ 2> /dev/null
	cp before_upgrade_$timestamp/fail2ban/filter.d/*.local /etc/fail2ban/filter.d/ 2> /dev/null
	cp before_upgrade_$timestamp/fail2ban/jail.d/*.local /etc/fail2ban/jail.d/ 2> /dev/null
	returnwait "Fail2ban configuration" "Restarting services"

	installtask restartservices
	returnwait "Restarting services" "Finish upgrade"
	echo Done.
	echo
	echo "\"installer.log\" file updated."
	return 0
}
