<?php

require_once('h_main.php');

Module::Register('ModOrder');

class ModOrder extends Module
{
	function Prepare()
	{
		global $_d;

		$q = $_d['q'];
		$act = array_pop($q);
		$target = array_pop($q);

		if ($target != 'order') return;

		if ($act == 'submit')
		{
			$this->success = array();

			$cons = Server::GetVar('con');
			if (empty($cons['email']) && empty($cons['phone']))
				$this->vals['con[email]'] =
				$this->vals['con[phone]'] =
					'Please enter either email or phone.';

			if (!empty($this->vals)) return;

			$t = new Template();
			$t->use_getvar = true;
			$t->Behavior->Bleed = false;
			$t->ReWrite('each', 'TagEach');
			//die(varinfo($t->ParseFile('content/t_order_email.xml')));
			die(json_encode(array('success' => 1)));
		}
	}

	function Get()
	{
		global $_d;

		$target = $_d['q'][0];
		$act = @$_d['q'][1];

		if ($target != 'order') return;

		if ($act == 'submit' && empty($this->valres))
		{
			if (empty($this->vals)) die("Order Submitted!");
			else die(json_encode($this->vals));
		}
		else
		{
			$t = new Template();
			$t->ReWrite('form', 'TagForm');
			return $t->ParseFile('content/Order Form.xml');
		}
	}
}

?>
