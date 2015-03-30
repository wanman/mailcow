#!/bin/bash

source /opt/vfilter/messages

WORKDIR="/opt/vfilter"
APIKEY=$(cat /var/www/VT_API_KEY)
VTENUP=$(cat /var/www/VT_ENABLE_UPLOAD)
RAND=$(echo $RANDOM)
PFDB=$(php -r 'include_once("/var/www/mail/pfadmin/config.local.php"); echo $CONF["database_name"];')
VDOMAINS=$(mysql -u vmail  -e "select domain from $PFDB.domain;" -BN)
REGEX_EXT="/etc/postfix/fufix_reject_attachments.regex"

# Pipe message into a file
cat > /tmp/message.$$

# Create a new random directory inside the scandir
mkdir -p "$WORKDIR/scandir/$RAND" 2> /dev/null

# Unpack attachments from piped message
/usr/bin/munpack -fq -C "$WORKDIR/scandir/$RAND" < /tmp/message.$$
#/bin/rm "$WORKDIR/scandir/$RAND"/part* 2>/dev/null

subject=$(cat /tmp/message.$$ | sed -n -e 's/^.*Subject: //p')

for file in $(ls "$WORKDIR/scandir/$RAND/"); do

        # If size exceeds 200MiB do not even check MD5 sum
        [[ $(stat -c %s "$WORKDIR/scandir/$RAND/$file") -ge 209715200 ]] && continue

        # Check extension against Postfix PCRE table to prevent scanning of allowed
        # extensions with multiple attachments
        extension="${file##*.}"
        [[ -z $(/bin/grep -oP '(?<=\().*(?=\))' $REGEX_EXT | /bin/grep -i $extension) ]] && continue

        # Get MD5 sum of current file
        upload="$WORKDIR/scandir/$RAND/$file"
        md5sum_upload=$(/usr/bin/md5sum $upload | head -c 32)

        # Get JSON formatted response from VT for MD5 sum
        vt_json_report=$(/usr/bin/curl -s -X POST https://www.virustotal.com/vtapi/v2/file/report --form apikey=$APIKEY --form resource=$md5sum_upload)

        # If a previous scan was finished, mail create a mail message with some information and a link
        if [[ $vt_json_report =~ "Scan finished" ]]; then

                message=$(echo $vt_json_report | /usr/bin/jq -r .verbose_msg)
                sha1=$(echo $vt_json_report | /usr/bin/jq -r .sha1)
                permalink=$(echo $vt_json_report | /usr/bin/jq -r .permalink)
                positives=$(echo $vt_json_report | /usr/bin/jq -r .positives)
                printf "$SCAN_FOUND" "$message" "$sha1" "$permalink" "$positives" > /tmp/response.$$

        # If no previous scan was found, check if we should upload the current file to VT
        # and receive JSON formatted response
        elif [[ $vt_json_report =~ "not among the finished" ]] && [[ $VTENUP == "1" ]]; then

                # Stop if file is greater than 32 MiB
                [[ $(stat -c %s "$WORKDIR/scandir/$RAND/$file") -ge 33554432 ]] && continue
                printf "$SCAN_PENDING" \
                "$file" "$(/usr/bin/curl -s -X POST https://www.virustotal.com/vtapi/v2/file/scan \
                        --form apikey=$APIKEY --form file=@"$upload" | \
                        /usr/bin/jq -r .permalink)" > /tmp/response.$$

        # Else omit this file
        else

                printf "$SCAN_OMITTED" "$file" > /tmp/response.$$

        fi

        # Send results to all users we are the final destination for
        for each in "${@:2}"; do

                domain=$(echo $each | cut -d @ -f2)

                if [[ ! -z $(echo $VDOMAINS | /bin/grep -i $domain) ]]; then
                        /usr/bin/mail -s "Virus scan for \"$file\" in \"$subject\"" "$each" -a "From:noreply@$(hostname -d)" < /tmp/response.$$
                fi

        done

done

# Parse original message to Spamassassin for further processing
/usr/bin/sudo -H -u debian-spamd /usr/bin/spamc -f -e /usr/sbin/sendmail -oi -f ${@} < /tmp/message.$$

# Cleanup
/bin/rm -r /tmp/message* 2>/dev/null
/bin/rm -r /tmp/response* 2>/dev/null
/bin/rm -rf $WORKDIR/scandir/* 2>/dev/null
