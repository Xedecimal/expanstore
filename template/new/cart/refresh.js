$(document).ready(function() {
	$('.ancCartRemove').click(function () {
		if (!confirm('Are you sure?')) return false;
		id = $(this).attr('id').match(/ancCartRemove\.(\d+)/)[1];
		$.post('index.php', {cs:'cart',ca:'cart_remove',ci:id}, function (res) {

			// Remove this cart item.
			if (res.res) $('#divCartProd\\.'+id).hide(500, function () {
				$(this).remove();

				// Cart is empty, get it out of the way.
				if ($('.cartItem').length < 1) $('#box_cart').hide(500);
			});
		},'json');
		return false;
	})
});
