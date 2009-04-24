<?php

session_start();

require_once('xedlib/h_utility.php');
HandleErrors();
require_once('xedlib/h_display.php');
require_once('xedlib/h_data.php');
require_once('xedlib/h_template.php');
require_once('xedlib/a_editor.php');

require_once('h_tools.php');

define('STR_VER', '1.0a');

define('PATH_VIEWED', 0);
define('PATH_PURCHASED', 1);

$_d['page_title'] = '';

//Persistant variables

$_d['me'] = GetVar('PHP_SELF'); //Current script.
//With the persist, the Admin -> Settings page has trouble with the
//payment module form forcing cs to 'admin' instead of the specified
//'payment'.
$_d['cs'] = Persist('cs', GetVar('cs', 'catalog'));  //Current Section
$_d['class'] = GetVar('class'); //Current class.
$_d['ca'] = GetVar('ca', 'main');     //Current action.
$_d['cc'] = GetVar('cc', 0);  //Current category.
$_d['ci'] = GetVar('ci'); //Current item.
$_d['editor'] = GetVar('editor');

if (file_exists('settings.txt'))
	$_d['settings'] = unserialize(file_get_contents('settings.txt'));

////////////////////////////////////////////////////////////////////////////////
//Data
//

function CreateDB()
{
	global $data;

	preg_match('#/([^/]+)$#', DATA_LOCATION, $matches);
	$data['db']->Query("CREATE DATABASE {$matches[1]}");
	$data['db']->Open(DATA_LOCATION);
}

$db = new Database();
$_d['db'] = $db;
$db->Handlers[1049] = 'CreateDB';
$db->Open($_d['settings']['data_location']);

$_d['log.ds'] = new DataSet($_d['db'], 'ype_log');

// Modules

class Module
{
	public $Block = 'left';
	function Link() {}
	function Prepare()
	{
		if (isset($GLOBALS['_d']['settings']['blocks'][get_class($this)]))
		$this->Block = $GLOBALS['_d']['settings']['blocks'][get_class($this)];
	}
	function Get() {}
}

function RegisterModule($name)
{
	$GLOBALS['mods'][$name] = new $name();
}

$files = glob('modules/*.php');
foreach ($files as $file) require_once($file);
foreach ($mods as $mod) $mod->Link();

?>
