<?php

session_start();

require_once('xedlib/classes/Server.php');
Server::HandleErrors();
require_once('xedlib/classes/data/Database.php');
require_once('xedlib/classes/data/DataSet.php');

require_once('h_tools.php');

define('STR_VER', '1.0a');

$_d['q'] = explode('/', $rw = Server::GetVar('rw', 'home'));

$_d['app_dir'] = dirname(__FILE__);
$_d['app_abs'] = Server::GetRelativePath($_d['app_dir']);

//Persistant variables

if (file_exists(__DIR__.'/settings.ini'))
	$_d['settings'] = parse_ini_file('settings.ini');

foreach (explode(',', $_d['settings']['module.disable']) as $m)
	$_d['module.disable'][$m] = true;

$_d['db'] = new Database();
$_d['db']->Open($_d['settings']['data_location']);

require_once('xedlib/modules/user/user.php');
require_once('xedlib/modules/nav.php');

?>
