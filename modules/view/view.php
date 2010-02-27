<?php

Module::RegisterModule('ModView');

/**
 * A view management box to allow configurable views.
 */
class ModView extends Module
{
	function __construct()
	{
	}

	function Link()
	{
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		$sort = GetVar('sort');
		switch ($sort)
		{
			case 1: $_d['product.ds.order']['price'] = 'ASC';   break;
			case 2: $_d['product.ds.order']['p.date'] = 'ASC';  break;
			case 3: $_d['product.ds.order']['rating'] = 'DESC'; break;
			case 4: $_d['product.ds.order']['orders'] = 'ASC';  break;
			case 5: $_d['product.ds.order']['cname'] = 'ASC';   break;
			case 6: $_d['product.ds.order']['views'] = 'ASC';   break;
			default: $_d['product.ds.order']['prod_name'] = 'ASC'; break;
		}
		$_d['products.callback.footer'] = array('ModView', 'ProductsFooter');
	}

	function ProductsFooter()
	{
		return GetPages($start, $amount, $total);
		return $ret;
	}

	function Get()
	{
		global $_d;

		if (GetVar('ca') == "setview")
		{
			$sort = Persist('view', GetVar('sort'));
			$amount = Persist('view', GetVar('amount'));
		}
		else
		{
			$sort = 0;
			$amount = 5;
		}

		$i = 0;
		$sltView = array
		(
			new SelOption("Name",      false, $sort == $i++),
			new SelOption("Price",     false, $sort == $i++),
			new SelOption("Date",      false, $sort == $i++),
			new SelOption("Rating",    false, $sort == $i++),
			new SelOption("Purchased", false, $sort == $i++),
			new SelOption("Company",   false, $sort == $i++)
		);

		$formView = new Form("formView");
		$formView->AddHidden("ca", "setview");
		$formView->AddInput(new FormInput('Sort', 'select', 'sort', $sltView));
		$formView->AddInput(new FormInput('Show', 'text', 'amount', $amount, 'size="10"'));
		$formView->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Refresh'));
		$out = $formView->Get('action="{{me}}" method="post"', 'class="form"');
		return GetBox('box_view', 'View', $out);
	}
}

?>
