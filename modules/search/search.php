<?php

/**
 * A simple interface to a search engine.
 * @todo Apply category.
 * @todo Search recursively.
 * @todo Support multiple words.
 */
class ModSearch extends Module
{
	public $Block = 'search';

	function PreLink()
	{
		global $_d;

		if ($_d['q'][0] != 'search') return;
		$_d['category.bypass'] = true;
		$_d['category.show'] = true;
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if ($_d['q'][0] != 'search') return;

		$query = GetVar('query');
		$_d['product.ds.match'][] = "(prod_name LIKE '%{$query}%' OR
			prod_desc LIKE '%{$query}%')";

		$_d['q'] = array('catalog');
	}

	function Get()
	{
		$t = new Template($GLOBALS['_d']);
		return $t->ParseFile(l('search/search.xml'));
	}
}

Module::RegisterModule('ModSearch');

?>
