#!/bin/bash
SENDMAIL="/usr/sbin/sendmail -G -i"
WORKDIR="/var/vmail/vfilter"
TIME=$(date +%s)
APIKEY=$(cat /var/www/VT_API_KEY)

cat > /tmp/message.$$
mkdir -p "$WORKDIR/scandir/$4/$TIME" 2> /dev/null
/usr/bin/munpack -fqt -C "$WORKDIR/scandir/$4/$TIME" < /tmp/message.$$
/bin/rm "$WORKDIR/scandir/$4/$TIME"/part*
subject=$(cat /tmp/message.$$ | sed -n -e 's/^.*Subject: //p')

for file in $(ls "$WORKDIR/scandir/$4/$TIME/"); do
        RP="$WORKDIR/scandir/$4/$TIME/$file"
        vt_hash=$(curl -X POST 'https://www.virustotal.com/vtapi/v2/file/scan' --form apikey=$APIKEY --form file=@"$RP")
        echo $vt_hash | /usr/bin/python -mjson.tool | /usr/bin/tr -d '{}",' | /usr/bin/awk 'NF' > /tmp/response.$$
        /usr/bin/mail -s "Virus scan for \"$file\" in \"$subject\"" "$4" -a "From: VirusTotalUploader" < /tmp/response.$$
done
cat /tmp/message.$$ | $SENDMAIL "$@"

