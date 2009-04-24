<?php

RegisterModule('ModSignup');

class ModSignup extends Module
{
	function Get()
	{
		global $_d;

		if ($_d['cs'] != 'signup') return;

		$GLOBALS['page_name'] = "Sign Up";

		$ca = GetVar('ca');

		if ($ca == "confirm")
		{
			$userinfo = GetVar("userinfo");
			$error = array();
			$errors = 0;
			if (strlen($userinfo[0]) < 3) //Username
			{
				$error[0] = '<font color="#A00000"><b>Failure</b></font>: Must be at least 3 characters long.';
				$errors = 1;
			}
			else
			{
				$user = $dsUsers->GetOne("user", $userinfo[0]);
				if (!isset($user)) $error[0] = '<font color="#00A000"><b>OK</b></font>';
				else
				{
					$errors = 1;
					$error[0] = '<font color="#A00000"><b>Failure</b></font>: Username is taken, please try another.';
				}
			}
			if (strlen($userinfo[1]) < 5) //Email
			{
				$error[1] = '<font color="#A00000"><b>Failure</b></font>: Must be at least 5 characters long.';
				$errors++;
			}
			else
			{
				$user = $dsUsers->GetOne("email", $userinfo[1]);
				if (isset($user))
				{
					$errors = 1;
					$error[1] = '<font color="#A00000"><b>Failure</b></font>: Email already exists in database.';
				}
				else
				{
					if (!strpos($userinfo[1], '@'))
					{
						$errors = 1;
						$error[1] = '<font color="#A00000"><b>Failure</b></font>: No @ in address.';
					}
					else
					{
						$error[1] = '<font color="#00A000">OK</font>';
					}
				}
			}

			if ($errors == 0)
			{
				$newpass = "";
				for ($x = 0; $x < 15; $x++) $newpass .= rand(0, 1) ? sprintf("%c", rand(65, 90)) : sprintf("%c", rand(97, 122));
				$newmd5pass = md5($newpass);
				$dsUsers->Add(array(null, time(), null, null, 1, $userinfo[0], $newmd5pass, $userinfo[1], $userinfo[2], $userinfo[3], $userinfo[3], $userinfo[3], $userinfo[3], $userinfo[3]));
				mail($userinfo[1],
					SITE_NAME . " Confirmation Email",
					"Welcome, your password is: $newpass\n" .
					"We suggest that you change it as soon as you can, to\n" .
					"avoid forgetting it. You can do so at http://" . $_SERVER["HTTP_HOST"] . "\n" .
					"login at top left and press the exposed 'Settings' button.\n\n".
					"Thank you, the " . SITE_NAME . " Staff\n");

				$ret = "Registration was complete, your password has been mailed to you.<br>\n";
				$ret .= "<center><a href=\"index.php\"> Return to Catalog </a></center>\n";
				$ret = GetBox("Registration Complete!", $ret);
				xslog($_d, "New user! {$userinfo[0]}");
				$page_body = $ret;
			}
		}
		if (!isset($userinfo)) $userinfo = array(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

		$GLOBALS['page_section'] = 'Create Account';
		$formSignup = new Form('formSignup');
		$formSignup->AddHidden('cs', 'signup');
		$formSignup->AddHidden('ca', 'confirm');
		$formSignup->AddInput(new FormInput('Username',  'text', 'userinfo[]', $userinfo[0], 'size="50"', (isset($error[0])) ? $error[0] : null));
		$formSignup->AddInput(new FormInput('Email',     'text', 'userinfo[]', $userinfo[1], 'size="50"', (isset($error[1])) ? $error[1] : null));
		$formSignup->AddInput(new FormInput('Real Name', 'text', 'userinfo[]', $userinfo[2], 'size="50"', (isset($error[2])) ? $error[2] : null));
		$formSignup->AddInput(new FormInput('Address',   'text', 'userinfo[]', $userinfo[3], 'size="50"', (isset($error[3])) ? $error[3] : null));
		$formSignup->AddInput(new FormInput('City',      'text', 'userinfo[]', $userinfo[4], 'size="50"', (isset($error[4])) ? $error[4] : null));
		$formSignup->AddInput(new FormInput('State',     'text', 'userinfo[]', $userinfo[5], 'size="50"', (isset($error[5])) ? $error[5] : null));
		$formSignup->AddInput(new FormInput('Zip',       'text', 'userinfo[]', $userinfo[6], 'size="50"', (isset($error[6])) ? $error[6] : null));
		$formSignup->AddInput(new FormInput('Phone',     'text', 'userinfo[]', $userinfo[7], 'size="50"', (isset($error[7])) ? $error[7] : null));
		$formSignup->AddInput('Your password will E-Mailed upon completion.');
		$formSignup->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Sign Up'));
		return GetBox("box_signup", "Sign up", $formSignup->Get('action="{{me}}" method="post"'));
	}
}

?>
