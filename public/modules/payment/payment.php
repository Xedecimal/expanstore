<?php

class ModPayment extends Module
{
	function __construct()
	{
		global $_d;

		$_d['payment.mods'] = array();
	}

	function Prepare()
	{
		global $_d;

		foreach (glob(dirname(__FILE__).'/pay_*.php') as $f)
			require_once($f);
	}

	function Link()
	{
		global $_d;

		$_d['cart.callbacks.knee']['payment'] = array(&$this, 'cart_knee');
		$_d['admin.callbacks.foot']['payment'] = array(&$this, 'admin_foot');
	}

	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'payment') return;

		$ca = $_d['q'][1];

		if ($ca == 'checkout')
		{
			global $_d;

			if (count($_d['payment.mods']) > 1) $mod = Server::GetVar('paytype');
			else list($mod) = array_keys($_d['payment.mods']);

			$mod = new $_d['payment.mods'][$mod];
			$items = ModCart::QueryCart();

			$id = $_d['pack.ds']->Add(array(
				'pkg_date' => SqlUnquote('NOW()'),
				'pkg_user' => $_d['cl']['usr_id']
			));

			foreach ($items as $i)
			{
				$pid = $_d['pack_prod.ds']->Add(array(
					'pp_package' => $id,
					'pp_name' => $i['prod_name'],
					'pp_model' => $i['prod_model'],
					'pp_price' => $i['prod_price']
				));

				RunCallbacks($_d['payment.cb.checkout.item'], $pid, $i);
			}

			$ret = $mod->Checkout($items);

			RunCallbacks(@$_d['payment.cb.checkout'], $items);
			return $ret;
		}

		if ($ca == "pay_check")
		{
			$pt = Server::GetVar("modules/payment/pay_{$pt}.php");

			$cname = "Pay{$pt}";
			$mod = new $cname;

			return GetBox("box_check", "Checking payment module: ".
				$mod->GetName(), $mod->GetCheck());
		}

		if ($ca == "pay_repair")
		{
			$pt = Server::GetVar('paytype');
			require_once("pay_{$pt}.php");

			$cname = "Pay{$pt}";
			$mod = new $cname;

			return GetBox('box_repair', "Repairing payment module: ".
				$mod->GetName(), $mod->GetRepair($_d));
		}
	}

	# Tags

	function TagMethods($t, $g)
	{
		global $_d;

		if (count($_d['payment.mods']) > 1) return $g;
	}

	function TagMethod($t, $g)
	{
		global $_d;

		$vp = new VarParser();
		$ret = null;

		foreach ($_d['payment.mods'] as $module => $class)
		{
			$mod = new $class;
			$d['module'] = $module;
			$d['name'] = $mod->GetName();
			$ret .= $vp->ParseVars($g, $d);
		}
		return $ret;
	}

	# Cart

	function cart_knee()
	{
		global $_d;

		$t = new Template($_d);
		$t->ReWrite('methods', array(&$this, 'TagMethods'));
		$t->ReWrite('method', array(&$this, 'TagMethod'));
		return $t->ParseFile(Module::L('payment/cart_knee.xml'));
	}

	# Admin

	function admin_foot()
	{
		$formPayMods = new Form('formPayMods');
		$formPayMods->AddHidden('cs', 'payment');

		#$formPayMods->AddInput(PaymentModule::GetSelect());

		$options = array(
			'pay_check' => new FormOption("Check"),
			'pay_repair' => new FormOption("Repair")
		);
		$formPayMods->AddInput(new FormInput('Action:', 'select', 'ca', $options));
		$formPayMods->AddInput(new FormInput(null, 'submit', 'butSubmit',
			'Execute', "onclick=\"if (document.getElementById('formPayMods_ca')".
			".value == 'pay_repair') return confirm('You will lose any related".
			" data with this module, are you sure?')\""));

		return GetBox("box_paymods", "Payment Modules",
			$formPayMods->Get('action="{{me}}" method="post"'));
	}

	static function RegisterPayMod($name, $cname)
	{
		global $_d;

		$enabled = explode(',', @$_d['settings']['pay.enable']);
		if (!empty($enabled[0]))
			if (!in_array($cname, $enabled))
				return;

		$disabled = explode(',', @$_d['settings']['pay.disable']);
		if (!empty($disabled[0]))
			if (in_array($cname, $disabled))
				return;

		$_d['payment.mods'][$name] = $cname;
	}
}

Module::Register('ModPayment', array('ModCart', 'ModSale'));

?>
