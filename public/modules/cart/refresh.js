$(function() {
	$('.ancCartRemove').click(function () {
		if (!confirm('Are you sure?')) return false;
		id = $(this).attr('href');
		$.post(app_abs+'/cart/remove/'+id, function (res) {
			// Remove this cart item.
			if (res.res) $('#divCartProd_'+id).hide(500, function () {
				$(this).remove();

				// Cart is empty, get it out of the way.
				if ($('.cart-item').length < 1) $('#box_cart').hide(500);
			});
		},'json');
		return false;
	})
});
