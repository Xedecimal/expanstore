<?php

Module::RegisterModule('ModPage');

class ModPage extends Module
{
	function Link()
	{
		global $_d;

		if (empty($_d['page.nav'])) return;

		// Attach to Navigation

		$pages = glob('content/*.xml');
		foreach ($pages as $p)
		{
			if (basename($p) == 'index.xml') continue;

			$name = str_replace('.xml', '', basename($p));
			$title = $this->GetTitle($name);
			$_d['page.links'][$title] = "{{me}}?cs=page&amp;ci={$name}";
		}
	}

	function Get()
	{
		global $_d;

		if ($_d['q'][0] == 'page')
		{
			$name = $_d['q'][1];
			if (file_exists("content/{$name}.xml"))
				$out = file_get_contents("content/{$name}.xml");
		}
		else if (file_exists('content/index.xml') && $_d['q'][0] == 'catalog' && empty($_d['cc']))
		{
			$name = 'Home';
			$out = file_get_contents('content/index.xml');
		}

		if (!empty($out)) return GetBox('box_page',
			$this->GetTitle($name), $out);
	}

	function GetTitle($filename)
	{
		return str_replace('_', ' ', basename($filename));
	}
}

?>
