$(function ()
{
	$('.delete').live('click', function () {
		return confirm('Are you sure you wish to delete this item?');
	});
});
