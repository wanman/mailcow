<?php
session_start();
include_once("fcc/vars.inc.php");


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?php echo $mailname ?></title>
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
<form action="fcc/" method="post" id="logout"><input type="hidden" name="logout"></form>
<div class="jumbotron">
<div class="container">
<h2>Welcome @ <?php echo $mailname; ?></h2>
<p>Setup a mail client to use SMTP and IMAP</p>
<div class="row">
<div class="col-md-6">
<table class="table">
<thead>
  <tr>
	<th>Service</th>
	<th>Port</th>
	<th>Encryption</th>
  </tr>
</thead>
<tbody>
  <tr>
	<td>IMAP</td>
	<td>143</td>
	<td>STARTTLS</td>
  </tr>
  <tr>
	<td>IMAPS</td>
	<td>993</td>
	<td>SSL</td>
  </tr>
</tbody>
</table>
</div>
</div>
<div class="row">
<div class="col-md-6">
<table class="table">
<thead>
  <tr>
	<th>Service</th>
	<th>Port</th>
	<th>Encryption</th>
  </tr>
</thead>
<tbody>
  <tr>
	<td>SMTP</td>
	<td>587</td>
	<td>STARTTLS</td>
  </tr>
</tbody>
</table>
</div>
</div>
</div>
</div>
<div class="container">
<div class="row">
<div class="col-md-4">
	<h1>Webmail</h1>
	<p>Use <b>Roundcube</b>, a browser-based multilingual IMAP client, to read, write and manage your mails.</p>
	<p><a class="btn btn-m btn-success" href="rc/" role="button">Open &raquo;</a></p>
</div>
<div class="col-md-4">
	<h2>Mailbox Administration</h2>
	<p><b>Postfix Admin</b> is a web based interface used to manage mailboxes, virtual domains and aliases.</p>
	<p><a class="btn btn-sm btn-warning" href="pfadmin/" role="button">Open &raquo;</a></p>
</div>
<div class="col-md-4">
	<h3>fufix control center</h3>
	<p><b>Only administrators</b> want to use the fufix control center. Change settings on lowest level here.</p>
	<p><a class="btn btn-xs btn-danger" href="fcc/" role="button">Open &raquo;</a></p>
</div>
</div>
<hr>
<footer>
<h4>Health check (© MXToolBox)</h4>
<p>"The Domain Health Check will execute hundreds of domain/email/network performance tests to make sure all of your systems are online and performing optimally. The report will then return results for your domain and highlight critical problem areas for your domain that need to be resolved."</p>
<a class="btn btn-default" href="http://mxtoolbox.com/SuperTool.aspx?action=smtp:<?php echo $mailname ?>" target="_blank">Run &raquo;</a>
</footer>
</div> <!-- /container -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
<![endif]-->
<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
<!-- Optional theme -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap-theme.min.css">
<!-- Latest compiled and minified JavaScript -->
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
<link href="../css/signin.form.css" rel="stylesheet">
</body>
</html>
