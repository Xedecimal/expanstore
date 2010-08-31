<?php

define('SALE_STATE_ORDERED', 0);
define('SALE_STATE_FAILED', 1);
define('SALE_STATE_SHIPPED', 2);

function GetStateName($id)
{
	switch ($id)
	{
		case SALE_STATE_ORDERED: return 'Ordered'; break;
		case SALE_STATE_FAILED: return 'Failed'; break;
		case SALE_STATE_SHIPPED: return 'Shipped'; break;
	}
	return null;
}

class ModSale extends Module
{
	function __construct()
	{
		global $_d;

		$_d['pack.ds'] = new DataSet($_d['db'], 'pack');
		$_d['pack_prod.ds'] = new DataSet($_d['db'], 'pack_prod');
	}

	function Link()
	{
		global $_d;

		// Attach to Navigation

		if (ModUser::RequestAccess(500))
		{
			$_d['page.links']['Admin']['Sales']
				= htmlspecialchars("{{app_abs}}/sale");
		}
	}

	function Prepare()
	{
		parent::Prepare();

		if (!ModUser::RequestAccess(500)) return;

		global $_d;
		if (@$_d['q'][0] != 'sale') return;

		if (@$_d['q'][1] == 'update')
		{
			$dsPackage = $_d['pack.ds'];
			$dsPackage->Update(array('pkg_id' => $_d['q'][2]),
				array('pkg_state' => GetVar('state')));
		}
		if (@$_d['q'][1] == 'delete')
		{
			$dsPackage = $_d['pack.ds'];
			$dsPackage->Remove(array('pkg_id' => $_d['q'][2]));
		}
	}

	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'sale') return;
		if (!ModUser::RequestAccess(500)) return;

		$dsPackage = $_d['pack.ds'];
		$dsPackageProd = $_d['pack_prod.ds'];
		$dsUsers = $_d['user.ds'];

		$q['columns'] = array('pkg_id', 'pkg_date', 'price' =>
			SqlUnquote('SUM(pp_price)'), 'pkg_state', 'usr_name', 'usr_id');

		$q['joins'] = array(
			new Join($dsPackageProd, 'pp_package = pkg_id', 'LEFT JOIN'),
			new Join($dsUsers, 'pkg_user = usr_id', 'LEFT JOIN')
		);
		$q['group'] = 'pkg_id';

		$packs = $dsPackage->Get($q);
		if (!empty($packs))
		{
			$tblSales = new Table('sales', array(null, '<b>Status</b>', '<b>Total</b>'), array('valign="top"'));
			foreach ($packs as $pack)
			{
				$linkDetails = "<a href=\"{{app_abs}}/sale/detail/{$pack['pkg_id']}#pack_details\">Details</a>";
				$tblSales->AddRow(array(
					$linkDetails,
					GetStateName($pack['pkg_state']).'<br/>'.$pack['pkg_date'],
					'$'.$pack['price']
				));
			}
			$body = $tblSales->Get();
		}
		else
		{
			$body = 'No sales yet, sorry.';
		}

		if (@$_d['q'][1] == 'detail')
		{
			$qd['joins'] = array(
				new Join($_d['pack_prod.ds'], 'pp_package = pkg_id',
					'LEFT JOIN'),
				new Join($_d['pack_prod_option.ds'], 'ppo_pprod = pp_id',
					'LEFT JOIN'),
				new Join($_d['user.ds'], 'pkg_user = usr_id'),
			);
			$qd['match'] = array('pkg_id' => $_d['q'][2]);

			$rows = $dsPackage->Get($qd);
			$this->prods = DataToTree($rows, array(
				'pkg_id' => array('pp_id', 'pp_package'),
				'pp_id' => array('ppo_id', 'ppo_pprod')
			))->children[$_d['q'][2]];

			$t = new Template();
			$t->ReWrite('package', array(&$this, 'TagPackage'));
			$t->Set($rows[0]);
			$body .= $t->ParseFile(l('sale/main.xml'));
		}
		return GetBox('box_details', 'Sales', $body);
	}

	function TagPackage($t, $g)
	{
		global $_d;

		$t2 = new Template();
		$t2->ReWrite('poption', array(&$this, 'TagPOption'));
		$ret = '';
		foreach ($this->prods->children as $p)
		{
			$this->prod = $p;
			$t2->Set($p->data);
			$ret .= $t2->GetString($g);
		}

		return $ret;
	}

	function TagPOption($t, $g)
	{
		$opts = null;
		if (!empty($this->prod))
		{
			$vp = new VarParser();
			foreach ($this->prod->children as $ppo)
			{
				$opts .= $vp->ParseVars($g, $ppo->data);
			}
		}
		return $opts;
	}
}

Module::Register('ModSale');

?>
