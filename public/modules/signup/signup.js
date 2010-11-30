$(function () {
	$('#usr_user').keyup(function () { $('#user_text').html(''); });
	$('#usr_user').blur(function () {
		$.get(app_abs+'/user/v', {'user[usr_user]': $(this).val()}, function (data) {
			if (data) $('#user_text').html(data.usr_user);
		}, 'json');
	});

	$('#usr_email').keyup(function () { $('#email_text').html(''); });
	$('#usr_email').blur(function () {
		$.get(app_abs+'/user/v', {'user[usr_email]': $(this).val()}, function (data) {
			if (data) $('#email_text').html(data.usr_email);
		}, 'json');
	});
});
