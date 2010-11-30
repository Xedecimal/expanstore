<?php

$_d['user.edit'] = 1;

require_once('h_main.php');

$_d['user.ds'] = new DataSet($_d['db'], 'user', 'usr_id');

Module::Initialize(true);

$mods['ModUser']->AddDataset($_d['user.ds'], 'usr_pass', 'usr_user');
$_d['user.login'] = true;
$_d['cl'] = $mods['ModUser']->Authenticate();

die(Module::Run(l('catalog/index.xml')));

?>
