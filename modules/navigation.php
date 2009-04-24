<?php

RegisterModule('ModNav');

function GetLinks($title, $link, $depth = -1)
{
	if (is_array($link))
	{
		$ret = null;

		if (strlen($title) > 0)
			$ret = str_repeat('&nbsp;&nbsp;&nbsp;', $depth)."$title<br/>\n";
		foreach ($link as $t => $l)
		{
			$ret .= GetLinks($t, $l, $depth+1);
		}
		return $ret;
	}
	else
	{
		return str_repeat('&nbsp;&nbsp;&nbsp;', $depth).
		"<a href=\"{$link}\">$title</a><br />\n";
	}
}

class ModNav extends Module
{
	function Prepare()
	{
		parent::Prepare();
	}

	function Get()
	{
		global $_d;

		$out = null;
		if (isset($_d['page.links']))
		{
			$links = $_d['page.links'];
			$out .= GetLinks(null, $links);
		}
		if (strlen($out) > 0) return GetBox('box_nav', 'Navigation', $out);
	}
}

?>
