$(document).ready(function () {
	$('.ancDeleteCategory').click(function () {
		id = $(this).attr('id').match(/ancLink\.(\d+)/)[1];
		if (!confirm('Are you sure?')) return false;
		$.post('index.php', {cs:'category',ca:'delete',ci:id}, function (res) {
			if (!res.res) alert('Failure: '+res.msg);
			else $('#liCategory\\.'+id).hide(500);
		}, 'json');
		return false;
	});
});