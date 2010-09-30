<?php

require_once('h_main.php');

Module::Initialize(true);

$_d['user.ds'] = new DataSet($_d['db'], 'user', 'usr_id');
$mods['ModUser']->AddDataset($_d['user.ds'], 'usr_pass', 'usr_user');
$_d['user.login'] = true;
$mods['ModUser']->Authenticate();

die(Module::Run(l('catalog/index.xml')));

?>
