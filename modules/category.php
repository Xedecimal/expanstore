<?

RegisterModule('ModCategory');

function QueryCat(&$_d, $id)
{
	$ds = $_d['category.ds'];
	return $ds->GetOne(array('cat_id' => $id), $_d['category.ds.joins']);
}

function QueryCats($_d, $parent)
{
	global $_d;

	return $_d['category.ds']->Get(array('cat_parent' => $parent), null, null,
		$_d['category.ds.joins']);
}

function QueryCatsAll(&$_d)
{
	return $_d['category.ds']->Get();
}

function GetBreadcrumb($_d, $cat)
{
	if ($cat == 0) return "<a href=\"{{me}}?cc=0\">Home</a>";
	$c = QueryCat($_d, $cat);
	$ret = "";
	while ($c != null && $c['cat_id'] != 0)
	{
		$ret = ' / <a href="{{me}}?cc='.$c['cat_id'].'">'.$c['cat_name'].'</a>'
			.$ret;
		$c = QueryCat($_d, $c['cat_parent']);
	}
	$ret = "<a href=\"{{me}}\">Home</a>" . $ret;
	return $ret;
}

class ModCategory extends Module
{
	function __construct()
	{
		global $_d;

		$dsCat = new DataSet($_d['db'], "ype_category");
		$dsCat->Shortcut = 'cat';
		$_d['category.ds'] = $dsCat;
		$_d['category.ds']->ErrorHandler = array($this, 'DataError');

		$dsCP = new DataSet($_d['db'], 'ype_cat_prod');
		$dsCP->Shortcut = 'cp';
		$_d['cat_prod.ds'] = $dsCP;
	}

	function Link()
	{
		global $_d;

		// Attach to Product

		$_d['product.ds.columns'][] = 'cat_id';
		$_d['product.ds.columns'][] = 'catprod_cat';

		$_d['product.callbacks.props']['category'] =
			array(&$this, 'ProductProps');
		$_d['product.callbacks.addfields']['category'] =
			array(&$this, 'ProductAddFields');
		$_d['product.callbacks.editfields']['category'] =
			array(&$this, 'ProductEditFields');
		$_d['product.callbacks.update']['category'] =
			array(&$this, 'ProductUpdate');

		$_d['product.ds.match']['cat_id'] = GetVar('cc', 0);

		$_d['product.ds.joins']['cat_prod'] =
			new Join($_d['cat_prod.ds'], 'catprod_prod = prod_id', 'LEFT JOIN');
		$_d['product.ds.joins']['category'] =
			new Join($_d['category.ds'], 'catprod_cat = cat_id', 'LEFT JOIN');

		$_d['product.latest.match']['catprod_cat'] = SqlNot(0);
		$_d['product.latest.hide'] = GetVar('cc', 0) != 0;
	}

	function Prepare($require = false)
	{
		parent::Prepare();
		global $_d;

		$cs = GetVar('cs');

		if ($cs == 'category')
		{
			$ca = GetVar('ca');

			if ($ca == 'delete')
			{
				$ci = GetVar('ci');

				$cats = QueryCats($_d, $ci);

				$dsProducts = $_d['product.ds'];
				$prods = $dsProducts->Get(array("cat" => $ci));
				if (!empty($cats)) $res = array('res' => 0, 'msg' => 'Category not empty.');
				else if (!empty($prods)) $res = array('res' => 0, 'msg' => 'Category not empty.');
				else
				{
					$_d['category.ds']->Remove(array('id' => $ci));
					#xslog($_d, "Removed category {$ci}");
					$res['res'] = 1;
					$res['msg'] = 'Successfully deleted.';
				}
				die(json_encode($res));
			}
		}

		$_d['category.current'] = QueryCat($_d, GetVar('cc'));
	}

	function DataError($errno)
	{
		global $_d;

		//No such table
		if ($errno == ER_NO_SUCH_TABLE)
		{
			$_d['db']->Query("CREATE TABLE `category` (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `parent` bigint(20) unsigned NOT NULL default '0',
  `spec` bigint(20) unsigned NOT NULL default '0',
  `name` varchar(100) NOT NULL default '',
  `desc` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `idxParent` (`parent`),
  KEY `idxSpec` (`spec`)
) ENGINE=MyISAM");
		}
	}

	function ProductProps($_d, $prod)
	{
		return array('Category' => GetBreadcrumb($_d, $prod['cat_id']));
	}

	function ProductAddFields($_d, $form)
	{
		$cats = QueryCatsAll($_d);

		$cc = GetVar('cc');

		$form->AddInput(new FormInput('Category', 'select', 'category',
			DataToSel($cats, 'name', 'id', $cc, 'Home'),
			'onchange="catChange();" style="width: 100%"'));
	}

	function ProductEditFields($_d, $prod, $form)
	{
		$cats = QueryCatsAll($_d);
		$sels = DataToSel($cats, 'cat_name', 'cat_id', $prod['catprod_cat'], 'Home');
		$form->AddInput(new FormInput('Category',
			'select', 'category', $sels, array('STYLE' => "width: 100%")));
	}

	function ProductUpdate($_d, $prod)
	{
		$_d['cat_prod.ds']->Add(array(
			'catprod_cat' => GetVar('formProdProps_category'),
			'catprod_prod' => GetVar('ci')
		), true);
	}

	function TagAdmin($t, $g)
	{
		global $_d;
		if ($_d['cl']['usr_access'] > 500) return $g;
	}

	function TagCategory($t, $g)
	{
		$tt = new Template();
		$tt->ReWrite('admin', array(&$this, 'TagAdmin'));
		$gen = array('icon' => 'images/folder.png');
		$ret = '';
		if (!empty($this->cats))
		foreach ($this->cats as $ix => $cat)
		{
			$this->cat = array_merge($cat, $gen);
			$tt->Set($this->cat);
			$ret .= $tt->GetString($g);
		}
		return $ret;
	}

	function Get()
	{
		global $_d;

		$breadcrumb = GetBreadcrumb($_d, number_format(GetVar('cc')));
		$_d['product.title'] = "Products in " . $breadcrumb;

		$this->data = $_d;
		$cc = GetVar('cc');
		$cl = $_d['cl'];

		$t = new Template();
		$t->Set('tempath', $_d['tempath']);
		$t->Set('cats', $this->cats = QueryCats($this->data, number_format($cc)));
		$t->ReWrite('notempty', 'TagNotEmpty');
		$t->ReWrite('admin', array(&$this, 'TagAdmin'));
		$t->ReWrite('category', array(&$this, 'TagCategory'));

		return $t->ParseFile($_d['tempath'].'category/fromCatalog.xml');

		if (!empty($_d['category.current']))
			$_d['page.title'] .= ' - '.$_d['category.current']['name'];
		else $_d['page.title'] .= ' - Home';

		$butparent = "<img src=\"images/dirup.gif\" alt=\"parent category\" border=\"0\" />";
		$butdir = "<img src=\"images/folder.png\" border=\"0\" alt=\"category\" />";

		//Display sub-categories.
		$cats = QueryCats($_d, number_format($cc));

		if (!empty($cats) || $cc != 0)
		{
			$this->out .= '<table width="100%"><tr><td>';

			//Up a level
			if ($cc != 0)
			{
				$curcat = QueryCat($_d, $_d['cc']);
				$this->out .= "<div class=\"float\"><a href=\"{{me}}?cc={$curcat['parent']}\">$butparent Parent Category</a></div>";
			}

			if (!empty($cats)) foreach ($cats as $cat)
			{
				$this->out .= '<div class="float"><span class="float-in">';
				$this->out .= "&nbsp;<a href=\"{{me}}?cc={$cat['id']}\">$butdir {$cat['name']}</a>";
				if (isset($_d['cl']) && $_d['cl']['usr_access'] > 500)
				{
					$this->out .= "<br /><a href=\""
						.htmlspecialchars("{{me}}?cs=category&ca=edit&ci={$cat['id']}&cc=$cc")
						."\" title=\"Edit\">Edit</a> | "
						."<a href=\""
						.htmlspecialchars("{{me}}?cs=category&ca=remove&ci={$cat['id']}&cc=$cc")
						."\" title=\"Delete\" onclick=\"return confirm('Are you sure?');\">Delete</a>";
				}
				$this->out .= " - {$cat['desc']}</span></div>";
			}
			$this->out .= '</td></tr></table>';
		}

		if (!empty($_d['category.callbacks.footer']))
			$this->out .= RunCallbacks($_d['category.callbacks.footer'], $_d);

		return GetBox('box_cats', 'Categories', $this->out);
	}
}

class ModCategoryAdmin extends Module
{
	function Prepare($required = false)
	{
		parent::Prepare();
		global $_d;

		$ca = GetVar('ca');

		if ($ca == "add")
		{
			$dsCats = $_d['category.ds'];
			$ci = $dsCats->Add(array(
				'date' => DeString('NOW()'),
				'parent' => GetVar('cc'),
				'spec' => GetVar('formAddCat_spec'),
				'name' => GetVar('formAddCat_name'),
				'desc' => GetVar('formAddCat_desc')));

			$_d['cs'] = 'catalog';
			$_d['cc'] = $ci;
		}

		else if ($ca == "update")
		{
			$_d['category.ds']->Update(array('id' => $_d['ci']), array(
				'parent' => GetVar('parent'),
				'spec' => GetVar('spec'),
				'name' => GetVar('name'),
				'desc' => GetVar('desc')
			));
		}
	}

	function Get()
	{
		global $_d;

		$ca = GetVar('ca');

		if ($ca == 'prepare')
		{
			$GLOBALS['page_section'] = 'Create Category';
			$formAddCat = new Form("formAddCat");
			$formAddCat->AddHidden("ca", "add");
			$formAddCat->AddHidden("cs", GetVar('cs'));
			$formAddCat->AddHidden("cc", GetVar('cc'));
			$formAddCat->AddInput(new FormInput('Name', 'text', 'name', null,
				'style="width: 100%"'));
			$formAddCat->AddInput(new FormInput('Desc:', 'area', 'desc', null,
				'style="width: 100%; height: 100px;"'));
			RunCallbacks($_d['category.callbacks.fields'], &$_d,
				$formAddCat);
			$formAddCat->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Add'));

			return GetBox('box_create',
				'Create Category',
				$formAddCat->Get('action="{{me}}" method="post"',
				'style="width: 100%"'));
		}

		if ($ca == 'edit')
		{
			$_d['page.title'] .= " - Category Properties";
			$dsCats = $_d['category.ds'];
			$cat = $dsCats->GetOne(array("id" => $_d['ci']));
			$cats = $dsCats->Get();
			$frmViewCat = new Form("formViewCat");
			$frmViewCat->AddHidden("cs", $_d['cs']);
			$frmViewCat->AddHidden("ca", "update");
			$frmViewCat->AddHidden("ci", $_d['ci']);
			$frmViewCat->AddInput(new FormInput('Parent', 'select', 'parent',
				DataToSel($cats, 'name', 'id', $cat['parent'], "Home")));
			$frmViewCat->AddInput(new FormInput('Name', 'text', 'name',
				$cat['name'], 'size="50"'));
			$frmViewCat->AddInput(new FormInput('Description', 'area', 'desc',
				$cat['desc'], 'style="width: 100%; height: 100px;"'));

			RunCallbacks($_d['category.callbacks.fields'], &$_d,
				$frmViewCat, $cat);

			$frmViewCat->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Save'));

			return GetBox("box_category", "Category Properties",
				$frmViewCat->Get());
		}

		if ($ca == 'update' || $ca == 'add' || $ca == 'remove')
		{
			#$_d['cs'] = 'catalog';
			#$_d['cc'] = $ca == 'update' ? GetVar('parent') : GetVar('cc');
			#$mod = RequireModule($_d, 'modules/content.php', 'ModContent');
			#$mod->Prepare($_d);
			#return $mod->Get($_d);
		}
	}
}

?>
