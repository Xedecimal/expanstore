<?php

class PackageManual
{
	function DeSerialize($data)
	{
		if (count($data) < 1) return null;
		if (is_array($data[0]))
		{
			$ret = array();
			foreach ($data as $item) $ret[] = CManualPackage::DeSerialize($item);
			return $ret;
		}

		$obj = new CManualPackage();
		return $obj;
	}
}

class PayManual
{
	function __construct()
	{
		global $_d;

		$_d['package.ds'] = new DataSet($_d['db'], 'pack', 'pkg_id');
		$_d['pack_ship.ds'] = new DataSet($_d['db'], 'pack_ship', 'ds_id');
	}

	function GetName() { return "Manual Processing"; }

	function GetCheck()
	{
		$ret = "Checking the database... ";
		$var = 1;
		if ($var == -1)
		{
			$ret .= "<span class=\"error\">Missing or malformed.</span><br/><br/>\n";
			$ret .= "<a href=\"$me?cs=admin&ca=pay_repair&ci=$ci\"> Repair </a>\n";
		}
		else
		{
			$ret .= "<span class=\"success\">OK</span><br/><br/>\n";
		}
		return $ret;
	}

	function GetRepair($data)
	{
		$data['db']->Query('DROP TABLE pay_man_pack');

		$data['db']->Query("CREATE TABLE pay_man_pack (" .
			"id int(11) not null auto_increment primary key, " .
			"cart_num varchar(255) not null, " .
			"card_name varchar(255) not null, " .
			"cart_exp date not null);");

		return "All done.";
	}

	function Checkout()
	{
		global $_d;

		$ca = @$_d['q'][2];

		if ($ca == 'finish')
		{
			$add_pack['pkg_date'] = SqlUnquote('NOW()');
			$add_pack['pkg_user'] = $_d['cl']['usr_id'];
			$id = $_d['package.ds']->Add($add_pack);

			if (empty($_d['settings']['pay_manual.no_payment']))
			{
				$adding['card_name'] = GetVar('card_name');
				$adding['card_num'] = GetVar('card_num');
				$adding['card_exp'] = GetVar('card_exp');
				$adding['card_verify'] = GetVar('card_verify');
			}

			$add_ship['ps_package'] = $id;

			if (GetVar('saved') == 'on')
			{
				$add_ship['ps_name'] = $_d['cl']['usr_name'];
				$add_ship['ps_address'] = $_d['cl']['usr_address'];
				$add_ship['ps_city'] = $_d['cl']['usr_city'];
				$add_ship['ps_state'] = $_d['cl']['usr_state'];
				$add_ship['ps_zip'] = $_d['cl']['usr_zip'];
			}
			else
			{
				$add_ship['ps_name'] = GetVar('ship_name');
				$add_ship['ps_address'] = GetVar('ship_address');
				$add_ship['ps_city'] = GetVar('ship_city');
				$add_ship['ps_state'] = GetVar('ship_state');
				$add_ship['ps_zip'] = GetVar('ship_zip');
			}

			$_d['pack_ship.ds']->Add($add_ship);

			if (!empty($cart))
			{
				$formCart = new Form("formCart");

				$formCart->AddHidden("ca", "checkout");
				$formCart->AddHidden("cs", "cart");

				//Selected options
				$totalprice = 0;

				foreach ($cart as $citem)
				{
					$totalprice = $prodprice = $citem['price'];

					$pprodid = $data['packageprod.ds']->Add(array(
						'package' => $id,
						'name' => $citem['prod_name'],
						'model' => $citem['model'],
						'price' => $totalprice
					));
				}
			}

			# Mail the owner if wanted

			if (!empty($_d['settings']['pay_manual.email']))
			{
				$add = GetVar('additional');
				$body = <<<EOF
{$_d['cl']['usr_name']} has placed an order. Login to http://{$_SERVER['HTTP_HOST']}{$_d['app_abs']} to manage it.

$add
EOF;
				mail($_d['settings']['pay_manual.email'], $_d['settings']['site_name'].' - Online Store Order',
					$body);
			}
		}
		else
		{
			$t = new Template();
			$t->Set($_d['cl']);
			$t->Set('no_payment', @$_d['settings']['pay_manual.no_payment']);
			$t->ReWrite('empty', 'TagEmpty');
			$body = $t->ParseFile(l('pay_manual/checkout.xml'));
			return GetBox('box_shipping', 'Shipping', $body);
		}
		return true;
	}
}

ModPayment::RegisterPayMod('manual', 'PayManual');

?>
