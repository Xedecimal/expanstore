<?

function VerifyInstall()
{
	if (!file_exists('settings.txt'))
	{
		require_once('xedlib/h_utility.php');

		if (GetVar('ca') == 'install')
		{
			$settings['data_location'] = 'mysql://'.GetVar('install_myuser').
				':'.GetVar('install_mypass').
				'@'.GetVar('install_myhost').
				'/'.GetVar('install_mydata');
			$settings['site_name'] = GetVar('install_loname');
			file_put_contents('settings.txt', serialize($settings));
			return true;
		}

		require_once('xedlib/h_display.php');

		$formInstall = new Form('install');
		$formInstall->AddHidden('ca', 'install');
		$formInstall->AddInput('<div style="font-weight: bold; border-bottom: 1px solid #000;">Database</div>');
		$formInstall->AddInput(new FormInput('MySQL Host', 'text', 'myhost', 'localhost'));
		$formInstall->AddInput(new FormInput('MySQL User', 'text', 'myuser', 'root'));
		$formInstall->AddInput(new FormInput('MySQL Pass', 'password', 'mypass'));
		$formInstall->AddInput(new FormInput('MySQL Database', 'text', 'mydata', 'xstore'));
		$formInstall->AddInput('<div style="font-weight: bold; border-bottom: 1px solid #000;">Administration</div>');
		$formInstall->AddInput(new FormInput('Username', 'text', 'aduser', 'admin'));
		$formInstall->AddInput(new FormInput('Password', 'password', 'adpass'));
		$formInstall->AddInput('<div style="font-weight: bold; border-bottom: 1px solid #000;">Look</div>');
		$formInstall->AddInput(new FormInput('Store Name', 'text', 'loname', 'My Store'));
		$formInstall->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Install'));
		echo $formInstall->Get('method="post"');
		return false;
	}

	return true;
}

if (VerifyInstall())
{
	require_once("h_main.php");
	global $_d;

	$tprep = new Template();
	$tprep->ReWrite('block', 'TagPrepBlock');
	$tprep->ParseFile($_d['tempath'].'index.xml');

	$t = new Template($_d);
	$t->ReWrite('block', 'TagBlock');

	global $mods;
	foreach ($mods as $mod) $mod->Prepare();
	foreach ($mods as $mod)
	{
		// Prepare this block for output.

		if (!isset($_d['blocks'][$mod->Block]))
			$_d['blocks'][$mod->Block] = null;

		$_d['blocks'][$mod->Block] .= $mod->Get();
	}

	echo $t->ParseFile($_d['tempath'].'index.xml');
}

function TagPrepBlock($t, $g, $a)
{
	global $_d;

	if (!isset($_d['blocks'][$a['NAME']]))
		$_d['blocks'][$a['NAME']] = null;
}

function TagBlock($t, $g, $a)
{
	return $GLOBALS['_d']['blocks'][$a['NAME']];
}

?>
