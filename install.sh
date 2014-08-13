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

# log generated passwords
echo ---------- > installer.log
echo Postfix password: $my_postfixpass >> installer.log
echo MySQL root password: $my_rootpw >> installer.log
echo ---------- >> installer.log

# fuglu
mkdir /var/log/fuglu
rm /tmp/fuglu_control.sock
chown nobody /var/log/fuglu
git clone https://github.com/gryphius/fuglu.git fuglu_git
cd fuglu_git/fuglu
python setup.py install
cp -R fuglu/* /etc/fuglu/
