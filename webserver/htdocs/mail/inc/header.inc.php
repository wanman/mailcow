<?php
session_start();
if (isset($_SESSION['mailcow_cc_loggedin']) && !empty($_SESSION['mailcow_cc_loggedin'])) {
	$logged_in_as = $_SESSION['mailcow_cc_username'];
	$logged_in_role = $_SESSION['mailcow_cc_role'];
}
else {
	$logged_in_role = "";
	$logged_in_as = "";
}
require_once "inc/vars.inc.php";
$link = mysqli_connect($database_host, $database_user, $database_pass, $database_name);
if (!$link) {
	die("Connection error: " . mysqli_connect_error());
}
require_once "inc/functions.inc.php";
require_once "inc/triggers.inc.php";
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
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
<link href="css/material.min.css" rel="stylesheet">
<link href="css/ripples.min.css" rel="stylesheet">
<?php
if (basename($_SERVER['PHP_SELF']) == "mailbox.php"):
?>
<style>
.panel-heading div {
	margin-top: -18px;
	font-size: 15px;
}
.panel-heading div span {
	margin-left:5px;
}
.panel-body {
	display: none;
}
.clickable {
	cursor: pointer;
}

</style>
<?php
endif;
?>
<style>
html {
	overflow-y:scroll;
}
.navbar.navbar, .navbar-default.navbar {
  background-color: #463168;
}
a, a:hover {
	color: #333;
}
.dropdown-menu>li>a:focus {
	color: #777 !important;
}
.dropdown-menu>li>a:hover {
	color: #777 !important;
}
@media(max-width:767px)  {
	.dropdown-menu>li>a:hover {
		color: #f5f5f5 !important;
	}
}
</style>
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
			<a class="navbar-brand" href="/"><img src="inc/xs_mailcow.png" /></a>
		</div>
		<div id="navbar" class="navbar-collapse collapse">
			<ul class="nav navbar-nav navbar-right">
				<li><a href="/rc">Webmail</a></li>
				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">Control center<span class="caret"></span></a>
					<ul class="dropdown-menu" role="menu">
<?php
if (isset($_SESSION['mailcow_cc_loggedin']) && $_SESSION['mailcow_cc_loggedin'] == "yes"):
?>
<?php
switch ($logged_in_role) {
	case "admin":
?>
						<li><a href="/admin.php">Administration</a></li>
<?php
	case "domainadmin":
?>
						<li><a href="/mailbox.php">Mailboxes</a></li>
<?php
	break;
	case "user":
?>
						<li><a href="/user.php">User settings</a></li>
<?php
}
else:
?>
						<li><a href="/admin.php">Login</a></li>
<?php
endif;
?>
					</ul>
				</li>
<?php
if (isset($_SESSION['mailcow_cc_loggedin']) && $_SESSION['mailcow_cc_loggedin'] == "yes"):
?>
				<li class="divider"></li>
				<li>
					<a href="#" onclick="logout.submit()">Hello, <strong><?=$logged_in_as;?></strong> (logout)</a>
				</li>
<?php
endif;
?>
			</ul>
		</div><!--/.nav-collapse -->
	</div><!--/.container-fluid -->
</nav>
<form action="/admin.php" method="post" id="logout"><input type="hidden" name="logout"></form>
<?php
if (isset($_SESSION['return'])):
?>
<div class="container">
	<div class="alert alert-<?=$_SESSION['return']['type'];?>" role="alert">
	<a href="#" class="close" data-dismiss="alert">&times;</a>
	<?=$_SESSION['return']['msg'];?>
	</div>
</div>
<?php
unset($_SESSION['return']);
endif;
?>