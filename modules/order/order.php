<?php

require_once('h_main.php');

Module::RegisterModule('ModOrder');

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
			$t = new Template();
			$t->use_getvar = true;
			$t->ReWrite('each', 'TagEach');
			die(varinfo($t->ParseFile('content/t_order_email.xml')));
		}
	}

	function Get()
	{
		global $_d;

		$q = $_d['q'];
		$act = array_pop($q);
		$target = array_pop($q);

		if ($target != 'order') return;
		if ($act == 'submit')
		{
			return "Order Submitted!";
		}
	}
}

?>