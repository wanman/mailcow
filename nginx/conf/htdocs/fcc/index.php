<?php
session_start();
include_once("inc/vars.inc.php");
include_once("inc/functions.inc.php");
include_once("inc/triggers.inc.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>fufix control center</title>
<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
<![endif]-->
<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
<!-- Optional theme -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap-theme.min.css">
<link href="css/signin.form.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-default">
	<div class="container-fluid">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="/"><?php echo $MYHOSTNAME ?></a>
		</div>
		<div id="navbar" class="navbar-collapse collapse">
			<ul class="nav navbar-nav navbar-right">
				<li><a href="/rc">Webmail</a></li>
				<li><a href="/pfadmin">Postfixadmin</a></li>
				<li><a href="/fcc">fufix control center</a></li>
				<li><a href="#" onclick="logout.submit()"><?php if (isset($_SESSION['fufix_cc_loggedin']) && $_SESSION['fufix_cc_loggedin'] == "yes") { echo "Logout"; } else { echo ""; } ?></a></li>
			</ul>
		</div><!--/.nav-collapse -->
	</div><!--/.container-fluid -->
</nav>
<form action="/fcc/" method="post" id="logout"><input type="hidden" name="logout"></form>
<div class="container">
<?php if (isset($_SESSION['fufix_cc_loggedin']) && $_SESSION['fufix_cc_loggedin'] == "yes"): ?>
<div class="row">

<h1><span class="glyphicon glyphicon-wrench" aria-hidden="true"></span> Configuration</h1>

<h3>Attachments</h3>
<form method="post">
<div class="form-group">
	<p>Provide a list of dangerous file types. Please take care of the formatting.</p>
	<input class="form-control" type="text" id="ext" name="ext" value="<?php echo return_fufix_reject_attachments("ext") ?>">
	<p><pre>Format: ext1|ext1|ext3
Enter "DISABLED" to disable this feature.</pre></p>
	<hr>
	<div class="row">
		<div class="col-xs-10">
		<small>
		<h4>VirusTotal Uploader</h4>
		<div class="checkbox">
			<label>
			<input name="virustotaltoggle" type="checkbox"  <?php echo return_fufix_reject_attachments_toggle() ?>>
			<b>Optional:</b> Scan dangerous attachments with VirusTotal. You will receive a mail including a link to the results. If unchecked, those mails will be <b>rejected</b>.
			</label>
		</div>
		<label for="vtapikey">VirusTotal API Key (<a href="https://www.virustotal.com/documentation/virustotal-community/#retrieve-api-key" target="_blank">?</a>)</label>
		<input class="form-control" id="vtapikey" type="text" name="vtapikey" value="<?php echo file_get_contents($VT_API_KEY); ?>">
		</small>
		</div>
	</div>
	<br /><button type="submit" class="btn btn-primary btn-sm">Apply</button>
</div>
</form>

<hr>
<h3>Sender Blacklist</h3>
<form method="post">
<div class="form-group">
	<p>Specify a list of senders or domains to blacklist access:</p>
	<textarea class="form-control" rows="6" name="sender"><?php echo_fufix_sender_access() ?></textarea>
	<br /><button type="submit" class="btn btn-primary btn-sm">Apply</button>
</div>
</form>

<hr>
<h3>Privacy</h3>
<form method="post">
<div class="form-group">
	<p>This option enables a PCRE table to remove "User-Agent", "X-Enigmail", "X-Mailer", "X-Originating-IP" and replaces "Received: from" headers with localhost/127.0.0.1.</p>
	<div class="checkbox">
	<label>
	<input type="hidden" name="anonymize_">
	<input name="anonymize" type="checkbox" <?php echo return_fufix_anonymize_toggle() ?>>
	<p>Anonymize outgoing mail.</p>
	</label>
	</div>
	<button type="submit" class="btn btn-primary btn-sm">Apply</button>
</div>
</form>

<hr>
<h3>DKIM signing</h3>
<?php if (return_fufix_anonymize_toggle() === false) { ?>
<p>Default behaviour is to sign with relaxed header and body canonicalization algorithm.</p>
<form method="post" action="index.php">
<h4>Active keys</h4>
<?php echo_fufix_opendkim_table() ?>
<h4>Add new key</h4>
<div class="form-group">
	<div class="row">
		<div class="col-xs-4">
			<strong>Domain</strong>
			<input class="form-control" id="dkim_domain" name="dkim_domain" placeholder="example.org">
		</div>
		<div class="col-xs-4">
			<strong>Selector</strong>
			<input class="form-control" id="dkim_selector" name="dkim_selector" placeholder="default">
		</div>
		<div class="col-xs-4">
			<br /><button type="submit" class="btn btn-primary btn-sm"><span class="glyphicon glyphicon-plus"></span> Add</button>
		</div>
	</div>
</div>
</form>
<?php } else { ?>
<p><span class="label label-danger">DKIM signing is not available when "Anonymize outgoing mail" is enabled.</span></p>
<? } ?>

<hr>
<form class="form-inline" method="post">
	<h3>Max message size</h3>
	<p>Current message size limitation: <strong><?php echo `echo $(( $(/usr/sbin/postconf -h message_size_limit) / 1048576 ))`; ?>MB</strong></p>
	<p>This changes values in PHP, Nginx and Postfix. Services will be reloaded.</p>
	<div class="form-group">
		<input type="number" class="form-control" id="maxmsgsize" name="maxmsgsize" placeholder="in MB" min="1" max="250">
	</div>
	<button type="submit" class="btn btn-primary">Set</button>
</form>

<br />
<h1><span class="glyphicon glyphicon-dashboard" aria-hidden="true"></span> Maintenance</h1>

<hr>
<h3>DNS records</h3>
<p>Below you see a list of <em>recommended</em> DNS records.</p>
<p>While some are mandatory for a mail server (A, MX), others are recommended to build a good reputation score (TXT/SPF) or used for auto-configuration of mail clients (A: "autoconfig" and SRV records).</p>
<p>In this automatically generated DNS zone file snippet, <mark>a generic TXT/SPF record</mark> is used to only allow THIS server to send mail for your domain. Please refer to <a href="http://www.openspf.org/SPF_Record_Syntax" target="_blank">openspf.org</a>.</p>
<p>It is <strong>highly recommended</strong> to create a DKIM TXT record with the <em>DKIM signing</em> utility tool above and install the given TXT record to your nameserver, too.</p>
<pre>
; ================
; Example forward zone file
; ================

[...]
_imaps._tcp         IN SRV     0 1 993 <?php echo $MYHOSTNAME; ?>.
_imap._tcp          IN SRV     0 1 143 <?php echo $MYHOSTNAME; ?>.
_submission._tcp    IN SRV     0 1 587 <?php echo $MYHOSTNAME; ?>.
@                   IN MX 10   <?php echo $MYHOSTNAME_0, "\n"; ?>
@                   IN TXT     "v=spf1 mx -all"
autoconfig          IN A       <?php echo $IP, "\n"; ?>
<?php echo str_pad($MYHOSTNAME_0, 20); ?>IN A       <?php echo $IP, "\n"; ?>

; !!!!!!!!!!!!!!!!
; Do not forget to set a PTR record in your Reverse DNS configuration!
; Your IPs PTR should point to <?php echo $MYHOSTNAME, "\n"; ?>
; !!!!!!!!!!!!!!!!
</pre>

<hr>
<h3>FAQ: Terminal tasks</h3>
<p><strong>Example usage of <em>doveadm</em> for common tasks regarding Dovecot.</strong></p>
<pre style="border: 0px; background-color: #333; color: #7CFC00;">
; Searching for inbox messages saved in the past 3 days for user "Bob.Cat":
doveadm search -u bob.cat@domain.com mailbox inbox savedsince 2d

; ...or search Bobs inbox for subject "important":
doveadm search -u bob.cat@domain.com mailbox inbox subject important

; Delete Bobs messages older than 100 days?
doveadm expunge -u bob.cat@domain.com mailbox inbox savedbefore 100d

; From Wiki: Move jane's messages - received in September 2011 - from her INBOX into her archive.
doveadm move -u jane Archive/2011/09 mailbox INBOX BEFORE 2011-10-01 SINCE 01-Sep-2011

; Visit http://wiki2.dovecot.org/Tools/Doveadm
</pre>

<p><strong>Backup mail</strong></p>
<pre style="border: 0px; background-color: #333; color: #7CFC00;">
; If you want to create a backup of Bobs maildir to /var/mailbackup, just go ahead and create the backup destination with proper rights:
mkdir /var/mailbackup
chown vmail:vmail /var/mailbackup/

; Afterwards you can start a full backup:
dsync -u bob.cat@domain.com backup maildir:/var/mailbackup/

; Visit http://wiki2.dovecot.org/Tools/Dsync
</pre>

<p><strong>Debugging</strong></p>
<pre style="border: 0px; background-color: #333; color: #7CFC00;">
; Pathes to important log files:
/var/log/mail.log
/var/log/syslog
/var/log/nginx/error.log
/var/www/mail/rc/logs/errors
/var/log/php5-fpm.log
</pre>

<hr>
<h3>System information</h3>
<p>This is a very simple system information function. Please be aware that a high RAM usage is what you want on a server.</p>
<div class="row">
	<div class="col-xs-6">
<?php $du = preg_replace('/\D/', '', `df -h /var/vmail/ | tail -n1 | awk {'print $5'}`); ?>
		<h4>Disk usage (/var/vmail) - <?php echo $du; ?>%</h4>
		<div class="progress">
		  <div class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?php echo $du; ?>"
		  aria-valuemin="0" aria-valuemax="100" style="width:<?php echo $du; ?>%">
		  </div>
		</div>
	</div>
	<div class="col-xs-6">
<?php $ru = round(`free | grep Mem | awk '{print $3/$2 * 100.0}'`); ?>
		<h4>RAM usage - <?php echo $ru; ?>%</h4>
		<div class="progress">
		  <div class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?php echo $ru; ?>"
		  aria-valuemin="0" aria-valuemax="100" style="width:<?php echo $ru; ?>%">
		  </div>
		</div>
	</div>
</div>

<h4>Mail queue</h4>
<pre>
<?php echo `mailq`; ?>
</pre>

</div>

<?php else: ?>
<h3>Login</h3>
<form class="form-signin" method="post">
	<input name="login_user" type="email" id="inputEmail" class="form-control" placeholder="pfadmin@domain.tld" required autofocus>
	<input name="pass_user" type="password" id="inputPassword" class="form-control" placeholder="Password" required>
	<p>You can login with any superadmin created in <b><a href="../pfadmin">Postfixadmin</a></b>.</p>
	<input type="submit" class="btn btn-success" value="Login">
</form>
<?php endif ?>
<hr>
<p><b><a href="../">&#8592; go back</a></b></p>
</div> <!-- /container -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
<!-- Latest compiled and minified JavaScript -->
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
</body>
</html>
