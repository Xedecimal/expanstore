<?php

RegisterModule('ModTemplate');

global $_d;

$_d['tempath'] =
	'template/'.$_d['settings']['site_template'].'/';

function TemplateCheck()
{
	global $_d;
	if (!isset($_d['settings']['site_template']))
		$_d['settings']['site_template'] = 'new';
}

class ModTemplate extends Module
{
	function Link()
	{
		global $_d;

		// Attach to Navigation

		$_d['page.links']['Admin']['Template'] = '{{me}}?cs=template';

		#$_d['admin.callbacks.settings'][] = array(&$this, 'AdminSettings');
		#$_d['admin.callbacks.setup'][] = array(&$this, 'AdminSetup');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if ($_d['cs'] != 'template') return;

		$ca = $_d['ca'];

		if ($ca == 'blocks')
		{
			$_d['settings']['blocks'] = GetVar('blocks');
			file_put_contents('settings.txt', serialize($_d['settings']));
		}
	}

	function Get()
	{
		global $_d, $mods;

		if ($_d['cs'] != 'template') return;

		$bnames = ArrayToSelOptions(array_keys($_d['blocks']), null, false);

		$ret = '<form action="{{me}}" method="post">';
		$ret .= '<input type="hidden" name="cs" value="template" />';
		$ret .= '<input type="hidden" name="ca" value="blocks" />';
		$ret .= '<table>';
		$ret .= '<tr><th>Module</th><th>Location</th></tr>';
		foreach ($mods as $mod)
		{
			$name = get_class($mod);

			if (isset($_d['settings']['blocks'][$name]))
				$sel = $_d['settings']['blocks'][$name];

			$sel = MakeSelect(array('name' => "blocks[{$name}]"), $bnames, $sel);
			$ret .= "<tr><td>{$name}</td>";
			$ret .= "<td>{$sel}</td>";
			$ret .= '</tr>';
		}
		$ret .= '</table>';
		$ret .= '<input type="submit" value="Update" />';
		$ret .= '</form>';
		return $ret;
	}

	function AdminSettings($frm)
	{
		$frm->AddInput(new FormInput('Default Template', 'select', 'template',
			$this->GetTemps()));
	}

	function AdminSetup()
	{
		$settings['template'] = GetVar('template');
	}

	function GetTemps()
	{
		global $_d;

		$temps = array();
		$dp = opendir('template');
		while ($f = readdir($dp))
		{
			if ($f[0] == '.') continue;
			if ($_d['settings']['site_template'] == $f) $sel = true;
			else $sel = false;
			if (is_dir("template/{$f}")) $temps[$f] = new SelOption($f, false, $sel);
		}
		return $temps;
	}
}

?>
