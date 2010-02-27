$(document).ready(function () {
	$('.aCatEdit,.aCatDelete').hide();

	$('.divCat').bind('mouseenter', function () {
		id = $(this).attr('id').match(/divCat_(\d+)/)[1];
		$('#aCatEdit_'+id+',#aCatDelete_'+id).show();
	});

	$('.divCat').bind('mouseleave', function ()
	{
		id = $(this).attr('id').match(/divCat_(\d+)/)[1];
		$('#aCatEdit_'+id+',#aCatDelete_'+id).hide();
	});

	$('.liCat').css('padding-left', '10px');
	$('.aCatDelete').click(function () {
		id = $(this).attr('id').match(/aCatDelete_(\d+)/)[1];
		if (!confirm('Are you sure?')) return false;
		$.post('index.php', {cs:'category',ca:'delete',ci:id}, function (res) {
			if (!res.res) alert('Failure: '+res.msg);
			else $('#liCategory\\.'+id).hide(500);
		}, 'json');
		return false;
	});
});