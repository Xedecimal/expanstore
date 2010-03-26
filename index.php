<?php

require_once('h_main.php');

Module::Initialize(true);
echo Module::Run(l('catalog/index.xml'));

function TagInstallForm($t)
{
	global $me;
	$formInstall = new Form('install');
	$formInstall->AddHidden('ca', 'install');
	$formInstall->AddInput('<div class="head">Database</div>');
	$formInstall->AddInput(new FormInput('MySQL Host', 'text', 'myhost',
		'localhost'));
	$formInstall->AddInput(new FormInput('MySQL User', 'text', 'myuser',
		'root'));
	$formInstall->AddInput(new FormInput('MySQL Pass', 'password',
		'mypass'));
	$formInstall->AddInput(new FormInput('MySQL Database', 'text',
		'mydata', substr(strrchr(dirname($me), '/'), 1)));
	$formInstall->AddInput('<div class="head">Look</div>');
	$formInstall->AddInput(new FormInput('Store Name', 'text',
		'loname', 'My Store'));
	global $mods;
	foreach ($mods as $mod) $mod->InstallFields($formInstall);
	$formInstall->AddInput(new FormInput(null, 'submit',
		'butSubmit', 'Install'));
	return $formInstall->Get('method="post" action="'.$me.'"');
}

function VerifyInstall()
{
	if (!file_exists('settings.txt'))
	{
		if (GetVar('ca') == 'install')
		{
			global $_d;

			$_d['settings']['data_location'] = 'mysql://'.GetVar('install_myuser').
				':'.GetVar('install_mypass').
				'@'.GetVar('install_myhost').
				'/'.GetVar('install_mydata');
			$_d['settings']['site_name'] = GetVar('install_loname');

			CreateDB();
			global $mods;
			foreach ($mods as $n => $m) $m->Install();

			file_put_contents('settings.txt', serialize($_d['settings']));

			return true;
		}


		$t = new Template();
		$t->ReWrite('installform', 'TagInstallForm');
		echo $t->ParseFile('t_install.xml');

		return false;
	}

	return true;
}

/*if (VerifyInstall())
{
	require_once("h_main.php");
	global $_d;

	$tprep = new Template();
	$tprep->ReWrite('block', 'TagPrepBlock');
	$tprep->ParseFile($_d['template.path'].'index.xml');

	$t = new Template($_d);
	$t->ReWrite('block', 'TagBlock');

	global $mods;
	RunCallbacks($_d['index.cb.prelink']);
	foreach ($mods as $mod) $mod->PreLink();
	foreach ($mods as $mod) $mod->Link();
	foreach ($mods as $mod) $mod->Prepare();
	foreach ($mods as $mod) RunCallbacks($_d['index.cb.get'], $mod);

	if (!empty($_d['template.rewrites']))
		foreach ($_d['template.rewrites'] as $rw)
			$t->ReWrite($rw[0], $rw[1]);

	echo $t->ParseFile($_d['template.path'].'index.xml');
}*/

function TagPrepBlock($t, $g, $a)
{
	global $_d;

	$_d['blocks']['none'] = null;
	if (!isset($_d['blocks'][$a['NAME']]))
		$_d['blocks'][$a['NAME']] = null;
}

function TagBlock($t, $g, $a)
{
	return $GLOBALS['_d']['blocks'][$a['NAME']];
}

?>
