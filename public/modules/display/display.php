<?php

global $_d;

if (!isset($_d['settings']['site_template'])) $_d['settings']['site_template'] = 'new';
$_d['template_path'] = $_d['settings']['site_template'];
$_d['template_url'] = $_d['app_abs'].'/template/'.$_d['settings']['site_template'];

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

		$_d['index.cb.get']['template'] = array(&$this, 'cb_index_get');
		$this->Load();
	}

	function cb_index_get($mod)
	{
		@$GLOBALS['_d']['blocks'][$mod->Block] .= $mod->Get();
	}

	function Link()
	{
		global $_d;

		// Attach to Navigation

		if (ModUser::RequireAccess(500))
			$_d['nav.links']['Admin/Display'] = '{{app_abs}}/display';

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
			file_put_contents('config/blocks.dat', serialize(Server::GetVar('blocks')));
			file_put_contents('config/order.dat', serialize(Server::GetVar('order')));
			U::RunCallbacks($_d['display.callbacks.update']);
			$this->Load();
			ModAdmin::SaveSettings();
		}
	}

	function Get()
	{
		global $_d, $mods;

		if (@$_d['q'][0] != 'display') return;

		$t = new Template();
		$t->ReWrite('modules', array(&$this, 'TagModules'));
		return $t->ParseFile(Module::L('display/display.xml'));
	}

	function TagModules($t, $g)
	{
		$t->ReWrite('module', array(&$this, 'TagModule'));
		return $t->GetString($g);
	}

	function TagModule($t, $g)
	{
		global $_d, $mods;

		$bnames = FormOption::FromArray(array_keys($_d['blocks']), null, false);

		$ret = null;
		foreach ($mods as $mod)
		{
			$t->Set('name', $name = get_class($mod));

			if (isset($_d['settings']['blocks'][$name]))
				$sel = $_d['settings']['blocks'][$name];
			else $sel = 'default';
			$t->Set('location', $sel = FormInput::GetSelect(array(
				'NAME' => "blocks[{$name}]"), $bnames, $sel));
			$t->Set('priority', @$_d['module.order'][$name]);

			$ret .= $t->GetString($g);
		}
		return $ret;
	}

	function AdminSettings($frm)
	{
		global $_d;

		$frm->AddInput(new FormInput('Default Template', 'text', 'template',
			$_d['settings']['site_template']));
	}

	function AdminSetup()
	{
		global $_d;
		$_d['settings']['site_template'] = Server::GetVar('template');
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
			if (is_dir("template/{$f}")) $temps[$f] =
				new FormOption($f, false, $sel);
		}
		return $temps;
	}

	function Load()
	{
		global $_d;

		if (file_exists('config/blocks.dat'))
		{
			$_d['settings']['blocks'] =
				unserialize(file_get_contents('config/blocks.dat'));
		}
		if (file_exists('config/order.dat'))
		{
			$orders = unserialize(file_get_contents('config/order.dat'));
			foreach ($orders as $m => $v) $_d['module.order'][$m] = $v;
		}
	}
}

Module::Register('ModTemplate');

?>
