fufix
=====

```
                    .hddddddh
                   `sdddddddd+
        `//:       .dddddddddd-`      -//`
      `+hdddyo/./syyyso++++osyhhs/.:oyddddo`
    `+hddddddddy+:.` `......` `.:ohdddddddddo
    `sddddddh+. `-+shhddddddhys/-` -ohdddddds
     `ydddd+` .+hddddddddddddddddh+. .+ddddy`
       ddy- .oddddddddddddddddddddddo` -ydd
      /dy` :hddddddddddddddddddddddddh- `yd.
    `.hy. -dddddhhhhddddddddddhhhhddddh- .hh.`
/ssyddd/ `yddd+.    `:sddddy/`    ./hddy` +dddyss/
ddddddd` :ddd/  .+o:   yddh.  -++-  -hdd: .ddddddd
ddddddh` /ddh-  /ddh.  oddy   sddo  `hdd/ `ddddddd
ddddddd` :ddh-   .-`.::oyys::``..   `hdd: .ddddddd
/osdddd/ .ydh-      `/oooooo/       `hdy` /ddddso/
   ``+dy. :dh-       `:oooo:        `hd- .hh/``
      /ds` :y-         :oo-         `s- `yd/
       sdy. ..          ..          `` -yds
     `ydddh+`                        `+ddddy`
    `sddddddh+.                    .+hdddddds`
    `+hddddddddy+-`            `:+yddddddddh+`
      `+hddddy+/+ydhso++//++oyhdy+/+yddddh+`
        `++-`     `-dddddddddd-`     `-+o`
                   `sdddddddd+
                    :dddddddh.
```

A mail server install script for **Debian and Debian based distributions**. 
This installer is permanently **tested on Debians stable branch** but is reported to run on newer branches, too. Debian Squeeze (old-stable) is not supported.

Please see https://www.debinux.de/2014/08/fufix-mailserver-installer-auf-basis-von-postfix-und-Dovecot/ for any further information.
Feel free to leave a comment or question (best in English or German).
# Table Of Contents
1. [Introduction](https://github.com/andryyy/fufix#introduction)
2. [Before You Begin](https://github.com/andryyy/fufix#before-you-begin)
3. [Installation](https://github.com/andryyy/fufix#installation)
4. [Configuration Files Used By fufix](https://github.com/andryyy/fufix#configuration-files-used-by-fufix)
  * [FuGlu](https://github.com/andryyy/fufix#fuglu)
  * [ClamAV and Spamassassin](https://github.com/andryyy/fufix#clamav-and-spamassassin)
  * [Postfix](https://github.com/andryyy/fufix#postfix)
  * [Nginx](https://github.com/andryyy/fufix#nginx)
  * [Fail2ban](https://github.com/andryyy/fufix#fail2ban)
  * [Postfixadmin](https://github.com/andryyy/fufix#postfixadmin)
  * [Dovecot](https://github.com/andryyy/fufix#dovecot)
5. [Debugging](https://github.com/andryyy/fufix#debugging)
6. [Maintenance](https://github.com/andryyy/fufix#maintenance)
  * [Queries](https://github.com/andryyy/fufix#queries)
  * [Backup](https://github.com/andryyy/fufix#backup)

# Introduction
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
* **Until a stable version 3.x is released, postfixadmin is pulled from SVN**

# Before You Begin
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

# Installation
**Please run all commands as root**

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
To help you modify the configuration, I created this little overview to get you started.

## FuGlu
Basic configuration. Set `group=nogroup` to run as nobody:nogroup (instead of group nobody). Set `defaultvirusaction` and `blockaction` to REJECT. Enabled ESMTP in `incomingport`:
* **/etc/fuglu/fuglu.conf**

Define attachments to deny/allow:
* **/etc/fuglu/rules/default-filenames.conf**
* **/etc/fuglu/rules/default-filetypes.conf**

Mail template for the bounce to inform sender about blocked attachment:
* **/etc/fuglu/templates/blockedfile.tmpl**

## ClamAV and Spamassassin
Added `TCPSocket 3310` and `TCPAddr 127.0.0.1` to create a TCP socket:
* **/etc/clamav/clamd.conf**

Added `rewrite_header Subject [SPAM]` and `report_safe 2` to prefix [SPAM] to junk mail and forward spam as attachment instead of original message (text/plain):
* **/etc/spamassassin/local.cf**

Enabled "spamd" by `ENABLED=1`, enabled cronjob by setting `CRON=1` and modified OPTIONS line `OPTIONS="--create-prefs --max-children 5 --helper-home-dir --username debian-spamd"` in:
* **/etc/default/spamassassin**

## Postfix
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
## Nginx
A site for mail is copied to `/etc/nginx/sites-available` and enabled via symbolic link to `/etc/nginx/sites-enabled`.
The sites root location is `/usr/share/nginx/mail`. Any default site installed by apt-get is removed.

A PHP socket configuration is located at `/etc/php5/fpm/pool.d/mail.conf`. 
Some PHP parameters are set right here to override those in `/etc/php5/fpm/php.ini` :
```
php_admin_value[short_open_tag] = on # Allow "<?" tags
php_admin_value[magic_quotes_runtime] = off
php_admin_value[register_globals] = off
php_admin_value[magic_quotes_gpc] = off
php_admin_value[date.timezone] = Europe/Berlin # This is just an example and replaced by the installer
php_admin_value[expose_php] = off # Do not display PHP version
```
Server tokens are turned off in Nginx default configuration file `/etc/nginx/nginx.conf`.

## Fail2ban
A file `/etc/fail2ban/jail.local` is created with the following content:
```
[DEFAULT]
bantime = 3600

[sshd]
enabled = true

[postfix-sasl]
enabled = true
```
Ban time is set to 1h, a jail for Postfix SASL (authentication) and - why not - SSHd is enabled.
Default configuration parameters can be reviewed in `/etc/fail2ban/jail.conf`. I recommend to add further/modify existing parameters in "jail.local" to override those in "jail.conf".

## Postfixadmin
The file "config.local.php" is copied to the target directory `/usr/share/nginx/mail/pfadmin`. Some parameters like "domain.tld" are dummies and replaced by the installer.
```
$CONF['configured'] = true;
$CONF['setup_password'] = 'changeme';
$CONF['default_language'] = 'de';
$CONF['database_user'] = 'my_postfixuser';
$CONF['database_password'] = 'my_postfixpass';
$CONF['database_name'] = 'my_postfixdb';
$CONF['admin_email'] = 'mailer@domain.tld';
$CONF['default_aliases'] = array (
    'abuse' => 'abuse@domain.tld',
    'hostmaster' => 'hostmaster@domain.tld',
    'postmaster' => 'postmaster@domain.tld',
    'webmaster' => 'webmaster@domain.tld'
);
$CONF['aliases'] = '10240';
$CONF['mailboxes'] = '10240';
$CONF['maxquota'] = '10240';
$CONF['domain_quota_default'] = '20480';
$CONF['quota'] = 'YES';
$CONF['backup'] = 'YES';
$CONF['fetchmail'] = 'NO';
$CONF['show_footer_text'] = 'NO';
$CONF['used_quotas'] = 'YES';
```
You can change some values to your personal needs by just editing them. No need to reload any service afterwards.

**Default quotas in MB.**

## Dovecot
If you really need to edit Dovecots configuration, you can find the required files in `/etc/dovecot`.

`/etc/dovecot/dovecot.conf` holds the default configuration. To keep it simple I chose not to split the configuration into multiple files. 
Some options you may want to find:
```
ssl_cipher_list = xyz # What ciphers are allowed? 
sieve_before = /var/vmail/sieve/spam-global.sieve # Sieve script to move messages prefixed by "[SPAM]" to junk, globally defined for every user and cannot be deleted or modified by those
sieve_max_script_size = 1M
sieve_quota_max_scripts = 0
sieve_quota_max_storage = 0
special_use = xyz # RFC 6154 tags
```
Dovecots SQL parameters can be found in either `/etc/dovecot/dovecot-dict-sql.conf` or `/etc/dovecot/dovecot-mysql.conf`.
"dovecot-dict-sql.conf" holds instructions for reading a users quota.

"dovecot-mysql.conf" contains some basic SQL commands:

* **driver** - What database
* **connect** - How to connect to the MySQL database
* **default_pass_scheme** - Password scheme. If you edit this you also need to adjust Postfixadmin!
* **password_query** - Validate passwords.
* **user_query** - Validate users.
* **iterate_query** - Iterate users, also needed by a lot of "doveadm" commands.


Furthermore a script "doverecalcq" is copied to `/etc/cron.daily` to recalculate quotas of all users daily. 
A system with a very large amount of virtual users should not do this on a daily basis. I recommend to move the script to "cron.weekly" then.

*Dovecot saves messages to `/var/vmail/DOMAINNAME/USERNAME` in maildir format.*

# Debugging

Most important files for debugging:

* **/var/log/mail.log**
* **/var/log/mail.warn**
* **/var/log/mail.err**
* **/var/log/syslog**
* **/var/log/fuglu/fuglu.log**
* **/var/log/nginx/error.log**
* **/var/log/mysql.err**

Please always see these files when troubleshooting your mail server.

# Maintenance
To help you administrate some basic tasks I decided to add a section "Maintenance".
A lot of work on mailboxes can be done by Dovecots "doveadm" tool.
## Queries

For example searching for inbox messages saved in the past 3 days for user "Bob.Cat":
```
doveadm search -u bob.cat@domain.com mailbox inbox savedsince 2d
```

Or search Bobs inbox for subject "important":
```
doveadm search -u bob.cat@domain.com mailbox inbox subject important
```

Want to delete Bobs messages older than 100 days?
```
doveadm expunge -u bob.cat@domain.com mailbox inbox savedsince 100d
```

From Dovecots wiki: Move jane's messages - received in September 2011 - from her INBOX into her archive.
```
doveadm move -u jane Archive/2011/09 mailbox INBOX BEFORE 2011-10-01 SINCE 01-Sep-2011
```

You find some more useful search queries and much more here: http://wiki2.dovecot.org/Tools/Doveadm

## Backup 

If you want to create a backup of Bobs maildir to /var/mailbackup, just go ahead and create the backup destination with proper rights:

```
mkdir /var/mailbackup
chown vmail:vmail /var/mailbackup/
```

Afterwards you can start a full backup:
```
dsync -u bob.cat@domain.com backup maildir:/var/mailbackup/
```

For more information about dsync (like the difference between backups and mirrors) visit http://wiki2.dovecot.org/Tools/Dsync


