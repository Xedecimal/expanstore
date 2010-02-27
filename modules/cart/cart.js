$(document).ready(function() {
	$('.ancAddCart').click(function () {
		id = $(this).attr('id').match(/ancAddCart.(\d+)/)[1];
		$.post('index.php', {cs:'cart',ca:'add',ci:id},function() {
			$('#divCart').load('index.php', {cs:'cart',ca:'part'})
		},'json');
		return false;
	});
});