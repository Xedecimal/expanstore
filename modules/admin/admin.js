$(function () {
	$('.aProductDelete').click(function () {
		if (!confirm('Are you sure?')) return false;

		id = $(this).attr('href').match(/(\d+)/)[1];
		$.post(app_abs+'/product/delete/'+id, function (res) {
			if (!res.res) alert('Failure: '+res.msg);
			else $('#divProd_'+id).hide(500)
		},'json')
		return false;
	});
})