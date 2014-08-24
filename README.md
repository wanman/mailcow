fufix
=====
A Mailserver installer for **Debian and Debian based distributions**. 
This installer is permanently **tested on Debians stable branch** but is reported to run on newer branches, too. Debian Squeeze (old-stable) is not supported.

## Introduction
A summary of what software is installed with which features enabled.
**System setup**
* Setting the Hostname & Fully Qualified Domain Name
* Timezone adjustment
* Automatically generated passwords with high complexity
* Self-signed SSL certificate for all supported services
* Nginx (+php5-fpm) installation with a site for Postfixadmin (SSL only, based on BetterCrypto)
* MySQL installation as backend for mail service
* DNS check via Google DNS to verify PTR and A Record
* Free Rsyslog from mail logs (mail.* only)

**Postfix**
* Submission activated (TCP/587)
* SMTPS disabled
* Require TLS Authentification
* Included ZEN blocklist
* Spam- and virus protection by [FuGlu Mail Content Scanner](http://www.fuglu.org)  with ClamAV and Spamassassin backend: Reject infected mails (<v0.2: delete), mark spam and move to "Junk"
* SSL based on BetterCrypto (but no definition of "high" ciphers for compatibility reasons)

**Dovecot**
* Default mailboxes to subscribe to automatically (Inbox, Sent, Drafts, Trash, Junk - SPECIAL-USE RFC 6154 tags)
* Sieve/ManageSieve (TCP/4190)
* Global sieve filter: Prefix spam with "[SPAM]" and move to "Junk"
* (IMAP) quotas
* LMTP (resident daemon)
* SSL based on BetterCrypto

**Postfixadmin**
* Automatic superuser configuration
* Full quota support
* "config.local.php" preconfigured

## Before you begin
**Please remove any web- and mail services** running on your server. I recommend using a clean Debian minimal installation.
Remember to purge Debians default MTA Exim4:
```
apt-get purge exim4*
``` 
If there is any firewall, unblock the following ports for incoming connections:

| Service             | Protocol | Port |
| ------------------- |:--------:|:-----|
| Postfix Submission  | TCP      | 587  |
| Postfix SMTP        | TCP      | 25   |
| Dovecot IMAP        | TCP      | 143  |
| Dovecot IMAPS       | TCP      | 993  |
| Dovecot ManageSieve | TCP      | 4190 |
| Nginx HTTPS         | TCP      | 443  |

## Installation
**Please run all these commands as root**

Install git to download fufix:
```
apt-get install git
```

Clone fufix into whichever directory (using ~/build here):
```
mkdir ~/build
git clone https://github.com/andryyy/fufix
cd fufix
```

**Now edit install.sh to fit your needs!**
```
nano install.sh
```

* **sys_hostname** - Hostname without domain
* **sys_domain** - Domain name. "$sys_hostname.$sys_domain" equals to FQDN.
* **sys_timezone** - The timezone must be definied in a valid format (Europe/Berlin, America/New_York etc.)
* **my_postfixdb, my_postfixuser, my_postfixpass** - MySQL database name, username and password for use with Postfix. **You can use the default values.**
* **my_rootpw** - MySQL root password is generated automatically by default. You can define a complex password here if you want to.
* **pfadmin_adminuser and pfadmin_adminpass** - Postfixadmin superuser definition: **Username MUST end with a valid domain name** but **does NOT need to be yours**. "yourname@outlook.com" is fine, "yourname@domain.invalid" or "yourname@aname" is not. Password policy: minimum length 5 chars, must contain at least 3 characters, must contain at least 2 digits. **You can use the default values**
* **"cert-" vars** - Used for the self-signed certificate. CN will be the servers FQDN.

You are ready to start the script:
```
./install.sh
```
Just be patient and confirm every step by pressing [ENTER] or CTRL-C to interrupt the installation.
More debugging is about to come. Though everything should work as intended.
## Configuration files used by fufix
To help you modify the configuration, I created a little index to get you started.

### FuGlu
Basic configuration. Set `group=nogroup` to run as nobody:nogroup (instead of group nobody). Set `defaultvirusaction` and `blockaction` to REJECT. Enabled ESMTP in `incomingport`:
* **/etc/fuglu/fuglu.conf**

Define attachments to deny/allow:
* **/etc/fuglu/rules/default-filenames.conf**
* **/etc/fuglu/rules/default-filetypes.conf**

Mail template for the bounce to inform sender about blocked attachment:
* **/etc/fuglu/templates/blockedfile.tmpl**

### ClamAV and Spamassassin
Added `TCPSocket 3310` and `TCPAddr 127.0.0.1` to create a TCP socket:
* **/etc/clamav/clamd.conf**

Added `rewrite_header Subject [SPAM]` and `report_safe 2` to prefix [SPAM] to junk mail and forward spam as attachment instead of original message (text/plain):
* **/etc/spamassassin/local.cf**

### Postfix
The files "main.cf" and "master.cf" contain a lot of changes. You should now what you do if you modify these files.
* **/etc/postfix/main.cf**
* **/etc/postfix/master.cf**

You also find the SQL based maps for virtual transport here:
* **/etc/postfix/sql/*.cf**

To pick some of the most important changes in "main.cf".
```
#SSL based:
smtpd_tls_auth_only = yes
smtpd_tls_mandatory_protocols = !SSLv2, !SSLv3
smtpd_tls_mandatory_ciphers=high
smtp_tls_security_level=may
smtpd_tls_cert_file = /etc/ssl/mail/mail.crt
smtpd_tls_key_file = /etc/ssl/mail/mail.key
smtpd_use_tls=yes
smtp_tls_cert_file = /etc/ssl/mail/mail.crt
smtp_tls_key_file = /etc/ssl/mail/mail.key

# Recipient restrictions
reject_rbl_client zen.spamhaus.org # ZEN blacklist
reject_unknown_reverse_client_hostname # Reject mails if no PTR is set or does not match

# Sender restrictions
reject_authenticated_sender_login_mismatch # Refuse to send mails when FROM address is not owned by sender (only matches for authenticated users.)
reject_unknown_sender_domain # Refuse to send mails from unknown domains

# Queue handling
maximal_queue_lifetime = 1d
bounce_queue_lifetime = 1d
queue_run_delay = 300s
maximal_backoff_time = 1800s
minimal_backoff_time = 300s
```
