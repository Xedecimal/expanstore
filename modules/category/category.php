<?php

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

		if ($_d['q'][0] == 'category' && is_numeric(@$_d['q'][1]))
			$_SESSION['cc'] = $_d['q'][1];

		$_d['category.all'] = DataToTree($_d['category.ds']->Get(),
			array('cat_id' => array('cat_id', 'cat_parent')), 0);
	}

	function Link()
 	{
		global $_d;

		# Attach to Product

		$_d['product.ds.query']['columns'][] = 'cat_id';
		$_d['product.ds.query']['columns'][] = 'catprod_cat';

		$_d['product.cb.template']['category'] =
			array(&$this, 'cb_product_template');
		$_d['product.callbacks.props']['category'] =
			array(&$this, 'cb_product_props');
		$_d['product.callbacks.addfields']['category'] =
			array(&$this, 'cb_product_fields');
		$_d['product.callbacks.editfields']['category'] =
			array(&$this, 'cb_product_fields');
		$_d['product.callbacks.update']['category'] =
			array(&$this, 'cb_product_update');
		$_d['product.callbacks.add']['category'] =
			array(&$this, 'cb_product_add');
		$_d['product.callbacks.delete']['category'] =
			array(&$this, 'cb_product_delete');

		if (empty($_d['category.bypass']))
			$_d['product.ds.query']['match']['cat_id'] = GetVar('cc', 0);

		$_d['product.ds.query']['joins']['cat_prod'] =
			new Join($_d['cat_prod.ds'], 'catprod_prod = prod_id', 'LEFT JOIN');
		$_d['product.ds.query']['joins']['category'] =
			new Join($_d['category.ds'], 'catprod_cat = cat_id', 'LEFT JOIN');

		$_d['product.latest.match']['catprod_cat'] = SqlNot(0);

		# Globally available tags for templates

		$_d['template.rewrites']['showcat'] = array(&$this, 'TagShowCat');
		$_d['template.rewrites']['category'] = array(&$this, 'TagCategory');
		$_d['template.rewrites']['catselect'] = array(&$this, 'TagCategorySelect');

		if (ModUser::RequestAccess(500))
		{
			$_d['page.links']['Admin']['Categories']['Listing'] =
				'{{app_abs}}/category/list';
			$_d['page.links']['Admin']['Categories']['Add'] =
				'{{app_abs}}/category/prepare';
		}
 	}

	function Prepare($require = false)
	{
		parent::Prepare();

		global $_d;

 		$_d['category.current'] = ModCategory::QueryCat(GetVar('cc'));

		$cs = $_d['q'][0];

		if ($cs != 'category') return;

		$ca = @$_d['q'][1];

		if ($ca == 'delete')
		{
			$cid = $_d['q'][2];

			$cats = ModCategory::QueryCats($cid);
			$dsProducts = $_d['product.ds'];
			$prods = QueryProductList(array('cat_id' => $cid));

			if (!empty($cats)) $res = array('res' => 0, 'msg' => 'Category not empty.');
			else if (!empty($prods)) $res = array('res' => 0, 'msg' => 'Category not empty.');
			else
			{
				$_d['category.ds']->Remove(array('cat_id' => $cid));
				#xslog($_d, "Removed category {$ci}");
				$res['res'] = 1;
				$res['msg'] = 'Successfully deleted.';
			}
			die(json_encode($res));
		}
		// Products only list with an empty _d['q']
		else if (is_numeric($_d['q'][1]))
			$_d['q'] = array('catalog');
	}

	function Get()
	{
		global $_d;

		$breadcrumb = ModCategoryLocation::GetBreadcrumb(GetVar('cc'), 'FIXME', 'Nothing');
		$_d['product.title'] = "Products in " . $breadcrumb;

		$this->data = $_d;
		$cc = GetVar('cc');

		$t = new Template();
		$t->Set('cats', $this->cats = ModCategory::QueryCats($cc, false));
		$t->ReWrite('notempty', 'TagNotEmpty');
		$t->ReWrite('category', array(&$this, 'TagCategory'));

		return $t->ParseFile(l('category/fromCatalog.xml'));
	}

	static function QueryAll()
	{
		return $GLOBALS['_d']['category.ds']->Get();
	}

	static function QueryCats($parent, $include_hidden = true)
	{
		global $_d;

		$m = array('cat_parent' => $parent);
		if (!$include_hidden) $m['cat_hidden'] = 0;

		return $_d['category.ds']->Get(array(
			'match' => $m,
			'order' => array('cat_name'),
			'joins' => @$_d['category.ds.joins']
		));
	}

	static function QueryCat($id)
	{
		global $_d;

		$q = array(
			'match' => array('cat_id' => $id),
			'joins' => @$_d['category.ds.joins']
		);
		return $_d['category.ds']->GetOne($q);
	}

	# Callbacks

	function cb_product_template()
	{
		global $_d;
		return l(@$_d['category.current']['cat_template']);
	}

	function cb_product_props($prod)
	{
		global $_d;

		if ($_d['q'][0] == 'catalog' && empty($_d['category.show'])) return;

		$prod['props']['Category'] = ModCategoryLocation::GetBreadcrumb($prod['cat_id']);
		return $prod;
	}

	function cb_product_fields($form, $prod = null)
	{
		global $_d;

		$form->AddInput(new FormInput('Category', 'select', 'category',
			DataToSel($_d['category.all']->Collapse(), 'cat_name', 'cat_id',
				$_d['category.current']['cat_id'], 'None')));
	}

	function cb_product_add($_d, $prod, $id)
	{
		$_d['cat_prod.ds']->Add(array(
			'catprod_cat' => GetVar('category'),
			'catprod_prod' => $id
		));
	}

	function cb_product_update($_d, $prod, $pid)
	{
		$_d['cat_prod.ds']->Add(array(
			'catprod_cat' => GetVar('category'),
			'catprod_prod' => $pid
		), true);
	}

	function cb_product_delete()
	{
		global $_d;

		$_d['cat_prod.ds']->Remove(array('catprod_prod' => $_d['q'][2]));
	}

	# Globally Available Tags

	function TagCategory($t, $g, $a)
	{
		if (isset($a['PARENT']))
			$this->cats = ModCategory::QueryCats($a['PARENT']);

		$tt = new Template();
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

	function TagShowCat($t, $g, $a)
	{
		$id = $a['ID'];
		$cat = ModCategory::QueryCat($id);
		$imgs = glob('catimages/'.$id.'.*');
		if (!empty($imgs))
			return "<div class=\"category\"><a href=\"{{app_abs}}/category/$id\">
				<img src=\"{{app_abs}}/$imgs[0]\" alt=\"category\" />
				<p>{$cat['cat_name']}</p></a></div>";
		else
			return "<div class=\"category\"><a
				href=\"{{app_abs}}/category/$id\">{$cat['cat_name']}</a></div>";
	}

	function TagCategorySelect($t, $g, $a)
	{
		global $_d;

		return MakeSelect($a, DataToSel(
			ModCategory::QueryAll(),
			'cat_name',
			'cat_id', 0, 'Catalog'));
	}
}

Module::Register('ModCategory');

class ModCategoryAdmin extends Module
{
	function Link() { global $_d; }

	function Prepare($required = false)
	{
		parent::Prepare();
		global $_d;

		if (@$_d['q'][0] != 'category') return;

		$ca = @$_d['q'][1];

		if ($ca == 'add')
		{
			$dsCats = $_d['category.ds'];
			$ci = $dsCats->Add(array(
				'cat_date' => SqlUnquote('NOW()'),
				'cat_parent' => GetVar('parent'),
				'cat_name' => GetVar('name'),
				'cat_desc' => GetVar('desc'),
				'cat_hidden' => GetVar('hidden')));

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
			$cid = $_d['q'][2];

			# Uploading a new category image.

			$f = GetVar('image');
			if (!empty($f))
			{
				// Get rid of the existing images.
				$existing = glob("catimages/{$cid}/.*");
				if (!empty($existing))
					foreach($existing as $ef)
						unlink($ef);

				if (!file_exists('catimages')) mkdir('catimages');

				$ext = fileext($f['name']);
				move_uploaded_file($f['tmp_name'], "catimages/{$cid}.{$ext}");
			}

			$_d['category.ds']->Update(array('cat_id' => $cid), GetVar('cat'));
		}
	}

	function Get()
	{
		global $_d;

		if (@$_d['q'][0] != 'category') return;

		if (!ModUser::RequestAccess(500)) return;

		$ca = @$_d['q'][1];

		if ($ca == 'prepare')
		{
			$formAddCat = new Form("formAddCat");
			$formAddCat->AddInput(new FormInput('Name', 'text', 'name', null,
				array('STYLE' => 'width: 100%')));
			$formAddCat->AddInput(new FormInput('Description', 'area', 'desc', null,
				array('STYLE' => 'width: 100%; height: 100px;')));
			$formAddCat->AddInput(new FormInput('Parent Category', 'select',
				'parent', DataToSel(ModCategory::QueryAll(), 'cat_name', 'cat_id',
				@$_d['category.current']['cat_id'], 'None')));
			$formAddCat->AddInput(new FormInput('Hide', 'checkbox', 'hidden'));
			$formAddCat->AddInput(new FormInput('Image', 'file', 'image'));
			RunCallbacks(@$_d['category.callbacks.fields'], $_d, $formAddCat);
			$formAddCat->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Add'));

			return GetBox('box_create',
				'Create Category',
				$formAddCat->Get('action="{{app_abs}}/category/add"
					method="post" enctype="multipart/form-data"',
					array('STYLE' => 'width: 100%')));
		}

		else if ($ca == 'edit')
		{
			$cid = $_d['q'][2];
			$cat = ModCategory::QueryCat($cid);

			$t = new Template($cat);
			return $t->ParseFile(l('category/form.xml'));

			$cats = $dsCats->Get();
			$frmViewCat = new Form("formViewCat");
			$frmViewCat->AddInput(new FormInput('Parent', 'select', 'parent',
				DataToSel($cats, 'cat_name', 'cat_id', $cat['cat_parent'], "Home")));
			$frmViewCat->AddInput(new FormInput('Name', 'text', 'name',
				$cat['cat_name'], 'size="50"'));
			$frmViewCat->AddInput(new FormInput('Description', 'area', 'desc',
				$cat['cat_desc'], 'style="width: 100%; height: 100px;"'));
			$frmViewCat->AddInput(new FormInput('Hide', 'checkbox', 'hidden',
				$cat['cat_hidden']));
			$frmViewCat->AddInput(new FormInput('Image','file','image'));
			$frmViewCat->AddInput(new FormInput('Custom Template', 'text', 'template'));

			RunCallbacks(@$_d['category.callbacks.fields'], $_d, $frmViewCat, $cat);

			$frmViewCat->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Save'));

			return GetBox("box_category", "Category Properties",
				$frmViewCat->Get('action="{{app_abs}}/category/update/'.$cid
					.'" method="post" enctype="multipart/form-data"'));
		}

		else if ($ca == 'list') // Category listing.
		{
			$ret = null;

			#$items = ModCategory::QueryAll();
			#$tree = DataToTree($items, array('cat_parent' => 'cat_id'), 0);
			$tree = $_d['category.all'];

			$ret = GetTree($tree,
				'<a href="{{app_abs}}/category/{{cat_id}}">{{cat_name}}</a>
				| <a href="{{app_abs}}/category/edit/{{cat_id}}">Edit</a>
				| <a href="{{app_abs}}/category/delete/{{cat_id}}"
				class="aCatDelete">Delete</a>');
			return $ret;
		}
	}
}

Module::Register('ModCategoryAdmin');

class ModCategoryLocation extends Module
{
	public $Block = 'location';

	function Get()
	{
		global $_d;
		$t = new Template($_d);
		if (@$_d['category.current']['cat_hidden']) return;
		$t->Behavior->Bleed = false;
		$t->Set($_d['category.current']);
		$t->ReWrite('path', array($this, 'TagPath'));
		return $t->ParseFile(l('category/location.xml'));
	}

	static function TagPath($t, $g, $a)
	{
		global $_d;

		return ModCategoryLocation::GetBreadcrumb(GetVar('cc'));
	}

	static function GetBreadcrumb($cat, $sep = '/', $guts = null)
	{
		global $_d;

		if (empty($cat)) return 'Catalog';

		$c = $_d['category.all']->Find($cat);
		$ret = null;

		do
		{
			if (!is_object($c)) varinfo($cat);
			$ret = '<a href="{{app_abs}}/category/'.$c->data['cat_id'].'">'
				.$c->data['cat_name'].'</a>'.$ret;
			if ($c->id) $ret = ' &raquo; '.$ret;
		} while ($c = $c->parent);

		$ret = "<a href=\"{{app_abs}}/category/0\">Catalog</a>" . $ret;
		return $ret;
	}
}

Module::Register('ModCategoryLocation');

?>
