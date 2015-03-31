#!/bin/bash
set -o errtrace

PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
source /opt/vfilter/replies
source /opt/vfilter/vfilter.conf

report_error() {
        echo "FAILED: line $1, exit code $2"
        postlog -t postfix/vfilter -p error VirusTotal Filter failed on line $1 with exit code $2
        exit 0
}
trap 'report_error $LINENO $?' ERR

write_log() {
        [ -d /opt/vfilter/log ] || mkdir /opt/vfilter/log
        echo "$(date +%b\ %d\ %T) - $1" >> /opt/vfilter/log/vfilter.log
}

write_log "vfilter triggered"

# Create tempdir if not exists
[ -d /opt/vfilter/tempdir ] || mkdir /opt/vfilter/tempdir

# Pipe message into a file
cat > "/opt/vfilter/tempdir/message.$$"

# Parse original message to Spamassassin for further processing NOW
# This is to prevent mail-loss
sudo -H -u debian-spamd spamc -f -e /usr/sbin/sendmail -oi -f ${@} < "/opt/vfilter/tempdir/message.$$"

# Create a new random directory inside the tempdir
mkdir -p "/opt/vfilter/tempdir/files.$$" 2> /dev/null

# Unpack attachments from piped message
munpack -fq -C "/opt/vfilter/tempdir/files.$$" < "/opt/vfilter/tempdir/message.$$"

for file in $(ls "/opt/vfilter/tempdir/files.$$/"); do

        write_log "Processing file $file"

        # If size exceeds 200MiB do not even check MD5 sum
        [[ $(stat -c %s "/opt/vfilter/tempdir/files.$$/$file") -ge 209715200 ]] && write_log "File size exceeds 200MB, file hash check skipped" && continue

        write_log "File $file does not exceed 200MB"

        # Check extension
        [[ -z $(echo $EXTENSIONS | grep -i "${file##*.}") ]] && continue

        write_log "Extension is listed as dangerous and will be scanned"

        # Get MD5 sum of current file
        upload="/opt/vfilter/tempdir/files.$$/$file"
        md5sum_upload=$(md5sum $upload | head -c 32)

        # Get JSON formatted response from VT for MD5 sum
        vt_json_report=$(curl -s -X POST https://www.virustotal.com/vtapi/v2/file/report --form apikey=$VT_API_KEY --form resource=$md5sum_upload)

        # If a previous scan was finished, mail create a mail message with some information and a link
        if [[ $vt_json_report =~ "Scan finished" ]]; then

                write_log "MD5 sum $md5sum_upload was found, getting previous report..."

                message=$(echo $vt_json_report | jq -r .verbose_msg)
                sha1=$(echo $vt_json_report | jq -r .sha1)
                permalink=$(echo $vt_json_report | jq -r .permalink)
                positives=$(echo $vt_json_report | jq -r .positives)
                printf "$SCAN_FOUND" "$message" "$sha1" "$permalink" "$positives" > "/opt/vfilter/tempdir/response.$$"

        # If no previous scan was found, check if we should upload the current file to VT
        # and receive JSON formatted response
        elif [[ $vt_json_report =~ "not among the finished" ]] && [[ $ENABLE_VT_UPLOAD == "1" ]]; then

                # Stop if file is greater than 32 MiB
                [[ $(stat -c %s "/opt/vfilter/tempdir/files.$$/$file") -ge 33554432 ]] && write_log "MD5 sum $md5sum_upload was not found, size exceeds 32MB, file upload skipped" && continue

                write_log "MD5 sum $md5sum_upload was not found, size does not exceed 32MB, upload started"

                printf "$SCAN_PENDING" \
                "$file" "$(curl -s -X POST https://www.virustotal.com/vtapi/v2/file/scan \
                        --form apikey=$VT_API_KEY --form file=@"$upload" | \
                        jq -r .permalink)" > "/opt/vfilter/tempdir/response.$$"

        # Else omit this file
        else

                write_log "File $file was omitted"

                printf "$SCAN_OMITTED" "$file" > "/opt/vfilter/tempdir/response.$$"

        fi

        # Send results to all users we are the final destination for
        for each in "${@:2}"; do

                domain=$(echo $each | cut -d @ -f2)

                if [[ ! -z $(echo $DOMAINS | grep -i $domain) ]]; then

                        write_log "Mailing report for file $file to $each"

                        mail -s "VirusTotal: \"$file\"" \
                        "$each" \
                        -a "From:noreply@$(hostname -d)" < "/opt/vfilter/tempdir/response.$$"
                fi

        done

done

# Cleanup
write_log "Cleaning up..."
rm -rf "/opt/vfilter/tempdir/message.$$" \
       "/opt/vfilter/tempdir/response.$$" \
       "/opt/vfilter/tempdir/files.$$/" 2>/dev/null

