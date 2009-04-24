<?php

RegisterModule('ModCart');

class ModCart extends Module
{
	function __construct()
	{
		global $_d;

		$dsCart = new DataSet($_d['db'], 'ype_cart');
		$dsCart->Shortcut = 'cart';
		$_d['cart.ds'] = $dsCart;

		$dsCartItems = new DataSet($_d['db'], 'ype_cart_item');
		$dsCartItems->Shortcut = 'ci';
		$dsCart->AddChild($dsCartItems, 'id', 'cart');
		$_d['cartitem.ds'] = $dsCartItems;

		$dsPackage = new DataSet($_d['db'], 'ype_package');
		$_d['package.ds'] = $dsPackage;

		$dsPackageProd = new DataSet($_d['db'], 'ype_package_product');
		$_d['packageprod.ds'] = $dsPackageProd;

		$dsPackage->AddChild(new Relation($dsPackageProd, 'id', 'package'));
		$dsPProdOption = new DataSet($_d['db'], 'ype_package_product_option');
		$_d['packageprodoption.ds'] = $dsPProdOption;
		$dsPackageProd->AddChild($dsPProdOption, 'id', 'package');
	}

	function Link()
	{
		global $_d;

		// Attach to Navigation

		if (!empty($_d['cl']))
			$_d['page.links']['Personal']['View Cart'] = '{{me}}?cs=cart';

		// Attach To Product

		$_d['product.ds.joins']['cartitem'] =
			new Join($_d['cartitem.ds'], 'ci_product = prod_id', 'LEFT JOIN');
		$_d['product.ds.joins']['cart'] =
			new Join($_d['cart.ds'], 'ci_cart = cart_id', 'LEFT JOIN');

		$_d['product.ds.columns'][] = 'ci_id';

		$_d['product.callbacks.knee']['cart'] = array(&$this, 'ProductKnee');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if (empty($_d['cl'])) return;

		// Handle Methods

		$ca = GetVar('ca');

		if ($ca == 'add')
		{
			$cart = $_d['cart.ds']->Get(array('cart_user' => $_d['cl']['usr_id']));

			$ci = GetVar('ci');

			$id = $_d['cartitem.ds']->Add(array(
				'ci_date' => Destring('NOW()'),
				'ci_cart' => $cart[0]['cart_id'],
				'ci_product' => $ci));

			RunCallbacks($_d['cart.callbacks.add'], $_d, $cart[0]['cart_id'],
				$id);

			die(json_encode(array('res' => 1)));
		}
		if ($ca == 'cart_update')
		{
			RunCallbacks($_d['cart.callbacks.update'], $_d);
		}
		if ($ca == 'cart_remove')
		{
			RunCallbacks($_d['cart.callbacks.remove'], $_d);
			$_d['cartitem.ds']->Remove(array('ci_id' => GetVar('ci')));
			die(json_encode(array('res' => 1)));
		}
		if ($ca == 'part')
		{
			die($this->Get());
		}
	}

	function Get()
	{
		$t = new Template();
		$tempath = $GLOBALS['_d']['tempath'];
		$t->Set('tempath', $tempath);
		$t->ReWrite('cart', array(&$this, 'GetCart'));
		return $t->ParseFile($tempath.'cart/index.xml');
	}

	function GetCart()
	{
		global $_d;

		$body = null;

		if (empty($_d['cl'])) return $body;

		$cart = QueryProductList(array('cart_user' => $_d['cl']['usr_id']));

		$ca = GetVar('ca');

		if ($ca == 'checkout' || $ca == 'finish')
		{
			//if ($ca == 'checkout') SaveCart(GetVar('atrs'));
			$paytype = GetVar('paytype');
			require_once("modules/payment/pay_{$paytype}.php");
			$objname = "Pay{$paytype}";
			$obj = new $objname();
			$body .= $obj->Checkout($_d, $cart);
		}
		else
		{
			if (!empty($cart[0]['prod_id']))
			{
				$_d['page.title'] = ' - View Cart';

				//Products

				$totalprice = 0;
				$totalitems = 0;

				$ciid = -1;

				$pt = new ProductTemplate('cart');
				$pt->prods = array();

				foreach ($cart as $citem)
				{
					if (empty($citem['prod_id'])) continue;
					if ($ciid != $citem['ci_id'])
					{
						$totalitems++;
						$ciid = $citem['ci_id'];
						$totalprice += $citem['prod_price'];
						$pt->prods[] = $citem;
					}
				}

				$t = new Template();
				$t->Set('totalitems', $totalitems);
				$t->Set('totalprice', $totalprice);
				$t->ReWrite('cartknee', array(&$this, 'CartKnee'));

				$body .= $pt->ParseString($t->ParseFile($_d['tempath'].
					'cart/product.xml'));
			}
		}

		if (!empty($body)) return GetBox('box_cart', 'Your Cart', $body);
	}

	function CartKnee()
	{
		global $_d;

		$knee = null;
		if (!empty($_d['cart.callbacks.knee']))
			$knee .= RunCallbacks($_d['cart.callbacks.knee']);
		return $knee;
	}

	function ProductKnee(&$_d, $prod)
	{
		return '<a id="{{name}}_ancAddCart.{{prod_id}}"
			class="ancAddCart" href="#">'.
			"<img src=\"{{tempath}}cart/cart_add.png\"".
			" title=\"Add To Cart\" alt=\"Add To Cart\" /></a>\n";
	}
}

?>
