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

	function __construct($inst)
	{
		global $_d;

		if (!$inst) return;

		$this->sql = 'user.sql';
		$ds = new DataSet($_d['db'], 'user', 'usr_id');
		$ds->ErrorHandler = array(&$this, 'DataError');
		$ds->Description = 'User';
		$_d['user.ds'] = $ds;
		$_d['user.auth.id'] = 'usr_id';
		$_d['user.auth.pass'] = 'usr_pass';
		$_d['user.auth.user'] = 'usr_user';
		$_d['user.auth.access'] = 'usr_access';

		$this->CheckAuth();
	}

	function InstallFields(&$frm)
	{
		$frm->AddInput('<div class="head">User</div>');
		$frm->AddInput(new FormInput('Admin Username', 'text', 'user_name'));
		$frm->AddInput(new FormInput('Admin Password', 'password', 'user_pass'));
	}

	function PreLink()
	{
		global $_d;

		if (empty($_d['cl']))
		{
			foreach ($_d['user.auth'] as $a)
			{
				if ($this->CheckAuth($a['ds'], $a['user'], $a['pass'],
					$a['access']))
					break;
			}
		}
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if (!empty($_d['cl']))
			$_d['page.links']['Log Out'] = $_d['app_abs'].'/lm/logout';

		if (@$_d['q'][0] != 'user') return;

		// Validate
		if (@$_d['q'][1] == 'v')
			die(json_encode(ModUser::Validate(GetVar('user'))));
	}

	function Get()
	{
		global $_d;

		$out = null;

		if (ModUser::RequestAccess(1))
		{
			$out .= "Welcome, {$_d['cl'][$_d['user.auth.user']]}<br/>\n";
		}
		else
		{
			$out .= $this->lm->Get();
			$out .= RunCallbacks(@$_d['user.callbacks.knee']);
			return GetBox('box_user', 'Login', $out);
		}
	}

	function CheckAuth()
	{
		global $_d;

		$this->lm = new LoginManager('lm');
		$this->lm->AddDataset($_d['user.ds'], $_d['user.auth.pass'], $_d['user.auth.user']);
		$_d['cl'] = $this->lm->Prepare(null, @$_d['user.ds.query']);
	}

	static function RequestAccess($acc)
	{
		global $_d;
		if (@$_d['cl'][$_d['user.auth.access']] >= $acc) return true;
		return false;
	}

	static function Validate($data)
	{
		global $_d;

		if (isset($data['usr_user']))
		{
			// Simple validations
			if (strlen($data['usr_user']) < 3) return array('usr_user' => 'Username is too short.');
			else // On to data validation
			{
				$q['match'] = array('usr_user' => $data['usr_user']);

				$item = $_d['user.ds']->GetOne($q);
				if (!empty($item)) return
					array('usr_user' => 'Username is already taken.');
			}
		}
		if (isset($data['usr_email']))
		{
			if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/', $data['usr_email']))
				return array('usr_email' => 'Invalid address.');
		}
	}
}

Module::Register('ModUser');

class ModUserAdmin extends Module
{
	function Link()
	{
		global $_d;

		if (ModUser::RequestAccess(500))
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

Module::Register('ModUserAdmin');

function AccessRequire($access)
{
	global $_d;
	if ($_d['cl']['usr_access'] >= $access) return true;
}

?>
