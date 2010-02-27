<?php

global $_d;

if (!isset($_d['settings']['site_template'])) $_d['settings']['site_template'] = 'new';
$_d['template_path'] = $_d['settings']['site_template'];
$_d['template_url'] = $_d['app_abs'].'/template/'.$_d['settings']['site_template'];

function t($path)
{
	global $_d;

	// Overloaded Path
	$opath = $_d['settings']['site_template'].'/'.$path;
	// Default Path
	$dpath = 'modules/'.$path;
	return file_exists($opath) ? $opath : $dpath;
}

function TemplateCheck()
{
	global $_d;
	if (!isset($_d['settings']['site_template'])) $_d['settings']['site_template'] = 'new';
}

class ModTemplate extends Module
{
	function __construct($inst)
	{
		global $_d;

		$_d['index.cb.prelink']['template'] = array(&$this, 'cb_index_prelink');
		$_d['index.cb.get']['template'] = array(&$this, 'cb_index_get');
	}

	function cb_index_prelink()
	{
		global $_d, $mods;

		foreach ($mods as $mod)
		{
			$modname = get_class($mod);
			if (isset($_d['settings']['blocks'][$modname]))
				$mod->Block = $_d['settings']['blocks'][$modname];
		}
	}

	function cb_index_get($mod)
	{
		@$GLOBALS['_d']['blocks'][$mod->Block] .= $mod->Get();
	}

	function Link()
	{
		global $_d;

		// Attach to Navigation

		if (!empty($_d['cl']) && $_d['cl']['usr_access'] >= 500)
			$_d['page.links']['Admin']['Display'] = '{{app_abs}}/display';

		// Attach to Administration

		$_d['admin.callbacks.settings'][] = array(&$this, 'AdminSettings');
		$_d['admin.callbacks.setup'][] = array(&$this, 'AdminSetup');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if (@$_d['q'][0] != 'display') return;

		if (@$_d['q'][1] == 'update')
		{
			$_d['settings']['blocks'] = GetVar('blocks');
			$_d['settings']['priority'] = GetVar('priority');
			file_put_contents('settings.txt', serialize($_d['settings']));
		}
	}

	function Get()
	{
		global $_d, $mods;

		if (@$_d['q'][0] != 'display') return;

		$bnames = ArrayToSelOptions(array_keys($_d['blocks']), null, false);

		$ret = '<form action="{{app_abs}}/display/update" method="post">';
		$ret .= '<table>';
		$ret .= '<tr><th>Module</th><th>Location</th><th>Priority</th></tr>';
		foreach ($mods as $mod)
		{
			$name = get_class($mod);

			if (isset($_d['settings']['blocks'][$name]))
				$sel = $_d['settings']['blocks'][$name];
			else $sel = 'default';

			$sel = MakeSelect(array('name' => "blocks[{$name}]"), $bnames, $sel);
			$pri = @$_d['settings']['priority'][$name];
			$ret .= "<tr><td>{$name}</td>";
			$ret .= "<td>{$sel}</td>";
			$ret .= "<td><input type=\"text\" name=\"priority[$name]\" value=\"{$pri}\" /></td>";
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
		global $_d;
		$_d['settings']['site_template'] = GetVar('settings_template');
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

Module::RegisterModule('ModTemplate');

?>
