#!/bin/bash
set -o errtrace

PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
source /opt/vfilter/messages
WORKDIR="/opt/vfilter"
APIKEY=$(cat /var/www/VT_API_KEY)
VTENUP=$(cat /var/www/VT_ENABLE_UPLOAD)
PFDB=$(php -r 'require "/var/www/mail/pfadmin/config.local.php"; echo $CONF["database_name"];')
VDOMAINS=$(mysql -u vmail  -e "select domain from $PFDB.domain;" -BN)
REGEX_TABLE="/etc/postfix/fufix_reject_attachments.regex"

report_error() {
		echo "FAILED: line $1, exit code $2"
		postlog -t postfix/vfilter -p error VirusTotal Filter failed on line $1 with exit code $2
		exit 0
}
trap 'report_error $LINENO $?' ERR

# Create tempdir if not exists
[ -d "$WORKDIR/tempdir" ] || mkdir "$WORKDIR/tempdir"

# Pipe message into a file
cat > "$WORKDIR/tempdir/message.$$"

# Parse original message to Spamassassin for further processing NOW
# This is to prevent mail-loss
sudo -H -u debian-spamd spamc -f -e /usr/sbin/sendmail -oi -f ${@} < "$WORKDIR/tempdir/message.$$"

# Create a new random directory inside the tempdir
mkdir -p "$WORKDIR/tempdir/$$" 2> /dev/null

# Unpack attachments from piped message
munpack -fq -C "$WORKDIR/tempdir/$$" < "$WORKDIR/tempdir/message.$$"

for file in $(ls "$WORKDIR/tempdir/$$/"); do
		# If size exceeds 200MiB do not even check MD5 sum
		[[ $(stat -c %s "$WORKDIR/tempdir/$$/$file") -ge 209715200 ]] && continue

		# Check extension against Postfix PCRE table to prevent scanning of allowed
		# extensions with multiple attachments
		extension="${file##*.}"
		[[ -z $(grep -oP '(?<=\().*(?=\))' $REGEX_TABLE | grep -i $extension) ]] && continue

		# Get MD5 sum of current file
		upload="$WORKDIR/tempdir/$$/$file"
		md5sum_upload=$(md5sum $upload | head -c 32)

		# Get JSON formatted response from VT for MD5 sum
		vt_json_report=$(curl -s -X POST https://www.virustotal.com/vtapi/v2/file/report --form apikey=$APIKEY --form resource=$md5sum_upload)

		# If a previous scan was finished, mail create a mail message with some information and a link
		if [[ $vt_json_report =~ "Scan finished" ]]; then

			message=$(echo $vt_json_report | jq -r .verbose_msg)
			sha1=$(echo $vt_json_report | jq -r .sha1)
			permalink=$(echo $vt_json_report | jq -r .permalink)
			positives=$(echo $vt_json_report | jq -r .positives)
			printf "$SCAN_FOUND" "$message" "$sha1" "$permalink" "$positives" > "$WORKDIR/tempdir/response.$$"

		# If no previous scan was found, check if we should upload the current file to VT
		# and receive JSON formatted response
		elif [[ $vt_json_report =~ "not among the finished" ]] && [[ $VTENUP == "1" ]]; then

			# Stop if file is greater than 32 MiB
			[[ $(stat -c %s "$WORKDIR/tempdir/$$/$file") -ge 33554432 ]] && continue
			printf "$SCAN_PENDING" \
			"$file" "$(curl -s -X POST https://www.virustotal.com/vtapi/v2/file/scan \
				--form apikey=$APIKEY --form file=@"$upload" | \
				jq -r .permalink)" > "$WORKDIR/tempdir/response.$$"

		# Else omit this file
		else

			printf "$SCAN_OMITTED" "$file" > "$WORKDIR/tempdir/response.$$"

		fi

		# Send results to all users we are the final destination for
		for each in "${@:2}"; do

			domain=$(echo $each | cut -d @ -f2)

			if [[ ! -z $(echo $VDOMAINS | grep -i $domain) ]]; then
					mail -s "VirusTotal: \"$file\"" "$each" -a "From:noreply@$(hostname -d)" < "$WORKDIR/tempdir/response.$$"
			fi

		done

done

# Cleanup
rm -rf "$WORKDIR/tempdir/message.$$" "$WORKDIR/tempdir/response.$$" "$WORKDIR/tempdir/$$/" 2>/dev/null
