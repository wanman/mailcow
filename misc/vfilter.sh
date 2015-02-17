#!/bin/bash
SENDMAIL="/usr/sbin/sendmail -G -i"
WORKDIR="/var/vmail/vfilter"
APIKEY=$(cat /var/www/VT_API_KEY)
RAND=$(echo $RANDOM)
PFDB=$(php -r 'include_once("/var/www/mail/pfadmin/config.local.php"); echo $CONF["database_name"];')
VDOMAINS=$(mysql --defaults-file=/etc/mysql/debian.cnf -e "select domain from $PFDB.domain;" -BN)

cat > /tmp/message.$$

mkdir -p "$WORKDIR/scandir/$RAND" 2> /dev/null

/usr/bin/munpack -fqt -C "$WORKDIR/scandir/$RAND" < /tmp/message.$$
/bin/rm "$WORKDIR/scandir/$RAND"/part*

subject=$(cat /tmp/message.$$ | sed -n -e 's/^.*Subject: //p')

for file in $(ls "$WORKDIR/scandir/$RAND/"); do
        upload="$WORKDIR/scandir/$RAND/$file"
        vt_response=$(/usr/bin/curl -X POST 'https://www.virustotal.com/vtapi/v2/file/scan' --form apikey=$APIKEY --form file=@"$upload")
        if [[ ! -z $vt_response ]]; then
                echo $vt_response | /usr/bin/python -mjson.tool > /tmp/response.$$
        else
                echo "Something went wrong. Please check your API key." > /tmp/response.$$
        fi
        for each in "${@:4}"; do
                if [[ $VDOMAINS =~ $each ]]; then
                        /usr/bin/mail -s "Virus scan for \"$file\" in \"$subject\"" "$each" -a "From:noreply@$(hostname -d)" < /tmp/response.$$
                fi
        done
done

cat /tmp/message.$$ | $SENDMAIL "$@"

# Cleanup
rm -r /tmp/message*
rm -r /tmp/response*
rm -rf $WORKDIR/scandir/*
