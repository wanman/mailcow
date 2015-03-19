<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
**Table of Contents**  *generated with [DocToc](http://doctoc.herokuapp.com/)*

- [fufix](#fufix)
- [Introduction](#introduction)
- [Before You Begin](#before-you-begin)
- [Installation](#installation)
- [Upgrade](#upgrade)
- [Configuration and common tasks](#configuration-and-common-tasks)
  - [SSL certificate](#ssl-certificate)
  - [Spamassassin](#spamassassin)
    - [Autolearn](#autolearn)
    - [Spam rewrite](#spam-rewrite)
    - [Spamassassin daemon options](#spamassassin-daemon-options)
  - [Postfix](#postfix)
  - [Nginx](#nginx)
  - [Fail2ban](#fail2ban)
  - [Postfixadmin](#postfixadmin)
  - [Dovecot](#dovecot)
    - [Trash folder quota](#trash-folder-quota)
    - [Dovecot SQL parameter](#dovecot-sql-parameter)
    - [Doveadm common tasks](#doveadm-common-tasks)
    - [Backup mail](#backup-mail)
  - [Roundcube](#roundcube)
  - [Change attachment/message size](#change-attachmentmessage-size)
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

![fufix v0.8](https://www.debinux.de/wp-content/uploads/ffg.png "fufix v0.8")

# Introduction
A summary of what software is installed with which features enabled.

**General setup**
* System environment adjustments (hostname, timzone, etc.)
* Automatically generated passwords with high complexity
* Self-signed SSL certificate for all installed and supporting services
* Optimized Nginx (+PHP5-FPM) installation (HTTP-to-HTTPS, BetterCrypto)
* MySQL database backend
* DNS-Checks by Google DNS (PTR, A-Record, SPF etc.)
* Learn ham and spam, [Heinlein Support](https://www.heinlein-support.de/) SA rules included
* Fail2ban brute force protection
* A webpanel

**Postfix**
* Postscreen on Port 25 to block most zombies sending spam
* Submission port activated (TCP/587), TLS-only
* The restrictions used are a good compromise between blocking spam and avoiding false-positives
* Incoming and outgoing spam protection
* VirusTotal Uploader for incoming mail
* SSL based on BetterCrypto 

**Dovecot**
* Default mailboxes to subscribe to automatically (Inbox, Sent, Drafts, Trash, Junk - "SPECIAL-USE" tags)
* Sieve/ManageSieve
* Global sieve filter: Move mail marked as spam into "Junk"
* (IMAP) Quotas
* LMTP service for Postfix virtual transport
* SSL based on BetterCrypto

**Postfixadmin**
* Automatically creates an Administrator
* Full quota support
* Fetchmail support

**Roundcube**
* ManageSieve support (w/ vacation)
* Users can change password
* Attachment reminder (multiple locales)
* Zip-download marked messages
* 25M attachment size (see "File size limitation")

# Before You Begin
- **Please remove any web- and mail services** running on your server. I recommend using a clean Debian minimal installation.
Remember to purge Debians default MTA Exim4:
```
apt-get purge exim4*
``` 

- If there is any firewall, unblock the following ports for incoming connections:

| Service               | Protocol | Port |
| -------------------   |:--------:|:-----|
| Postfix Submission    | TCP      | 587  |
| Postfix SMTP          | TCP      | 25   |
| Dovecot IMAP          | TCP      | 143  |
| Dovecot IMAPS         | TCP      | 993  |
| Dovecot ManageSieve   | TCP      | 4190 |
| Nginx HTTPS           | TCP      | 443  |
| Nginx HTTP (Redirect) | TCP      | 80   |

- Next it is important that you **do not use Google DNS** or another public DNS which is known to be blocked by DNS-based Blackhole List (DNSBL) providers.

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

**Option 2 - NOT RECOMMENDED, this may or may not work: Install from git**

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
* **inst_debug** - Sets Bash mode -x
* An unattended installation is possible, but not recommended ("inst_unattended")
* Set **conf_done** to **yes** or anything except "no".

**Empty configuration values are invalid!**

You are ready to start the script:
```
./install.sh
```
Just be patient and confirm every step by pressing [ENTER] or [CTRL-C] to interrupt the installation.
If you run into problems, try to locate the error with "inst_debug" enabled in your configuration.
Please contact me when you need help or found a bug.

More debugging is about to come. Though everything should work as intended.

After the installation, visit your dashboard @ **https://hostname.domain.tld**, use the logged credentials in `./installer.log`

Remember to create an alias- or a mailbox for Postmaster. ;-)

# Upgrade
**Please run all commands as root**

Upgrade is supported since fufix v0.7.x. You need the file `installer.log` from a previous installation.

**! IMPORTANT for v0.7.x to v0.8:** Please install the following packages before running the upgrade:
```
apt-get install sudo bzip2 curl mpack fetchmail liblockfile-simple-perl libdbi-perl libmime-base64-urlsafe-perl libtest-tempdir-perl liblogger-syslog-perl
```

To start the upgrade, run the following command:
```
./install.sh -u /path/to/installer.log
```

# Configuration and common tasks
To help you modify the configuration, I created this little overview to get you started.

## SSL certificate
The SSL certificate is located at `/etc/ssl/mail/mail.{key,crt}`.
You can replace it by just copying over your own files. 
Services effected and necessary to restart are `postfix`, `dovecot` and `nginx`.

## Spamassassin
Spamassassin main configuration file:
* **/etc/spamassassin/local.cf**

### Autolearn
Move undetected spam to "Junk" to make Spamassassin autolearn it. This is done by a daily cronjob.

Ham (non-spam) is learned the same way. Move false-positives to your inbox to autolearn them.

### Spam rewrite
fufix adds `rewrite_header Subject [SPAM]` and `report_safe 2` to prefix [SPAM] to junk mail and forward spam as attachment instead of original message (text/plain). 

The prefix "[SPAM]" is not important for the sieve filter and can be changed to whatever text. Spam will be moved when te Spam Flag is set the header.

### Spamassassin daemon options
Default startup options for Spamassassin in `/etc/default/spamassassin`:
- Enabled "spamd" by adding `ENABLED=1`
- Enabled cronjob by setting `CRON=1`
- Modified OPTIONS line to `OPTIONS="--create-prefs --max-children 5 --helper-home-dir"`.

## Postfix
The files "main.cf" and "master.cf" contain a lot of changes. You should now what you do if you modify these files.
* **/etc/postfix/main.cf**
* **/etc/postfix/master.cf**

I try to comment as much as possible inside these files to help you understand the configuration.

You also find the SQL based maps for virtual transport here:
* **/etc/postfix/sql/*.cf**

For a quick overview of the restrictions [click here](https://github.com/andryyy/fufix/blob/master/postfix/conf/main.cf).

## Nginx
A site for mail is copied to `/etc/nginx/sites-available` and enabled via symbolic link to `/etc/nginx/sites-enabled`.
The sites root location is `/var/www/mail/`. Any default site installed by "apt-get" is removed.

A PHP socket configuration is located at `/etc/php5/fpm/pool.d/mail.conf`. 
Some PHP parameters are set right here to override those in `/etc/php5/fpm/php.ini`.

Nginx' default configuration file is `/etc/nginx/nginx.conf`.

## Fail2ban
A file `/etc/fail2ban/jail.local` is created with some pre-configured jails.

Ban time is set to 1h. "Jails" are created to lock unauthorized users (Postfix SASL [authentication], Sieve. etc.).
Default configuration parameters (example: retry count) can be reviewed in `/etc/fail2ban/jail.conf`.

I recommend to use `/etc/fail2ban/jail.local` to add or modify the configuration. 
`jail.local` has higher priority than `jail.conf`.

## Postfixadmin
The file "config.local.php" is copied to the target directory `/var/www/mail/pfadmin`. Some parameters like "domain.tld" are dummies and replaced by the installer.

You can change some of these values to fit your personal needs by just editing or adding them to this file. 
All values inside "config.local.php" override the global configuration file (`config.inc.php`) of Postfixadmin. No need to reload any service afterwards. 

**Default quotas in MiB**

## Dovecot
If you really need to edit Dovecots configuration, you can find the required files in `/etc/dovecot`.

`/etc/dovecot/dovecot.conf` holds the default configuration. To keep it simple I chose not to split the configuration into multiple files. 

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

## Roundcube

Roundcube is configured by multiple configuration files.

There are two files for the general configuration:

`/var/www/mail/rc/config/defaults.php.inc` and `/var/www/mail/rc/config/config.php.inc`. 
The later file is the one you want to edit. Every parameter set in `config.php.inc` will override the parameter set in `defaults.php.inc`.

Some plug-ins come with a seperate "config.inc.php" file. You can find them in `/var/www/mail/rc/plugins/PLUGIN_NAME/`.

If no domain is specified for a login address, the webservers domain part will be appended.

## Change attachment/message size
Default file size limit is set to 25 MB. If you want to change this, either use the fufix control center or the command `fufix_msg_size` in a terminal:

```
fufix_msg_size VALUE_IN_MB
``` 

# Uninstall
Run `bash misc/purge.sh` from within fufix directory to **completely purge** fufix, mailboxes, databases and any related service.
