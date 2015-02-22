#!/bin/bash
WORKDIR="/var/vmail/vfilter"
APIKEY=$(cat /var/www/VT_API_KEY)
RAND=$(echo $RANDOM)
PFDB=$(php -r 'include_once("/var/www/mail/pfadmin/config.local.php"); echo $CONF["database_name"];')
VDOMAINS=$(mysql -u vmail  -e "select domain from $PFDB.domain;" -BN)
REGEX_EXT="/etc/postfix/fufix_reject_attachments.regex"

cat > /tmp/message.$$

mkdir -p "$WORKDIR/scandir/$RAND" 2> /dev/null

/usr/bin/munpack -fqt -C "$WORKDIR/scandir/$RAND" < /tmp/message.$$
/bin/rm "$WORKDIR/scandir/$RAND"/part*

subject=$(cat /tmp/message.$$ | sed -n -e 's/^.*Subject: //p')

for file in $(ls "$WORKDIR/scandir/$RAND/"); do
        extension="${file##*.}"
        [[ -z $(grep -oP '(?<=\().*(?=\))' $REGEX_EXT | grep -i $extension) ]] && continue
        upload="$WORKDIR/scandir/$RAND/$file"
        md5sum_upload=$(md5sum $upload | head -c 32)
        vt_hash_report_lookup=$(/usr/bin/curl -s -X POST 'https://www.virustotal.com/vtapi/v2/file/report' --form apikey=$APIKEY --form resource=$md5sum_upload)

        if [[ $vt_hash_report_lookup =~ "Scan finished" ]]; then
                echo $vt_hash_report_lookup | /usr/bin/python -mjson.tool > /tmp/response.$$
        elif [[ $vt_hash_report_lookup =~ "not among the finished" ]]; then
                vt_new_scan=$(/usr/bin/curl -X POST 'https://www.virustotal.com/vtapi/v2/file/scan' --form apikey=$APIKEY --form file=@"$upload")
                echo $vt_new_scan | /usr/bin/python -mjson.tool > /tmp/response.$$
        else
                echo "Something went wrong. Please check your API key." > /tmp/response.$$
        fi

        for each in "${@:2}"; do
                domain=$(echo $each | cut -d @ -f2)
                if [[ ! -z $(echo $VDOMAINS | grep -i $domain) ]]; then
                        /usr/bin/mail -s "Virus scan for \"$file\" in \"$subject\"" "$each" -a "From:noreply@$(hostname -d)" < /tmp/response.$$
                fi
        done
done

sudo -H -u debian-spamd /usr/bin/spamc -f -e /usr/sbin/sendmail -oi -f ${@} < /tmp/message.$$

rm -r /tmp/message*
rm -r /tmp/response*
rm -rf $WORKDIR/scandir/*
