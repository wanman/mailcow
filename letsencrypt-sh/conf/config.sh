#!/bin/bash
CA="https://acme-v01.api.letsencrypt.org/directory"
LICENSE="https://letsencrypt.org/documents/LE-SA-v1.1.1-August-1-2016.pdf"
CHALLENGETYPE="http-01"
BASEDIR="/etc/ssl/mail"
WELLKNOWN="/var/www/mail/.well-known/acme-challenge"
KEYSIZE="2048"
RENEW_DAYS="30"
CONTACT_EMAIL="postmaster@MAILCOW_DOMAIN"
# Better when using TLSA Records, be sure to use 3 1 1
PRIVATE_KEY_RENEW="no"
