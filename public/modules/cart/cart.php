<?php

class ModCart extends Module
{
	public $Block = 'cart';

	function __construct($installed)
	{
		if (!$installed) return;

		global $_d;

		$this->CheckActive('cart');

		$dsCart = new DataSet($_d['db'], 'cart');
		$dsCart->Shortcut = 'cart';
		$_d['cart.ds'] = $dsCart;

		$dsCartItems = new DataSet($_d['db'], 'cart_item');
		$dsCartItems->Shortcut = 'ci';
		$_d['cartitem.ds'] = $dsCartItems;

		$_d['cart.ds.query']['joins']['cartitem'] = new Join(
			$_d['cartitem.ds'], 'ci_cart = cart_id', 'LEFT JOIN'
		);

		$_d['cart.query'] = array();

		# User

		$_d['user.ds.query']['joins']['cart'] = new Join($_d['cart.ds'],
			'cart_user = usr_id', 'LEFT JOIN');
	}

	function Link()
	{
		global $_d;

		# Attach to Navigation

		if (ModUser::RequireAccess(0))
			$_d['nav.links']['Personal/View Cart'] = '{{app_abs}}/cart';

		# Attach to Product

		$_d['product.callbacks.knee']['cart'] = array(&$this, 'product_knee');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if (empty($_d['cl'])) return;

		// Handle Methods

		if ($_d['q'][0] != 'cart') return;
		$ca = @$_d['q'][1];

		if ($ca == 'add')
		{
			// This user needs a new cart.

			if (empty($_d['cl']['cart_id']))
			{
				$_d['cl']['cart_id'] = $_d['cart.ds']->Add(array(
					'cart_date' => Database::SqlUnquote('NOW()'),
					'cart_user' => $_d['cl']['usr_id']
				), true);
			}

			$ci = $_d['q'][2];

			$id = $_d['cartitem.ds']->Add(array(
				'ci_date' => Database::SqlUnquote('NOW()'),
				'ci_cart' => $_d['cl']['cart_id'],
				'ci_product' => $ci
			), true);

			U::RunCallbacks($_d['cart.callbacks.add'], $_d['cl']['cart_id'], $id);

			die(json_encode(array('res' => 1)));
		}
		if ($ca == 'update')
		{
			U::RunCallbacks($_d['cart.callbacks.update'], $_d['cl']['cart_id'], $_d['q'][2]);
			die();
		}
		if ($ca == 'remove')
		{
			$ci = $_d['q'][2];
			U::RunCallbacks($_d['cart.callbacks.remove'], $ci);
			$_d['cartitem.ds']->Remove(array('ci_id' => $ci));
			die(json_encode(array('res' => 1)));
		}
		if ($ca == 'part')
		{
			die($this->Get());
		}
	}

	function Get()
	{
		global $_d;

		$t = new Template();
		$t->ReWrite('cart', array(&$this, 'TagCart'));
		return $t->ParseFile(Module::L('cart/index.xml'));
	}

	function TagCart()
	{
		global $_d;

		if ($_d['q'][0] != 'cart') return;
		if (empty($_d['cl'])) return;

		$cart = ModCart::QueryCart();

		$ca = Server::GetVar('ca');

		// TODO: Cart does not handle checkout!
		if ($ca == 'checkout' || $ca == 'finish')
		{
			$paytype = Server::GetVar('paytype');
			require_once("modules/payment/pay_{$paytype}.php");
			$objname = "Pay{$paytype}";
			$obj = new $objname();
			$body .= $obj->Checkout($_d, $cart);
		}
		else
		{
			if (!empty($cart[0]['prod_id']))
			{
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
						if (!empty($_d['cart.callbacks.price']))
							$citem['prod_price'] =
								U::RunCallbacks($_d['cart.callbacks.price'],
									$citem);
						$totalprice += $citem['prod_price'];
						$pt->prods[] = $citem;

						$ciid = $citem['ci_id'];
					}
				}

				$t = new Template();
				$t->Set('totalitems', $totalitems);
				$t->Set('totalprice', $totalprice);
				$t->ReWrite('cartknee', array(&$this, 'CartKnee'));
				$body = $pt->ParseString($t->ParseFile(Module::L('cart/product.xml')));
			}
			else
			{
				return "No items are currently in your cart.";
			}
		}

		if (!empty($body)) return Box::GetBox('box_cart', 'Your Cart', $body);
	}

	function CartKnee()
	{
		global $_d;

		$knee = null;
		if (!empty($_d['cart.callbacks.knee']))
			$knee .= U::RunCallbacks($_d['cart.callbacks.knee']);
		return $knee;
	}

	function product_knee()
	{
		global $_d;

		if (empty($_d['cl'])) return;

		$img = p('cart/cart_add.png');
		return <<<EOF
<a class="ancAddCart" rel="{{prod_id}}" href="#divCart">
<img src="$img" title="Add To Cart" alt="Add To Cart" /></a>
EOF;
	}

	static function QueryCart()
	{
		global $_d;

		$q['columns'][] = 'ci_id';
		$q['match']['cart_user'] = $_d['cl'][$_d['user.auth.id']];
		$q['joins']['cart_item'] = new Join($_d['cartitem.ds'], 'ci_product = prod_id');
		$q['joins']['cart'] = new Join($_d['cart.ds'], 'ci_cart = cart_id');

		return ModProduct::QueryProducts(array_merge_recursive($q, $_d['cart.query']));
	}
}

Module::Register('ModCart', array('ModUser'));

?>
