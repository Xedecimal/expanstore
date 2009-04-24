<?php

RegisterModule('ModProduct');
RegisterModule('ModProductList');
RegisterModule('ModProdsLatest');

function QueryProductList($match = null, $sort = null)
{
	global $_d;

	$columns = array(
		'prod_id',
		'prod_name',
		'prod_price',
		'prod_desc'
	);

	if (!empty($_d['product.ds.columns']))
		$columns = array_merge($_d['product.ds.columns'], $columns);

	if (!empty($_d['product.ds.order']) && !empty($sort))
		$sort = array_merge($sort, $_d['product.ds.order']);

	else if (!empty($_d['product.ds.order']))
		$order = $_d['product.ds.order'];

	return $_d['product.ds']->Get($match, $sort,
		$_d['product.ds.count'], $_d['product.ds.joins'], $columns,
		'prod_id');
}

function QueryProductDetails($_d, $ci)
{
	return QueryProductList(array('prod_id' => $ci));
}

function QueryProductCount($_d, $cat)
{
	return $_d['product.ds']->GetCount(array('cat' => number_format($cat)));
}

function GetProductImages($id)
{
	$arimages = array();
	$x = 0;
	$set = 0;

	if (file_exists("prodimages/{$id}/"))
	{
		$dir = opendir("prodimages/{$id}/");
		while (($file = readdir($dir)))
		{
			if ($file == "." || $file == ".." || strpos($file, ".") == null) continue;
			if ($x % 3 == 0) $arimages[$set][0] = "prodimages/{$id}/$file";
			if ($x % 3 == 1) $arimages[$set][1] = "prodimages/{$id}/$file";
			if ($x % 3 == 2)
			{
				$arimages[$set][2] = "prodimages/{$id}/$file";
				$set++;
			}
			$x++;
		}
	}
	return $arimages;
}

class ModProduct extends Module
{
	function __construct()
	{
		global $_d;

		$ds = new DataSet($_d['db'], "ype_product");
		$ds->Shortcut = 'p';

		$_d['product.ds'] = $ds;
		$_d['product.ds.match'] = array();
		$_d['product.ds.count'] = array();

		$_d['product.ds.admin.match'] = array();

		$_d['product.title'] = 'Products';
	}

	function Link()
	{
		global $_d;

		// Attach to Navigation.

		if (isset($_d['cl']) && $_d['cl']['usr_access'] >= 500)
		{
			$_d['page.links']['Admin']['Products'] = '{{me}}?cs=product';
		}
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if ($_d['cs'] != 'product') return;

		$ca = GetVar('ca');

		if ($ca == 'add')
		{
			if (!preg_match("/([\d]+\.*[\d]{0,2})*/", GetVar('price', 0), $m))
			{
				$ca = 'prepare';
				$error['price'] = "You must specify a numeric price (eg. 52.32)";
			}
			else
			{
				$prod = array(
					'date' => DeString('NOW()'),
					'prod_name' => GetVar('formProduct_name'),
					'description' => GetVar('formProduct_desc'),
					'model' => GetVar('formProduct_model'),
					'price' => number_format($m[1], 2),
					'cat' => GetVar('formProduct_category'),
					'atrgroup' => GetVar('formProduct_atrg'),
					'company' => $_d['cl']['company']);

				$prod['prod_id'] = $_d['product.ds']->Add($prod);

				RunCallbacks($_d['product.callbacks.add'], $_d, $prod, $prod['prod_id']);

				#xslog($_d, "Added product {$prod['prod_name']} ({$prod['model']})");
			}

			if (empty($error)) $_d['ca'] = 'view';
		}
		else if ($ca == 'add_image')
		{
			$file = GetVar("frmImages_file");
			if ($file["size"] > 1048576) die("Image size too large, must be less than 1mb");

			#$prod = QueryProduct($_d, $_d['ci']);
			$images = GetProductImages($_d['ci']);

			$num = count($images);
			if ($num >= 5) die("Too many images, you may only have 5 images per product.");

			$tempfile = $file["tmp_name"];

			CreateProductThumbnails($_d['ci'], $num, $file);

			$_d['ca'] = 'edit';
		}
		else if ($ca == 'remove_image')
		{
			$prod = QueryProduct($_d, $_d['ci']);
			$image = GetVar("image");

			$images = GetProductImages($prod['prod_id']);
			if (isset($images[$image][0])) unlink($images[$image][0]);
			if (isset($images[$image][1])) unlink($images[$image][1]);
			if (isset($images[$image][2])) unlink($images[$image][2]);

			for ($x = $image + 1; $x < count($images); $x++)
			{
				rename($images[$x][0], $images[$x-1][0]);
				rename($images[$x][1], $images[$x-1][1]);
				rename($images[$x][2], $images[$x-1][2]);
			}

			if ($x == 1) DelTree("prodimages/{$prod['prod_id']}/");
			$_d['ca'] = 'edit';
		}
		if ($ca == 'update')
		{
			$ci = GetVar('ci');

			$cols = array(
				'prod_name'  => GetVar('formProdProps_name'),
				'prod_desc'  => GetVar('formProdProps_desc'),
				'prod_price' => GetVar('formProdProps_price'),
			);

			RunCallbacks($_d['product.callbacks.update'], $_d, $cols,
				$ci);

			$_d['product.ds']->Update(array('prod_id' => $ci), $cols);

			#$_d['ca'] = 'view';
		}
		else if ($ca == 'delete')
		{
			$ci = GetVar('ci');

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

		if ($_d['cs'] != 'product') return;

		$ca = GetVar('ca');

		if ($ca == 'view')
		{
			$ci = GetVar('ci');

			$_d["page_title"] .= " - View Product";

			$ret = '';

			$pt = new ProductTemplate('admin');
			$pt->prods = QueryProductDetails($_d, $ci);

			if (!empty($_d['product.callbacks.details']))
			$ret .= RunCallbacks($_d['product.callbacks.details'], $_d,
				$pt->prods[0]);

			$ret .= $pt->ParseFile($_d['tempath'].'product/details.xml');

			return $ret;
		}

		if ($ca == 'prepare')
		{
			$_d['page_title'] .= " - Add Product";

			$cc = GetVar('cc');

			$frmAdd = new Form("formProduct");
			$frmAdd->AddHidden("cs", "product");
			$frmAdd->AddHidden("ca", "add");
			$frmAdd->AddHidden("cc", $cc);
			$frmAdd->AddInput(new FormInput('Name', 'text', 'name',
				GetVar('name'), 'style="width: 100%"'));
			$frmAdd->AddInput(new FormInput('Description', 'area', 'desc',
				GetVar('desc'), 'cols="30" rows="10"'));
			$frmAdd->AddInput(new FormInput('Model', 'text', 'model',
				GetVar('model'), 'style="width: 100%"'));
			$frmAdd->AddInput(new FormInput('Price', 'text', 'price',
				GetVar('price'), 'style="width: 100%"',
				isset($error['price']) ? $error['price'] : null));

			RunCallbacks($_d['product.callbacks.addfields'], $_d, $frmAdd);

			$frmAdd->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Create Product'));
			$ret = GetBox('box_create', 'Create Product',
				$frmAdd->Get('action="{{me}}" method="post" id="formProduct"',
				'width="100%"'));
			return $ret;
		}

		if ($ca == 'edit')
		{
			$_d['page_title'] .= ' - View Product';

			$ret = GetVar("ret");

			$prod = $_d['product.ds']->Get(array('prod_id' => GetVar('ci')),
				isset($_d['product.ds.sort'])?$_d['product_ds_sort']:null,
				isset($_d['product.ds.limit'])?$_d['product_ds_limit']:null,
				$_d['product.ds.joins']);

			$prod = $prod[0];

			$frmViewProd = new Form('formProdProps',
				array(null, 'width="100%"'));
			$frmViewProd->AddHidden('cs', GetVar('cs'));
			$frmViewProd->AddHidden('ca', 'update');
			$frmViewProd->AddHidden('ci', GetVar('ci'));
			if ($ret) $frmViewProd->AddHidden('ret', $ret);
			$frmViewProd->AddInput(new FormInput('Name', 'text', 'name',
				$prod['prod_name'], 'style="width: 100%"'));
			$frmViewProd->AddInput(new FormInput('Description', 'area', 'desc',
				$prod['prod_desc'], array('cols' => '30', 'rows' => '10')));
			$frmViewProd->AddInput(new FormInput('Price', 'text', 'price',
				$prod['prod_price'], 'style="width: 100%"'));

			RunCallbacks($_d['product.callbacks.editfields'], $_d, $prod,
				$frmViewProd);

			$frmViewProd->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Save'));

			$body = GetBox('box_props', 'Product Properties for '.
				$prod['prod_name'],
				$frmViewProd->Get('action="{{me}}" method="post"',
				'width="100%"'));

			$images = GetProductImages($prod['prod_id']);
			if (count($images) > 0)
			{
				$str = "<table><tr>";
				for ($x = 0; $x < count($images); $x++)
				{
					$set = $images[$x];
					$str .= '<td align="center">';
					$str .= "<a href=\"$set[0]\" target=\"_blank\">
						<img src=\"$set[1]\" border=\"0\"
						alt=\"Click to enlarge\" /></a><br/>";
					$str .= "<a href=\"{{me}}?cs={$_d['cs']}&amp;".
						"ca=remove_image&amp;ci={$prod['prod_id']}&amp;".
						"image=$x&amp;cc={$_d['cc']}\"".
						" onclick=\"return confirm('Are you sure?');\">".
						"Remove</a>\n";
					$str .= "</td>";
				}
				$str .= "</tr></table>\n";

				$body .= GetBox('box_images', 'Product Images', $str);
			}

			if (count($images) < 5)
			{
				$frmImages = new Form('frmImages');
				$frmImages->AddHidden('cs', GetVar('cs'));
				$frmImages->AddHidden('ca', 'add_image');
				$frmImages->AddHidden('ci', GetVar('ci'));
				$frmImages->AddInput(new FormInput('File', 'file', 'file',
					null, 'size="50"'));
				$frmImages->AddInput(new FormInput(null, 'submit', 'butSubmit',
					'Upload'));
				$body .= GetBox('box_upload', 'Upload Product Image',
					$frmImages->Get('action="{{me}}" method="post"'));
			}

			return $body;
		}

		else if ($ca == 'check')
		{
			$dsp = $_d['product.ds'];
		}

		$pt = new ProductTemplate('admin');

		$pt->prods = $this->GetAdminProducts();

		return $pt->ParseFile($_d['tempath'].'product/listing.xml');

		$cl = $_d['cl'];
		$GLOBALS['page.title'] = ' - View Products';

		$sort = GetVar('sort');
		$order = GetVar('order');

		$tblProds = new SortTable('tblProds', array('prod_name' => 'Name',
			'model' => 'Model', 'cost' => 'Price'));

		$dsProducts = $_d['product.ds'];

		$filter = null;
		if ($cl['usr_access'] < 500) $filter = array(
			'company' => $cl['company']);

		if ($sort) $prods = $dsProducts->Get($filter);
		else $prods = $dsProducts->Get($filter);

		if (is_array($prods))
		{
			foreach ($prods as $prod)
			{
				$tblProds->AddRow(array(GetLinkProductEdit($prod),
					$prod['model'], $prod['price'],
					/*GetLinkProductDelete($prod)*/));
			}
		}

		$tblProds->AddRow(array('Total Products: '.
			$dsProducts->GetCount(array('company' => $cl['company']))));
		$ret = GetBox("box_products", "Products", $tblProds->Get());

		if (isset($cl->access))
		{
			$ret .= "<center>\n";
			if (isset($cl['company']) && $cl['company'] != 0)
			{
				$ret .= "<a href=\""
				.htmlspecialchars("{{me}}?cs=product&ca=prepare&cc={{cc}}")
				."\">Add Product</a>\n";
			}
			$ret .= "</center>\n";
		}

		return $ret;
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
}

class ModProductList extends Module
{
	function __construct()
	{
		$this->Name = 'List';
	}

	function Get()
	{
		global $_d;

		$cs = GetVar('cs');
		if (!empty($cs)) return;

		$ret = null;

		$retProds = null;

		$pt = new ProductTemplate($this->Name);

		$pt->prods = QueryProductList($_d['product.ds.match']);

		$retProds .= $pt->ParseFile($_d['tempath'].'product/fromCatalog.xml');

		$ret .= GetBox("box_prods", $_d['product.title'], $retProds);

		if (!empty($_d['products.callbacks.footer']))
			$ret .= RunCallbacks($_d['products.callbacks.footer'], $_d);

		return $ret;
	}
}

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

		$cs = GetVar('cs');
		if (!empty($cs)) return;

		if ($_d['product.latest.hide']) return;

		$pt = new ProductTemplate('latest');

		$sort = array('prod_date' => 'DESC');

		$pt->prods = QueryProductList($_d['product.latest.match'], $sort);

		if (empty($pt->prods)) return;
		return GetBox("box_latest_prods", "Latest Products",
			$pt->ParseFile($_d['tempath'].'product/fromCatalog.xml'));
	}
}

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
		$tt->ReWrite('prodprop', array(&$this, 'TagProp'));
		$tt->ReWrite('prodimages', array(&$this, 'TagImages'));
		$tt->ReWrite('proddesc', array(&$this, 'TagDesc'));
		$tt->ReWrite('prodknee', array(&$this, 'TagKnee'));
		$tt->ReWrite('prodfoot', array(&$this, 'TagFoot'));
		$tt->ReWrite('admin_product', array(&$this, 'TagAdminProduct'));

		$ret = '';

		if (!empty($this->prods))
		foreach ($this->prods as $p)
		{
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

	function TagProps($t, $guts)
	{
		if (!empty($this->props)) return $guts;
	}

	function TagProp($t, $guts)
	{
		global $_d;

		$this->props['Price'] = "\$".$this->prod['prod_price'];
		if (!empty($this->prod['model'])) $this->props['Model'] = $this->prod['model'];

		$ret = '';

		if (!empty($_d['product.callbacks.props']))
		{
			foreach ($_d['product.callbacks.props'] as $cb)
			{
				$rv = call_user_func($cb, $_d, $this->prod);
				if (is_array($rv)) $this->props = array_merge($this->props, $rv);
				else $ret .= $rv;
			}
		}

		$vp = new VarParser();
		foreach ($this->props as $f => $v)
			$ret .= $vp->ParseVars($guts, array('field' => $f, 'value' => $v));
		return $ret;
	}

	function TagImages($t, $guts)
	{
		$arimages = GetProductImages($this->prod['prod_id']);

		$imgout = null;
		if (!empty($arimages))
		{
			foreach ($arimages as $image)
			{
				$imgout .= "<a href=\"{$image[0]}\" rel=\"shadowbox[{$this->prod['prod_id']}]\">\n";
				$imgout .= "<img src=\"{$image[1]}\" border=\"0\" alt=\"Click to enlarge\" /></a>\n";
			}
		}
		return $imgout;
	}

	function TagDesc($t, $guts)
	{
		global $_d;

		if (isset($this->prod['desc']))
		{
			if (GetVar('cs') != 'product')
				return htmlspecialchars(ChompString($this->prod['desc'], 255));
			else return htmlspecialchars($this->prod['desc']);
		}
	}

	function TagKnee($t, $guts)
	{
		global $_d;

		$knee = null;

		//if ($this->admin) { }

		if (!empty($_d['product.callbacks.knee']))
			$knee .= RunCallbacks($_d['product.callbacks.knee'], $_d, $this->prod);

		return $knee;
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

		if (!empty($_d['product.callbacks.admin']))
			if (!RunCallbacks($_d['product.callbacks.admin'], $this->prod))
				return;

		$this->admin = true;
		return $g;
	}

	function TagAdminAnyProduct($t, $g)
	{
		if ($this->admin) return $g;
		return $g;
	}

	function ParseFile($temp)
	{
		global $_d;
		$this->props = array();

		$t = new Template();
		//Properties
		$t->Set('name', $this->Name);
		$t->Set('tempath', $_d['tempath']);
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

function CreateProductThumbnails($id, $num, $src)
{
	$destfile1 = "prodimages/{$id}/{$num}l.png";
	$destfile2 = "prodimages/{$id}/{$num}m.png";
	$destfile3 = "prodimages/{$id}/{$num}s.png";

	$pal = false;

	if (is_array($src['type']))
	{
		$filetype = $src["type"];
		$filename = $src['tmp_name'];
	}
	else
	{
		$filetype = fileext($src['name']);
		$filename = $src['tmp_name'];
	}

	if (!file_exists($filename))
	{
		return "Error: [PID: $id] Image does not exist {$filename}.";
	}

	switch (strtolower($filetype))
	{
		case 'image/jpeg':
		case 'jpg':
			$img = imagecreatefromjpeg($filename);
			break;
		case 'image/x-png':
		case 'image/png':
		case 'png':
			$img = imagecreatefrompng($filename);
			if (!imageistruecolor($img)) $pal = true;
			break;
		case 'image/gif':
		case 'gif':
			$img = imagecreatefromgif($filename);
			$pal = true;
			break;
		default:
			return "Error: [PID: $id] Unknown image type: $filetype.";
			break;
	}

	mkrdir("prodimages/{$id}/");
	chmod("prodimages/{$id}/", 0755);
	imagepng($img, $destfile1);
	$img2 = ResizeImage($img, 100, 100, $pal);
	imagepng($img2, $destfile2);
	$img3 = ResizeImage($img, 64, 64, $pal);
	imagepng($img3, $destfile3);
}

?>
