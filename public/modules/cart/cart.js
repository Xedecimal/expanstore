$(function() {
	$('.ancAddCart').click(function () {
		id = $(this).attr('rel');

		var atrs = {};

		$('#product_'+id+' .product_value').each(function () {
			atrs[$(this).attr('name')] = $(this).val();
		});

		$.post(app_abs+'/cart/add/'+id, atrs, function() {
			$('#divCart').load(app_abs+'/cart/part');
		});
	});
});