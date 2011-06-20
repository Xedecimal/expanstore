<?php

require_once(dirname(__FILE__).'../../xedlib/modules/user/user.php');

class User extends ModUser
{
	function __construct()
	{
		global $_d;

		parent::__construct();
		$this->AddUserDataset($_d['user.ds'], 'usr_pass', 'usr_user');
		$_d['user.login'] = true;
	}
}

Module::Register('User');

?>
