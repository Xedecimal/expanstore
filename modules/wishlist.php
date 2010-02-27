<?php

class ModWishlist extends Module
{
	function __construct($inst)
	{
		global $_d;

		if (!$inst) return;

		$dsWL = $_d['wishlist.ds'] = new DataSet($_d['db'], 'wishlist');
		$dsWL->ErrorHandler = array(&$this, 'error_db');
		$dsWL->Shortcut = 'wl';
	}

	function Install()
	{
		$data = <<<EOF
		CREATE TABLE IF NOT EXISTS `wishlist` (
  `wl_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wl_prod` bigint(20) unsigned DEFAULT NULL,
  `wl_user` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`wl_id`),
  KEY `fk_wl_prod` (`wl_prod`),
  KEY `fk_wl_user` (`wl_user`),
  CONSTRAINT `fk_wl_prod` FOREIGN KEY (`wl_prod`) REFERENCES `product` (`prod_id`),
  CONSTRAINT `fk_wl_user` FOREIGN KEY (`wl_user`) REFERENCES `user` (`usr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=FIXED;
EOF;
		global $_d;
		$_d['db']->Queries($data);
	}

	function Link()
	{
		global $_d;

		// Attach to Navigation.

		if (!empty($_d['cl']))
			$_d['page.links']['Personal']['Wishlist'] = '{{me}}?cs=wishlist';

		// Attach to Product.

		$_d['product.ds.joins']['wishlist'] =
			new Join($_d['wishlist.ds'], 'wl_prod = prod_id', 'LEFT JOIN');

		$_d['product.callbacks.knee'][] = array(&$this, 'ProductFooter');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if (@$_d['q'][1] == 'wishlist_add')
		{
			$dsWL->Add(array(
				'wl_prod' => $_d['ci'],
				'wl_user' => $_d['cl']['id'],
				'wl_value' => 1
			));
		}
	}

	function ProductFooter()
	{
		/*return '<a href="{{me}}?ca=wishlist_add&amp;ci='.$prod['prod_id'].'">
			<img src="'.$_d['tempath'].'wishlist/star.png"
			alt="Add to Wishlist" title="Add to Wishlist" /></a>';*/
	}

	function error_db($err)
	{
		global $db;

		//Table doesn't exist, we need to create it.
		if ($err == 1146)
		{
			$db->Query('CREATE TABLE ype_wishlist
				(wl_id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				wl_prod int,
				wl_user int,
				wl_value int)');
		}
	}

	function Get()
	{
		global $_d;

		$items = QueryProductList(array('wl_user' => $_d['cl']['usr_id']));

		$out = null;
		if (!empty($items))
		foreach ($items as $i)
		{
			$out .= GetProduct($_d, $i);
		}
		$out .= 'Incomplete.';

		return GetBox('box_wishlist', 'Wishlist', $out);
	}
}

Module::RegisterModule('ModWishlist');

?>
