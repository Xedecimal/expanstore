$(function() {
	$('.ancAddCart').click(function () {
		id = $(this).attr('href');
		$.post(app_abs+'/cart/add', {ci:id}, function() {
			$('#divCart').load(app_abs+'/cart/part')
		});
		return false;
	});
});