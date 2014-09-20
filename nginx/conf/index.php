<!DOCTYPE html>
<html>
<head>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
<script>$(document).ready(function(){$("#usersettings").click(function(){$("#usersettingsbox").fadeIn();$("#adminsettingsbox").hide();$("#debuginfobox").hide()});$("#adminsettings").click(function(){$("#adminsettingsbox").fadeIn();$("#usersettingsbox").hide();$("#debuginfobox").hide()});$("#debuginfo").click(function(){$("#debuginfobox").fadeIn();$("#usersettingsbox").hide();$("#adminsettingsbox").hide()})})</script>
<meta charset=utf-8 />
<title>fufix - <?php echo $_SERVER['HTTP_HOST']; ?></title>
<style type="text/css">a:active,a:hover,a:link,a:visited{color:inherit;text-decoration:none}body{background-color:#dfdfdf;font-family:"Lucida Sans Unicode","Lucida Grande",Sans-Serif;font-size:12px;color:#555}.box{background-color:#fff;width:430px;border-radius:5px;-moz-border-radius:5px;margin:30px auto;-moz-box-shadow:0 1px 10px 0 rgba(0,0,0,.25);-webkit-box-shadow:0 1px 10px 0 rgba(0,0,0,.25);box-shadow:0 1px 6px 0 rgba(0,0,0,.2)}.box h2{font-size:14px;margin:0 0 10px 30px;padding-top:30px;color:#555}.line{margin:20px 0 20px 30px;width:365px;height:1px;background-color:#d7d7d7}.boxselect{padding:11px 5px 12px 24px;background-color:#f2f2f2;border:1px solid #c8c8c8;width:335px;color:#838383;margin:0 0 10px 30px;font-size:15px;text-align:center}.boxselect:hover{background-color:#fefefe}form input[type=text],input[type=password]{padding:11px 5px 12px 24px;background-color:#fefefe;border:1px solid #c8c8c8;width:335px;color:#838383;margin:0 0 10px 30px;font-size:15px}form input[type=submit]{font-size:15px;margin:20px 0 30px 30px}</style>
</head>

<body>

<div class="box">
<h2>fufix @ <?php echo $_SERVER['HTTP_HOST']; ?></h2>
<div class="line"></div>
<a href="#"><div id="usersettings" class="boxselect">Change user settings</div></a>
<a href="#"><div id="adminsettings" class="boxselect">System Settings</div></a>

<div id="usersettingsbox" style="display:none">
<h2>Change user settings</h2>
<form name="frmLogin" method="post" action="pfadmin/users/login.php">
<input type="text" name="fUsername" placeholder="your.name@domain.tld" autofocus/>
<input type="password" name="fPassword" placeholder="password" />
<input type="submit" name="submit" value="Login" />
</form>
</div>

<div id="adminsettingsbox" style="display:none">
<h2>System Settings</h2>
<form name="frmLogin" method="post" action="pfadmin/login.php">
<input type="text" name="fUsername" placeholder="postfixadmin@domain.tld" autofocus/>
<input type="password" name="fPassword" placeholder="password" />
<input type="submit" name="submit" value="Login" />
</form>
</div>

<br />
</body>
</html>
