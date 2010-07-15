<?php

Module::Register('ModPayment');

class ModPayment extends Module
{
	function Prepare()
	{
		global $_d;

		$_d['payment.mods'] = array();

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

			if (count($_d['payment.mods']) > 1) $mod = GetVar('paytype');
			else list($mod) = array_keys($_d['payment.mods']);
			$mod = new $_d['payment.mods'][$mod];
			return $mod->Checkout();
		}

		if ($ca == "pay_check")
		{
			$pt = GetVar("modules/payment/pay_{$pt}.php");

			$cname = "Pay{$pt}";
			$mod = new $cname;

			return GetBox("box_check", "Checking payment module: ".
				$mod->GetName(), $mod->GetCheck());
		}

		if ($ca == "pay_repair")
		{
			$pt = GetVar('paytype');
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

		$t = new Template();
		$t->ReWrite('methods', array(&$this, 'TagMethods'));
		$t->ReWrite('method', array(&$this, 'TagMethod'));
		return $t->ParseFile(l('payment/cart_knee.xml'));
	}

	# Admin

	function admin_foot()
	{
		$formPayMods = new Form('formPayMods');
		$formPayMods->AddHidden('cs', 'payment');

		#$formPayMods->AddInput(PaymentModule::GetSelect());

		$options = array(
			'pay_check' => new SelOption("Check"),
			'pay_repair' => new SelOption("Repair")
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

		$_d['payment.mods'][$name] = $cname;
	}
}

?>
