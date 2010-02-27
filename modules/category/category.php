<?php

Module::RegisterModule('ModCategory');
Module::RegisterModule('ModCategoryAdmin');

function QueryCat(&$_d, $id)
{
	global $_d;

	$q = array(
		'match' => array('cat_id' => $id),
		'joins' => $_d['category.ds.joins']
	);
	return $_d['category.ds']->GetOne($q);
}

function QueryCats($_d, $parent, $include_hidden = true)
{
	global $_d;

	$m = array('cat_parent' => $parent);
	if (!$include_hidden) $m['cat_hidden'] = 0;

	return $_d['category.ds']->Get($m, 'cat_name', null,
		@$_d['category.ds.joins']);
}

function QueryCatsAll(&$_d)
{
	return $_d['category.ds']->Get();
}

function GetBreadcrumb($_d, $cat)
{
	if ($cat == 0) return "<a href=\"{{app_abs}}?cc=0\">Home</a>";
	$c = QueryCat($_d, $cat);
	$ret = "";
	while ($c != null && $c['cat_id'] != 0)
	{
		$ret = ' / <a href="{{app_abs}}?cc='.$c['cat_id'].'">'.$c['cat_name'].'</a>'
			.$ret;
		$c = QueryCat($_d, $c['cat_parent']);
	}
	$ret = "<a href=\"{{app_abs}}\">Home</a>" . $ret;
	return $ret;
}

class ModCategory extends Module
{
	function __construct($inst)
	{
		global $_d;

		if (!$inst) return;

		$dsCat = new DataSet($_d['db'], "category");
		$_d['category.ds'] = $dsCat;

		$dsCP = new DataSet($_d['db'], 'cat_prod');
		$_d['cat_prod.ds'] = $dsCP;
	}

	function Install()
	{
		$data = <<<EOF
CREATE TABLE IF NOT EXISTS `category` (
  `cat_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cat_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `cat_parent` bigint(20) unsigned NOT NULL DEFAULT '0',
  `cat_name` varchar(100) NOT NULL,
  `cat_desc` mediumtext NOT NULL,
  PRIMARY KEY (`cat_id`) USING BTREE,
  KEY `idxParent` (`cat_parent`) USING BTREE,
  CONSTRAINT `fk_cat_cat` FOREIGN KEY (`cat_parent`) REFERENCES `category` (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `cat_prod` (
  `catprod_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `catprod_prod` bigint(20) unsigned NOT NULL,
  `catprod_cat` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`catprod_id`) USING BTREE,
  UNIQUE KEY `idxUnique` (`catprod_prod`,`catprod_cat`),
  KEY `fk_catprod_cat` (`catprod_cat`) USING BTREE,
  KEY `fk_catprod_prod` (`catprod_prod`) USING BTREE,
  CONSTRAINT `fk_catprod_cat` FOREIGN KEY (`catprod_cat`) REFERENCES `category` (`cat_id`),
  CONSTRAINT `fk_catprod_prod` FOREIGN KEY (`catprod_prod`) REFERENCES `product` (`prod_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

SET FOREIGN_KEY_CHECKS = 0;

INSERT IGNORE INTO `category` (`cat_id`, `cat_name`, `cat_desc`, `cat_parent`)
VALUES(0, 'Home', 'Home Category', 0);

SET FOREIGN_KEY_CHECKS = 1;
EOF;

		global $_d;
		$_d['db']->Queries($data);
	}

	function Link()
	{
		global $_d;

		// Attach to Product

		$_d['product.ds.columns'][] = 'cat_id';
		$_d['product.ds.columns'][] = 'catprod_cat';

		$_d['product.callbacks.props']['category'] = array(&$this, 'cb_product_props');
		$_d['product.callbacks.addfields']['category'] = array(&$this, 'cb_product_addfields');
		$_d['product.callbacks.editfields']['category'] = array(&$this, 'cb_product_editfields');
		$_d['product.callbacks.update']['category'] = array(&$this, 'cb_product_update');
		$_d['product.callbacks.add']['category'] = array(&$this, 'cb_product_add');
		$_d['product.callbacks.delete']['category'] = array(&$this, 'cb_product_delete');

		$_d['product.ds.match']['cat_id'] = GetVar('cc', 0);

		$_d['product.ds.joins']['cat_prod'] =
			new Join($_d['cat_prod.ds'], 'catprod_prod = prod_id', 'LEFT JOIN');
		$_d['product.ds.joins']['category'] =
			new Join($_d['category.ds'], 'catprod_cat = cat_id', 'LEFT JOIN');

		$_d['product.latest.match']['catprod_cat'] = SqlNot(0);
		$_d['product.latest.hide'] = GetVar('cc', 0) != 0;

		// Globally available tags for templating
		$_d['template.rewrites']['showcat'] = array(&$this, 'TagShowCat');
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
				$prods = QueryProductList(array('cat_id' => $ci));
				if (!empty($cats)) $res = array('res' => 0, 'msg' => 'Category not empty.');
				else if (!empty($prods)) $res = array('res' => 0, 'msg' => 'Category not empty.');
				else
				{
					$_d['category.ds']->Remove(array('cat_id' => $ci));
					#xslog($_d, "Removed category {$ci}");
					$res['res'] = 1;
					$res['msg'] = 'Successfully deleted.';
				}
				die(json_encode($res));
			}
		}

		$_d['category.current'] = QueryCat($_d, GetVar('cc'));
	}

	function cb_product_props($_d, $prod)
	{
		return array('Category' => GetBreadcrumb($_d, $prod['cat_id']));
	}

	function cb_product_addfields($_d, $form)
	{
		$cats = QueryCatsAll($_d);

		$cc = GetVar('cc');

		$form->AddInput(new FormInput('Category', 'select', 'category',
			DataToSel($cats, 'cat_name', 'cat_id', $cc, 'Home'),
			'onchange="catChange();" style="width: 100%"'));
	}

	function cb_product_editfields($_d, $prod, $form)
	{
		$cats = QueryCatsAll($_d);
		$sels = DataToSel($cats, 'cat_name', 'cat_id', $prod['catprod_cat'], 'Home');
		$form->AddInput(new FormInput('Category',
			'select', 'category', $sels, array('STYLE' => "width: 100%")));
	}

	function cb_product_add($_d, $prod, $id)
	{
		$_d['cat_prod.ds']->Add(array(
			'catprod_cat' => GetVar('formProduct_category'),
			'catprod_prod' => $id
		));
	}

	function cb_product_update($_d, $prod)
	{
		$_d['cat_prod.ds']->Add(array(
			'catprod_cat' => GetVar('category'),
			'catprod_prod' => GetVar('ci')
		), true);
	}

	function cb_product_delete()
	{
		global $_d;

		$_d['cat_prod.ds']->Remove(array('catprod_prod' => $_d['q'][2]));
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
			if (empty($this->cat['cat_name'])) { $this->cat['cat_name'] = '[BLANK]'; }
			$tt->Set($this->cat);
			$ret .= $tt->GetString($g);
		}
		return $ret;
	}

	// Globally Available Tags
	function TagShowCat($t, $g, $a)
	{
		$id = $a['ID'];
		$imgs = glob('catimages/'.$id.'.*');
		if (!empty($imgs))
			return "<a href=\"{{app_abs}}?cc=$id\"><img src=\"{{app_abs}}/$imgs[0]\" alt=\"category\" /></a>";
		else
			return $id;
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
		$t->Set('cats', $this->cats = QueryCats($this->data, number_format($cc), false));
		$t->ReWrite('notempty', 'TagNotEmpty');
		$t->ReWrite('admin', array(&$this, 'TagAdmin'));
		$t->ReWrite('category', array(&$this, 'TagCategory'));

		return $t->ParseFile(t('category/fromCatalog.xml'));

		if (!empty($_d['category.current']))
			$_d['page.title'] .= ' - '.$_d['category.current']['name'];
		else $_d['page.title'] .= ' - Home';

		$butparent = "<img src=\"images/dirup.gif\" alt=\"parent category\" border=\"0\" />";
		$butdir = "<img src=\"images/folder.png\" border=\"0\" alt=\"category\" />";

		//Display sub-categories.
		$cats = QueryCats($_d, number_format($cc), false);

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
	function Link()
	{
		global $_d;

		#if (ModUser::RequestAccess(500))
		#{
		#	$_d['page.links']['Admin']['Categories'] = '{{me}}?cs=category';
		#}
	}

	function Prepare($required = false)
	{
		parent::Prepare();
		global $_d;

		if (@$_d['q'][0] != 'category') return;

		$ca = GetVar('ca');

		if ($ca == 'add')
		{
			$dsCats = $_d['category.ds'];
			$ci = $dsCats->Add(array(
				'cat_date' => SqlUnquote('NOW()'),
				'cat_parent' => GetVar('cc'),
				'cat_name' => GetVar('formAddCat_name'),
				'cat_desc' => GetVar('formAddCat_desc'),
				'cat_hidden' => GetVar('formAddCat_hidden')));

			$f = GetVar('formAddCat_image');
			if (!empty($f))
			{
				// Get rid of the existing images.
				$existing = glob('catimages/'.$ci.'.*');
				if (!empty($existing))
					foreach($existing as $ef)
						unlink($ef);

				if (!file_exists('catimages')) mkdir('catimages');

				$ext = fileext($f['name']);
				move_uploaded_file($f['tmp_name'],'catimages/'.$ci.'.'.$ext);
			}

			$_d['cs'] = 'catalog';
			$_d['cc'] = $ci;
		}

		else if ($ca == 'update')
		{
			$f = GetVar('formViewCat_image');
			if (!empty($f))
			{
				// Get rid of the existing images.
				$existing = glob('catimages/'.$_d['ci'].'.*');
				if (!empty($existing))
					foreach($existing as $ef)
						unlink($ef);

				if (!file_exists('catimages')) mkdir('catimages');

				$ext = fileext($f['name']);
				move_uploaded_file($f['tmp_name'],'catimages/'.$_d['ci'].'.'.$ext);
			}

			$_d['category.ds']->Update(array('cat_id' => $_d['ci']), array(
				'cat_parent' => GetVar('formViewCat_parent'),
				'cat_name' => GetVar('formViewCat_name'),
				'cat_desc' => GetVar('formViewCat_desc'),
				'cat_hidden' => GetVar('formViewCat_hidden')
			));
		}
	}

	function Get()
	{
		global $_d;

		$ca = GetVar('ca');

		if ($_d['q'][0] != 'category') return;

		if ($ca == 'prepare')
		{
			$GLOBALS['page_section'] = 'Create Category';
			$formAddCat = new Form("formAddCat");
			$formAddCat->AddHidden("ca", "add");
			$formAddCat->AddHidden("cs", GetVar('cs'));
			$formAddCat->AddHidden("cc", GetVar('cc'));
			$formAddCat->AddInput(new FormInput('Name', 'text', 'name', null,
				'style="width: 100%"'));
			$formAddCat->AddInput(new FormInput('Description', 'area', 'desc', null,
				'style="width: 100%; height: 100px;"'));
			$formAddCat->AddInput(new FormInput('Hide','checkbox','hidden'));
			$formAddCat->AddInput(new FormInput('Image','file','image'));
			RunCallbacks($_d['category.callbacks.fields'], $_d, $formAddCat);
			$formAddCat->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Add'));

			return GetBox('box_create',
				'Create Category',
				$formAddCat->Get('action="{{me}}" method="post" enctype="multipart/form-data"',
				'style="width: 100%"'));
		}

		else if ($ca == 'edit')
		{
			$_d['page_title'] .= " - Category Properties";
			$dsCats = $_d['category.ds'];
			$cat = $dsCats->GetOne(array("cat_id" => $_d['ci']));
			$cats = $dsCats->Get();
			$frmViewCat = new Form("formViewCat");
			$frmViewCat->AddHidden("cs", $_d['cs']);
			$frmViewCat->AddHidden("ca", "update");
			$frmViewCat->AddHidden("ci", $_d['ci']);
			$frmViewCat->AddInput(new FormInput('Parent', 'select', 'parent',
				DataToSel($cats, 'cat_name', 'cat_id', $cat['cat_parent'], "Home")));
			$frmViewCat->AddInput(new FormInput('Name', 'text', 'name',
				$cat['cat_name'], 'size="50"'));
			$frmViewCat->AddInput(new FormInput('Description', 'area', 'desc',
				$cat['cat_desc'], 'style="width: 100%; height: 100px;"'));
			$frmViewCat->AddInput(new FormInput('Hide', 'checkbox', 'hidden',
				$cat['cat_hidden']));
			$frmViewCat->AddInput(new FormInput('Image','file','image'));

			RunCallbacks($_d['category.callbacks.fields'], $_d, $frmViewCat, $cat);

			$frmViewCat->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Save'));

			return GetBox("box_category", "Category Properties",
				$frmViewCat->Get('action="{{me}}" method="post" enctype="multipart/form-data"'));
		}

		else if ($ca == 'update' || $ca == 'add' || $ca == 'remove')
		{
			#$_d['cs'] = 'catalog';
			#$_d['cc'] = $ca == 'update' ? GetVar('parent') : GetVar('cc');
			#$mod = RequireModule($_d, 'modules/content.php', 'ModContent');
			#$mod->Prepare($_d);
			#return $mod->Get($_d);
		}

		else // Category listing.
		{
			$ret = null;

			$items = QueryCatsAll($_d);
			foreach ($items as $i)
			{
				$ret .= "<p><a href=\"{{me}}?cs=category&ca=edit&ci={$i['cat_id']}\">{$i['cat_name']}</a></p>";
			}
			return $ret;
		}
	}
}

?>
