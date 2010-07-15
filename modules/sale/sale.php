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
	function Link()
	{
		// Attach to Navigation

		if (isset($_d['cl']) && $_d['cl']['usr_access'] >= 500)
		{
			$_d['page.links']['Admin']['Sales']
				= htmlspecialchars("{{me}}?cs=sale");
		}
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;
		if (@$_d['q'][0] != 'sale') return;

		if ($_d['ca'] == 'update')
		{
			$dsPackage = $_d['package.ds'];
			$dsPackage->Update(array('id' => $_d['ci']),
				array('state' => GetVar('state')));
		}
		if ($_d['ca'] == 'delete')
		{
			$dsPackage = $_d['package.ds'];
			$dsPackage->Remove(array('id' => $_d['ci']));
		}
	}

	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'sale') return;

		$GLOBALS["page_section"] = 'Sales';

		$ca = $_d['ca'];

		$dsPackage = $_d['package.ds'];
		$dsPackageProd = $_d['packageprod.ds'];
		$dsUsers = $_d['user.ds'];

		$packs = $dsPackage->GetCustom("SELECT
			pkg_id, pkg_date, SUM(pp_price) price, pkg_state,
			pkg_ship_name, pkg_ship_address, pkg_ship_city, pkg_ship_state,
			pkg_ship_zip, usr_name, usr_id
			FROM {$dsPackage->table} p
			LEFT JOIN {$dsPackageProd->table} pp ON (pp_package = pkg_id)
			LEFT JOIN {$dsUsers->table} u ON(pkg_user = usr_id)
			GROUP BY pkg_id"
		);
		if (!empty($packs))
		{
			$tblSales = new Table('sales', array(null, null, '<b>Price</b>', '<b>Destination</b>', '<b>User</b>'), array('valign="top"'));
			foreach ($packs as $pack)
			{
				$linkDetails = "<a href=\"{{me}}?cs={{cs}}&amp;ca=details&amp;ci={$pack['pkg_id']}#box_details\">Details</a>";
				$tblSales->AddRow(array(
					$linkDetails,
					GetStateName($pack['pkg_state']).'<br/>'.$pack['pkg_date'],
					'$'.$pack['price'],
					$pack['pkg_ship_name'].'<br/>'.
					$pack['pkg_ship_address'].'<br/>'.
					$pack['pkg_ship_city'].', '.$pack['pkg_ship_state'].' '.$pack['pkg_ship_zip'],
					"<a href=\"{{me}}?cs=sale&amp;ca=view_user&amp;ci={$pack['usr_id']}\">{$pack['usr_name']}</a>"
				));
			}
			$body = $tblSales->Get();
		}
		else
		{
			$body = 'No sales yet, sorry.';
		}

		if ($ca == 'details')
		{
			$pack = $dsPackage->GetOne(array('id' => $_d['ci']));
			$pprods = $dsPackageProd->Get(array('package' => $_d['ci']));
			$t = new Template();
			$packages = null;
			$dsppo = $_d['packageprodoption.ds'];
			if (!empty($pprods))
			foreach ($pprods as $pprod)
			{
				$pprodopts = $dsppo->Get(array('pproduct' => $pprod['id']));
				$opts = null;
				if (!empty($pprodopts))
				{
					foreach ($pprodopts as $pprodopt)
					{
						$t->Set($pprodopt);
						$opts .= $t->ParseFile($_d['tempath'].'admin/sales/package_option.html');
					}
				}
				$t->set('opts', $opts);
				$t->Set($pprod);
				$packages .= $t->ParseFile($_d['tempath'].'admin/sales/package.html');
			}
			$t->Set('packages', $packages);
			$t->Set($pack);
			$body .= $t->ParseFile($_d['tempath'].'admin/sales/main.html');
		}
		return GetBox('box_details', 'Sales', $body);
	}
}

Module::Register('ModSale');

?>
