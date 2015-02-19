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
<title>fufix control center</title>
<link href="../css/bootstrap.min.css" rel="stylesheet">
<link href="../css/signin.form.css" rel="stylesheet">
<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script
<![endif]-->
</head>

<body>

<div class="container">

<form name="logout" method="post">
<input name="logout" type="hidden">
</form>

<nav class="navbar navbar-inverse navbar-fixed-top">
	<div class="container">
		<div class="navbar-header">
			<a class="navbar-brand" href="#">fufix control center</a>
		</div>
		<div id="navbar" class="navbar-collapse collapse">
			<ul class="nav navbar-nav navbar-right">
				<li><a href="#" onclick="logout.submit()"><?php if ($_SESSION['fufix_cc_loggedin'] == "yes") { echo "Logout"; } else { echo ""; } ?></a></li>
			</ul>
		</div><!--/.nav-collapse -->
	</div><!--/.container-fluid -->
</nav>

<?php if ($_SESSION['fufix_cc_loggedin'] == "yes"): ?>

<div class="row" style="margin: 50px 20px 20px 50px;">

<h2>Attachments</h2>
<form method="post">
<div class="form-group">
<label for="ext">Dangerous file types</label>
<input class="form-control" type="text" id="ext" name="ext" value="<?php echo get_fufix_reject_attachments("ext") ?>">
<p><pre>Format: ext1|ext1|ext3
Enter "DISABLED" to disable this feature.
</pre></p>
<div class="checkbox">
<label>
<input name="virustotaltoggle" type="checkbox" <?php get_fufix_reject_attachments_toggle() ?>>
<p><b>Optional:</b> Scan dangerous attachments with VirusTotal.<br />
You will receive a mail including a link to the results.<br />
If unchecked, those mails will be <b>rejected</b>.</p>
</label>
</div>
<label for="vtapikey">VirusTotal API Key (<a href="https://www.virustotal.com/documentation/virustotal-community/#retrieve-api-key" target="_blank">?</a>)</label>
<input class="form-control" id="vtapikey" type="text" name="vtapikey" value="<?php echo file_get_contents($VT_API_KEY); ?>">
<br /><button type="submit" class="btn btn-primary">Apply</button>
</div>
</form>

<hr><h2>Sender Blacklist</h2>
<form method="post">
<div class="form-group"
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
<h2 style="margin-top:100px">Login</h2>
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
</body>
</html>






