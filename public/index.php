<?php


session_start();
date_default_timezone_set('America/Los_Angeles');

require_once('xedlib/classes/Module.php');
require_once('xedlib/classes/Server.php');
Server::HandleErrors();
require_once('xedlib/classes/data/Database.php');
require_once('xedlib/classes/data/DataSet.php');
require_once('xedlib/classes/present/EditorData.php');

require_once('h_tools.php');

define('STR_VER', '1.0a');

//Persistant variables

if (file_exists(__DIR__.'/settings.ini'))
	$_d['settings'] = parse_ini_file('settings.ini');

foreach (explode(',', $_d['settings']['module.disable']) as $m)
	$_d['module.disable'][$m] = true;

$_d['db'] = new Database();
$_d['db']->Open($_d['settings']['data_location']);

require_once('xedlib/modules/nav.php');

$_d['user.edit'] = 1;
$_d['user.ds'] = new DataSet($_d['db'], 'user', 'usr_id');

Module::Initialize(dirname(__FILE__), true);
$_d['cl'] = $mods['User']->Authenticate();
die(Module::Run(Module::L('catalog/index.xml')));

?>
