<?php

class ModNarrow extends Module
{
	function Prepare()
	{
		parent::Prepare();

		$narrows = Server::GetVar('narrows');
		if (!empty($narrows))
		{
			$_d['product.ds.query']['joins'][] =
				new Join($_d['category.ds'], 'cat.id = p.cat');
			$_d['product.ds.query']['joins'][] =
				new Join($_d['specprop.ds'], 'sprop.spec = cat.spec');
			$_d['product.ds.query']['joins'][] =
				new Join($_d['specpropprod.ds'], 'spp.product = p.id',
				'LEFT JOIN');
			$_d['product.ds.query']['joins'][] =
				new Join($_d['specprod.ds'], 'spp.prod = sprod.id',
				'LEFT JOIN');

			$iy = 0;
			$m = '(';
			foreach ($narrows as $pid => $dids)
			{
				if ($iy++ > 0) $m .= ' OR (';
				$m .= "sprop.id = {$pid} AND sprod.id IN (";
				$ix = 0;
				foreach ($dids as $did => $show)
				{
					if ($ix++ > 0) $m .= ', ';
					$m .= "{$did}";
				}
				$m .= '))';
			}

			$_d['product.ds.match'][] = $m;
		}
	}

	function Get()
	{
		global $_d;

		if (!isset($_d['category.ds'])) return;

		global $me;

		$props = QuerySPP($_d);

		if (!empty($props))
		{
			$out = '';
			$pid = -1;
			$did = -1;
			$narrows = Server::GetVar('narrows');

			$nprops = DataToArray($props, 'did');

			foreach ($props as $prop)
			{
				$nnarrow = null;
				if ($prop['pid'] != $pid)
					$out .= ($pid != -1?'</p>':null)."<p><b>{$prop['name']}</b><br/>\n";
				if ($prop['did'] != $did)
				{
					//New Spec Prod
					$t = $this->NarrowURL($_d['cc'], $narrows, $prop['pid'],
						$prop['did'], $found);
					if ($found) $out .= GetImg('next.png', 'Delete').' ';
					$out .= '<a href="'.$t."\">{$prop['val']}</a><br/>\n";
					$t = null;
				}
				$pid = $prop['pid'];
				$did = $prop['did'];
			}
			if ($pid != -1) $out .= '</p>';
			return '<div style="width: 200px">'.GetBox('box_narrow',
				'Narrow Results', $out).'</div>';
		}
	}

	function NarrowURL($cc, $array, $apid, $adid, &$found)
	{
		$found = false;
		$ret = "{{me}}?cc={$cc}";
		if (!empty($array))
		foreach ($array as $pid => $dids)
			foreach ($dids as $did => $show)
			{
				if ($pid == $apid && $did == $adid) { $found = true; continue; }
				$ret .= "&amp;narrows[{$pid}][{$did}]={$show}";
			}
		if (!$found) $ret .= "&amp;narrows[{$apid}][{$adid}]=1";
		return $ret;
	}
}

Module::Register('ModNarrow', array('ModDetail'));

?>
