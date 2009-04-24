<?php

RegisterModule('ModImportOsc');

class ModImportOsc extends Module
{
	var $dsOsc_Cat;
	var $dsOsc_CatDesc;
	var $dsOsc_Prod;
	var $dsOsc_ProdDesc;
	var $dsOsc_ProdToCat;

	var $dsTargetCat;
	var $dsTargetProd;

	function Link()
	{
		if (AccessRequire(500))
			$_d['page.links']['Admin']['osCommerce Import'] =
				'{{me}}?cs=oscimport&amp;class=oscimport&amp;ca=admin';
	}

	function Get()
	{
		global $_d;

		if ($_d['cs'] != 'importosc') return;

		$ca = $_d['ca'];

		if ($ca == 'start')
		{
			//Prepare database and datasets...

			$osci_host = $_SESSION['osci_host'];
			$osci_user = $_SESSION['osci_user'];
			$osci_pass = $_SESSION['osci_pass'];
			$osci_data = $_SESSION['osci_data'];
			$osci_dir = $_SESSION['osci_dir'];

			$db = new Database();
			$db->Open("mysql://{$osci_user}:{$osci_pass}@{$osci_host}/{$osci_data}");

			$this->dsOsc_Cat       = new Dataset($db, 'categories');
			$this->dsOsc_CatDesc   = new DataSet($db, 'categories_description');
			$this->dsOsc_Prod      = new DataSet($db, 'products');
			$this->dsOsc_ProdDesc  = new DataSet($db, 'products_description');
			$this->dsOsc_ProdToCat = new DataSet($db, 'products_to_categories');

			$osc_joins = array(
				new Join($this->dsOsc_CatDesc, 'categories_description.categories_id = categories.categories_id')
			);

			//Clear out existing data...

			$this->dsTargetCat = $_d['category.ds'];
			$this->dsTargetCat->Truncate();
			$this->dsTargetProd = $_d['product.ds'];
			$this->dsTargetProd->Truncate();

			//Read some source data to get our bearings...

			$items = $this->dsOsc_Cat->Get(null, null, null, $osc_joins);
			$tree = BuildTree($items, 'categories_id', 'parent_id');
			$out = '';

			//Statistics

			//session_start();
			$_SESSION['osci_prog']['prods'] =
			$_SESSION['osci_prog']['cats'] =
			$_SESSION['osci_prog']['total'] =
			$_SESSION['osci_prog']['done'] = 0;
			$_SESSION['osci_prog']['console'] = array();
			session_write_close();

			set_time_limit(0);

			foreach ($tree->children as $child)
				$this->ImportCategory(0, $child);

			session_start();
			$_SESSION['osci_prog']['done'] = 1;
			session_write_close();
			die('Done');
		}

		if ($ca == 'progress')
		{
			$prog = $_SESSION['osci_prog'];

			$console = '[ ';
			foreach ($prog['console'] as $ix => $con)
				$console .= ($ix > 0 ? ', ' : '')."'{$con}'";
			$console .= ' ]';

			$out = <<<EOF
{
	prods: {$prog['prods']},
	done: {$prog['done']},
	console: $console
}
EOF;
			$_SESSION['osci_prog']['console'] = array();
			session_write_close();
			die($out);
		}

		if ($ca == 'import')
		{
			$_SESSION['osci_host'] = GetVar('host');
			$_SESSION['osci_user'] = GetVar('user');
			$_SESSION['osci_pass'] = GetVar('pass');
			$_SESSION['osci_data'] = GetVar('data');
			$_SESSION['osci_dir'] = GetVar('oscdir');

			$t = new Template();
			$out = $t->ParseFile($_d['tempath'].'oscimport/import.xml');
		}

		else
		{
			$t = new Template();
			$out = $t->ParseFile($_d['tempath'].'oscimport/index.xml');
		}

		return GetBox('box_oscimport', 'osCommerce Import', $out);
	}

	function ImportCategory($parent, $item)
	{
		//Import this category
		$this->dsTargetCat->Add(array(
			'id' => $item->data['categories_id'],
			'date' => $item->data['date_added'],
			'parent' => $parent,
			'name' => $item->data['categories_name']
		));

		//Import associated products.
		$prod_joins = array(
			new Join($this->dsOsc_ProdDesc, 'products.products_id = products_description.products_id'),
			new Join($this->dsOsc_ProdToCat, 'products.products_id = products_to_categories.products_id', 'LEFT JOIN'),
		);

		$prods = $this->dsOsc_Prod->Get(array("categories_id = {$item->data['categories_id']}"), null, null, $prod_joins);

		if (!empty($prods))
		foreach ($prods as $prod)
		{
			$id = $this->dsTargetProd->Add(array(
				'prod_id' => $prod['products_id'],
				'date' => $prod['products_date_added'],
				'modified' => $prod['products_last_modified'],
				'available' => $prod['products_date_available'],
				'weight' => $prod['products_weight'],
				'status' => $prod['products_status'],
				'name' => $prod['products_name'],
				'description' => $prod['products_description'],
				'model' => $prod['products_model'],
				'price' => $prod['products_price'],
				'cat' => $item->data['categories_id'],
				'views' => $prod['products_viewed'],
				'company' => $prod['manufacturers_id']
			));

			session_start();
			$_SESSION['osci_prog']['prods']++;
			if ($err = CreateProductThumbnails($id, 0, $_SESSION['osci_dir'].'/images/'.$prod['products_image']))
			{
				$_SESSION['osci_prog']['console'][] = $err;
			}
			session_write_close();
		}

		foreach ($item->children as $child)
			$this->ImportCategory($item->data['categories_id'], $child);
	}
}

?>
