<?php

/**
* Login management.
*/
class ModUser extends Module
{
	/**
	 * Associated login manager.
	 *
	 * @var LoginManager
	 */
	public $lm;

	//public $Block = 'left';

	function __construct($inst)
	{
		global $_d;

		if (!$inst) return;

		$this->sql = 'user.sql';
		$ds = new DataSet($_d['db'], 'user', 'usr_id');
		$ds->ErrorHandler = array(&$this, 'DataError');
		$ds->Shortcut = 'u';
		$_d['user.ds'] = $ds;
	}

	static function RequestAccess($acc)
	{
		global $_d;
		if ($_d['cl']['usr_access'] >= $acc) return true;
		return false;
	}

	function InstallFields(&$frm)
	{
		$frm->AddInput('<div class="head">User</div>');
		$frm->AddInput(new FormInput('Admin Username', 'text', 'user_name'));
		$frm->AddInput(new FormInput('Admin Password', 'password', 'user_pass'));
	}

	function Install()
	{
		$user = GetVar('install_user_name');
		$pass = MD5(GetVar('install_user_pass'));

		$data = <<<EOF
CREATE TABLE IF NOT EXISTS `user` (
  `usr_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usr_date` datetime DEFAULT NULL,
  `usr_lastseen` datetime DEFAULT NULL,
  `usr_access` int(10) unsigned NOT NULL DEFAULT '0',
  `usr_user` varchar(100) NOT NULL,
  `usr_pass` char(32) NOT NULL,
  `usr_email` varchar(100) NOT NULL,
  `usr_name` varchar(100) NOT NULL,
  `usr_address` varchar(100) NOT NULL,
  `usr_city` varchar(100) NOT NULL,
  `usr_state` varchar(100) NOT NULL,
  `usr_zip` varchar(100) NOT NULL,
  `usr_phone` varchar(100) NOT NULL,
  PRIMARY KEY (`usr_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=FIXED;

INSERT INTO `user` (`usr_id`, `usr_date`, `usr_access`, `usr_user`, `usr_pass`)
VALUES (0, NOW(), 1000, '$user', '$pass');

INSERT INTO `company` (comp_name) VALUES ('Default Company');

INSERT INTO `comp_user` (c2u_company, c2u_user)
VALUES(@@LAST_INSERT_ID, 0);
EOF;

		global $_d;
		$_d['db']->Queries($data);
	}

	function PreLink()
	{
		global $_d;

		$this->lm = new LoginManager('lm');
		$this->lm->AddDataSet($_d['user.ds'], 'usr_pass', 'usr_user');
		$_d['cl'] = $this->lm->Prepare();
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if (!empty($_d['cl']))
		{
			$ds = $_d['user.ds'];

			$m = array('usr_id' => $_d['cl']['usr_id']);
			$_d['cl'] = $ds->GetOne(array('match' => $m, 'joins' => @$_d['user.ds.joins']));

			$_d['page.links']['Log Out'] = 'lm/logout';
		}
	}

	function Get()
	{
		global $_d;

		$out = null;

		if (isset($_d['cl']) && $_d['cl']['usr_access'] > 0)
		{
			$out .= "Welcome, {$_d['cl']['usr_user']}<br/>\n";
		}
		else
		{
			$out .= $this->lm->Get();
			$out .= RunCallbacks(@$_d['user.callbacks.knee']);
			$out .= "Password forgotten? Remind me<br />\n";
			return GetBox('box_user', 'Login', $out);
		}
	}
}

Module::RegisterModule('ModUser');

class ModUserAdmin extends Module
{
	function Link()
	{
		global $_d;

		if ($_d['cl']['usr_access'] > 500)
		{
			$_d['page.links']['Admin']['Users'] = '{{app_abs}}/user';

			$dsUser = $_d['user.ds'];
			$dsUser->Description = 'User';
			$dsUser->DisplayColumns = array(
				'usr_user' => new DisplayColumn('User')
			);

			$dsUser->FieldInputs['usr_user']    = new FormInput('User', 'text');
			$dsUser->FieldInputs['usr_pass']    = new FormInput('Password', 'password');
			$dsUser->FieldInputs['usr_email']   = new FormInput('EMail', 'text');
			$dsUser->FieldInputs['usr_name']    = new FormInput('Name', 'text');
			$dsUser->FieldInputs['usr_address'] = new FormInput('Address', 'text');
			$dsUser->FieldInputs['usr_city']    = new FormInput('City', 'text');
			$dsUser->FieldInputs['usr_state']   = new FormInput('State', 'text');
			$dsUser->FieldInputs['usr_zip']     = new FormInput('Zip', 'text');
			$dsUser->FieldInputs['usr_phone']   = new FormInput('Phone', 'text');
			$dsUser->FieldInputs['usr_access']  = new FormInput('Access', 'text');
		}
	}

	function Get()
	{
		global $_d;
		if ($_d['q'][0] != 'user') return;

		$dsUser = $_d['user.ds'];

		$dsUser->joins = array_merge($dsUser->joins, $_d['user.ds.joins']);
		$edUsers = new EditorData('user', $dsUser);
		$edUsers->Behavior->Target = 'user';
		if (!empty($_d['user.ds.handlers']))
			foreach ($_d['user.ds.handlers'] as $h)
				$edUsers->AddHandler($h);
		$edUsers->Prepare();
		return $edUsers->GetUI('cs');
	}
}

Module::RegisterModule('ModUserAdmin');

function AccessRequire($access)
{
	global $_d;
	if ($_d['cl']['usr_access'] >= $access) return true;
}

?>
