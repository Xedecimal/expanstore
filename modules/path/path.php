<?php

define('PATH_VIEWED', 0);
define('PATH_PURCHASED', 1);

Module::Register('ModPath');

function QueryPath($_d, $product)
{
}

class ModPath extends Module
{
	function __construct($inst)
	{
		global $_d;

		if (!$inst) return;

		$dsPath = new DataSet($_d['db'], 'path');
		$_d['path.ds'] = $dsPath;
	}

	function Install()
	{
		$data = <<<EOF
CREATE TABLE IF NOT EXISTS `path` (
  `path_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `path_date` datetime NOT NULL,
  `path_user` bigint(20) unsigned NOT NULL DEFAULT '0',
  `path_type` int(10) unsigned NOT NULL DEFAULT '0',
  `path_target` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`path_id`) USING BTREE,
  UNIQUE KEY `idxUnique` (`path_user`,`path_target`) USING BTREE,
  CONSTRAINT `fk_path_user` FOREIGN KEY (`path_user`) REFERENCES `user` (`usr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOF;
		global $_d;
		$_d['db']->Queries($data);
	}

	function Link()
	{
		global $_d;

		$_d['product.callbacks.details'][] = array(&$this, 'cb_product_details');
		$_d['product.ds.query']['joins']['path'] =
			new Join($_d['path.ds'], 'path_target = prod_id', 'LEFT JOIN');
		#$_d['product.ds']->AddChild($_d['path.ds'], 'id', 'target', 'id');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;


		//Do this when a product is viewed.
		//			$dsPath->Add(array(
//				'date' => SqlUnquote('NOW()'),
//				'user' => $cl['id'],
//				'type' => PATH_VIEWED,
//				'target' => $prod['id']
//			), true);
	}

	function cb_product_details($_d, $prod)
	{
		$paths = QueryPath($_d, $prod['prod_id']);

		$ret = null;
		if (!empty($paths))
		{
			$boxpath = null;
			foreach ($paths as $item)
			{
				$boxpath .= GetLinkProductView($item).'<br/>';
			}
			$ret .= GetBox('box_path',
				'Customers that viewed this item, also viewed',
				$boxpath)."<br />";
		}
		return $ret;
	}
}

?>
