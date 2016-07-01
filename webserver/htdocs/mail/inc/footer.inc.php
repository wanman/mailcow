<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/1.12.0/jquery.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/js/bootstrap.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-switch/3.3.2/js/bootstrap-switch.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/7.0.2/bootstrap-slider.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.9.4/js/bootstrap-select.js"></script>
<?php
// Prevent bootstrap-switch from failing
if (preg_match("/admin.php/i", $_SERVER['REQUEST_URI'])):
?>
<script src="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
<?php
endif;
?>
<script>
function setLang(sel) {
	$.post( "<?=$_SERVER['REQUEST_URI'];?>", {lang: sel} );
	location.reload();
}
$(document).ready(function() {
	$("#alert-fade").fadeTo(7000, 500).slideUp(500, function(){
		$("#alert-fade").alert('close');
	});
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
	<?php
	if (preg_match("/admin.php/i", $_SERVER['REQUEST_URI'])):
	?>
	$( "[id*=srr-sortable]" ).sortable({
		items: "li:not(.list-heading)",
		cancel: ".ui-state-disabled",
		connectWith: "[id*=srr-sortable]",
		dropOnEmpty: true,
		placeholder: "ui-state-highlight"
	});
	$( "[id*=ssr-sortable]" ).sortable({
		items: "li:not(.list-heading)",
		cancel: ".ui-state-disabled",
		connectWith: "[id*=ssr-sortable]",
		dropOnEmpty: true,
		placeholder: "ui-state-highlight"
	});
	$('#srr_form').submit(function(){
		var srr_joined_vals = $("[id^=srr-sortable-active] li").map(function() {
			return $(this).data("value");
		}).get().join(', ');
		var input = $("<input>").attr("type", "hidden").attr("name", "srr_value").val(srr_joined_vals);
		$('#srr_form').append($(input));
	});
	$('#ssr_form').submit(function(){
		var ssr_joined_vals = $("[id^=ssr-sortable-active] li").map(function() {
			return $(this).data("value");
		}).get().join(', ');
		var input = $("<input>").attr("type", "hidden").attr("name", "ssr_value").val(ssr_joined_vals);
		$('#ssr_form').append($(input));
	});
	<?php
	elseif (preg_match("/mailbox.php/i", $_SERVER['REQUEST_URI'])):
	?>
	$('tbody').filter(function (index) { 
		return $(this).children().length < 1; 
	}).remove();
	$('[data-toggle="tooltip"]').tooltip();
	var rowCountDomainAlias = $('#domainaliastable >tbody >tr').length;
	var rowCountDomain = $('#domaintable >tbody >tr').length;
	var rowCountMailbox = $('#mailboxtable >tbody >tr').length;
	var rowCountAlias = $('#aliastable >tbody >tr').length;
	$("#numRowsDomainAlias").text(rowCountDomainAlias);
	$("#numRowsDomain").text(rowCountDomain);
	$("#numRowsMailbox").text(rowCountMailbox);
	$("#numRowsAlias").text(rowCountAlias);
	$.fn.extend({
		filterTable: function(){
			return this.each(function(){
				$(this).on('keyup', function(e){
					$('.filterTable_no_results').remove();
					var $this = $(this),
                        search = $this.val().toLowerCase(),
                        target = $this.attr('data-filters'),
                        $target = $(target),
                        $rows = $target.find('tbody tr');
					if(search == '') {
						$rows.show();
					} else {
						$rows.each(function(){
							var $this = $(this);
							$this.text().toLowerCase().indexOf(search) === -1 ? $this.hide() : $this.show();
						})
						if($target.find('tbody tr:visible').size() === 0) {
							var col_count = $target.find('tr').first().find('td').size();
							var no_results = $('<tr class="filterTable_no_results"><td colspan="100%">-</td></tr>')
							$target.find('tbody').append(no_results);
						}
					}
				});
			});
		}
	});
	$('[data-action="filter"]').filterTable();
	$('.container').on('click', '.panel-heading span.filter', function(e){
		var $this = $(this),
		$panel = $this.parents('.panel');
		$panel.find('.panel-body').slideToggle("fast");
		if($this.css('display') != 'none') {
			$panel.find('.panel-body input').focus();
		}
	});
	<?php
	endif;
	if (isset($_SESSION['mailcow_cc_role'])):
	?>
	$('select').selectpicker();
	$.fn.bootstrapSwitch.defaults.onColor = 'success';
	$("[name='tls_out']").bootstrapSwitch();
	$("[name='tls_in']").bootstrapSwitch();

	$("#score").slider({ id: "slider1", min: 1, max: 30, step: 0.5, range: true, value: [<?=get_spam_score($link, $_SESSION['mailcow_cc_username']);?>] });

	if ( !$("#togglePwNew").is(':checked') ) {
		$(".passFields").hide();
	}

	$('#togglePwNew').click(function() {
		$("#user_new_pass").attr("disabled", !this.checked);
		$("#user_new_pass2").attr("disabled", !this.checked);
		var $this = $(this);
		if ($this.is(':checked')) {
			$(".passFields").slideDown();
		} else {
			$(".passFields").slideUp();
		}
	});

	$('#trigger_set_time_limited_aliases').hide(); 
	$('#validity').change(function(){
		$('#trigger_set_time_limited_aliases').show(); 
	});
	<?php
	if (isset($_SESSION['last_expanded'])):
	?>
	$('#<?=$_SESSION['last_expanded'];?>').collapse('toggle');
	<?php
	unset($_SESSION['last_expanded']);
	endif;
	endif;
	// basename to validate against "/" without filename
	if (preg_match('/index/i', basename($_SERVER['PHP_SELF']))):
	?>
	$('nav').hide();
	<?php
	endif;
	?>
});
</script>
</body>
</html>
<?php mysqli_close($link); ?>
