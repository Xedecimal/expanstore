<?php

Module::Register('ModSignup');

class ModSignup extends Module
{
	function __construct()
	{
		$this->errors = array();

		$this->CheckActive('signup');
	}

	function Link()
	{
		global $_d;

		// Attach to User

		$_d['user.callbacks.knee']['signup'] = array(&$this, 'cb_user_knee');
	}

	function Prepare()
	{
		global $_d;

		if (!$this->Active) return;

		if (@$_d['q'][1] == 'signup')
		{
			$this->user = Server::GetVar('user');

			$this->errors = ModUser::Validate($this->user);
			if (empty($this->errors))
			{
				$newpass = "";
				for ($x = 0; $x < 15; $x++) $newpass .= rand(0, 1)
					? sprintf("%c", rand(65, 90))
					: sprintf("%c", rand(97, 122));
				$this->user['usr_date'] = SqlUnquote('NOW()');
				$this->user['usr_pass'] = md5($newpass);
				$this->user['usr_access'] = 1;
				$this->user['usr_id'] = $_d['user.ds']->Add($this->user);

				if ($_d['settings']['mail_from']) $heads =
					"From: {$_d['settings']['mail_from']}\r\n"
					."Reply-To: {$_d['settings']['mail_from']}\r\n";
				else $heads = '';

				mail($this->user['usr_email'],
					$_d['settings']['site_name']." Confirmation Email",
					"Welcome, your password is: $newpass\n" .
					"We suggest that you change it as soon as you can, to\n" .
					"avoid forgetting it. You can do so at http://" . $_SERVER["HTTP_HOST"] . "\n" .
					"login at top left and press the exposed 'Settings' button.\n\n".
					"Thank you, the ".$_d['settings']['site_name']." Staff\n", $heads);

				$this->success = true;
			}
		}
	}

	function Get()
	{
		global $_d;

		if (!$this->Active) return;

		if (@$_d['q'][1] == 'signup' && @$this->success)
		{
			$ret = "Registration was complete, your password has been mailed to you.<br />\n";
			$ret .= "<a href=\"{{app_abs}}\"> Return to Catalog </a>\n";
			$ret = GetBox('box_complete', 'Registration Complete!', $ret);
			ModLog::Log("New user: {$this->user['usr_user']} "
				."({$this->user['usr_id']})");
			return $ret;
		}
		else
		{
			$t = new Template($_d);
			if (!empty($this->user)) $t->Set($this->user);
			$t->Set('errors', $this->errors);
			$t->Behavior->Bleed = false;
			return $t->ParseFile(Module::L('signup/signup.xml'));
		}
	}

	function cb_user_knee()
	{
		return "Not a user? <a href=\"{{app_abs}}/signup\">Sign Up</a><br />\n";
	}
}

?>
