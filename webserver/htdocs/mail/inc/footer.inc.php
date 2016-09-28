<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-switch/3.3.2/js/bootstrap-switch.min.js" integrity="sha384-QIv8AGAxdI0cTHbmvoLkNuELOU7DQoz9LACnXQ61JsVJll396XlhlYsimV/19bJr" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/7.0.2/bootstrap-slider.min.js" integrity="sha384-zdPTsjljZsv2x02Dh9tJkwW1pVC3fUT0N1eWPzxmKQ5KiUPgE7I8L/Zvwq7624Ew" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.9.4/js/bootstrap-select.js" integrity="sha384-EW/AEsB10NrX7B55CM1thFvSw6+KfMAvsUYrqudLjOIXZUKQ0nJbQuiXAcZA/dfI" crossorigin="anonymous"></script>
<script>
// Select language and reopen active URL without POST
function setLang(sel) {
	$.post( "<?=$_SERVER['REQUEST_URI'];?>", {lang: sel} );
	window.location.href = window.location.pathname + window.location.search;
}

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
		<?=$_SESSION['return']['msg'];?>
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
