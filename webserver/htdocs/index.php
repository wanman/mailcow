<?php
session_start();
require_once "fcc/inc/vars.inc.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $MYHOSTNAME ?></title>
<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
<![endif]-->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
<link href="css/material.min.css" rel="stylesheet">
<link href="css/ripples.min.css" rel="stylesheet">
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
<form action="fcc/" method="post" id="logout"><input type="hidden" name="logout"></form>
<div class="jumbotron">
        <div class="container">
                <h2>Welcome @ <?php echo $MYHOSTNAME; ?></h2>
                <p>Setup a mail client to use SMTP and IMAP</p>
                <div class="row">
                        <div class="col-md-6">
                                <small><b>IMAP(S)</b></small>
                                <ul class="ul-horizontal">
                                        <li><code><?php echo $MYHOSTNAME; ?>:143/tcp</code></li>
                                        <li><code><?php echo $MYHOSTNAME; ?>:993/tcp</code></li>
                                </ul>
                                <small><b>SMTP</b></small>
                                <ul>
                                        <li><code><?php echo $MYHOSTNAME; ?>:587/tcp</code></li>
                                </ul>
                        </div>
                </div>
        </div>
</div>
<div class="container">
                <h4>Health check (© MXToolBox)</h4>
                <p>"The Domain Health Check will execute hundreds of domain/email/network performance tests to make sure all of your systems are online and performing optimally. The report will then return results for your domain and highlight critical problem areas for your domain that need to be resolved."</p>
                <a class="btn btn-material-grey" href="http://mxtoolbox.com/SuperTool.aspx?action=smtp:<?php echo $MYHOSTNAME ?>" target="_blank">Run &raquo;</a>
                <br />
</div> <!-- /container -->
<script src="https://code.jquery.com/jquery-1.10.2.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
<script src="js/ripples.min.js"></script>
<script src="js/material.min.js"></script>
<script>
$(document).ready(function() {
        $.material.init();
});
</script>
</body>
</html>
