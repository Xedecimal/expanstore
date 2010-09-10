$(function () {
	$('#billing_personal').hide();
	$('#addy_save_yes,#addy_save_no').change(function () {
		$('#billing_personal').ct($('#addy_save_yes:checked').val());
		$('#billing_custom').ct($('#addy_save_no:checked').val());
	});
});

jQuery.fn.ct = function(show) {
	if (show) $(this).show();
	else $(this).hide();
};
