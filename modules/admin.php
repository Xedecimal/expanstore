<?

RegisterModule('ModAdmin');

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
				htmlspecialchars("{{me}}?cs=admin");
		}

		// Attach to Product.

		$_d['product.callbacks.footer'][] = array(&$this, 'ProductFooter');
		$_d['products.callbacks.footer'][] = array(&$this, 'ProductsFooter');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		$ca = $_d['ca'];
		$cl = $_d['cl'];

		if ($ca == 'setup')
		{
			$_d['settings']['data_location'] = GetVar('settings_data');
			$_d['settings']['site_name'] = GetVar('settings_name');

			if (!empty($_d['admin.callbacks.setup']))
				RunCallbacks($_d['admin.callbacks.setup']);

			file_put_contents('settings.txt', serialize($_d['settings']));
			xslog($_d, "Updated settings");
		}
	}

	function ProductsFooter($_d)
	{
		$cl = $_d['cl'];

		$ret = null;
		if (isset($cl['usr_access']))
		{
			if (isset($cl['company']) && $cl['company'] != 0)
			{
				$ret .= "<a href=\""
					.htmlspecialchars("{{me}}?cs=product&ca=prepare&cc=".GetVar('cc'))
					."\">Add Product Here</a>\n";
			}
		}
		return $ret;
	}

	function Get()
	{
		global $_d;

		if ($_d['cs'] != 'admin') return;

		$GLOBALS["page_section"] = "General Settings";
		$ret = GetBox("box_motd",
			"Welcome", "Welcome to the administration, FAQ and such can go here.");

		//General Settings

		$frmGeneral = new Form('settings');
		$frmGeneral->AddHidden('cs', $_d['cs']);
		$frmGeneral->AddHidden('ca', 'setup');
		$frmGeneral->AddInput(new FormInput('Data Location', 'text', 'data',
			$_d['settings']['data_location'] , 'size="50"'));
		$frmGeneral->AddInput(new FormInput('Store Name', 'text', 'name',
			$_d['settings']['site_name'], 'size="50"'));

		RunCallbacks($_d['admin.callbacks.settings'], $frmGeneral);

		$frmGeneral->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Update'));
		$ret .= GetBox("box_general", "General Settings",
			$frmGeneral->Get('action="{{me}}" method="post"'));

		$ret .= RunCallbacks($_d['admin.callbacks.foot']);

		return $ret;
	}
}

?>
