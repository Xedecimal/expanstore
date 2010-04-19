<?php

class ModAdmin extends Module
{
	function __construct()
	{
	}

	function Link()
	{
		global $_d;

		// Attach to Navigation.

		if (!empty($_d['cl']) && $_d['cl']['usr_access'] >= 500)
		{
			$_d['page.links']['Admin']['Settings'] =
				'{{app_abs}}/admin';
			$_d['page.links']['Admin']['Products']['Create'] =
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
			$_d['settings']['data_location'] = GetVar('data');
			$_d['settings']['site_name'] = GetVar('name');

			RunCallbacks(@$_d['admin.callbacks.setup']);

			ModAdmin::SaveSettings();
			//file_put_contents('settings.txt', serialize($_d['settings']));
			ModLog::Log('Updated settings');
		}
	}

	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'admin') return;

		$GLOBALS["page_section"] = "General Settings";
		$ret = GetBox("box_motd",
			"Welcome", "Welcome to the administration, FAQ and such can go here.");

		//General Settings

		$frmGeneral = new Form('settings');
		//$frmGeneral->AddHidden('ca', 'setup');
		$frmGeneral->AddInput(new FormInput('Data Location', 'text', 'data',
			$_d['settings']['data_location'] , 'size="50"'));
		$frmGeneral->AddInput(new FormInput('Store Name', 'text', 'name',
			$_d['settings']['site_name'], 'size="50"'));

		RunCallbacks(@$_d['admin.callbacks.settings'], $frmGeneral);

		$frmGeneral->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Update'));
		$ret .= GetBox("box_general", "General Settings",
			$frmGeneral->Get('action="{{app_abs}}/admin/save" method="post"'));

		$ret .= RunCallbacks($_d['admin.callbacks.foot']);

		return $ret;
	}

	function cb_catalog_head()
	{
		global $_d;

		if ($_d['cl']['usr_access'] > 500)
			return '<script type="text/javascript" src="{{app_abs}}/modules/admin/admin.js"></script>';
	}

	function cb_product_knee()
	{
		global $_d;

		if ($_d['cl']['usr_access'] < 500) return;

		$eb = 'catalog/edit.png';
		$db = 'catalog/delete.png';

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
		if ($_d['settings'][$a[1]] != $val) varinfo("Changed '{$a[1]}' from '{$val}' to '{$_d['settings'][$a[1]]}'");
		else varinfo("Unchanged: {$a[1]}");
	}
}

Module::RegisterModule('ModAdmin');

?>
