$(document).ready(function () {
	$('.aCatEdit,.aCatDelete').hide();

	$('.aCatDelete').click(function () {
		id = $(this).attr('id').match(/aCatDelete_(\d+)/)[1];
		if (!confirm('Are you sure?')) return false;
		$.post(app_abs+'/category/delete/'+id, function (res) {
			if (!res.res) alert('Failure: '+res.msg);
			else $('#liCategory\\.'+id).hide(500);
		}, 'json');
		return false;
	});
});