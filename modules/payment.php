<?php

Module::RegisterModule('ModPayment');

class ModPayment extends Module
{
	function Prepare()
	{
		parent::Prepare();

		global $_d;

		$_d['cart.callbacks.knee']['payment'] = array(&$this, 'CartKnee');
		$_d['admin.callbacks.foot']['payment'] = array(&$this, 'AdminFoot');
	}

	function CartKnee()
	{
		global $_d;

		$t = new Template();
		$t->ReWrite('method', array(&$this, 'TagMethod'));
		return $t->ParseFile($_d['tempath'].'payment/cartKnee.xml');
	}

	function AdminFoot()
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

	function TagMethod($t, $g)
	{
		$vp = new VarParser();
		$ret = null;

		foreach (glob('modules/payment/pay_*.php') as $file)
		{
			$module = preg_replace('/pay_(\w+)\.php/', '\1', basename($file));
			require_once($file);
			$name = 'Pay' . $module;
			$mod = new $name();
			$d['module'] = $module;
			$d['name'] = $mod->GetName();
			$ret .= $vp->ParseVars($g, $d);
		}
		return $ret;
	}

	function Get()
	{
		$ca = GetVar('ca');

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
}

?>
