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

		global $_d;
		if (@$_d['q'][0] != 'sale') return;

		if (@$_d['q'][1] == 'update')
		{
			$dsPackage = $_d['pack.ds'];
			$dsPackage->Update(array('id' => $_d['ci']),
				array('state' => GetVar('state')));
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
			$tblSales = new Table('sales', array(null, null, '<b>Price</b>', '<b>User</b>'), array('valign="top"'));
			foreach ($packs as $pack)
			{
				$linkDetails = "<a href=\"{{app_abs}}/sale/detail/{$pack['pkg_id']}#box_details\">Details</a>";
				$tblSales->AddRow(array(
					$linkDetails,
					GetStateName($pack['pkg_state']).'<br/>'.$pack['pkg_date'],
					'$'.$pack['price'],
					"<a href=\"{{me}}?cs=sale&amp;ca=view_user&amp;ci={$pack['usr_id']}\">{$pack['usr_name']}</a>"
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
			$pack = $dsPackage->GetOne(array('id' => $_d['q'][1]));
			$pprods = $dsPackageProd->Get(array('package' => $_d['q'][1]));
			$t = new Template();
			$packages = null;
			$dsppo = $_d['pack_prod_option.ds'];
			if (!empty($pprods))
			foreach ($pprods as $pprod)
			{
				$pprodopts = $dsppo->Get(array('pproduct' => $pprod['pp_id']));
				$opts = null;
				if (!empty($pprodopts))
				{
					foreach ($pprodopts as $pprodopt)
					{
						$t->Set($pprodopt);
						$opts .= $t->ParseFile(l('sale/package_option.xml'));
					}
				}
				$t->set('opts', $opts);
				$t->Set($pprod);
				$packages .= $t->ParseFile(l('sale/package.xml'));
			}
			$t->Set('packages', $packages);
			$t->Set($pack);
			$body .= $t->ParseFile(l('sale/main.xml'));
		}
		return GetBox('box_details', 'Sales', $body);
	}
}

Module::Register('ModSale');

?>
