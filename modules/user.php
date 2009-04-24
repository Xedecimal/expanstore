<?php

RegisterModule('ModUser');
RegisterModule('ModUserAdmin');

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

	public $Block = 'left';

	function __construct()
	{
		global $_d;

		$ds = new DataSet($_d['db'], 'ype_user', 'usr_id');
		$ds->ErrorHandler = array($this, 'DataError');
		$ds->Shortcut = 'u';

		$_d['user.ds'] = $ds;

		$this->lm = new LoginManager('lm');
		$this->lm->AddDataSet($ds, 'usr_pass', 'usr_user');
		$_d['cl'] = $this->lm->Prepare();
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if (!empty($_d['cl']))
		{
			/** @var DataSet */
			$ds = $_d['user.ds'];

			$_d['cl'] = $ds->GetOne(array('usr_id' => $_d['cl']['usr_id']),
				$_d['user.ds.joins']);

			$_d['page.links']['Log Out'] = "{{me}}?lm_action=logout";
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
			$out .= "Not a user? <a href=\"{{me}}?cs=signup\">Sign Up</a><br />\n";
			$out .= "Password forgotten? Remind me<br />\n";
			return GetBox('box_user', 'Login', $out);
		}
	}

	function DataError($errno)
	{
		global $_d;

		//No such table.
		if ($errno == ER_NO_SUCH_TABLE)
		{
			$_d['db']->Query("CREATE TABLE `user` (
  `id` int(11) NOT NULL auto_increment,
  `date` int(11) NOT NULL default '0',
  `lastlog` datetime NOT NULL default '0000-00-00 00:00:00',
  `company` bigint(20) unsigned NOT NULL default '0',
  `access` int(11) unsigned NOT NULL default '0',
  `user` varchar(100) NOT NULL default '',
  `pass` varchar(100) NOT NULL default '',
  `email` varchar(100) NOT NULL default '',
  `name` varchar(100) NOT NULL default '',
  `address` varchar(100) NOT NULL default '',
  `city` varchar(100) NOT NULL default '',
  `state` varchar(100) NOT NULL default '',
  `zip` varchar(100) NOT NULL default '',
  `phone` varchar(100) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `idxCompany` (`company`)
) ENGINE=MyISAM");
			return false;
		}
	}
}

class ModUserAdmin extends Module
{
	function Link()
	{
		global $_d;

		if ($_d['cl']['usr_access'] > 500)
		{
			$_d['page.links']['Admin']['Users'] =
				'{{me}}?cs=user&amp;class=userdisplay&amp;ca=admin';

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
		if ($_d['cs'] != 'user') return;

		$_d['page_title'] .= 'User Administration';

		$dsUser = $_d['user.ds'];



		$dsUser->joins = array_merge($dsUser->joins, $_d['user.ds.joins']);
		$edUsers = new EditorData('user', $dsUser);
		foreach ($_d['user.ds.handlers'] as $h) $edUsers->AddHandler($h);
		$edUsers->Prepare();
		return $edUsers->GetUI('cs');
	}
}

function AccessRequire($access)
{
	global $_d;
	if ($_d['cl']['usr_access'] >= $access) return true;
}

?>
