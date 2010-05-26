<?php

session_start();

require_once('xedlib/h_utility.php');
require_once('xedlib/h_display.php');
require_once('xedlib/h_data.php');
require_once('xedlib/h_template.php');
require_once('xedlib/h_module.php');
require_once('xedlib/a_editor.php');

require_once('h_tools.php');

define('STR_VER', '1.0a');

$_d['q'] = explode('/', GetVar('q'));

$_d['app_dir'] = dirname(__FILE__);
$_d['app_abs'] = GetRelativePath($_d['app_dir']);

//Persistant variables

if (file_exists('settings.ini'))
	$_d['settings'] = parse_ini_file('settings.ini');

foreach (explode(',', $_d['settings']['module.disable']) as $m)
	$_d['module.disable'][$m] = true;

$_d['db'] = new Database();
$_d['db']->Open($_d['settings']['data_location']);

?>
