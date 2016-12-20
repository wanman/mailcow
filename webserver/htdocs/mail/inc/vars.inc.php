<?php

/*
PLEASE USE THE FILE "vars.local.inc.php" TO OVERWRITE SETTINGS AND MAKE THEM PERSISTENT!
This file will be reset on upgrades.
*/

// SQL database connection variables
$database_type = "mysql";
$database_host = "my_dbhost";
$database_user = "my_mailcowuser";
$database_pass = "my_mailcowpass";
$database_name = "my_mailcowdb";

// Where to go after adding and editing objects
// Can be "form" or "previous"
// "form" will stay in the current form, "previous" will redirect to previous page
$FORM_ACTION = "previous";

// File locations should not be changed
$MC_ANON_HEADERS = "/etc/postfix/mailcow_anonymize_headers.pcre";
$MC_PUB_FOLDER = "/etc/dovecot/mailcow_public_folder.conf";
$MC_ODKIM_TXT = "/etc/opendkim/dnstxt";
$PFLOG = "/var/log/pflogsumm.log";

// Change default language, "en", "pt", "fr", "de" or "nl"
$DEFAULT_LANG = "en";

// Change theme (default: lumen)
// Needs to be one of those: cerulean, cosmo, cyborg, darkly, flatly, journal, lumen, paper, readable, sandstone,
// simplex, slate, spacelab, superhero, united, yeti
// See https://bootswatch.com/
$DEFAULT_THEME = "lumen";

// Unlisted elements cannot be moved to "inactive".
// reject_unauth_destination is not listed to prevent accidental removal.
// Known smtpd_sender_restrictions:
$VALID_SSR = array(
	'reject_authenticated_sender_login_mismatch',
	'permit_mynetworks',
	'reject_sender_login_mismatch',
	'permit_sasl_authenticated',
	'reject_unlisted_sender',
	'reject_unknown_sender_domain',
);
// Known smtpd_recipient_restrictions:
$VALID_SRR = array(
	'permit_sasl_authenticated',
	'permit_mynetworks',
	'reject_invalid_helo_hostname',
	'reject_unknown_helo_hostname',
	'reject_unknown_reverse_client_hostname',
	'reject_unknown_client_hostname',
	'reject_non_fqdn_helo_hostname',
	'greylist',
);

// Default hashing mechanism should not be changed. If changed, adjust dovecot-mysql.conf accordingly
$HASHING = "MAILCOW_HASHING";

?>
