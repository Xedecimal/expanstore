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

	function Checkout($items)
	{
		global $_d;

		$ca = @$_d['q'][2];

		if ($ca == 'finish')
		{
			if (empty($items)) return;

			$add_pack['pkg_date'] = SqlUnquote('NOW()');
			$add_pack['pkg_user'] = $_d['cl']['usr_id'];
			$id = $_d['package.ds']->Add($add_pack);

			if (empty($_d['settings']['pay_manual.no_payment']))
			{
				$adding['card_name'] = Server::GetVar('card_name');
				$adding['card_num'] = Server::GetVar('card_num');
				$adding['card_exp'] = Server::GetVar('card_exp');
				$adding['card_verify'] = Server::GetVar('card_verify');
			}

			$add_ship['ps_package'] = $id;

			if (Server::GetVar('saved') == 'yes')
			{
				$name = $_d['cl']['usr_name'];
				$add_ship['ps_name'] = $name;
				$add_ship['ps_address'] = $_d['cl']['usr_address'];
				$add_ship['ps_city'] = $_d['cl']['usr_city'];
				$add_ship['ps_state'] = $_d['cl']['usr_state'];
				$add_ship['ps_zip'] = $_d['cl']['usr_zip'];
			}
			else
			{
				$name = Server::GetVar('ship_name');
				$add_ship['ps_name'] = $name;
				$add_ship['ps_address'] = Server::GetVar('ship_address');
				$add_ship['ps_city'] = Server::GetVar('ship_city');
				$add_ship['ps_state'] = Server::GetVar('ship_state');
				$add_ship['ps_zip'] = Server::GetVar('ship_zip');
			}

			$_d['pack_ship.ds']->Add($add_ship);

			$_d['cart.ds']->Remove(array('cart_user' => $_d['cl']['usr_id']));

			# Mail the owner if wanted

			if (!empty($_d['settings']['pay_manual.email']))
			{
				$add = Server::GetVar('additional');
				$body = <<<EOF
{$name} has placed an order. Login to http://{$_SERVER['HTTP_HOST']}{$_d['app_abs']} to manage it.

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
			$t->ReWrite('nempty', 'TagNEmpty');
			$body = $t->ParseFile(Module::L('pay_manual/checkout.xml'));
			return GetBox('box_shipping', 'Shipping', $body);
		}
		return true;
	}
}

ModPayment::RegisterPayMod('manual', 'PayManual');

?>
