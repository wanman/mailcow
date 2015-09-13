<?php
require_once("inc/header.inc.php");
$_SESSION['return_to'] = basename($_SERVER['PHP_SELF']);
?>
<div class="jumbotron">
	<div class="container">
		<h2>Welcome @ <?php echo $MYHOSTNAME; ?></h2>
		<p style="font-weight:300;font-size:24px;margin-right:151px;line-height:30px;margin-top:-2px"><i>Get cownnected...</i></h4>
		<div class="row">
			<div class="col-md-6">
				<small><b>IMAP (STARTTLS) or IMAPS</b></small>
				<ul class="ul-horizontal">
					<li><code><?php echo $MYHOSTNAME; ?>:143/tcp</code></li>
					<li><code><?php echo $MYHOSTNAME; ?>:993/tcp</code></li>
				</ul>
				<small><b>SMTP (STARTTLS)</b></small>
				<ul>
					<li><code><?php echo $MYHOSTNAME; ?>:587/tcp</code></li>
				</ul>
				<small><b>Cal- and CardDAV</b></small><br />
				<small><a href="user.php" style="text-decoration:underline">Navigate to your personal settings</a> and copy the full path of your desired calendar or address book.</small>
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
<?php
require_once("inc/footer.inc.php");
?>
