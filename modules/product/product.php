<?php

function QueryProductList($match = null, $sort = null, $limit = null)
{
	global $_d;

	$columns = array(
		'prod_id',
		'prod_name',
		'prod_price',
		'prod_desc'
	);

	if (!empty($_d['product.ds.columns']))
		$q['columns'] = array_merge($_d['product.ds.columns'], $columns);


	if (!empty($_d['product.ds.order']))
		$q['sort'] = $_d['product.ds.order'];
	else $q['sort'] = array();
	if (!empty($sort))
		$q['sort'] += $sort;

	if (!empty($_d['product.ds.order']))
		$q['order'] = $_d['product.ds.order'];

	$q['match'] = $match;
	$q['limit'] = isset($limit) ? $limit : $_d['product.ds.count'];
	$q['joins'] = @$_d['product.ds.joins'];
	$q['group'] = 'prod_id';

	return $_d['product.ds']->Get($q);
}

function QueryProductDetails($_d, $ci)
{
	return QueryProductList(array('prod_id' => $ci));
}

function QueryProductCount($_d, $cat)
{
	return $_d['product.ds']->GetCount(array('cat' => number_format($cat)));
}

function GetProductImages($id, $limit = null)
{
	$arimages = array();

	if (file_exists("prodimages/{$id}/"))
	{
		foreach (glob("prodimages/{$id}/*") as $ix => $f)
		{
			preg_match('/(l|m|s)_([^.]+)\.([^.]+)$/', basename($f), $m);
			$arimages[$m[2]][$m[1]] = $f;
		}
	}

	if (is_numeric($limit)) return array_splice($arimages, 0, $limit);
	return $arimages;
}

class ModProduct extends Module
{
	function __construct($inst)
	{
		global $_d;

		if (!$inst) return;

		$ds = new DataSet($_d['db'], 'product');
		$ds->Shortcut = 'p';
		$ds->ErrorHandler = array(&$this, 'DataError');
		$this->sql = 'product.sql';

		$_d['product.ds'] = $ds;
		$_d['product.ds.match'] = array();
		$_d['product.ds.count'] = array();

		$_d['product.ds.admin.match'] = array();

		$_d['product.title'] = 'Products';
	}

	function Install()
	{
		$data = <<<EOF
CREATE TABLE IF NOT EXISTS `product` (
  `prod_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `prod_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `prod_name` varchar(100) NOT NULL,
  `prod_desc` text NOT NULL,
  `prod_price` float(6,2) NOT NULL DEFAULT '0.00',
  `prod_modified` datetime NOT NULL,
  `prod_available` datetime NOT NULL,
  PRIMARY KEY (`prod_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=FIXED;
EOF;

		global $_d;
		$_d['db']->Queries($data);
	}

	function Link()
	{
		global $_d;

		// Attach to Navigation.

		if (isset($_d['cl']) && $_d['cl']['usr_access'] >= 500)
		{
			$_d['page.links']['Admin']['Products']['Listing'] = '{{app_abs}}/product';
		}

		$_d['display.callbacks.options']['product'] = array(&$this,
			'display_options');
		$_d['display.callbacks.update']['product'] = array(&$this,
			'display_update');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if ($_d['q'][0] != 'product') return;

		$ca = @$_d['q'][1];

		if ($ca == 'image')
		{
			$ca2 = @$_d['q'][2];

			if ($ca2 == 'add')
			{
				$pid = $_d['q'][3];

				$file = GetVar("file");
				if ($file["size"] > 1024*1024*5) die("Image size too large, must be less than 5mb");

				$images = GetProductImages($pid);

				$num = count($images);
				if ($num >= 5) die("Too many images, you may only have 5 images per product.");

				$tempfile = $file["tmp_name"];

				UploadProductImage($pid, $file);

				$_d['q'][1] = 'edit';
				$_d['q'][2] = $pid;
			}
			if ($ca2 == 'rem')
			{
				$pid = $_d['q'][3];
				$img = $_d['q'][4];

				$images = GetProductImages($pid);

				if (isset($images[$img]['l'])) unlink($images[$img]['l']);
				if (isset($images[$img]['m'])) unlink($images[$img]['m']);
				if (isset($images[$img]['s'])) unlink($images[$img]['s']);

				$_d['q'][1] = 'edit';
				$_d['q'][2] = $pid;
			}
		}
		if ($ca == 'add')
		{
			$prod = GetVar('prod');

			if (!preg_match('/([\d]+)/', $prod['prod_price'], $m))
			{
				$_d['q'][1] = 'prepare';
				$this->errors['price'] = "You must specify a numeric price (eg. 52.32)";
			}
			else
			{
				$prod['prod_date'] = SqlUnquote('NOW()');
				$prod['prod_id'] = $_d['product.ds']->Add($prod);

				if (!empty($_d['product.callbacks.add']))
					RunCallbacks($_d['product.callbacks.add'], $_d, $prod,
						$prod['prod_id']);

				ModLog::Log("Added product {$prod['prod_name']} ({$prod['prod_id']})");
			}

			if (empty($error)) $_d['ca'] = 'view';
		}
		else if ($ca == 'update')
		{
			$pid = $_d['q'][2];

			$prod = GetVar('prod');

			RunCallbacks($_d['product.callbacks.update'], $_d, $prod,
				$pid);

			$_d['product.ds']->Update(array('prod_id' => $pid), $prod);
			$_d['q'][1] = 'view';
		}
		else if ($ca == 'delete')
		{
			$ci = $_d['q'][2];

			//Images
			if (file_exists("prodimages/{$ci}"))
			{
				$dir = opendir("prodimages/{$ci}");
				while (($f = readdir($dir)))
				{
					if ($f != ".." && $f != ".") unlink("prodimages/{$ci}/$f");
				}
				closedir($dir);
				rmdir("prodimages/{$ci}");
			}

			if (!empty($_d['product.callbacks.delete']))
				RunCallbacks($_d['product.callbacks.delete'], $_d);

			//Product
			$_d['product.ds']->Remove(array('prod_id' => $ci));

			$res['res'] = 1;
			die(json_encode($res));
		}
	}

	function Get()
	{
		global $_d;

		$cs = @$_d['q'][0];

		if (@$cs != 'product') return;

		$ca = @$_d['q'][1];

		if ($ca == 'view')
		{
			$ci = @$_d['q'][2];

			$ret = null;

			$pt = new ProductTemplate('admin');
			$pt->prods = QueryProductDetails($_d, $ci);

			if (!empty($_d['product.callbacks.details']))
			$ret .= RunCallbacks($_d['product.callbacks.details'], $_d,
				$pt->prods[0]);

			$ret .= $pt->ParseFile(l('product/details.xml'));

			return $ret;
		}
		else if ($ca == 'prepare' || $ca == 'edit')
		{
			if ($ca == 'edit')
			{
				$pid = $_d['q'][2];
				$query = array(
					'match' => array('prod_id' => $pid),
					'sort' => @$_d['product.ds.sort'],
					'limit' => @$_d['product.ds.limit'],
					'joins' => @$_d['product.ds.joins']
				);
				$data = $_d['product.ds']->GetOne($query);
				$title = $data['prod_name'].' Properties';
				$act = 'update/'.$pid;
			}
			else
			{
				$data = GetVar('prod');
				$title = 'Create Product';
				$act = 'add';
			}

			$frmAdd = new Form("formProduct");
			$frmAdd->AddInput(new FormInput('Name', 'text',
				'prod[prod_name]', $data['prod_name'], 'style="width: 100%"'));
			$frmAdd->AddInput(new FormInput('Description', 'area',
				'prod[prod_desc]', $data['prod_desc'], 'cols="30" rows="10"'));
			$frmAdd->AddInput(new FormInput('Price', 'text',
				'prod[prod_price]', $data['prod_price'], 'style="width: 100%"',
				isset($this->errors['price']) ? $this->errors['price'] : null));

			if ($ca == 'prepare')
				RunCallbacks($_d['product.callbacks.addfields'], $frmAdd);
			if ($ca == 'edit')
				RunCallbacks($_d['product.callbacks.editfields'], $frmAdd, $data);

			$frmAdd->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Save'));
			$ret = GetBox('box_create', $title,
				$frmAdd->Get('action="{{app_abs}}/product/'.$act.'" method="post" id="formProduct"',
				'width="100%"'));

			if ($ca == 'edit')
			{
				$images = GetProductImages($data['prod_id']);

				if (count($images) > 0)
				{
					$str = "<table><tr>";
					foreach ($images as $f => $sizes)
					{
						$str .= '<td align="center">';
						$str .= "<a href=\"{$sizes['l']}\" target=\"_blank\">
							<img src=\"{{app_abs}}/{$sizes['s']}\" border=\"0\"
							alt=\"Click to enlarge\" title=\"Click to enlarge\"
							/></a><br/>";
						$str .= "<a href=\"{{app_abs}}/{$cs}/image/rem/{$pid}/{$f}\""
							." onclick=\"return confirm('Are you sure?');\">".
							"Remove</a>\n";
						$str .= "</td>";
					}
					$str .= "</tr></table>\n";

					$ret .= GetBox('box_images', 'Product Images', $str);
				}

				if (count($images) < 5)
				{
					$frmImages = new Form('frmImages');
					$frmImages->AddHidden('ci', GetVar('ci'));
					$frmImages->AddInput(new FormInput('File', 'file', 'file',
						null, 'size="50"'));
					$frmImages->AddInput(new FormInput(null, 'submit', 'butSubmit',
						'Upload'));
					$ret .= GetBox('box_upload', 'Upload Product Image',
						$frmImages->Get('action="{{app_abs}}/product/image/add/'.
							$_d['q'][2].'" method="post"'));
				}
			}

			return $ret;
		}
		else if ($ca == 'edit')
		{
			$pid = $_d['q'][2];

			if (!ModUser::RequestAccess(500))
				if (!RequestCompany($prod['comp_id'])) return;

			$frmViewProd = new Form('formProdProps',
				array(null, 'width="100%"'));
			$frmViewProd->AddHidden('ca', 'update');
			$frmViewProd->AddHidden('ci', $pid);
			$frmViewProd->AddHidden('cs', 'product');

			$frmViewProd->AddInput(new FormInput('Name', 'text', 'name',
				$prod['prod_name'], 'style="width: 100%"'));
			$frmViewProd->AddInput(new FormInput('Description', 'area', 'desc',
				$prod['prod_desc'], array('cols' => '30', 'rows' => '10')));
			$frmViewProd->AddInput(new FormInput('Price', 'text', 'price',
				$prod['prod_price'], 'style="width: 100%"'));

			$frmViewProd->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Save'));

			$body = GetBox('box_props', 'Product Properties for '.
				$prod['prod_name'],
				$frmViewProd->Get('action="{{app_abs}}/product/update/'.$pid
					.'" method="post"', 'width="100%"'));

			return $body;
		}
		else if (@$_d['q'][1] == 'check')
		{
			$dsp = $_d['product.ds'];
		}

		$pt = new ProductTemplate('admin');
		$pt->prods = $this->GetAdminProducts();
		return $pt->ParseFile(l('product/listing.xml'));
	}

	/**
	 * Collect a series of products that can possibly be edited.
	 */
	function GetAdminProducts()
	{
		global $_d;

		$ds = $_d['product.ds'];

		return $ds->Get($_d['product.ds.admin.match'], null, array(0, 100),
			$_d['product.ds.joins']);
	}

	# Display

	function display_options()
	{
		global $_d;

		$t = new Template();
		$t->Behavior->Bleed = false;
		$t->Set('product_image_size_small',
			@$_d['settings']['product_image_size_small']);
		$t->Set('product_image_size_medium',
			@$_d['settings']['product_image_size_medium']);
		return $t->ParseFile(l('product/display_options.xml'));
	}

	function display_update()
	{
		global $_d;

		set_time_limit(0);

		$resize = false;

		if (@$_d['settings']['product_image_size_small'] !=
			GetVar('product_image_size_small') ||
			@$_d['settings']['product_image_size_medium'] !=
			GetVar('product_image_size_medium'))
			$resize = true;

		$_d['settings']['product_image_size_small'] =
			GetVar('product_image_size_small');
		$_d['settings']['product_image_size_medium'] =
			GetVar('product_image_size_medium');

		if ($resize)
			foreach (glob('prodimages/*/l_*') as $f)
				CreateProductThumbnails($f);
	}
}

Module::RegisterModule('ModProduct');

class ModProductList extends Module
{
	function __construct()
	{
		$this->Name = 'List';
	}

	function Get()
	{
		global $_d;

		$cs = @$_d['q'][0];
		if (!empty($cs) && $cs != 'category') return;

		$ret = null;

		$retProds = null;

		$pt = new ProductTemplate($this->Name);

		$pt->prods = QueryProductList($_d['product.ds.match']);

		$ret .= $pt->ParseFile(l('product/fromCatalog.xml'));

		if (!empty($_d['products.callbacks.footer']))
			$ret .= RunCallbacks($_d['products.callbacks.footer'], $_d);

		return $ret;
	}
}

Module::RegisterModule('ModProductList');

class ModProdsLatest extends Module
{
	function __construct()
	{
		global $_d;

		$_d['product.latest.hide'] = false;
		$_d['product.latest.match'] = array();
	}

	function Get()
	{
		global $_d;

		if ($_d['product.latest.hide']) return;

		$pt = new ProductTemplate('latest');

		$sort = array('prod_date' => 'DESC');

		$pt->prods = QueryProductList($_d['product.latest.match'], $sort,
			array(0, 10));

		if (empty($pt->prods)) return;
		return $pt->ParseFile(l('product/latest.xml'));
	}
}

Module::RegisterModule('ModProdsLatest');

class ProductTemplate
{
	function __construct($name)
	{
		$this->Name = $name;
		$this->admin = false;
	}

	function TagProduct($t, $g)
	{
		$tt = new Template();

		$tt->ReWrite('prodhead', array(&$this, 'TagHead'));
		$tt->ReWrite('prodneck', array(&$this, 'TagNeck'));
		$tt->ReWrite('prodprops', array(&$this, 'TagProps'));
		$tt->ReWrite('prodimage', array(&$this, 'TagImage'));
		$tt->ReWrite('prodknee', array(&$this, 'TagKnee'));
		$tt->ReWrite('prodfoot', array(&$this, 'TagFoot'));
		$tt->ReWrite('admin_product', array(&$this, 'TagAdminProduct'));

		$ret = '';

		if (!empty($this->prods))
		foreach ($this->prods as $p)
		{
			if (empty($p['prod_name'])) $p['prod_name'] = 'Blank Title';
			$this->prod = $p;
			$tt->Set($p);
			$ret .= $tt->GetString($g);
		}
		return $ret;
	}

	function TagHead($t, $guts)
	{
		global $_d;

		if (!empty($_d['product.callbacks.head']))
			return RunCallbacks($_d['product.callbacks.head'], $_d, $this->prod);
	}

	function TagNeck($t, $guts)
	{
		global $_d;

		if (!empty($_d['product.callbacks.neck']))
			return RunCallbacks($_d['product.callbacks.neck'], $_d, $prod);
	}

	function TagProps($t, $g, $a)
	{
		global $_d;

		$this->props = array();

		if (!empty($_d['product.callbacks.props']))
		{
			foreach ($_d['product.callbacks.props'] as $cb)
			{
				$rv = call_user_func($cb, $_d, $this->prod);
				if (is_array($rv)) $this->props = array_merge($this->props, $rv);
			}
		}

		if (!@$_d['settings']['hideanonprice'] || !empty($_d['cl']))
			$this->props['Price'] = "\$".$this->prod['prod_price'];
		if (!empty($this->prod['model']))
			$this->props['Model'] = $this->prod['model'];

		if (!empty($a['EXCLUDE']))
			foreach (explode(',', $a['EXCLUDE']) as $i)
				unset($this->props[$i]);

		$tt = new Template();
		if (!empty($this->props))
		{
			$tt->ReWrite('prodprop', array(&$this, 'TagProp'));
			return $tt->GetString($g);
		}
	}

	function TagProp($t, $guts)
	{
		global $_d;

		if (!@$_d['settings']['hideanonprice'] || !empty($_d['cl']))
			$this->props['Price'] = "\$".$this->prod['prod_price'];
		if (!empty($this->prod['model']))
			$this->props['Model'] = $this->prod['model'];

		$ret = null;

		$vp = new VarParser();
		foreach ($this->props as $f => $v)
			$ret .= $vp->ParseVars($guts, array('field' => $f, 'value' => $v));
		return $ret;
	}

	function TagImage($t, $g, $a)
	{
		$vp = new VarParser();
		$imgout = null;
		foreach (GetProductImages($this->prod['prod_id'], @$a['LIMIT'])
			as $f => $sizes)
		{
			$d = $this->prod;
			$d['large'] = $sizes['l'];
			$d['medium'] = $sizes['m'];
			$d['small'] = $sizes['s'];
			$imgout .= $vp->ParseVars($g, $d);
		}
		return $imgout;
	}

	function TagFoot($t)
	{
		global $_d;

		if (!empty($_d['product.callbacks.foot']))
			return RunCallbacks($_d['product.callbacks.foot'], $_d, $this->prod);
	}

	function TagAdminProduct($t, $g)
	{
		global $_d;

		if (!$this->admin) return;

		if (!empty($_d['product.callbacks.admin']))
			if (!RunCallbacks($_d['product.callbacks.admin'], $this->prod))
				return;

		return $g;
	}

	function TagAdminAnyProduct($t, $g)
	{
		if ($this->admin) return $g;
	}

	function ParseFile($temp)
	{
		global $_d;
		$this->props = array();

		$t = new Template();
		//Properties
		$t->Set('name', $this->Name);
		$t->ReWrite('product', array(&$this, 'TagProduct'));
		$t->ReWrite('admin_anyproduct', array(&$this, 'TagAdminAnyProduct'));
		return $t->ParseFile($temp);
	}

	function ParseString($str)
	{
		global $_d;
		$this->props = array();

		$t = new Template();
		//Properties
		$t->Set('name', $this->Name);
		$t->ReWrite('product', array(&$this, 'TagProduct'));
		$t->ReWrite('admin_anyproduct', array(&$this, 'TagAdminAnyProduct'));
		return $t->GetString($str);
	}
}

function UploadProductImage($id, $file)
{
	$dst = "prodimages/{$id}/l_{$file['name']}";
	mkrdir("prodimages/{$id}/");
	chmod("prodimages/{$id}/", 0755);
	copy($file['tmp_name'], $dst);
	CreateProductThumbnails($dst);
}

function CreateProductThumbnails($file)
{
	global $_d;

	preg_match('#(\d+)/l_(.*)\.([^.]+)#', $file, $m);

	if (class_exists('finfo'))
	{
		$fi = new finfo(FILEINFO_MIME_TYPE);
		$mimetype = $fi->file(realpath($file));
	}
	else
	{
		$mimetype = $m[3];
	}


	$id = $m[1];

	$thumb_medium = "prodimages/{$id}/m_{$m[2]}.{$m[3]}";
	$thumb_small = "prodimages/{$id}/s_{$m[2]}.{$m[3]}";

	if (!file_exists($file)) return "Error: [PID: $id] Image does not exist {$file}.";

	switch (strtolower($mimetype))
	{
		case 'image/jpeg':
		case 'jpg':
			$img = imagecreatefromjpeg($file);
			$dst_func = 'imagejpeg';
			break;
		case 'image/x-png':
		case 'image/png':
		case 'png':
			$img = imagecreatefrompng($file);
			$dst_func = 'imagepng';
			break;
		case 'image/gif':
		case 'gif':
			$img = imagecreatefromgif($file);
			$dst_func = 'imagegif';
			break;
		default:
			return "Error: [PID: $id] Unknown image type: $mimetype.";
			break;
	}

	$sm = $_d['settings']['product_image_size_medium'];
	$img_medium = ResizeImage($img, $sm, $sm);
	$dst_func($img_medium, $thumb_medium);
	$ss = $_d['settings']['product_image_size_small'];
	$img_small = ResizeImage($img, $ss, $ss);
	$dst_func($img_small, $thumb_small);
}

?>
