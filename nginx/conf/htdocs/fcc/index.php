<?php
session_start();
include_once("vars.inc.php");
include_once("functions.inc.php");
include_once("triggers.inc.php");
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
<link href="signin.form.css" rel="stylesheet">
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
			<a class="navbar-brand" href="/"><?php echo $mailname ?></a>
		</div>
		<div id="navbar" class="navbar-collapse collapse">
			<ul class="nav navbar-nav navbar-right">
				<li><a href="/rc">Webmail</a></li>
				<li><a href="/pfadmin">Postfixadmin</a></li>
				<li><a href="/fcc">fufix control center</a></li>
				<li><a href="#" onclick="logout.submit()"><?php if ($_SESSION['fufix_cc_loggedin'] == "yes") { echo "Logout"; } else { echo ""; } ?></a></li>
			</ul>
		</div><!--/.nav-collapse -->
	</div><!--/.container-fluid -->
</nav>
<form action="/fcc/" method="post" id="logout"><input type="hidden" name="logout"></form>
<div class="container">
<?php if ($_SESSION['fufix_cc_loggedin'] == "yes"): ?>
<div class="row">
<h2>Attachments</h2>
<form method="post">
<div class="form-group">
<label for="ext">Dangerous file types</label>
<input class="form-control" type="text" id="ext" name="ext" value="<?php echo get_fufix_reject_attachments("ext") ?>">
<p><pre>Format: ext1|ext1|ext3
Enter "DISABLED" to disable this feature.
</pre></p>
</div>
<small>
<div class="form-group-sm">
<div class="checkbox">
<label>
<input name="virustotaltoggle" type="checkbox" <?php get_fufix_reject_attachments_toggle() ?>>
<b>Optional:</b> Scan dangerous attachments with VirusTotal.<br />
You will receive a mail including a link to the results.<br />
If unchecked, those mails will be <b>rejected</b>.
</label>
</div>
<label for="vtapikey">VirusTotal API Key (<a href="https://www.virustotal.com/documentation/virustotal-community/#retrieve-api-key" target="_blank">?</a>)</label>
<input class="form-control" id="vtapikey" type="text" name="vtapikey" value="<?php echo file_get_contents($VT_API_KEY); ?>">
<br /><button type="submit" class="btn btn-primary">Apply</button>
</div>
</small>
</form>

<hr><h2>Sender Blacklist</h2>
<form method="post">
<div class="form-group">
<p>Specify a list of senders or domains to blacklist access:</p>
<textarea class="form-control" rows="6" name="sender"><?php echo get_fufix_sender_access() ?></textarea>
<p> </p>
<button type="submit" class="btn btn-primary">Apply</button>
</div>
</form>

<hr><h2>Privacy</h2>
<form method="post">
<div class="form-group">
<p>Anonymize outgoing mail.</p>
<div class="checkbox">
<label>
<input type="hidden" name="anonymize_">
<input name="anonymize" type="checkbox" <?php get_fufix_anonymize_toggle() ?>>
<p>This option enables a PCRE table to remove "User-Agent", "X-Enigmail", "X-Mailer", "X-Originating-IP" and replaces "Received: from" headers with localhost/127.0.0.1.</p>
</label>
</div>
<button type="submit" class="btn btn-primary">Apply</button>
</div>
</form>

<hr><h2>Backup mail</h2>
<form method="post">
<div class="form-group">
<p>Download a copy of your vmail directory as tar.bz2 archive.
This is a very simple function that may or may not work. Consider it unstable.</p>
<button type="submit" class="btn btn-info">Download</button>
<input type="hidden" name="backupdl">
</div>
</form>
</div>

<?php else: ?>
<h2>Login</h2>
<form class="form-signin" method="post">
<div class="form-group"
<p><input name="login_user" type="email" id="inputEmail" class="form-control" placeholder="pfadmin@domain.tld" required autofocus></p>
<p><input name="pass_user" type="password" id="inputPassword" class="form-control" placeholder="Password" required></p>
<p>You can login with any superadmin created in <b><a href="../pfadmin">Postfixadmin</a></b>.</p>
<input type="submit" class="btn btn-success" value="Login">
</div>
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
