$(function () {
	$('#billing_custom').hide();
	$('#addy_save_yes,#addy_save_no').change(function () {
		$('#billing_personal').ct($('#addy_save_yes').attr('checked'));
		$('#billing_custom').ct($('#addy_save_no').attr('checked'));
	});
});

jQuery.fn.ct = function(show) {
	if (show) $(this).show();
	else $(this).hide();
};
