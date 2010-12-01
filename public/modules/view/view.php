<?php

Module::Register('ModView');

/**
 * A view management box to allow configurable views.
 */
class ModView extends Module
{
	public $Block = 'view';

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

		$sort = Server::GetVar('sort');
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

		if (Server::GetVar('ca') == "setview")
		{
			$sort = Persist('view', Server::GetVar('sort'));
			$amount = Persist('view', Server::GetVar('amount'));
		}
		else
		{
			$sort = 0;
			$amount = 5;
		}

		$i = 0;
		$sltView = array
		(
			new FormOption("Name",      false, $sort == $i++),
			new FormOption("Price",     false, $sort == $i++),
			new FormOption("Date",      false, $sort == $i++),
			new FormOption("Rating",    false, $sort == $i++),
			new FormOption("Purchased", false, $sort == $i++),
			new FormOption("Company",   false, $sort == $i++)
		);

		$t = new Template();
		return $t->ParseFile(Module::L('view/view.xml'));

		$formView = new Form("formView");
		$formView->AddHidden("ca", "setview");
		$formView->AddInput(new FormInput('Sort', 'select', 'sort', $sltView, 'id="view-sort"'));
		$formView->AddInput(new FormInput('Show', 'text', 'amount', $amount, 'size="10"'));
		$formView->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Refresh'));
		$out = $formView->Get('action="{{me}}" method="post"', 'class="form"');
		return Box::GetBox('box_view', 'View', $out);
	}
}

?>
