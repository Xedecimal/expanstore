<?php

require_once(__DIR__.'/../../h_main.php');

class ModAdmin extends Module
{
	function __construct()
	{
	}

	function Link()
	{
		global $_d;

		// Attach to Navigation.

		if (ModUser::RequireAccess(500))
		{
			$_d['nav.links']['Admin/Settings'] = '{{app_abs}}/admin';
			$_d['nav.links']['Admin/Products/Create'] =
				'{{app_abs}}/product/prepare';
		}

		// Attach to Product.

		$_d['catalog.callbacks.head']['admin'] = array(&$this, 'cb_catalog_head');
		$_d['product.callbacks.knee'][] = array(&$this, 'cb_product_knee');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		$GLOBALS['mods']['ModUser']->Prepare();

		if ($_d['q'][0] != 'admin') return;

		$ca = @$_d['q'][1];
		$cl = $_d['cl'];

		if ($ca == 'save')
		{
			$_d['settings']['data_location'] = Server::GetVar('data');
			$_d['settings']['site_name'] = Server::GetVar('name');

			U::RunCallbacks(@$_d['admin.callbacks.setup']);

			ModAdmin::SaveSettings();
			ModLog::Log('Updated settings');
		}
	}

	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'admin') return;

		$GLOBALS["page_section"] = "General Settings";
		$ret = Box::GetBox("box_motd",
			"Welcome", "Welcome to the administration, FAQ and such can go here.");

		//General Settings

		$frmGeneral = new Form('settings');
		$frmGeneral->AddInput(new FormInput('Data Location', 'text', 'data',
			$_d['settings']['data_location'] , 'size="50"'));
		$frmGeneral->AddInput(new FormInput('Store Name', 'text', 'name',
			$_d['settings']['site_name'], 'size="50"'));

		U::RunCallbacks(@$_d['admin.callbacks.settings'], $frmGeneral);

		$frmGeneral->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Update'));
		$ret .= Box::GetBox("box_general", "General Settings",
			$frmGeneral->Get('action="{{app_abs}}/admin/save" method="post"'));

		$ret .= U::RunCallbacks($_d['admin.callbacks.foot']);

		return $ret;
	}

	function cb_catalog_head()
	{
		global $_d;

		if (ModUser::RequireAccess(500))
			return '<script type="text/javascript" src="{{app_abs}}/modules/admin/admin.js"></script>';
	}

	function cb_product_knee()
	{
		global $_d;

		if (!ModUser::RequireAccess(500)) return;

		$eb = 'admin/edit.png';
		$db = 'admin/delete.png';

		return <<<EOF
		<a href="{{app_abs}}/product/edit/{{prod_id}}"
			class="{{name}}_ancEditProduct">
			<img src="$eb" title="Edit" alt="Edit" />
		</a>
		<a href="{{app_abs}}/product/delete/{{prod_id}}" class="aProductDelete">
			<img src="$db" title="Delete" alt="Delete" />
		</a>
EOF;
	}

	static function SaveSettings()
	{
		global $_d;
		$data = null;
		foreach ($_d['settings'] as $k => $v)
		{
			if (!is_array($v)) $data .= $k.'="'.$v."\"\r\n";
		}
		file_put_contents('settings.ini', $data);
	}

	static function setting_replace($a)
	{
		global $_d;

		$val = trim($a[2], '"');
		if ($_d['settings'][$a[1]] != $val) U::VarInfo("Changed '{$a[1]}' from '{$val}' to '{$_d['settings'][$a[1]]}'");
		else U::VarInfo("Unchanged: {$a[1]}");
	}
}

Module::Register('ModAdmin');

?>
