textb() {
	echo $(tput bold)${1}$(tput sgr0);
}

greenb() {
	echo $(tput bold)$(tput setaf 2)${1}$(tput sgr0);
}

redb() {
	echo $(tput bold)$(tput setaf 1)${1}$(tput sgr0);
}

yellowb() {
	echo $(tput bold)$(tput setaf 3)${1}$(tput sgr0);
}

pinkb() {
	echo $(tput bold)$(tput setaf 5)${1}$(tput sgr0);
}

usage() {
	echo "mailcow install script command-line parameters."
	echo $(textb "Do not append any parameters to run mailcow in default mode.")
 	echo "./install.sh [ACTION] [PARAMETERS]"
	echo '
	ACTIONS:
	-h | -?
		Print this text

	-u
		Upgrade mailcow to a newer version

	-U
		Upgrade mailcow to a newer version
		and do not ask to press any key to continue

	-s	Retry to obtain Lets Encrypt certificates

	PARAMETERS:
	Note: Only available when upgrading
		-H hostname
			Overwrite hostname detection

		-D example.org
			Overwrite domain detection

	EXAMPLES:
		Upgrade using mail.example.org as FQDN:
		./install.sh -u -H mail -D example.org
	'
}

genpasswd() {
	count=0
	while [ $count -lt 3 ]; do
		pw_valid=$(tr -cd A-Za-z0-9 < /dev/urandom | fold -w24 | head -n1)
		count=$(grep -o "[0-9]" <<< $pw_valid | wc -l)
	done
	echo $pw_valid
}

returnwait_task=""
returnwait() {
	if [[ ! -z "${returnwait_task}" ]]; then
		echo "$(greenb [OK]) - Task $(textb "${returnwait_task}") completed"
		echo "----------------------------------------------"
	fi
	returnwait_task="${1}"
	if [[ ${inst_confirm_proceed} == "yes" && "$2" != "no" ]]; then
		read -p "$(yellowb !) Press ENTER to continue with task $(textb "${returnwait_task}") (CTRL-C to abort) "
	fi
	echo "$(pinkb [RUNNING]) - Task $(textb "${returnwait_task}") started, please wait..."
}

checksystem() {
	if [[ $(grep MemTotal /proc/meminfo | awk '{print $2}') -lt 800000 ]]; then
		echo "$(yellowb [WARN]) - At least 800MB of memory is highly recommended"
		read -p "Press ENTER to skip this warning or CTRL-C to cancel the process"
	fi
	[[ ! -z $(ip -6 addr | grep "scope global") ]] && IPV6="yes"

}

checkports() {
	if [[ -z $(which mysql) ]] || [[ -z $(which dig) ]] || [[ -z $(which nc) ]]; then
		echo "$(textb [INFO]) - Installing prerequisites for DNS and port checks"
		apt-get -y update > /dev/null ; apt-get -y install curl nc dnsutils mysql-client > /dev/null 2>&1
	fi
	for port in 25 143 465 587 993 995 8983
	do
		if [[ $(nc -z localhost $port; echo $?) -eq 0 ]]; then
			echo "$(redb [ERR]) - An application is blocking the installation on Port $(textb $port)"
			# Wait until finished to list all blocked ports.
			blocked_port=1
		fi
	done
	[[ ${blocked_port} -eq 1 ]] && exit 1
	if [[ $(nc -z ${my_dbhost} 3306; echo $?) -eq 0 ]] && [[ $(mysql --host ${my_dbhost} -u root -p${my_rootpw} -e ""; echo $?) -ne 0 ]]; then
		echo "$(redb [ERR]) - Cannot connect to SQL database server at ${my_dbhost} with given root password"
		exit 1
	elif [[ $(nc -z ${my_dbhost} 3306; echo $?) -eq 0 ]] && [[ $(mysql --host ${my_dbhost} -u root -p${my_rootpw} -e ""; echo $?) -eq 0 ]]; then
		if [[ -z $(mysql --host ${my_dbhost} -u root -p${my_rootpw} -e "SHOW GRANTS" | grep "WITH GRANT OPTION") ]]; then
			echo "$(redb [ERR]) - SQL root user is missing GRANT OPTION"
			exit 1
		fi
		echo "$(textb [INFO]) - Successfully connected to SQL server at ${my_dbhost}"
		echo
		if [[ ${my_dbhost} == "localhost" || ${my_dbhost} == "127.0.0.1" ]] && [[ -z $(mysql -V | grep -i "mariadb") && ${my_usemariadb} == "yes" ]]; then
			echo "$(redb [ERR]) - Found MySQL server but \"my_usemariadb\" is \"yes\""
			exit 1
		elif [[ ${my_dbhost} == "localhost" || ${my_dbhost} == "127.0.0.1" ]] && [[ ! -z $(mysql -V | grep -i "mariadb") && ${my_usemariadb} != "yes" ]]; then
			echo "$(redb [ERR]) - Found MariaDB server but \"my_usemariadb\" is not \"yes\""
			exit 1
		fi
		mysql_useable=1
	fi
}

checkconfig() {
	if [[ ${httpd_platform} != "nginx" && ${httpd_platform} != "apache2" ]]; then
		echo "$(redb [ERR]) - \"httpd_platform\" is neither nginx nor apache2"
		exit 1
	elif [[ ${httpd_platform} = "apache2" && -z $(apt-cache show apache2 | grep Version | grep "2.4") ]]; then
		echo "$(redb [ERR]) - Unable to install Apache 2.4, please use Nginx or upgrade your distribution"
		exit 1
	fi
	for var in sys_hostname sys_domain sys_timezone my_dbhost my_mailcowdb my_mailcowuser my_mailcowpass my_rootpw my_rcuser my_rcpass my_rcdb mailcow_admin_user mailcow_admin_pass
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
}

installtask() {
	case $1 in
		environment)
			[[ -z $(grep fs.inotify.max_user_instances /etc/sysctl.conf) ]] && echo "fs.inotify.max_user_instances=1024" >> /etc/sysctl.conf
			sysctl -p > /dev/null 2>&1
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
			mkdir -p /var/mailcow/log;
			echo "${sys_hostname}.${sys_domain}" > /etc/mailname
			echo "$(textb [INFO]) - Installing prerequisites..."
			apt-get -y update > /dev/null ; apt-get -y install lsb-release whiptail apt-utils ssl-cert > /dev/null 2>&1
			dist_codename=$(lsb_release -cs)
			dist_id=$(lsb_release -is)
			;;
		installpackages)
			if [[ ! -z $(apt-cache search --names-only '^php5-cli$') ]]; then
				PHP="php5"
				PHPCONF="/etc/php5"
				PHPLIB="/var/lib/php5"
			else
				PHP="php"
				PHPCONF="/etc/php/7.0"
				PHPLIB="/var/lib/php"
			fi
			if [[ $dist_id == "Debian" ]]; then
				if [[ $dist_codename == "jessie" ]]; then
					if [[ ${httpd_platform} == "apache2" ]]; then
						WEBSERVER_BACKEND="apache2 apache2-utils libapache2-mod-${PHP}"
					else
						WEBSERVER_BACKEND="nginx-extras ${PHP}-fpm"
					fi
					SQLITE="sqlite"
					OPENJDK="openjdk-7"
					JETTY_NAME="jetty8"
				else
					echo "$(redb [ERR]) - Your Debian distribution is currently not supported"
					exit 1
				fi
			elif [[ $dist_id == "Ubuntu" ]]; then
				if [[ $dist_codename == "trusty" ]]; then
					if [[ ${httpd_platform} == "apache2" ]]; then
						echo "$(textb [INFO]) - Adding ondrej/apache2 repository..."
						echo "deb http://ppa.launchpad.net/ondrej/apache2/ubuntu trusty main" > /etc/apt/sources.list.d/ondrej.list
						apt-key adv --keyserver keyserver.ubuntu.com --recv E5267A6C > /dev/null 2>&1
						apt-get -y update >/dev/null
						WEBSERVER_BACKEND="apache2 apache2-utils libapache2-mod-${PHP}"
					else
						WEBSERVER_BACKEND="nginx-extras ${PHP}-fpm"
					fi
					SQLITE="sqlite"
					OPENJDK="openjdk-7"
					JETTY_NAME="jetty"
				elif [[ $dist_codename == "xenial" ]]; then
					if [[ ${httpd_platform} == "apache2" ]]; then
						WEBSERVER_BACKEND="apache2 apache2-utils libapache2-mod-${PHP}"
					else
						WEBSERVER_BACKEND="nginx-extras ${PHP}-fpm"
					fi
					SQLITE="sqlite3"
					OPENJDK="openjdk-9"
					JETTY_NAME="jetty8"
				else
					echo "$(redb [ERR]) - Your Ubuntu distribution is currently not supported"
					exit 1
				fi
				echo "$(yellowb [WARN]) - You are running Ubuntu. The installation will not fail, though you may see a lot of output until the installation is finished."
			fi
			/usr/sbin/make-ssl-cert generate-default-snakeoil --force-overwrite
			echo "$(textb [INFO]) - Installing packages unattended, please stand by, errors will be reported."
			apt-get -y update >/dev/null
			if [[ ${my_dbhost} == "localhost" || ${my_dbhost} == "127.0.0.1" ]] && [[ $is_upgradetask != "yes" ]]; then
				if [[ ${my_usemariadb} == "yes" ]]; then
					DATABASE_BACKEND="mariadb-client mariadb-server"
				else
					DATABASE_BACKEND="mysql-client mysql-server"
				fi
			else
				DATABASE_BACKEND=""
			fi
DEBIAN_FRONTEND=noninteractive apt-get --force-yes -y install zip dnsutils python-setuptools libmail-spf-perl libmail-dkim-perl file \
openssl php-auth-sasl php-http-request php-mail php-mail-mime php-mail-mimedecode php-net-dime php-net-smtp \
php-net-socket php-net-url php-pear php-soap ${PHP} ${PHP}-cli ${PHP}-common ${PHP}-curl ${PHP}-gd ${PHP}-imap subversion \
${PHP}-intl ${PHP}-xsl libawl-php ${PHP}-mcrypt ${PHP}-mysql ${PHP}-${SQLITE} libawl-php ${PHP}-xmlrpc ${DATABASE_BACKEND} ${WEBSERVER_BACKEND} mailutils pyzor razor \
postfix postfix-mysql postfix-pcre postgrey pflogsumm spamassassin spamc sudo bzip2 curl mpack opendkim opendkim-tools unzip clamav-daemon \
python-magic unrar-free liblockfile-simple-perl libdbi-perl libmime-base64-urlsafe-perl libtest-tempdir-perl liblogger-syslog-perl bsd-mailx \
${OPENJDK}-jre-headless libcurl4-openssl-dev libexpat1-dev rrdtool mailgraph fcgiwrap spawn-fcgi \
solr-jetty > /dev/null
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
DEBIAN_FRONTEND=noninteractive apt-get --force-yes -y install dovecot-common dovecot-core dovecot-imapd dovecot-lmtpd dovecot-managesieved dovecot-sieve dovecot-mysql dovecot-pop3d dovecot-solr >/dev/null
			install -m 755 misc/mc_clean_spam_aliases /etc/cron.daily/mc_clean_spam_aliases
			install -m 755 misc/mc_pfset /usr/local/sbin/mc_pfset
			install -m 755 misc/mc_pflog_renew /usr/local/sbin/mc_pflog_renew
			install -m 755 misc/mc_msg_size /usr/local/sbin/mc_msg_size
			install -m 755 misc/mc_dkim_ctrl /usr/local/sbin/mc_dkim_ctrl
			install -m 755 misc/mc_resetadmin /usr/local/sbin/mc_resetadmin
			;;
		ssl)
			mkdir /etc/ssl/mail 2> /dev/null
			[[ $inst_keepfiles == "no" ]] && rm /etc/ssl/mail/* 2> /dev/null
			echo "$(textb [INFO]) - Generating 2048 bit DH parameters, this may take a while, please wait..."
			openssl dhparam -out /etc/ssl/mail/dhparams.pem 2048 2> /dev/null
			openssl req -new -newkey rsa:4096 -sha256 -days 1095 -nodes -x509 -subj "/C=ZZ/ST=mailcow/L=mailcow/O=mailcow/CN=${sys_hostname}.${sys_domain}/subjectAltName=DNS.1=${sys_hostname}.${sys_domain},DNS.2=autodiscover.{sys_domain}" -keyout /etc/ssl/mail/mail.key -out /etc/ssl/mail/mail.crt
			chmod 600 /etc/ssl/mail/mail.key
			cp /etc/ssl/mail/mail.crt /usr/local/share/ca-certificates/
			update-ca-certificates
			;;
		ssl_le)
			curled_ip="$(curl -4s ifconfig.co)"
			for ip in  $(dig ${sys_hostname}.${sys_domain} a +short)
			do
				if [[ "${ip}" == "${curled_ip}" ]]; then
					ip_useable=1
				fi
			done
			if [[ ${ip_useable} -ne 1 ]]; then
				echo "$(redb [ERR]) - Cannot validate IP address against DNS"
				echo "You can retry to obtain Let's Encrypt certificates after fixing this error by running ./install.sh -s"
			else
				mkdir -p /opt/letsencrypt-sh/
				mkdir -p "/var/www/mail/.well-known/acme-challenge"
				tar xf letsencrypt-sh/inst/${letsencrypt_sh_version}.tar -C letsencrypt-sh/inst/ 2> /dev/null
				cp -R letsencrypt-sh/inst/${letsencrypt_sh_version}/* /opt/letsencrypt-sh/
				install -m 644 letsencrypt-sh/conf/config.sh /opt/letsencrypt-sh/config.sh
				install -m 644 letsencrypt-sh/conf/domains.txt /etc/ssl/mail/domains.txt
				sed -i "s/MAILCOW_HOST.MAILCOW_DOMAIN/${sys_hostname}.${sys_domain}/g" /etc/ssl/mail/domains.txt
				# Set postmaster as certificate owner
				sed -i "s/MAILCOW_DOMAIN/${sys_domain}/g" /opt/letsencrypt-sh/config.sh
				install -m 755 letsencrypt-sh/conf/le-renew /etc/cron.weekly/le-renew
				rm -rf letsencrypt-sh/inst/${letsencrypt_sh_version}
				/etc/cron.weekly/le-renew
				if [[ $? -eq 0 ]]; then
					mv /etc/ssl/mail/mail.key /etc/ssl/mail/mail.key_self-signed
					mv /etc/ssl/mail/mail.crt /etc/ssl/mail/mail.crt_self-signed
					ln -s /etc/ssl/mail/certs/${sys_hostname}.${sys_domain}/fullchain.pem /etc/ssl/mail/mail.crt
					ln -s /etc/ssl/mail/certs/${sys_hostname}.${sys_domain}/privkey.pem /etc/ssl/mail/mail.key
				fi
				service ${httpd_platform} restart
			fi
			;;
		mysql)
			if [[ ${mysql_useable} -ne 1 ]]; then
				mysql --defaults-file=/etc/mysql/debian.cnf -e "UPDATE mysql.user SET Password=PASSWORD('$my_rootpw') WHERE USER='root'; FLUSH PRIVILEGES;"
			fi
			mysql --host ${my_dbhost} -u root -p${my_rootpw} -e "DROP DATABASE IF EXISTS ${my_mailcowdb}; DROP DATABASE IF EXISTS $my_rcdb;"
			mysql --host ${my_dbhost} -u root -p${my_rootpw} -e "CREATE DATABASE ${my_mailcowdb}; GRANT SELECT, UPDATE, DELETE, INSERT ON ${my_mailcowdb}.* TO '${my_mailcowuser}'@'%' IDENTIFIED BY '${my_mailcowpass}';"
			mysql --host ${my_dbhost} -u root -p${my_rootpw} -e "CREATE DATABASE $my_rcdb; GRANT ALL PRIVILEGES ON $my_rcdb.* TO '$my_rcuser'@'%' IDENTIFIED BY '$my_rcpass';"
			mysql --host ${my_dbhost} -u root -p${my_rootpw} -e "GRANT SELECT ON ${my_mailcowdb}.* TO 'vmail'@'%'; FLUSH PRIVILEGES;"
			;;
		postfix)
			mkdir -p /etc/postfix/sql
			chown root:postfix "/etc/postfix/sql"; chmod 750 "/etc/postfix/sql"
			for file in $(ls postfix/conf/sql)
			do
				install -o root -g postfix -m 640 postfix/conf/sql/${file} /etc/postfix/sql/${file}
			done
			install -m 644 postfix/conf/master.cf /etc/postfix/master.cf
			install -m 644 postfix/conf/main.cf /etc/postfix/main.cf
			install -o www-data -g www-data -m 644 postfix/conf/mailcow_anonymize_headers.pcre /etc/postfix/mailcow_anonymize_headers.pcre
                        install -m 644 postfix/conf/postscreen_access.cidr /etc/postfix/postscreen_access.cidr
			install -m 644 postfix/conf/smtp_dsn_filter.pcre /etc/postfix/smtp_dsn_filter.pcre
			sed -i "s/sys_hostname.sys_domain/${sys_hostname}.${sys_domain}/g" /etc/postfix/main.cf
			sed -i "s/sys_domain/${sys_domain}/g" /etc/postfix/main.cf
			sed -i "s/my_mailcowpass/${my_mailcowpass}/g" /etc/postfix/sql/*
			sed -i "s/my_mailcowuser/${my_mailcowuser}/g" /etc/postfix/sql/*
			sed -i "s/my_mailcowdb/${my_mailcowdb}/g" /etc/postfix/sql/*
			sed -i "s/my_dbhost/${my_dbhost}/g" /etc/postfix/sql/*
			sed -i '/^POSTGREY_OPTS=/s/=.*/="--inet=127.0.0.1:10023"/' /etc/default/postgrey
			chmod 755 /var/spool/
			sed -i "/%www-data/d" /etc/sudoers 2> /dev/null
			sed -i "/%vmail/d" /etc/sudoers 2> /dev/null
			echo '%www-data ALL=(ALL) NOPASSWD: /usr/bin/doveadm * sync *, /usr/local/sbin/mc_pfset *, /usr/bin/doveadm quota recalc -A, /usr/sbin/dovecot reload, /usr/sbin/postfix reload, /usr/local/sbin/mc_dkim_ctrl, /usr/local/sbin/mc_msg_size, /usr/local/sbin/mc_pflog_renew' >> /etc/sudoers.d/mailcow
			chmod 440 /etc/sudoers.d/mailcow
			;;
		fuglu)
			if [[ -z $(grep fuglu /etc/passwd) ]]; then
				userdel fuglu 2> /dev/null
				groupadd fuglu 2> /dev/null
				useradd -g fuglu -s /bin/false fuglu
				usermod -a -G debian-spamd fuglu
				usermod -a -G clamav fuglu
			fi
			rm /tmp/fuglu_control.sock 2> /dev/null
			mkdir /var/log/fuglu 2> /dev/null
			chown fuglu:fuglu /var/log/fuglu
			tar xf fuglu/inst/${fuglu_version}.tar -C fuglu/inst/ 2> /dev/null
			(cd fuglu/inst/${fuglu_version} ; python setup.py -q install)
			cp -R fuglu/conf/* /etc/fuglu/
			if [[ -f /lib/systemd/systemd ]]; then
				cp fuglu/inst/${fuglu_version}/scripts/startscripts/debian/8/fuglu.service /etc/systemd/system/fuglu.service
				systemctl disable fuglu
				[[ -f /lib/systemd/system/fuglu.service ]] && rm /lib/systemd/system/fuglu.service
				systemctl daemon-reload
				systemctl enable fuglu
			else
				install -m 755 fuglu/inst/${fuglu_version}/scripts/startscripts/debian/7/fuglu /etc/init.d/fuglu
				update-rc.d fuglu defaults
			fi
			rm -rf fuglu/inst/${fuglu_version}
			;;
		dovecot)
			if [[ -f /lib/systemd/systemd ]]; then
				systemctl disable dovecot.socket > /dev/null 2>&1
			fi
			if [[ -z $(grep '/var/vmail:' /etc/passwd | grep '5000:5000') ]]; then
				userdel vmail 2> /dev/null
				groupdel vmail 2> /dev/null
				groupadd -g 5000 vmail
				useradd -g vmail -u 5000 vmail -d /var/vmail
			fi
			chmod 755 "/etc/dovecot/"
			install -o root -g dovecot -m 640 dovecot/conf/dovecot-dict-sql.conf /etc/dovecot/dovecot-dict-sql.conf
			install -o root -g vmail -m 640 dovecot/conf/dovecot-mysql.conf /etc/dovecot/dovecot-mysql.conf
			install -m 644 dovecot/conf/dovecot.conf /etc/dovecot/dovecot.conf
			touch /etc/dovecot/mailcow_public_folder.conf
			chmod 664 "/etc/dovecot/mailcow_public_folder.conf"; chown root:www-data "/etc/dovecot/mailcow_public_folder.conf"
			DOVEFILES=$(find /etc/dovecot -maxdepth 1 -type f -printf '/etc/dovecot/%f ')
			sed -i "s/MAILCOW_HOST.MAILCOW_DOMAIN/${sys_hostname}.${sys_domain}/g" ${DOVEFILES}
			sed -i "s/MAILCOW_DOMAIN/${sys_domain}/g" ${DOVEFILES}
			sed -i "s/my_mailcowpass/${my_mailcowpass}/g" ${DOVEFILES}
			sed -i "s/my_mailcowuser/${my_mailcowuser}/g" ${DOVEFILES}
			sed -i "s/my_mailcowdb/${my_mailcowdb}/g" ${DOVEFILES}
			sed -i "s/my_dbhost/${my_dbhost}/g" ${DOVEFILES}
			[[ ${IPV6} != "yes" ]] && sed -i '/listen =/c\listen = *' /etc/dovecot/dovecot.conf
			mkdir /etc/dovecot/conf.d 2> /dev/null
			mkdir -p /var/vmail/sieve 2> /dev/null
			mkdir -p /var/vmail/public 2> /dev/null
			if [ ! -f /var/vmail/public/dovecot-acl ]; then
				echo "anyone lrwstipekxa" > /var/vmail/public/dovecot-acl
			fi
			install -m 644 dovecot/conf/global.sieve /var/vmail/sieve/global.sieve
			touch /var/vmail/sieve/default.sieve
			sievec /var/vmail/sieve/global.sieve
			chown -R vmail:vmail /var/vmail
			[[ -f /etc/cron.daily/doverecalcq ]] && rm /etc/cron.daily/doverecalcq
			install -m 755 dovecot/conf/dovemaint /etc/cron.daily/
			install -m 644 dovecot/conf/solrmaint /etc/cron.d/
			# Solr
			#if [[ -z $(curl -s --connect-timeout 3 "http://127.0.0.1:8983/solr/admin/info/system" 2> /dev/null | grep -o '[0-9.]*' | grep "^${solr_version}\$") ]]; then
			#	(
			#	TMPSOLR=$(mktemp -d)
			#	cd $TMPSOLR
			#	MIRRORS_SOLR=(http://mirror.23media.de/apache/lucene/solr/${solr_version}/solr-${solr_version}.tgz
			#	http://mirror2.shellbot.com/apache/lucene/solr/${solr_version}/solr-${solr_version}.tgz
			#	http://mirrors.koehn.com/apache/lucene/solr/${solr_version}/solr-${solr_version}.tgz
			#	http://mirrors.sonic.net/apache/lucene/solr/${solr_version}/solr-${solr_version}.tgz
			#	http://apache.mirrors.ovh.net/ftp.apache.org/dist/lucene/solr/${solr_version}/solr-${solr_version}.tgz
			#	http://mirror.nohup.it/apache/lucene/solr/${solr_version}/solr-${solr_version}.tgz
			#	http://ftp-stud.hs-esslingen.de/pub/Mirrors/ftp.apache.org/dist/lucene/solr/${solr_version}/solr-${solr_version}.tgz
			#	http://mirror.netcologne.de/apache.org/lucene/solr/${solr_version}/solr-${solr_version}.tgz)
			#	for i in "${MIRRORS_SOLR[@]}"; do
			#		if curl --connect-timeout 3 --output /dev/null --silent --head --fail "$i"; then
			#			SOLR_URL="$i"
			#			break
			#		fi
			#	done
			#	if [[ -z ${SOLR_URL} ]]; then
			#		echo "$(redb [ERR]) - No Solr mirror was usable"
			#		exit 1
			#	fi
			#	echo $(textb "Downloading Solr ${solr_version}...")
			#	curl ${SOLR_URL} -# | tar xfz -
			#	if [[ ! -d /opt/solr ]]; then
			#		mkdir /opt/solr/
			#	fi
			#	cp -R solr-${solr_version}/* /opt/solr
			#	rm -r ${TMPSOLR}
			#	)
			#	if [[ ! -d /var/solr ]]; then
			#		mkdir /var/solr/
			#	fi
			#	if [[ ! -f /var/solr/solr.in.sh ]]; then
			#	install -m 644 /opt/solr/bin/solr.in.sh /var/solr/solr.in.sh
			#	sed -i '/SOLR_HOST/c\SOLR_HOST=127.0.0.1' /var/solr/solr.in.sh
			#	sed -i '/SOLR_PORT/c\SOLR_PORT=8983' /var/solr/solr.in.sh
			#	sed -i "/SOLR_TIMEZONE/c\SOLR_TIMEZONE=\"${sys_timezone}\"" /var/solr/solr.in.sh
			#	[[ -z $(grep "jetty.host=localhost" /var/solr/solr.in.sh) ]] && echo 'SOLR_OPTS="$SOLR_OPTS -Djetty.host=localhost"' >> /var/solr/solr.in.sh
			#fi
			#if [[ ! -f /etc/init.d/solr ]]; then
			#	install -m 755 /opt/solr/bin/init.d/solr /etc/init.d/solr
			#	update-rc.d solr defaults
			#	if [[ -f /lib/systemd/systemd ]]; then
			#		systemctl daemon-reload
			#	fi
			#fi
			#if [[ -z $(grep solr /etc/passwd) ]]; then
			#	useradd -r -d /opt/solr solr
			#fi
			#chown -R solr: /opt/solr
			#service solr restart
			#sleep 2
			#if [[ ! -d /opt/solr/server/solr/dovecot2/ ]]; then
			#	sudo -u solr /opt/solr/bin/solr create -c dovecot2
			#fi
			#fi
			update-rc.d -f solr remove > /dev/null 2>&1
			service solr stop > /dev/null 2>&1
			[[ -f /usr/share/doc/dovecot-core/dovecot/solr-schema.xml ]] && cp /usr/share/doc/dovecot-core/dovecot/solr-schema.xml /etc/solr/conf/schema.xml
			[[ -f /usr/share/dovecot/solr-schema.xml ]] && cp /usr/share/dovecot/solr-schema.xml /etc/solr/conf/schema.xml
			sed -i '/NO_START/c\NO_START=0' /etc/default/${JETTY_NAME}
                        sed -i '/JETTY_HOST/c\JETTY_HOST=127.0.0.1' /etc/default/${JETTY_NAME}
			sed -i '/JETTY_PORT/c\JETTY_PORT=8983' /etc/default/${JETTY_NAME}
			;;
		clamav)
			usermod -a -G vmail clamav 2> /dev/null
			service clamav-freshclam stop > /dev/null 2>&1
			killall freshclam 2> /dev/null
			[[ $inst_keepfiles == "no" ]] && rm -f /var/lib/clamav/* 2> /dev/null
			sed -i '/DatabaseMirror/d' /etc/clamav/freshclam.conf
			sed -i '/MaxFileSize/c\MaxFileSize 10240M' /etc/clamav/clamd.conf
			sed -i '/StreamMaxLength/c\StreamMaxLength 10240M' /etc/clamav/clamd.conf
			echo "DatabaseMirror clamav.netcologne.de
DatabaseMirror clamav.internet24.eu
DatabaseMirror clamav.inode.at" >> /etc/clamav/freshclam.conf
			if [[ -f /etc/apparmor.d/usr.sbin.clamd || -f /etc/apparmor.d/local/usr.sbin.clamd ]]; then
				rm /etc/apparmor.d/usr.sbin.clamd > /dev/null 2>&1
				rm /etc/apparmor.d/local/usr.sbin.clamd > /dev/null 2>&1
				service apparmor restart > /dev/null 2>&1
			fi
			install -m 755 clamav/clamav-unofficial-sigs.sh /usr/local/bin/clamav-unofficial-sigs.sh
			install -m 644 clamav/clamav-unofficial-sigs.conf /etc/clamav-unofficial-sigs.conf
			install -m 644 clamav/clamav-unofficial-sigs.8 /usr/share/man/man8/clamav-unofficial-sigs.8
			install -m 755 clamav/clamav-unofficial-sigs-cron /etc/cron.d/clamav-unofficial-sigs-cron
			install -m 644 clamav/clamav-unofficial-sigs-logrotate /etc/logrotate.d/clamav-unofficial-sigs-logrotate
			[[ ! -d /var/log/clamav-unofficial-sigs ]] && mkdir /var/log/clamav-unofficial-sigs
			sed -i '/MaxFileSize/c\MaxFileSize 10M' /etc/clamav/clamd.conf
			sed -i '/StreamMaxLength/c\StreamMaxLength 10M' /etc/clamav/clamd.conf
			freshclam 2> /dev/null
			;;
		opendkim)
			echo 'SOCKET="inet:10040@localhost"' > /etc/default/opendkim
			mkdir -p /etc/opendkim/{keyfiles,dnstxt} 2> /dev/null
			touch /etc/opendkim/{KeyTable,SigningTable}
			install -m 644 opendkim/conf/opendkim.conf /etc/opendkim.conf
			;;
		spamassassin)
			cp spamassassin/conf/local.cf /etc/spamassassin/local.cf
			if [[ ! -f /etc/spamassassin/local.cf.include ]]; then
                        	cp spamassassin/conf/local.cf.include /etc/spamassassin/local.cf.include
                        fi
			sed -i '/^OPTIONS=/s/=.*/="--create-prefs --max-children 5 --helper-home-dir --username debian-spamd --socketpath \/var\/run\/spamd.sock --socketowner debian-spamd --socketgroup debian-spamd"/' /etc/default/spamassassin
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
				# Some systems miss the default php fpm listener, reinstall it now
				apt-get -o Dpkg::Options::="--force-confmiss" install -y --reinstall ${PHP}-fpm > /dev/null
				rm /etc/nginx/sites-enabled/{000-0-mailcow*,000-0-fufix} 2>/dev/null
				cp webserver/nginx/conf/sites-available/mailcow /etc/nginx/sites-available/
				cp webserver/php5-fpm/conf/pool/mail.conf ${PHPCONF}/fpm/pool.d/mail.conf
				cp webserver/php5-fpm/conf/php-fpm.conf ${PHPCONF}/fpm/php-fpm.conf
				sed -i "/date.timezone/c\php_admin_value[date.timezone] = ${sys_timezone}" ${PHPCONF}/fpm/pool.d/mail.conf
				ln -s /etc/nginx/sites-available/mailcow /etc/nginx/sites-enabled/000-0-mailcow 2>/dev/null
				[[ ! -z $(grep "server_names_hash_bucket_size" /etc/nginx/nginx.conf) ]] && \
					sed -i "/server_names_hash_bucket_size/c\ \ \ \ \ \ \ \ server_names_hash_bucket_size 64;" /etc/nginx/nginx.conf || \
					sed -i "/http {/a\ \ \ \ \ \ \ \ server_names_hash_bucket_size 64;" /etc/nginx/nginx.conf
				sed -i "s/MAILCOW_HOST.MAILCOW_DOMAIN;/${sys_hostname}.${sys_domain};/g" /etc/nginx/sites-available/mailcow
				sed -i "s/MAILCOW_DOMAIN;/${sys_domain};/g" /etc/nginx/sites-available/mailcow
			elif [[ ${httpd_platform} == "apache2" ]]; then
				rm /etc/apache2/sites-enabled/{mailcow*,000-0-mailcow,000-0-fufix,000-0-mailcow.conf} 2>/dev/null
				cp webserver/apache2/conf/sites-available/mailcow.conf /etc/apache2/sites-available/
				ln -s /etc/apache2/sites-available/mailcow.conf /etc/apache2/sites-enabled/000-0-mailcow.conf 2>/dev/null
				sed -i "s/\"\MAILCOW_HOST.MAILCOW_DOMAIN\"/\"${sys_hostname}.${sys_domain}\"/g" /etc/apache2/sites-available/mailcow.conf
				sed -i "s/MAILCOW_DOMAIN\"/${sys_domain}\"/g" /etc/apache2/sites-available/mailcow.conf
				sed -i "s#MAILCOW_TIMEZONE#${sys_timezone}#g" /etc/apache2/sites-available/mailcow.conf
				a2enmod rewrite ssl headers cgi > /dev/null 2>&1
			fi
			mkdir ${PHPLIB}/sessions 2> /dev/null
			cp -R webserver/htdocs/mail /var/www/
			find /var/www/mail -type d -exec chmod 755 {} \;
			find /var/www/mail -type f -exec chmod 644 {} \;
			echo none > /var/mailcow/log/pflogsumm.log
			sed -i "s/my_dbhost/${my_dbhost}/g" /var/www/mail/inc/vars.inc.php
			sed -i "s/my_mailcowpass/${my_mailcowpass}/g" /var/www/mail/inc/vars.inc.php
			sed -i "s/my_mailcowuser/${my_mailcowuser}/g" /var/www/mail/inc/vars.inc.php
			sed -i "s/my_mailcowdb/${my_mailcowdb}/g" /var/www/mail/inc/vars.inc.php
			chown -R www-data: /var/www/{.,mail} ${PHPLIB}/sessions
			mysql --host ${my_dbhost} -u root -p${my_rootpw} ${my_mailcowdb} < webserver/htdocs/init.sql
			if [[ -z $(mysql --host ${my_dbhost} -u root -p${my_rootpw} ${my_mailcowdb} -e "SHOW COLUMNS FROM domain LIKE 'relay_all_recipients';" -N -B) ]]; then
				mysql --host ${my_dbhost} -u root -p${my_rootpw} ${my_mailcowdb} -e "ALTER TABLE domain ADD relay_all_recipients tinyint(1) NOT NULL DEFAULT '0';" -N -B
			fi
			if [[ $(mysql --host ${my_dbhost} -u root -p${my_rootpw} ${my_mailcowdb} -s -N -e "SELECT * FROM admin;" | wc -l) -lt 1 ]]; then
				mailcow_admin_pass_hashed=$(doveadm pw -s SHA512-CRYPT -p $mailcow_admin_pass)
				mysql --host ${my_dbhost} -u root -p${my_rootpw} ${my_mailcowdb} -e "INSERT INTO admin VALUES ('$mailcow_admin_user','$mailcow_admin_pass_hashed',1,now(),now(),1);"
				mysql --host ${my_dbhost} -u root -p${my_rootpw} ${my_mailcowdb} -e "INSERT INTO domain_admins (username, domain, created, active) VALUES ('$mailcow_admin_user', 'ALL', now(), '1');"
			else
				echo "$(textb [INFO]) - At least one administrator exists, will not create another mailcow administrator"
			fi
			;;
		roundcube)
			mkdir -p /var/www/mail/rc
			tar xf roundcube/inst/${roundcube_version}.tar -C roundcube/inst/
			cp -R roundcube/inst/${roundcube_version}/* /var/www/mail/rc/
			if [[ $is_upgradetask != "yes" ]]; then
				cp -R roundcube/conf/* /var/www/mail/rc/
				sed -i "s/my_dbhost/${my_dbhost}/g" /var/www/mail/rc/config/config.inc.php
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
			chown -R www-data: /var/www/mail/rc
			rm -rf roundcube/inst/${roundcube_version}
			rm -rf /var/www/mail/rc/installer/
			;;
		restartservices)
			[[ -f /lib/systemd/systemd ]] && echo "$(textb [INFO]) - Restarting services, this may take a few seconds..."
			if [[ ${httpd_platform} == "nginx" ]]; then
				FPM="${PHP}-fpm"
			else
				FPM=""
			fi
			for var in ${JETTY_NAME} ${httpd_platform} ${FPM} spamassassin fuglu dovecot postfix opendkim clamav-daemon mailgraph
			do
				service $var stop
				sleep 1.5
				service $var start
			done
			;;
	esac
}
upgradetask() {
	if [[ ! -f /etc/mailcow_version && ! -f /etc/fufix_version ]]; then
		echo "$(redb [ERR]) - mailcow is not installed"
		exit 1
	fi
	if [[ -z $(cat /etc/{fufix_version,mailcow_version} 2> /dev/null | grep -E "0.9|0.10|0.11|0.12|0.13|0.14") ]]; then
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
	[[ -z ${sys_hostname} ]] && sys_hostname=$(hostname -s)
	[[ -z ${sys_domain} ]] && sys_domain=$(hostname -d)
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
	echo "$(pinkb [NOTICE]) - mailcow needs your SQL root password to perform higher privilege level tasks"
        read -p "Please enter your SQL root user password: " my_rootpw
	while [[ $(mysql --host ${my_dbhost} -u root -p${my_rootpw} -e ""; echo $?) -ne 0 ]]; do
		read -p "Please enter your SQL root user password: " my_rootpw
	done
	[[ -z ${my_dbhost} ]] && my_dbhost="localhost"
	for var in httpd_platform sys_hostname sys_domain sys_timezone my_dbhost my_mailcowdb my_mailcowuser my_mailcowpass my_rcuser my_rcpass my_rcdb
	do
		if [[ -z ${!var} ]]; then
			echo "$(redb [ERR]) - Could not gather required information: \"${var}\" empty, upgrade failed..."
			echo
			exit 1
		fi
	done
	echo -e "\nThe following configuration was detected:"
	echo "
$(textb "Hostname")               ${sys_hostname}
$(textb "Domain")                 ${sys_domain}
$(textb "FQDN")                   ${sys_hostname}.${sys_domain}
$(textb "Timezone")               ${sys_timezone}
$(textb "mailcow MySQL")          ${my_mailcowuser}:${my_mailcowpass}@${my_dbhost}/${my_mailcowdb}
$(textb "Roundcube MySQL")        ${my_rcuser}:${my_rcpass}@${my_dbhost}/${my_rcdb}
$(textb "Web server")             ${httpd_platform^}
$(textb "Web root")               https://${sys_hostname}.${sys_domain}

--------------------------------------------------------
THIS UPGRADE WILL RESET SOME OF YOUR CONFIGURATION FILES
--------------------------------------------------------
A backup will be stored in ./before_upgrade_$timestamp
--------------------------------------------------------
"
	if [[ ${inst_confirm_proceed} == "yes" ]]; then
		echo "$(pinkb [NOTICE]) - You can overwrite the detected hostname and domain by calling the installer with -H hostname and -D example.org"
		read -p "Press ENTER to continue or CTRL-C to cancel the upgrade process"
	fi
	echo -en "Creating backups in ./before_upgrade_$timestamp... \t"
	mkdir before_upgrade_$timestamp
	cp -R /var/www/mail/ before_upgrade_$timestamp/mail_wwwroot
	mysqldump -u ${my_mailcowuser} -p${my_mailcowpass} ${my_mailcowdb} > backup_mailcow_db.sql 2>/dev/null
	mysqldump -u ${my_rcuser} -p${my_rcpass} ${my_rcdb} > backup_roundcube_db.sql 2>/dev/null
	cp -R /etc/{postfix,dovecot,spamassassin,${httpd_platform},fuglu,mysql,${PHP},clamav} before_upgrade_$timestamp/
	echo -e "$(greenb "[OK]")"
	echo -en "\nStopping services, this may take a few seconds... \t\t"
	if [[ ${httpd_platform} == "nginx" ]]; then
		FPM="${PHP}-fpm"
	else
		FPM=""
	fi
	for var in ${httpd_platform} ${FPM} spamassassin fuglu dovecot postfix opendkim clamav-daemon mailgraph
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

	returnwait "Package installation"
	installtask installpackages

	returnwait "Postfix configuration"
	installtask postfix

	returnwait "Dovecot configuration"
	installtask dovecot

	returnwait "FuGlu configuration"
	installtask fuglu

	returnwait "ClamAV configuration"
	installtask clamav

	returnwait "Spamassassin configuration"
	installtask spamassassin

	returnwait "Webserver configuration"
	rm -rf ${PHPLIB}/sessions/*
	mkdir -p /var/mailcow/log
	mv /var/www/PFLOG /var/mailcow/log/pflogsumm.log 2> /dev/null

	installtask webserver

	returnwait "Roundcube configuration"
	installtask roundcube

	returnwait "OpenDKIM configuration"
	installtask opendkim

	returnwait "Restarting services"
	installtask restartservices

	returnwait "Finish upgrade" "no"
	echo Done.
	echo
	echo "\"installer.log\" file updated."
	return 0
}
