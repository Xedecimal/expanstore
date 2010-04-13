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

		$_d['catalog.cb.head']['admin'] = array(&$this, 'cb_catalog_head');
		$_d['product.cb.knee'][] = array(&$this, 'cb_product_knee');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		$GLOBALS['mods']['ModUser']->Prepare();

		$ca = $_d['q'][0];
		$cl = $_d['cl'];

		if ($ca == 'setup')
		{
			$_d['settings']['data_location'] = GetVar('settings_data');
			$_d['settings']['site_name'] = GetVar('settings_name');

			RunCallbacks(@$_d['admin.callbacks.setup']);

			file_put_contents('settings.txt', serialize($_d['settings']));
			xslog($_d, "Updated settings");
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
		$frmGeneral->AddHidden('ca', 'setup');
		$frmGeneral->AddInput(new FormInput('Data Location', 'text', 'data',
			$_d['settings']['data_location'] , 'size="50"'));
		$frmGeneral->AddInput(new FormInput('Store Name', 'text', 'name',
			$_d['settings']['site_name'], 'size="50"'));

		RunCallbacks(@$_d['admin.callbacks.settings'], $frmGeneral);

		$frmGeneral->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Update'));
		$ret .= GetBox("box_general", "General Settings",
			$frmGeneral->Get('action="{{me}}" method="post"'));

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
}

Module::RegisterModule('ModAdmin');

?>
