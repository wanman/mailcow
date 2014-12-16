<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
**Table of Contents**  *generated with [DocToc](http://doctoc.herokuapp.com/)*

- [fufix](#fufix)
- [Introduction](#introduction)
- [Before You Begin](#before-you-begin)
- [Installation](#installation)
- [Configuration and common tasks](#configuration-and-common-tasks)
  - [SSL certificate](#ssl-certificate)
  - [FuGlu](#fuglu)
    - [Filter mail](#filter-mail)
    - [Filter statistics](#filter-statistics)
  - [ClamAV and Spamassassin](#clamav-and-spamassassin)
    - [Spam rewrite](#spam-rewrite)
    - [Spamassassin daemon options](#spamassassin-daemon-options)
    - [Max file size for virus scanning](#max-file-size-for-virus-scanning)
  - [Postfix](#postfix)
    - [Message size limit](#message-size-limit)
  - [Nginx](#nginx)
  - [Fail2ban](#fail2ban)
  - [Postfixadmin](#postfixadmin)
  - [Dovecot](#dovecot)
    - [Disallow insecure IMAP connections](#disallow-insecure-imap-connections)
    - [Trash folder quota](#trash-folder-quota)
    - [Dovecot SQL parameter](#dovecot-sql-parameter)
    - [Doveadm common tasks](#doveadm-common-tasks)
    - [Backup mail](#backup-mail)
  - [Roundcube](#roundcube)
    - [Attachment size](#attachment-size)
- [Debugging](#debugging)
- [Uninstall](#uninstall)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

fufix
=====

```


   m""           m""    "
 mm#mm  m   m  mm#mm  mmm    m   m
   #    #   #    #      #     #m#
   #    #   #    #      #     m#m
   #    "mm"#    #    mm#mm  m" "m


```

A mail server install script with a lot of features for **Debian and Debian based distributions**. 
This installer is permanently **tested on Debians stable branch** but is reported to run on newer branches, too. Debian Squeeze (old-stable) is not supported.

Please see https://www.debinux.de/fufix for further information.
Feel free to leave a comment or question.

![fufix Frontend](https://www.debinux.de/fufix_frontend.png)

# Introduction
A summary of what software is installed with which features enabled.

**General setup**
* System environment adjustments (Hostname, Timezone,...)
* Automatically generated passwords with high complexity
* Self-signed SSL certificate for all supported services
* Nginx and PHP5 FPM fully optimized
* MySQL database backend
* DNS checks via Google DNS after setup
* Syslog adjustments
* Autoconfiguration for Thunderbird
* **A HTTPS Frontend**
* Autolearn spam from mail moved to "Junk" folder

**Postfix**
* Submission activated (TCP/587)
* SMTPS disabled
* Require TLS Authentification
* Included ZEN blocklist
* In- and outgoing spam- and virus protection plus attachment filter via [FuGlu Mail Content Scanner](http://www.fuglu.org) (uses ClamAV and Spamassassin backend)
* Reject infected mails, mark spam
* SSL based on BetterCrypto 

**Dovecot**
* Default mailboxes to subscribe to automatically (Inbox, Sent, Drafts, Trash, Junk - SPECIAL-USE RFC 6154 tags)
* Sieve/ManageSieve (TCP/4190)
* Global sieve filter: Move mail marked as spam into "Junk"
* (IMAP) Quotas
* LMTP service for Postfix virtual transport
* SSL based on BetterCrypto

**Postfixadmin**
* Automatically creates an Administrator
* Full quota support

**Roundcube**
* ManageSieve support (w/ vacation)
* Change password
* Attachment reminder (multiple locales)
* Zip-download marked messages
* 25M attachment size (see "File size limitation")

# Before You Begin
**Please remove any web- and mail services** running on your server. I recommend using a clean Debian minimal installation.
Remember to purge Debians default MTA Exim4:
```
apt-get purge exim4*
``` 
If there is any firewall, unblock the following ports for incoming connections:

| Service               | Protocol | Port |
| -------------------   |:--------:|:-----|
| Postfix Submission    | TCP      | 587  |
| Postfix SMTP          | TCP      | 25   |
| Dovecot IMAP          | TCP      | 143  |
| Dovecot IMAPS         | TCP      | 993  |
| Dovecot ManageSieve   | TCP      | 4190 |
| Nginx HTTPS           | TCP      | 443  |
| Nginx HTTP (Redirect) | TCP      | 80   |

# Installation
**Please run all commands as root**

**Option 1: Download a stable release**

Download fufix to whichever directory (using ~/build here).
Replace "v0.x" with the tag of the latest release: https://github.com/andryyy/fufix/releases/latest
```
mkdir ~/build ; cd ~/build
wget -O - https://github.com/andryyy/fufix/archive/v0.x.tar.gz | tar xfz -
cd fufix-*
```

**Option 2: Install from git**

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
**Now edit "configuration" file to fit your needs!**
```
nano configuration
```

* **sys_hostname** - Hostname without domain
* **sys_domain** - Domain name. "$sys_hostname.$sys_domain" equals to FQDN.
* **sys_timezone** - The timezone must be definied in a valid format (Europe/Berlin, America/New_York etc.)
* **my_postfixdb, my_postfixuser, my_postfixpass** - MySQL database name, username and password for use with Postfix. **You can use the default values.**
* **my_rcdb, my_rcuser, my_rcpass** - MySQL database name, username and password for Roundcube. **You can use the default values.**
* **my_rootpw** - MySQL root password is generated automatically by default. You can define a complex password here if you want to.
* **pfadmin_adminuser and pfadmin_adminpass** - Postfixadmin superuser definition: **Username MUST end with a valid domain name** but **does NOT need to be yours**. "yourname@outlook.com" is fine, "yourname@domain.invalid" or "yourname@aname" is not. Password policy: minimum length 8 chars, must contain uppercase and lowercase letters and at least 2 digits. **You can use the default values**
* **"cert-" vars** - Used for the self-signed certificate. CN will be the servers FQDN. "cert_country" must be a vaild two letter country code.
* Set **conf_done** to **yes** or anything except "no".
* An unattended installation is possible, but not recommended ("inst_unattended")

**Empty configuration values are invalid!**

You are ready to start the script:
```
./install.sh
```
Just be patient and confirm every step by pressing [ENTER] or [CTRL-C] to interrupt the installation.

More debugging is about to come. Though everything should work as intended.

After the installation, visit your dashboard @ **https://hostname.domain.tld**, use the logged credentials in `./installer.log`

Remember to create an alias- or a mailbox for Postmaster. ;-)

# Configuration and common tasks
To help you modify the configuration, I created this little overview to get you started.

## SSL certificate
The SSL certificate is located at `/etc/ssl/mail/mail.{key,crt}`.
You can replace it by just copying over your own files. 
Services effected and necessary to restart are `postfix`, `dovecot` and `nginx`.

## FuGlu
Basic configuration. Set `group=nogroup` to run as nobody:nogroup (instead of group nobody). Set `defaultvirusaction` and `blockaction` to REJECT. Enabled ESMTP in `incomingport`:
* **/etc/fuglu/fuglu.conf**

Define attachments to deny/allow:
* **/etc/fuglu/rules/default-filenames.conf**
* **/etc/fuglu/rules/default-filetypes.conf**

Mail template for the bounce to inform sender about blocked attachment:
* **/etc/fuglu/templates/blockedfile.tmpl**

### Filter mail
You can use FuGlus Action Override plugin to create custom filters.  
To add an action open the file `/etc/fuglu/actionrules.regex`.  
Use the following syntax:
```
<headername> <regex> <argument>
``` 
Valid header names (a email header name, eg Received, To, From, Subject ... also supports ‘*’ as wildcard character):

> - mime:headername (to get mime Headers in the message payload eg: mime:Content-Disposition)
> - envelope_from (the envelope from address)
> - from_domain (domain part of envelope_from)
> - envelope_to (envelope to address)
> - to_domain (domain part of envelope_to)
> - a message Tag prepended by the @ symbol, eg. @incomingport
> - body:raw (to match the the decoded message body (only applies to text/* partsl))
> - body:stripped or just body (to match the the message body (only applies to text/* parts), with stripped tags and newlines replaced with space (similar to SpamAssassin body rules))
> - body:full (to match the full body)
Valid arguments:
> - DUNNO : This plugin decides not to take any final action, continue with the next plugin (this is the most common case)
> - ACCEPT : Whitelist this message, don’t run any remaining plugins
> - DELETE : Silently delete this message (The sender will think it has been delivered)
> - DEFER : Temporary Reject (4xx error), used for error conditions in after-queue mode or things like greylisting in before-queue mode
> - REJECT : Reject this message, should only be used in before-queue mode (in after-queue mode this would produce a bounce / backscatter)

Some examples with regex:

```
# Reject mails with "Hello" in the subject:
Subject Hello REJECT

# Delete mail sent from domain.org or any subdomain
from_domain (\.)?domain.org$ DELETE

# Whitelist mail sent from domain.org or any subdomain. No plug-in will be run on these mails!
from_domain (\.)?domain.org$ ACCEPT

# Reject if a X-Spam-<something> header exists
X-Spam-* .* REJECT
```

**You do not need to restart/reload FuGlus service!**  
The file actionrules.regex will be reloaded automatically.

See more details at http://gryphius.github.io/fuglu/plugins-index.html

### Filter statistics
If you want to see a statistic of FuGlus activity, just run `fuglu_control stats`

## ClamAV and Spamassassin
ClamAV main configuration file:
* **/etc/clamav/clamd.conf**

Spamassassin main configuration file:
* **/etc/spamassassin/local.cf**

Virus and spam filters are **enabled for both incoming and outgoing** mail.

Move undetected spam to "Junk" to make Spamassassin autolearn it. This is done by a daily cronjob.

### Spam rewrite
Fufix adds `rewrite_header Subject [SPAM]` and `report_safe 2` to prefix [SPAM] to junk mail and forward spam as attachment instead of original message (text/plain). 

The prefix "[SPAM]" is not important for the sieve filter and can be changed to whatever text. Spam will be moved when te Spam Flag is set the header.

### Spamassassin daemon options
Default startup options for Spamassassin in `/etc/default/spamassassin`:
- Enabled "spamd" by adding `ENABLED=1`
- Enabled cronjob by setting `CRON=1`
- Modified OPTIONS line to: 

 `OPTIONS="--create-prefs --max-children 5 --helper-home-dir --username debian-spamd"`.

### Max file size for virus scanning
The file size limit for incoming attachments is set to 25M. This is defined with `MaxFileSize` in the main configuratin file.

Also there is `StreamMaxLength`. This value should match your mail transport agent’s (MTA) limit for a maximum attachment size (see section "Postfix).

## Postfix
The files "main.cf" and "master.cf" contain a lot of changes. You should now what you do if you modify these files.
* **/etc/postfix/main.cf**
* **/etc/postfix/master.cf**

I try to comment as much as possible inside these files to help you understand the configuration.

You also find the SQL based maps for virtual transport here:
* **/etc/postfix/sql/*.cf**

### Message size limit
The parameter `message_size_limit` in `/etc/postfix/main.cf` is set to 26214400 bytes (25M). This has an effect on incoming and outgoing mail.

## Nginx
A site for mail is copied to `/etc/nginx/sites-available` and enabled via symbolic link to `/etc/nginx/sites-enabled`.
The sites root location is `/usr/share/nginx/mail/`. Any default site installed by "apt-get" is removed.

A PHP socket configuration is located at `/etc/php5/fpm/pool.d/mail.conf`. 
Some PHP parameters are set right here to override those in `/etc/php5/fpm/php.ini`.

Nginx' default configuration file `/etc/nginx/nginx.conf` contains changes to enable chunking, a connection rate limit and more.

## Fail2ban
A file `/etc/fail2ban/jail.local` is created with some pre-configured jails.

Ban time is set to 1h. "Jails" are created to lock unauthorized users (Postfix SASL [authentication], Sieve. etc.).
Default configuration parameters (example: retry count) can be reviewed in `/etc/fail2ban/jail.conf`.

I recommend to use `/etc/fail2ban/jail.local` to add or modify the configuration. 
`jail.local` has higher priority than `jail.conf`.

## Postfixadmin
The file "config.local.php" is copied to the target directory `/usr/share/nginx/mail/pfadmin`. Some parameters like "domain.tld" are dummies and replaced by the installer.

You can change some of these values to fit your personal needs by just editing or adding them to this file. 
All values inside "config.local.php" override the global configuration file (`config.inc.php`) of Postfixadmin. No need to reload any service afterwards. 

**Default quotas in MiB**

## Dovecot
If you really need to edit Dovecots configuration, you can find the required files in `/etc/dovecot`.

`/etc/dovecot/dovecot.conf` holds the default configuration. To keep it simple I chose not to split the configuration into multiple files. 

### Disallow insecure IMAP connections

Some people want to disable unencrypted authentication methods and require their users to either use SSL on Port 993 or STARTTLS on Port 143. To do so you need to change...

```
protocol imap {
  mail_plugins = quota imap_quota
}
```

...to...

```
protocol imap {
  mail_plugins = quota imap_quota
  ssl = required
  disable_plaintext_auth = yes
}
```

### Trash folder quota

The folder "Trash" is configured to allow an extra 100% of the set quota to allow moving mails to trash when a mailbox reaches >=51% of its quota. 

This is defined with `quota_rule2 = Trash:storage=+100%%` in `/etc/dovecot/dovecot.conf`.

### Dovecot SQL parameter
Dovecots SQL parameters can be found in either `/etc/dovecot/dovecot-dict-sql.conf` or `/etc/dovecot/dovecot-mysql.conf`.

- `dovecot-dict-sql.conf` holds instructions for reading a users quota.

- `dovecot-mysql.con` contains some basic SQL commands:
**driver** - What database  
**connect** - How to connect to the MySQL database   **default_pass_scheme** - Password scheme. If you edit this you also need to adjust Postfixadmin!  
**password_query** - Validate passwords.  
**user_query** - Validate users.  
**iterate_query** - Iterate users, also needed by a lot of "doveadm" commands.  


Furthermore a script `doverecalcq` is copied to `/etc/cron.daily` to recalculate quotas of all users daily.
 
A system with a very large amount of virtual users should not do this on a daily basis. I recommend to move the script to "cron.weekly" then.

*Dovecot saves messages to `/var/vmail/DOMAINNAME/USERNAME` in maildir format.*

### Doveadm common tasks

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

### Backup mail

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

## Roundcube

Roundcube is configured by multiple configuration files.

There are two files for the general configuration:

`/usr/share/nginx/mail/rc/config/defaults.php.inc` and `/usr/share/nginx/mail/rc/config/config.php.inc`. 
The later file is the one you want to edit. Every parameter set in `config.php.inc` will override the parameter set in `defaults.php.inc`.

Some plug-ins come with a seperate "config.inc.php" file. You can find them in `/usr/share/nginx/mail/rc/plugins/PLUGIN_NAME/`.

If no domain is specified for a login address, the webservers domain part will be appended.

### Attachment size
Default file size limit is set to 25 MB. If you want to change this, you need to see three files:

1. Open `/etc/php5/fpm/pool.d/mail.conf` and set `upload_max_filesize` to your new value. Change `post_max_size` to the same value + about 1M:
```
php_admin_value[upload_max_filesize] = 25M
php_admin_value[post_max_size] = 26M
```

2. Open Nginx' main configuration file `/etc/nginx/nginx.conf` and change the value of `client_max_body_size` to the value of `upload_max_filesize`.

3. Make sure `message_size_limit` (defined in bytes) in  `/etc/postfix/main.cf` is set >= `upload_max_filesize` . 

Restart "php5-fpm", "postfix" and "nginx" services.

# Debugging

Most important files for debugging:

* **/var/log/mail.log**
* **/var/log/mail.warn**
* **/var/log/mail.err**
* **/var/log/syslog**
* **/var/log/fuglu/fuglu.log**
* **/var/log/nginx/error.log**
* **/var/log/mysql.err**
* **/usr/share/nginx/mail/rc/logs/errors**
* **/var/log/php5-fpm.log**

Please always see these files when troubleshooting your mail server.

Keep in mind that you may need to enable debugging options for affected services!

# Uninstall
Run `bash misc/purge.sh` from within fufix directory to **completely purge** fufix, mailboxes, databases and any related service.
