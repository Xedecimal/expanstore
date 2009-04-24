<?php

RegisterModule('ModAttribute');
RegisterModule('ModAtrAssign');

function QueryAtrgs($match = null)
{
	global $_d;

	/** @var DataSet */
	$ds = $_d['atrg.ds'];

	return $ds->Get($match, null, null, $_d['atrg.ds.joins']);
}

function QueryAttributes(&$_d, $atrg)
{
	//$dsAtrg = $_d['atrg.ds'];
	$dsAttrib = $_d['attribute.ds'];
	$dsOptions = $_d['option.ds'];

	$columns = array(
		'atrg_id',
		'atr_id',
		'atr_name',
		'opt_id',
		'opt_name',
		'opt_formula'
	);

	if (!empty($_d['attribute.columns']))
		$columns = array_merge($columns, $_d['attribute.columns']);

	$joins = array(
		$dsAttrib->table => new Join($dsAttrib, 'atr_atrg = atrg_id'),
		$dsOptions->table => new Join($dsOptions, 'opt_attrib = atr_id')
	);

	if (!empty($_d['attribute.joins']))
		$joins = array_merge($joins, $_d['attribute.joins']);

	return $_d['atrg.ds']->Get(array('atrg_id' => $atrg), array('atr_id' => 'ASC'), null,
		$joins, $columns);
}

class ModAttribute extends Module
{
	function __construct()
	{
		global $_d;

		// dsAtrg

		$dsAtrg = new DataSet($_d['db'], 'ype_atrgroup', 'atrg_id');
		$dsAtrg->Shortcut = 'atrg';
		$_d['atrg.ds'] = $dsAtrg;

		$dsAg2p = new DataSet($_d['db'], 'ype_atrg_prod', 'ag2p_id');
		$_d['ag2p.ds'] = $dsAg2p;

		// dsAttrib

		$dsAttrib = new DataSet($_d['db'], "ype_attribute");
		$dsAttrib->Shortcut = 'attrib';
		$_d['attribute.ds'] = $dsAttrib;

		// dsOption

		$dsOption = new DataSet($_d['db'], "ype_option");
		$dsOption->Shortcut = 'o';
		$_d['option.ds'] = $dsOption;

		// dsCartOption

		$dsCartOption = new DataSet($_d['db'], "ype_cart_option");
		$dsCartOption->Shortcut = 'co';
		$_d['cartoption.ds'] = $dsCartOption;
	}

	function Link()
	{
		global $_d;

		// Attach to Navigation.

		if (!empty($_d['cl']) && $_d['cl']['usr_access'] >= 500)
		{
			$_d['page.links']["Control Panel"]["Attributes"] =
				'{{me}}?cs=attribute&amp;ca=view_atrgs';
		}

		// Internal Joins

		$_d['atrg.ds.joins']['attrib'] =
			new Join($_d['attribute.ds'], 'atr_atrg = atrg_id', 'LEFT JOIN');
		$_d['atrg.ds.joins']['option'] =
			new Join($_d['option.ds'], 'opt_attrib = atr_id', 'LEFT JOIN');

		// Attach to Product.

		$_d['product.ds.joins']['ag2p'] =
			new Join($_d['ag2p.ds'], 'ag2p_prod = prod_id', 'LEFT JOIN');
		$_d['product.ds.joins']['atrg'] =
			new Join($_d['atrg.ds'], 'ag2p_atrg = atrg_id', 'LEFT JOIN');

		$_d['product.ds.columns'][] = 'atrg_id';

		$_d['product.callbacks.addfields'][] =
			array(&$this, 'ProductAddFields');
		$_d['product.callbacks.editfields'][] =
			array(&$this, 'ProductEditFields');
		$_d['product.callbacks.update']['attribute'] =
			array(&$this, 'ProductUpdate');
		$_d['product.callbacks.props'][] =
			array(&$this, 'ProductProps');

		// Attach to Cart.

		$_d['cart.callbacks.add'][] = array(&$this, 'CartAdd');
		$_d['cart.callbacks.price'][] = array(&$this, 'CartPrice');
		$_d['cart.callbacks.update'][] = array(&$this, 'CartUpdate');
		$_d['cart.callbacks.remove'][] = array(&$this, 'CartRemove');

		$dsCartOption = &$_d['cartoption.ds'];

		$_d['cart.joins'][$dsCartOption->table] =
			new Join($dsCartOption, 'carto_item = ci_id', 'LEFT JOIN');

		$dsOption = &$_d['option.ds'];

		$_d['cart.joins'][$dsOption->table] =
			new Join($dsOption, "opt_id = carto_option", 'LEFT JOIN');

		$_d['cart.columns'][] = 'opt_formula';
		$_d['cart.columns'][] = 'carto_option';
	}

	function Prepare()
	{
		parent::Prepare();
		$ca = GetVar('ca');

		global $_d;

		if ($_d['cs'] != 'attribute') return;

		if ($ca == 'update' || $ca == 'create' || $ca == 'delete')
		{
			$type = GetVar('type');

			if ($type == 'Atrg')
			{
				$insert['atrg_name'] = GetVar('atrg_name');
				$res['name'] = $insert['atrg_name'];
				$matchcol = 'atrg_id';
				$dsname = 'atrg.ds';
			}
			if ($type == 'Atr')
			{
				$insert['atr_name'] = GetVar('atr_name');
				$insert['atr_type'] = GetVar('atr_type');
				$res['name'] = $insert['atr_name'];
				if ($ca == 'create')
					$insert['atr_atrg'] = GetVar('parent');
				$matchcol = 'atr_id';
				$dsname = 'attribute.ds';
			}
			if ($type == 'Opt')
			{
				$insert['opt_name'] = GetVar('name');
				$insert['opt_formula'] = GetVar('formula');
				if ($ca == 'create')
					$insert['opt_attrib'] = GetVar('parent');
				$matchcol = 'opt_id';
				$dsname = 'option.ds';
			}

			if ($ca == 'create')
			{
				$_d[$dsname]->Add($insert);
			}
			if ($ca == 'update')
			{
				$id = GetVar('id');
				$_d[$dsname]->Update(array($matchcol => $id),
					$insert);
			}
			if ($ca == 'delete')
			{
				$id = GetVar('id');
				$_d[$dsname]->Remove(array($matchcol => $id));
			}

			die(json_encode(array_merge($insert, array('res' => 1))));
		}

		// Old Crap

		if ($ca == "atrg_create")
		{
			$dsAtrgs->Add(array(
				'date' => Destring("NOW()"),
				'company' => $_d['cl']['company'],
				'name' => GetVar("name")
			));
			$_d['ca'] = 'view_atrgs';
		}
		else if ($ca == "atrg_create_under")
		{
			$sel = explode("|", $_d['ci']);

			if ($sel[0] == "atrg")
			{
				$dsAttribs->Add(array(
					'date' => Destring('NOW()'),
					'atrg' => $sel[1],
					'name' => GetVar("name")));
				xslog($_d, "Added attribute " . GetVar("name") . " to {$sel[1]}");
				$_d['ca'] = 'view_atrgs';
			}
			else if ($sel[0] == "attrib")
			{
				$dsOptions->Add(array(
					'date' => Destring('NOW()'),
					'attrib' => $sel[1],
					'name' => GetVar("name"),
					'formula' => GetVar("formula")));
				xslog($_d, "Added option " . GetVar("name") . " to {$sel[1]}");
			}
			else
			{
				$name = GetVar("name");
				$dsAtrgs->Add(array(Destring("NULL"), $name, $cl->company->id));
				xslog($_d, "Added attribute group $name");
			}
			$_d['ca'] = 'view_atrgs';
		}
		else if ($ca == "atrg_delete")
		{
			$sel = explode("|", $_d['ci']);

			if ($sel[0] == "atrg")
			{
				$_d['atrg.ds']->Remove(array('id' => $sel[1]));

				xslog($_d, "Removed attribute group {$_d['ci']} and all"
					." children.");
				$ret = GetVar("ret");
			}

			if ($sel[0] == "attrib")
			{
				$dsAttribs->Remove(array('id' => $sel[1]));
				xslog($_d, "Removed attribute {$_d['ci']} and all children.");
				$ret = GetVar("ret");
			}

			if ($sel[0] == "option")
			{
				$dsOptions->Remove(array('id' => $sel[1]));
				xslog($_d, "Removed option {$sel[1]}.");
				$ret = GetVar("ret");
			}
			$_d['ca'] = 'view_atrgs';
		}
		else if ($ca == 'atrg_swap')
		{
			$dsAtrgs->Swap(array('id' => GetVar('csrc')), array('id' => GetVar('ctgt')), 'id');
			$_d['ca'] = 'view_atrgs';
		}
		else if ($ca == 'attrib_swap')
		{
			$dsAttribs = $_d['attribute.ds'];
			$dsAttribs->Swap(array('id' => GetVar('csrc')), array('id' => GetVar('ctgt')), 'id');
			$_d['ca'] = 'view_atrgs';
		}
		else if ($ca == 'opt_swap')
		{
			$dsOptions->Swap(array('id' => GetVar('csrc')), array('id' => GetVar('ctgt')), 'id');
			$_d['ca'] = 'view_atrgs';
		}
		else if ($ca == "update_atrg")
		{
			$sel = explode("|", $_d['ci']);

			if ($sel[0] == "atrg")
			{
				$dsAtrgs->Update(array('id' => $sel[1]), array("name" => GetVar("name")));
				xslog($_d, "Updated attribute group {$_d['ci']} to " . GetVar("name"));
			}

			if ($sel[0] == "attrib")
			{
				$dsAttribs->Update(array('id' => $sel[1]), array(
					"name" => GetVar("name")
				));
				xslog($_d, "Updated attribute {$_d['ci']} to " . GetVar("name"));
			}

			if ($sel[0] == "option")
			{
				$name = GetVar("name");
				$cols = array("name" => $name, "formula" => GetVar("formula"));
				$dsOptions->Update(array("id" => $sel[1]), $cols);
				xslog($_d, "Updated option {$_d['ci']} to $name");
			}
			$_d['ca'] = 'view_atrgs';
		}
	}

	function Get()
	{
		global $_d;

		global $me;
		$ca = $_d['ca'];

		if ($ca == "view_atrgs")
		{
			$GLOBALS['page_section'] = 'Attributes';

			$t = new Template();
			$t->Set('tempath', $_d['tempath']);
			$t->ReWrite('atrg', array(&$this, 'TagAtrg'));

			return $t->ParseFile($_d['tempath'].'attribute/index.xml');
		}

		if ($ca == 'getatrg')
		{
			$atrs = $_d['attribute.ds']->Get(array('atr_atrg' => GetVar('ci')));
			$xml = simplexml_load_file($_d['tempath'].'attribute/index.xml');
			$e = $xml->xpath('//attribs');
			$tt = new Template();
			$tt->ReWrite('attrib', array(&$this, 'TagAttrib'));
			die($tt->GetString($e[0]->asXML()));
		}
	}

	function TagAtrg($t, $g, $a)
	{
		$tt = new Template();
		$tt->ReWrite('attrib', array(&$this, 'TagAttrib'));
		$ret = '';

		global $_d;
		$atrgs = $_d['atrg.ds']->Get();

		foreach ($atrgs as $a)
		{
			$tt->Set($a);
			$this->atrg = $a;
			$ret .= $tt->GetString($g);
		}
		return $ret;
	}

	function TagAttrib($t, $g, $a)
	{
		$tt = new Template();
		$tt->ReWrite('option', array(&$this, 'TagOption'));
		$tt->ReWrite('selatr', array(&$this, 'TagSelAtr'));
		$ret = null;
		$atrs = QueryAtrgs(array('atr_atrg' => GetVar('ci')));

		foreach ($atrs as $atr)
		{
			$tt->Set($atr);
			$this->atr = $atr;
			varinfo($g);
			$ret .= $tt->GetString($g);
		}
		return $ret;
	}

	function TagOption($t, $g, $a)
	{
		$tt = new Template();
		$ret = null;
		foreach ($this->atr->opts as $opt)
		{
			// No options for a numeric attribute.
			if ($this->atr->data['atr_type'] == 1) continue;

			$tt->Set($opt->data);
			$ret .= $tt->GetString($g);
		}
		return $ret;
	}

	function TagSelAtr($t, $g)
	{
		if ($this->atr->data['atr_type'] == 1) return;
		return $g;
	}

	function ProductAddFields(&$_d, &$form)
	{
		$atrgs = QueryAtrgs($_d);
		$form->AddInput('Attribute Related');
		$form->AddInput(new FormInput('Attribute Group', 'select',
			'atrg', DataToSel($atrgs, 'name', 'id', GetVar('atrg'), 'None')));
	}

	function ProductEditFields(&$_d, &$prod, $form)
	{
		$atrgs = QueryAtrgs();
		$form->AddInput('Attribute Related');

		$form->AddInput(new FormInput('Attribute Group', 'select', 'atrg',
			DataToSel($atrgs, 'atrg_name', 'atrg_id', $prod['atrg_id'], 'None'),
			'style="width: 100%"'));
	}

	function ProductUpdate(&$_d, &$prod, $id)
	{
		$atrg = GetVar('formProdProps_atrg');
		if ($atrg == 0) $_d['ag2p.ds']->Remove(array('ag2p_prod' => $id));
		else
		$_d['ag2p.ds']->Add(array(
			'ag2p_atrg' => GetVar('formProdProps_atrg'),
			'ag2p_prod' => $id
		), true);
	}

	function ProductProps(&$_d, &$prod)
	{
		$t = new Template($_d);

		$price_offset = 0;

		$outprops = null;

		if (!empty($prod['atrg_id']))
		{
			$fp = new CFormulaParser();

			if ($_d['cs'] == 'cart')
			{
				$dsCart = $_d['cart.ds'];
				$dsCartItem = $_d['cartitem.ds'];
				$dsCartOption = $_d['cartoption.ds'];

				$_d['attribute.joins'][$dsCart->table] =
					new Join($dsCart, "cart.user = {$_d['cl']['usr_id']}");
				$_d['attribute.joins'][$dsCartItem->table] =
					new Join($dsCartItem, "ci.cart = cart.id");
				$_d['attribute.joins'][$dsCartOption->table] =
					new Join($dsCartOption, 'co.cart = cart.id AND co.cartitem = ci.id AND co.attribute = attrib.id');

				$_d['attribute.columns']['co.option'] = 'selected';
			}

			$attribs = QueryAttributes($_d, $prod['atrg_id']);

			$aid = -1;
			$oid = -1;

			if (!empty($attribs))
			foreach ($attribs as $atr)
			{
				$selname = "atrs[{$atr['atr_id']}]";

				if ($aid != $atr['atr_id'])
				{
					if ($aid != -1)
					{
						$options .= "</select>\n";
						$t->Set('prop', $aname);
						$t->Set('value', $options);
						$outprops .= $t->ParseFile($_d['tempath'].'catalog/product_property.html');
					}
					$options = "<select name=\"{$selname}\" class=\"input_edit\">\n";
				}

				if ($atr['opt_formula'] == null) $result = $prod['prod_price'];
				else $result = $fp->GetFormula($prod, $atr['opt_formula']);

				$selected = null;

				if (isset($atr['selected']))
				{
					if ($atr['selected'] == $atr['oid'])
					{
						$price_offset += $result;
						$selected = ' selected="true"';
					}
				}

				$options .= "<option value=\"{$atr['opt_id']}\"$selected>".htmlspecialchars($atr['opt_name']);
				if ($result != 0) $options .= " $".($result > -1 ? '+' : null)."$result</option>";
				else $options .= "</option>";

				$aid = $atr['atr_id'];
				$aname = $atr['atr_name'];
			}
			if ($aid != -1)
			{
				$options .= "</select>\n";
				$t->Set('prop', $aname);
				$t->Set('value', $options);
				$outprops .= $t->ParseFile($_d['tempath'].'catalog/product_property.html');
			}

			if (!isset($_d['product.totalprice']))
				$_d['product.totalprice'] = $prod['prod_price']+$price_offset;
			else $_d['product.totalprice'] += $price_offset;

			if ($_d['cs'] == 'cart')
			{
				$t->Set(array('prop' => 'Total price', 'value' => '<b>$'.($prod['price']+$price_offset).'</b>'));
				$outprops .= $t->ParseFile($_d['tempath'].'catalog/product_property.html');
			}
		}

		return $outprops;
	}

	function CartAdd(&$_d, $cart, $ciid)
	{
		$atrs = GetVar("atrs");

		if (!empty($atrs))
		{
			$dsCartOptions = $_d['cartoption.ds'];

			foreach ($atrs as $atr => $opt)
			{
				$dsCartOptions->Add(array(
					'cart' => $cart,
					'cartitem' => $ciid,
					'attribute' => $atr,
					'option' => $opt));
			}
		}
	}

	function CartUpdate(&$_d)
	{
		$atrs = GetVar('atrs');

		if (!empty($atrs))
		{
			$dsCartOptions = $_d['cartoption.ds'];
			$ci = $_d['ci'];

			foreach ($atrs as $atr => $opt)
			{
				$dsCartOptions->Update(
					array(
						'carto_item' => $ci,
						'carto_attribute' => $atr
					),
					array('option' => $opt)
				);
			}
		}
	}

	function CartRemove(&$_d)
	{
		$_d['cartoption.ds']->Remove(array('carto_item' => GetVar('ci')));
	}
}

class ModAtrAssign extends Module
{
	function Get()
	{
		global $_d;

		if ($_d['cs'] == 'product' && $_d['ca'] == 'edit')
		return GetBox('box_atrgs', 'Test', 'Blah.');
	}
}

?>
