#!/bin/bash
SENDMAIL="/usr/sbin/sendmail -G -i"
WORKDIR="/var/vmail/vfilter"
APIKEY=$(cat /var/www/VT_API_KEY)
RAND=$(echo $RANDOM)

cat > /tmp/message.$$

mkdir -p "$WORKDIR/scandir/$RAND" 2> /dev/null

/usr/bin/munpack -fqt -C "$WORKDIR/scandir/$RAND" < /tmp/message.$$
/bin/rm "$WORKDIR/scandir/$RAND"/part*

subject=$(cat /tmp/message.$$ | sed -n -e 's/^.*Subject: //p')

for file in $(ls "$WORKDIR/scandir/$RAND/"); do
        RP="$WORKDIR/scandir/$RAND/$file"
        vt_response=$(/usr/bin/curl -X POST 'https://www.virustotal.com/vtapi/v2/file/scan' --form apikey=$APIKEY --form file=@"$RP")
        echo $vt_response | /usr/bin/python -mjson.tool > /tmp/response.$$

        for each in "${@:4}"; do
                mail -s "Virus scan for \"$file\" in \"$subject\"" "$each" -a "From: virusservice" < /tmp/response.$$
        done

done

cat /tmp/message.$$ | $SENDMAIL "$@"

# Cleanup
rm -r /tmp/message*
rm -r /tmp/response*
rm -rf $WORKDIR/scandir/*
