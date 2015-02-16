<?php
session_start();

$fufix_anonymize_headers = "/etc/postfix/fufix_anonymize_headers.pcre";
$fufix_reject_attachments = "/etc/postfix/fufix_reject_attachments.regex";
$fufix_sender_access = "/etc/postfix/fufix_sender_access";

function check_login($user, $pass, $pfconfig) {
	if(!filter_var($user, FILTER_VALIDATE_EMAIL)) {
		return false;
	}
	$pass = escapeshellcmd($pass);
	include_once($pfconfig);
	$link = mysql_connect('localhost', $CONF['database_user'], $CONF['database_password']);
	mysql_select_db($CONF['database_name']);
	$result = mysql_query("select password from admin where superadmin=1 and username='$user'");
	while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
		$row = "'".$row[0]."'";
		if (strpos(shell_exec("echo $pass | doveadm pw -s SHA512-CRYPT -t $row"), "verified") !== false) {
			return true;
		}
	}
	return false;
}
function postfix_reload() {
	shell_exec("sudo /usr/sbin/postfix reload");
}
function get_fufix_reject_attachments() {
	$read_mime_check = file($GLOBALS["fufix_reject_attachments"])[0];
	preg_match('#\((.*?)\)#', $read_mime_check, $match);
	echo $match[1];
}
function get_fufix_anonymize_toggle() {
	$state = file_get_contents($GLOBALS["fufix_anonymize_headers"]);
	if (!empty($state)) { echo "checked"; } else { return 1; }
}
function get_fufix_sender_access() {
	$state = file($GLOBALS["fufix_sender_access"]);
	foreach ($state as $each) {
		$each_expl = explode("     ", $each);
		echo $each_expl[0], "\n";
	}
}
function set_fufix_sender_access($what) {
	file_put_contents($GLOBALS["fufix_sender_access"], "");
	foreach(preg_split("/((\r?\n)|(\r\n?))/", $what) as $each) {
		if ($each != "" && preg_match("/^[a-zA-Z0-9-\ .@]+$/", $each)) {
			file_put_contents($GLOBALS["fufix_sender_access"], "$each     REJECT     Sender not allowed".PHP_EOL, FILE_APPEND);
		}
	}
	$sender_map = $GLOBALS["fufix_sender_access"];
	shell_exec("/usr/sbin/postmap $sender_map");
}
function set_fufix_reject_attachments($ext) {
	foreach (explode("|", $ext) as $each_ext) { if (!ctype_alnum($each_ext) || strlen($each_ext) >= 10 ) { return false; } }
	file_put_contents($GLOBALS["fufix_reject_attachments"], "/name=[^>]*\.($ext)/     REJECT     Dangerous files are prohibited on this server.".PHP_EOL);
}
function set_fufix_anonymize_headers($toggle) {
	$template = '/^\s*(Received: from)[^\n]*(.*)/ REPLACE $1 [127.0.0.1] (localhost [127.0.0.1])$2
/^\s*User-Agent/        IGNORE
/^\s*X-Enigmail/        IGNORE
/^\s*X-Mailer/          IGNORE
/^\s*X-Originating-IP/  IGNORE
';
	if ($toggle == "on") {
		file_put_contents($GLOBALS["fufix_anonymize_headers"], $template);
	} else {
		file_put_contents($GLOBALS["fufix_anonymize_headers"], "");
	}
}
if (isset($_POST["sender"])) {
	set_fufix_sender_access($_POST["sender"]);
	postfix_reload();
}
if (isset($_POST["ext"])) {
	set_fufix_reject_attachments($_POST["ext"]);
	postfix_reload();
}
if (isset($_POST["anonymize_"])) {
	if (!isset($_POST["anonymize"])) { $_POST["anonymize"] = ""; }
	set_fufix_anonymize_headers($_POST["anonymize"]);
	postfix_reload();
}
if (isset($_POST["login_user"]) && isset($_POST["pass_user"])) {
	if (check_login($_POST["login_user"], $_POST["pass_user"], "/var/www/mail/pfadmin/config.local.php") == true) { $_SESSION['fufix_cc_loggedin'] = "yes"; }
}
if (isset($_POST["logout"])) {
        $_SESSION['fufix_cc_loggedin'] = "no";
}
if (isset($_POST["backupdl"])) {
	shell_exec("sudo /bin/tar -cvjf /tmp/backup_vmail.tar.bz2 /var/vmail/");
	$filename = "backup_vmail.tar.bz2";
	$filepath = "/tmp/";
	header("Content-Description: File Transfer");
	header("Content-type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"".$filename."\"");
	header("Content-Transfer-Encoding: binary");
	header("Content-Length: ".filesize($filepath.$filename));
	ob_end_flush();
	@readfile($filepath.$filename);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset=utf-8 />
<title>fufix control center</title>
<style type="text/css">
a:active,a:hover,a:link,a:visited{color:inherit;text-decoration:none;outline:0;}
body{overflow-y:scroll;background-color:#dfdfdf;font-family:"Lucida Sans Unicode","Lucida Grande",Sans-Serif;font-size:12px;color:#555}
.box{background-color:#fff;width:530px;border-radius:5px;-moz-border-radius:5px;margin:30px auto;-moz-box-shadow:0 1px 10px 0 rgba(0,0,0,.25);-webkit-box-shadow:0 1px 10px 0 rgba(0,0,0,.25);box-shadow:0 1px 6px 0 rgba(0,0,0,.2);padding:30px 20px}
.box h1{font-size:18px;color:#555}
.box h2{font-size:14px;color:#777;margin:2px;}
.line{width:100%;height:1px;background-color:#d7d7d7}
.right{float:right;margin:20px 0 10px 0;width:300px}
.left{float:left;margin:20px 0 10px 0;width:180px}
.clearfix:after{content:"";display:table;clear:both;}
textarea,input[type="text"],input[type="password"]{width:95%;}
input[type="submit"]{padding:5px;border:0px;font-family:monospace;margin:5px 10px 20px 10px;}
</style>
</head>
<body>

<div class="box">
<pre>
   m""           m""    "
 mm#mm  m   m  mm#mm  mmm    m   m
   #    #   #    #      #     #m#
   #    #   #    #      #     m#m
   #    "mm"#    #    mm#mm  m" "m
</pre>
<h1>control center</h1>
<?php if ($_SESSION['fufix_cc_loggedin'] == "yes"): ?>

<h2>Sender Blacklist</h2>
<form method="post">
<div class="line"></div>
<div class="left">Specify a list of senders or domains to blacklist access:</div>
<div class="right"><textarea rows="6" name="sender"><?php echo get_fufix_sender_access() ?></textarea></div>
<div class="clearfix"></div>
<input type="submit" value="Save">
</form>

<h2>Attachments</h2>
<form method="post">
<div class="line"></div>
<div class="left">Deny attachments by their extension.<br />
Provide a "|" seperated list of extensions: ext1|ext2|ext3 <br />
Warning: Mails will be bounced!</div>
<div class="right"><input type="text" name="ext" value="<?php echo get_fufix_reject_attachments("ext") ?>"></div>
<div class="clearfix"></div>
<p>To <b>disable</b> this feature, enter "DISABLED" as extension name.</p>
<input type="submit" value="Save">
</form>

<h2>Privacy</h2>
<form method="post">
<div class="line"></div>
<div class="left">Anonymize outgoing mail:</div>
<div class="right"><input name="anonymize" type="checkbox" <?php get_fufix_anonymize_toggle() ?>></div>
<input type="hidden" name="anonymize_">
<div class="clearfix"></div>
<p>This option enables a PCRE table to remove "User-Agent", "X-Enigmail", "X-Mailer", "X-Originating-IP" and replaces "Received: from" headers with localhost/127.0.0.1.</p>
<input type="submit" value="Save">
</form>

<h2>Backup mail</h2>
<form method="post">
<div class="line"></div>
<div class="left">Download a copy of your vmail directory as tar.bz2 archive.
<br />This is a very simple function that may or may not work. Consider it unstable.</div>
<div class="right"><input name="backupdl" type="submit" value="Download"></div>
<div class="clearfix"></div>
</form>
<div class="line"></div>

<form method="post">
<br />
<input name="logout" type="submit" value="Logout">
<div class="clearfix"></div>
</form>

<?php else: ?>
<h2>Login</h2>
<form method="post">
<div class="line"></div>
<div class="left">Postfixadmin User</div>
<div class="right"><input name="login_user" type="text"></div>
<div class="left">Password</div>
<div class="right"><input name="pass_user" type="password"></div>
<div class="clearfix"></div>
<p>You can login with any superadmin created in <b><a href="../pfadmin">Postfixadmin</a></b>.</p>
<input type="submit" value="Login">
</form>
<?php endif ?>
<p><b><a href="../">&#8592; go back</a></b></p>
</div>
</body>
</html>
