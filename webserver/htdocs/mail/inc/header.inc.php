<?php
ini_set("session.cookie_secure", 1);
ini_set("session.cookie_httponly", 1);
session_start();
if (isset($_POST["logout"])) {
	session_unset();
	session_destroy();
	session_write_close();
	setcookie(session_name(),'',0,'/');
}

require_once 'inc/vars.inc.php';
include_once 'inc/vars.local.inc.php';

$dsn = "$database_type:host=$database_host;dbname=$database_name";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = new PDO($dsn, $database_user, $database_pass, $opt);

if (isset($_POST['lang'])) {
	switch ($_POST['lang']) {
		case "de":
			$_SESSION['mailcow_locale'] = 'de';
		break;
		case "en":
			$_SESSION['mailcow_locale'] = 'en';
		break;
		case "pt":
			$_SESSION['mailcow_locale'] = 'pt';
		break;
	}
}
if (!isset($_SESSION['mailcow_locale'])) {
	$_SESSION['mailcow_locale'] = strtolower(trim($DEFAULT_LANG));
}
require_once 'lang/lang.en.php';
include 'lang/lang.'.$_SESSION['mailcow_locale'].'.php';
require_once 'inc/functions.inc.php';
require_once 'inc/triggers.inc.php';

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo gethostname() ?></title>
<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
<![endif]-->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.0/jquery.min.js" integrity="sha384-XxcvoeNF5V0ZfksTnV+bejnCsJjOOIzN6UVwF85WBsAnU3zeYh5bloN+L4WLgeNE" crossorigin="anonymous"></script>
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/css/bootstrap.min.css">
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootswatch/3.3.6/<?=strtolower(trim($DEFAULT_THEME));?>/bootstrap.min.css">
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.9.4/css/bootstrap-select.min.css">
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/7.0.2/css/bootstrap-slider.min.css">
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-switch/3.3.2/css/bootstrap3/bootstrap-switch.min.css">
<link rel="stylesheet" href="//fonts.googleapis.com/css?family=Source+Sans+Pro:400,600,700&subset=latin,latin-ext">
<link rel="stylesheet" href="/inc/languages.min.css">
<link rel="shortcut icon" href="/favicon.png" type="image/png" />
<link rel="icon" href="/favicon.png" type="image/png" />
<style>
ul[id*="sortable"] { word-wrap: break-word; list-style-type: none; float: left; padding: 0 15px 0 0; width: 48%; cursor:move}
ul[id$="sortable-active"] li {cursor:move; }
ul[id$="sortable-inactive"] li {cursor:move }
.list-heading { cursor:default !important}
.ui-state-disabled { cursor:no-drop; color:#ccc; }
.ui-state-highlight {background: #F5F5F5 !important; height: 41px !important; cursor:move }
#slider1 .slider-selection {
	background: #FFD700;
}
#slider1 .slider-track-high {
	background: #FF4500;
}
#slider1 .slider-track-low {
	background: #66CD00;
}
table[data-sortable] {
  border-collapse: collapse;
  border-spacing: 0;
}
table[data-sortable] th {
  vertical-align: bottom;
  font-weight: bold;
}
table[data-sortable] th, table[data-sortable] td {
  text-align: left;
  padding: 10px;
}
table[data-sortable] th:not([data-sortable="false"]) {
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  -o-user-select: none;
  user-select: none;
  -webkit-tap-highlight-color: rgba(0, 0, 0, 0);
  -webkit-touch-callout: none;
  cursor: pointer;
}
table[data-sortable] th:after {
  content: "";
  visibility: hidden;
  display: inline-block;
  vertical-align: inherit;
  height: 0;
  width: 0;
  border-width: 5px;
  border-style: solid;
  border-color: transparent;
  margin-right: 1px;
  margin-left: 10px;
  float: right;
}
table[data-sortable] th[data-sorted="true"]:after {
  visibility: visible;
}
table[data-sortable] th[data-sorted-direction="descending"]:after {
  border-top-color: inherit;
  margin-top: 8px;
}
table[data-sortable] th[data-sorted-direction="ascending"]:after {
  border-bottom-color: inherit;
  margin-top: 3px;
}
table[data-sortable].sortable-theme-bootstrap thead th {
  border-bottom: 2px solid #e0e0e0;
}
table[data-sortable].sortable-theme-bootstrap th[data-sorted="true"] {
  color: #3a87ad;
  background: #d9edf7;
  border-bottom-color: #bce8f1;
}
table[data-sortable].sortable-theme-bootstrap th[data-sorted="true"][data-sorted-direction="descending"]:after {
  border-top-color: #3a87ad;
}
table[data-sortable].sortable-theme-bootstrap th[data-sorted="true"][data-sorted-direction="ascending"]:after {
  border-bottom-color: #3a87ad;
}
table[data-sortable].sortable-theme-bootstrap.sortable-theme-bootstrap-striped tbody > tr:nth-child(odd) > td {
  background-color: #f9f9f9;
}
</style>
<?php
if (preg_match("/mailbox.php/i", $_SERVER['REQUEST_URI'])):
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
</head>
<body style="padding-top:70px">
<nav class="navbar navbar-default navbar-fixed-top"  role="navigation">
	<div class="container-fluid">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="/"><img style="margin-top:-5px;" src="/img/xs_mailcow.png" /></a>
		</div>
		<div id="navbar" class="navbar-collapse collapse">
			<ul class="nav navbar-nav navbar-right">
				<?php
				if (isset($_SESSION['mailcow_locale'])) {
				?>
				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><span class="lang-sm lang-lbl" lang="<?=$_SESSION['mailcow_locale'];?>"></span><span class="caret"></span></a>
					<ul class="dropdown-menu" role="menu">
						<li <?=($_SESSION['mailcow_locale'] == 'de') ? 'class="active"' : ''?>> <a href="#" onClick="setLang('de')"><span class="lang-xs lang-lbl-full" lang="de"></span></a></li>
						<li <?=($_SESSION['mailcow_locale'] == 'en') ? 'class="active"' : ''?>> <a href="#" onClick="setLang('en')"><span class="lang-xs lang-lbl-full" lang="en"></span></a></li>
						<li <?=($_SESSION['mailcow_locale'] == 'pt') ? 'class="active"' : ''?>> <a href="#" onClick="setLang('pt')"><span class="lang-xs lang-lbl-full" lang="pt"></span></a></li>
					</ul>
				</li>
				<?php
				}
				if (isset($_SESSION['mailcow_cc_role'])) {
				?>
				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><?=$lang['header']['mailcow_settings'];?><span class="caret"></span></a>
					<ul class="dropdown-menu" role="menu">
					<?php
						if (isset($_SESSION['mailcow_cc_role'])) {
							if ($_SESSION['mailcow_cc_role'] == "admin") {
							?>
								<li <?=(preg_match("/admin/i", $_SERVER['REQUEST_URI'])) ? 'class="active"' : ''?>><a href="/admin.php"><?=$lang['header']['administration'];?></a></li>
							<?php
							}
							if ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin") {
							?>
								<li <?=(preg_match("/mailbox/i", $_SERVER['REQUEST_URI'])) ? 'class="active"' : ''?>><a href="/mailbox.php"><?=$lang['header']['mailboxes'];?></a></li>
							<?php
							}
							if ($_SESSION['mailcow_cc_role'] == "user") {
							?>
								<li <?=(preg_match("/user/i", $_SERVER['REQUEST_URI'])) ? 'class="active"' : ''?>><a href="/user.php"><?=$lang['header']['user_settings'];?></a></li>
							<?php
							}
						}
						?>
					</ul>
				</li>
					<?php
				}
				if (isset($_SESSION['mailcow_cc_username'])):
				?>
					<li><a style="border-left:1px solid #E7E7E7" href="#" onclick="logout.submit()"><?=sprintf($lang['header']['logged_in_as_logout'], $_SESSION['mailcow_cc_username']);?></a></li>
				<?php
				endif;
				?>
			</ul>
		</div><!--/.nav-collapse -->
	</div><!--/.container-fluid -->
</nav>
<form action="/" method="post" id="logout"><input type="hidden" name="logout"></form>
