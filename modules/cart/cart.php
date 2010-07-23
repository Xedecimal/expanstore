<?php

class ModCart extends Module
{
	function __construct($installed)
	{
		global $_d;

		if (!$installed) return;

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

		# Attach to User. (Before PreLink)

		$_d['user.ds.query']['joins']['cart'] = new Join($_d['cart.ds'],
			'cart_user = usr_id', 'LEFT JOIN');
	}

	function Install()
	{
		$data = <<<EOF
CREATE TABLE IF NOT EXISTS `cart` (
  `cart_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cart_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `cart_user` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`cart_id`) USING BTREE,
  UNIQUE KEY `idxUser` (`cart_user`) USING BTREE,
  CONSTRAINT `fkUser` FOREIGN KEY (`cart_user`) REFERENCES `user` (`usr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `cart_item` (
  `ci_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ci_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ci_cart` bigint(20) unsigned NOT NULL DEFAULT '0',
  `ci_product` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`ci_id`) USING BTREE,
  KEY `idxCart` (`ci_cart`) USING BTREE,
  KEY `idxProduct` (`ci_product`) USING BTREE,
  CONSTRAINT `fkCart` FOREIGN KEY (`ci_cart`) REFERENCES `cart` (`cart_id`),
  CONSTRAINT `fkProduct` FOREIGN KEY (`ci_product`) REFERENCES `product` (`prod_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `pack` (
  `pkg_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pkg_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `pkg_card_num` varchar(255) NOT NULL,
  `pkg_card_exp` varchar(45) NOT NULL,
  `pkg_card_verify` varchar(45) NOT NULL,
  `pkg_user` bigint(20) unsigned NOT NULL DEFAULT '0',
  `pkg_state` int(10) unsigned NOT NULL DEFAULT '0',
  `pkg_ship_name` varchar(255) NOT NULL,
  `pkg_ship_address` varchar(255) NOT NULL,
  `pkg_ship_city` varchar(255) NOT NULL,
  `pkg_ship_state` varchar(255) NOT NULL,
  `pkg_ship_zip` varchar(255) NOT NULL,
  `pkg_card_name` varchar(255) NOT NULL,
  PRIMARY KEY (`pkg_id`) USING BTREE,
  KEY `idxUser` (`pkg_user`) USING BTREE,
  CONSTRAINT `fkPkgUser` FOREIGN KEY (`pkg_user`) REFERENCES `user` (`usr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `pack_prod` (
  `pp_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pp_name` varchar(255) NOT NULL,
  `pp_model` varchar(255) NOT NULL,
  `pp_price` float NOT NULL DEFAULT '0',
  `pp_package` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`pp_id`) USING BTREE,
  KEY `idxPackage` (`pp_package`) USING BTREE,
  CONSTRAINT `fk_pkg2prod` FOREIGN KEY (`pp_package`) REFERENCES `pack` (`pkg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `pack_prod_option` (
  `ppo_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ppo_pprod` bigint(20) unsigned NOT NULL DEFAULT '0',
  `ppo_attribute` varchar(255) NOT NULL,
  `ppo_value` varchar(255) NOT NULL,
  PRIMARY KEY (`ppo_id`) USING BTREE,
  KEY `idxPProduct` (`ppo_pprod`) USING BTREE,
  CONSTRAINT `fkPPO_PProd` FOREIGN KEY (`ppo_pprod`) REFERENCES `pack_prod` (`pp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;
EOF;

		global $_d;
		$_d['db']->Queries($data);
	}

	function Link()
	{
		global $_d;

		# Attach to Navigation

		if (!empty($_d['cl']))
			$_d['page.links']['Personal']['View Cart'] = '{{app_abs}}/cart';

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
					'cart_date' => SqlUnquote('NOW()'),
					'cart_user' => $_d['cl']['id']
				), true);
			}

			$ci = $_d['q'][2];

			$id = $_d['cartitem.ds']->Add(array(
				'ci_date' => SqlUnquote('NOW()'),
				'ci_cart' => $_d['cl']['cart_id'],
				'ci_product' => $ci
			), true);

			RunCallbacks($_d['cart.callbacks.add'], $_d['cl']['cart_id'], $id);

			die(json_encode(array('res' => 1)));
		}
		if ($ca == 'update')
		{
			RunCallbacks($_d['cart.callbacks.update'], $_d['cl']['cart_id'], $_d['q'][2]);
		}
		if ($ca == 'remove')
		{
			$ci = $_d['q'][2];
			RunCallbacks($_d['cart.callbacks.remove'], $ci);
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
		return $t->ParseFile(l('cart/index.xml'));
	}

	function TagCart()
	{
		global $_d;

		if ($_d['q'][0] != 'cart') return;
		if (empty($_d['cl'])) return;

		$cart = ModCart::QueryCart();

		$ca = GetVar('ca');

		// TODO: Cart does not handle checkout!
		if ($ca == 'checkout' || $ca == 'finish')
		{
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
				//Products

				$totalprice = 0;
				$totalitems = 0;

				$ciid = -1;

				$pt = new ProductTemplate('cart');
				$pt->prods = array();

				foreach ($cart as $citem)
				{
					$_d['cart.item'] = $citem;
					if (empty($citem['prod_id'])) continue;
					if ($ciid != $citem['ci_id'])
					{
						$totalitems++;
						if (!empty($_d['cart.callbacks.price']))
							$citem['prod_price'] =
								RunCallbacks($_d['cart.callbacks.price'],
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
				$body = $pt->ParseString($t->ParseFile(l('cart/product.xml')));
			}
			else
			{
				return "No items are currently in your cart.";
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

	function product_knee()
	{
		global $_d;

		if (empty($_d['cl'])) return;

		return '<a class="ancAddCart" href="{{prod_id}}">'
			.'<img src="'.p('cart/cart_add.png').'"'
			." title=\"Add To Cart\" alt=\"Add To Cart\" /></a>\n";
	}

	static function QueryCart()
	{
		global $_d;

		$q['columns'][] = 'ci_id';
		$q['match']['cart_user'] = $_d['cl']['id'];
		$q['joins']['cart_item'] = new Join($_d['cartitem.ds'], 'ci_product = prod_id');
		$q['joins']['cart'] = new Join($_d['cart.ds'], 'ci_cart = cart_id');

		return ModProduct::QueryProducts(array_merge_recursive($q, $_d['cart.query']));
	}
}

Module::Register('ModCart');

?>
