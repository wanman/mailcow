<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
![mailcow](https://www.debinux.de/256.png)

- [mailcow](#mailcow)
- [Introduction](#introduction)
- [Before You Begin](#before-you-begin)
- [Installation](#installation)
- [Upgrade](#upgrade)
- [Uninstall](#uninstall)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

mailcow
=====

mailcow is a mail server suite based on Dovecot, Postfix, SabreDAV (Cal- and CardDAV) and other open source software, that provides a modern web UI for user/server administration.

mailcow supports **Debian stable (8.x)**

**Please see this album on imgur.com for screenshots -> http://imgur.com/a/elHnA**

# Introduction

* Multi-SAN self-signed SSL certificate for all installed and supporting services
    * Let's Encrypt optional
* Webserver installation
    * Apache or Nginx (+PHP5-FPM)
* SQL database backend, remote database support
    * MySQL or MariaDB
* **mailcow web UI**
    * Add domains, mailboxes, aliases, set limits, monitor mail statistics, change mail server settings, create/delete DKIM records, share and create calendars/address books and more...
* Postscreen activated and configured
* STARTTLS and SMTPS support
* The default restrictions used are a good compromise between blocking spam and avoiding false-positives
* Incoming and outgoing spam and virus protection with FuGlu as pre-queue content filter; [Heinlein Support](https://www.heinlein-support.de/) spamassassin rules included; Advanced ClamAV malware filters
* Sieve/ManageSieve (default filter: move spam to "Junk" folder, move tagged mail to folder "tag")
* Public folder support via control center
* per-user ACL
* Shared Namespace (per-user seen-flag)
* Quotas
* Roundcube
    * ManageSieve support (w/ vacation)
    * Attachment reminder (multiple locales)
    * Zip-download marked messages

# Before You Begin
- **Please remove any web- and mail services** running on your server. I recommend using a clean Debian minimal installation.
Remember to purge Debians default MTA Exim4:
```
apt-get purge exim4*
``` 

- If there is any firewall, unblock the following ports for incoming connections:

| Service               | Protocol | Port   |
| -------------------   |:--------:|:-------|
| Postfix Submission    | TCP      | 587    |
| Postfix SMTPS         | TCP      | 465    |
| Postfix SMTP          | TCP      | 25     |
| Dovecot IMAP          | TCP      | 143    |
| Dovecot IMAPS         | TCP      | 993    |
| Dovecot ManageSieve   | TCP      | 4190   |
| HTTP(S)               | TCP      | 80/443 |

- Next it is important that you **do not use Google DNS** or another public DNS which is known to be blocked by DNS-based Blackhole List (DNSBL) providers.

# Installation
**Please run all commands as root**

**Download a stable release**

Download mailcow to whichever directory (using ~/build here).
Replace "v0.x" with the tag of the latest release: https://github.com/andryyy/mailcow/releases/latest
```
mkdir ~/build ; cd ~/build
wget -O - https://github.com/andryyy/mailcow/archive/v0.x.tar.gz | tar xfz -
cd mailcow-*
```

**Now edit the file "configuration" to fit your needs!**
```
nano mailcow.config
```

* **sys_hostname** - Hostname without domain
* **sys_domain** - Domain name. "$sys_hostname.$sys_domain" equals to FQDN.
* **sys_timezone** - The timezone must be defined in a valid format (Europe/Berlin, America/New_York etc.)
* **httpd_platform** - Select wether to use Nginx ("nginx") or Apache2 ("apache2"). Nginx is default.
* **httpd_dav_subdomain** - A dedicated subdomain for Cal- and CardDAV. Must not be sys_hostname.
* **my_dbhost** - ADVANCED: Leave as-is ("localhost") for a local database installation. Anything but "localhost" or "127.0.0.1" is recognized as a remote installation.
* **my_usemariadb** - Use MariaDB instead of MySQL. Only valid for local databases. Installer stops when MariaDB is detected, but MySQL selected - and vice versa.
* **my_mailcowdb, my_mailcowuser, my_mailcowpass** - SQL database name, username and password for use with Postfix. **You can use the default values.**
* **my_rcdb, my_rcuser, my_rcpass** - SQL database name, username and password for Roundcube. **You can use the default values.**
* **my_rootpw** - SQL root password is generated automatically by default. You can define a complex password here if you want to. *Set to your current root password to use an existing SQL instance*.
* **mailcow_admin_user and mailcow_admin_pass** - mailcow administrator. Password policy: minimum length 8 chars, must contain uppercase and lowercase letters and at least 2 digits. **You can use the default values**.
* **inst_debug** - Sets Bash mode -x
* **inst_keepfiles** - Do not regenerate some expensive files (crypto parameters, ClamAV files) to increase debugging speed
* **inst_confirm_proceed** - Skip "Press any key to continue" dialogs by setting this to "no"

**Empty configuration values are invalid!**

You are ready to start the script:
```
./install.sh
```
Just be patient and confirm every step by pressing [ENTER] or [CTRL-C] to interrupt the installation.
If you run into problems, try to locate the error with "inst_debug" enabled in your configuration.
Please contact me when you need help or found a bug.

More debugging is about to come. Though everything should work as intended.

After the installation, visit your dashboard @ **https://hostname.example.com**, use the logged credentials in `./installer.log`

Remember to create an alias- or a mailbox for Postmaster. ;-)

Please set/update all DNS records accordingly. See "FAQ" -> "DNS records" in your mailcow admin panel.

# Upgrade
**Please run all commands as root**

Upgrade is supported since mailcow v0.7.x. From v0.9 on you do not need the file `installer.log` from a previous installation.

The mailcow configuration file will not be read, so there is no need to adjust it in any way before upgrading.

To start the upgrade, run the following command:
```
./install.sh -u
```

If you don't want to confirm each step of the upgrade, use `-U` instead:
```
./install -U
```

When autodetection of your hostname and/or domain name fails, use the `-H` parameter to overwrite the hostname and/or `-D` to overwrite the domain name:
```
# FQDN: mx.example.org
./install -u -H mx -D example.org
```

# Uninstall
Run `bash misc/purge.sh` from within mailcow directory to remove mailcow main components.

Your web server + web root, MySQL server + databases as well as your mail directory (/var/vmail) will **not** be removed (>= v0.9).

Please open and review the script before running it!

**You can perform a FULL WIPE** by appending `--all`:

```bash misc/purge.sh --all```

This WILL purge sensible data like your web root, databases + MySQL installation, mail directory and more... 
