<?

RegisterModule('ModPath');

function QueryPath($_d, $product)
{
}

class ModPath extends Module
{
	function __construct()
	{
		global $_d;

		$dsPath = new DataSet($_d['db'], 'ype_path');
		$_d['path.ds'] = $dsPath;
	}

	function Link()
	{
		global $_d;

		$_d['product.callbacks.details'][] = array(&$this, 'Details');
		$_d['product.ds.joins']['path'] =
			new Join($_d['path.ds'], 'path_target = prod_id', 'LEFT JOIN');
		#$_d['product.ds']->AddChild($_d['path.ds'], 'id', 'target', 'id');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;


		//Do this when a product is viewed.
		//			$dsPath->Add(array(
//				'date' => DeString('NOW()'),
//				'user' => $cl['id'],
//				'type' => PATH_VIEWED,
//				'target' => $prod['id']
//			), true);
	}

	function Details(&$_d, $prod)
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

	#function Get() { }
}

?>
