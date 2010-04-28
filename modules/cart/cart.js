$(function() {
	$('.ancAddCart').click(function () {
		id = $(this).attr('href');

		var atrs = {};

		$('.atrs-'+id).each(function () {
			atrs[$(this).attr('name')] = $(this).val();
		});

		$.post(app_abs+'/cart/add/'+id, atrs, function() {
			$('#divCart').load(app_abs+'/cart/part')
		});
		return false;
	});
});