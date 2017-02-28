<script src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/js/bootstrap.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-switch/3.3.2/js/bootstrap-switch.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/7.0.2/bootstrap-slider.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.9.4/js/bootstrap-select.js"></script>
<script>
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();
});

$('.nav-tabs > li > #domainadmin').click(function(event){
	if(document.getElementById('admin_un_cache').value != document.getElementById('login_user').value) {
		$('#user_un_cache').val(document.getElementById('login_user').value);
		$('#login_user').val(document.getElementById('admin_un_cache').value);

		$('#user_pw_cache').val(document.getElementById('pass_user').value);
		$('#pass_user').val(document.getElementById('admin_pw_cache').value);
	}
	$('#login_role').val(event.target.id);
});

$('.nav-tabs > li > #mailboxuser').click(function(event){
	if(document.getElementById('user_un_cache').value != document.getElementById('login_user').value) {
		$('#admin_un_cache').val(document.getElementById('login_user').value);
		$('#login_user').val(document.getElementById('user_un_cache').value);

		$('#admin_pw_cache').val(document.getElementById('pass_user').value);
		$('#pass_user').val(document.getElementById('user_pw_cache').value);
	}
	$('#login_role').val(event.target.id);
});

$(document).ready(function() {
	// Hide alerts after n seconds
	$("#alert-fade").fadeTo(7000, 500).slideUp(500, function(){
		$("#alert-fade").alert('close');
	});

	// Disable submit after submitting form
	$('form').submit(function() {
		if ($('form button[type="submit"]').data('submitted') == '1') {
			return false;
		} else {
			$(this).find('button[type="submit"]').first().text('<?=$lang['footer']['loading'];?>');
			$('form button[type="submit"]').attr('data-submitted', '1');
			function disableF5(e) { if ((e.which || e.keyCode) == 116 || (e.which || e.keyCode) == 82) e.preventDefault(); };
			$(document).on("keydown", disableF5);
		}
	});

	// IE fix to hide scrollbars when table body is empty
	$('tbody').filter(function (index) {
		return $(this).children().length < 1;
	}).remove();

	// Init Bootstrap Selectpicker
	$('select').selectpicker();

	// Collapse last active panel
	<?php
	if (isset($_SESSION['last_expanded'])):
	?>
		$('#<?=$_SESSION['last_expanded'];?>').collapse('toggle');
		<?php
		unset($_SESSION['last_expanded']);
	endif;
	?>
});
</script>
<?php
if (isset($_SESSION['return'])):
?>
<div class="container">
	<div style="position:fixed;bottom:8px;right:25px;min-width:300px;max-width:350px;z-index:2000">
		<div <?=($_SESSION['return']['type'] == 'danger') ? null : 'id="alert-fade"'?> class="alert alert-<?=$_SESSION['return']['type'];?>" role="alert">
		<a href="#" class="close" data-dismiss="alert"> &times;</a>
		<?=htmlspecialchars($_SESSION['return']['msg']);?>
		</div>
	</div>
</div>
<?php
unset($_SESSION['return']);
endif;
?>
</body>
</html>
<?php $stmt = null; $pdo = null; ?>
