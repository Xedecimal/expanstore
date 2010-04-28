<?php

Module::RegisterModule('ModAttribute');

class ModAttribute extends Module
{
	function __construct($installed)
	{
		if (!$installed) return;

		global $_d;

		# attribute_product

		$_d['a2p.ds'] = new DataSet($_d['db'], 'attribute_product', 'a2p_id');

		# dsAttrib

		$dsAttrib = new DataSet($_d['db'], 'attribute', 'atr_id');
		$dsAttrib->Shortcut = 'attrib';
		$dsAttrib->Description = 'Attribute';
		$dsAttrib->DisplayColumns = array(
			'atr_name' => new DisplayColumn('Name'),
			'atr_text' => new DisplayColumn('Text')
		);
		$dsAttrib->FieldInputs = array(
			'atr_name' => new FormInput('Name'),
			'atr_text' => new FormInput('Text')
		);
		$_d['attribute.ds'] = $dsAttrib;

		# dsOption

		$dsOption = new DataSet($_d['db'], 'option', 'opt_id');
		$dsOption->Shortcut = 'o';
		$dsOption->Description = 'Option';
		$dsOption->DisplayColumns = array(
			'opt_name' => new DisplayColumn('Name'),
			'opt_formula' => new DisplayColumn('Formula')
		);
		$dsOption->FieldInputs = array(
			'opt_date' => 'NOW()',
			'opt_attrib' => @$_d['q'][2],
			'opt_name' => new FormInput('Name'),
			'opt_formula' => new FormInput('Formula')
		);
		$_d['option.ds'] = $dsOption;

		# dsCartOption

		$dsCartOption = new DataSet($_d['db'], "cart_option");
		$dsCartOption->Shortcut = 'co';
		$_d['cartoption.ds'] = $dsCartOption;
	}

	function Link()
	{
		global $_d;

		# Attach to Navigation

		if (!empty($_d['cl']) && $_d['cl']['usr_access'] >= 500)
		{
			$_d['page.links']["Control Panel"]["Attributes"] =
				'{{app_abs}}/attribute';
		}

		# Attach to Product.

		$_d['product.ds.joins']['a2p'] = new Join(
			$_d['a2p.ds'], 'a2p_product = prod_id', 'LEFT JOIN'
		);
		$_d['product.ds.joins']['attribute'] = new Join(
			$_d['attribute.ds'], 'a2p_attribute = atr_id', 'LEFT JOIN');

		$_d['product.ds.columns'][] = 'atr_id';

		$_d['product.callbacks.addfields'][] =
			array(&$this, 'ProductFields');
		$_d['product.callbacks.editfields'][] =
			array(&$this, 'ProductFields');
		$_d['product.callbacks.update']['attribute'] =
			array(&$this, 'ProductUpdate');
		$_d['product.callbacks.props'][] =
			array(&$this, 'ProductProps');

		# Attach to Cart.

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

		global $_d;

		if (@$_d['q'][0] != 'attribute') return;
		$ca = @$_d['q'][1];

		if ($ca == 'update' || $ca == 'create' || $ca == 'delete')
		{
			$type = 'attribute';

			if ($type == 'Atrg')
			{
				$insert['atrg_name'] = GetVar('atrg_name');
				$res['name'] = $insert['atrg_name'];
				$matchcol = 'atrg_id';
				$dsname = 'atrg.ds';
			}
			if ($type == 'attribute')
			{
				$insert['atr_date'] = SqlUnquote('NOW()');
				$insert['atr_name'] = GetVar('name');
				$insert['atr_type'] = GetVar('type');
				$res['name'] = $insert['atr_name'];
				if ($ca == 'create') $insert['atr_atrg'] = GetVar('parent');
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
				$id = $_d['q'][2];
				$_d[$dsname]->Update(array($matchcol => $id),
					$insert);
				$_d['q'][1] = 'view';
			}
			if ($ca == 'delete')
			{
				$id = GetVar('id');
				$_d[$dsname]->Remove(array($matchcol => $id));
			}
		}

		// Old Crap

		if ($ca == "atrg_create")
		{
			$dsAtrgs->Add(array(
				'date' => SqlUnquote("NOW()"),
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
					'date' => SqlUnquote('NOW()'),
					'atrg' => $sel[1],
					'name' => GetVar("name")));
				xslog($_d, "Added attribute " . GetVar("name") . " to {$sel[1]}");
				$_d['ca'] = 'view_atrgs';
			}
			else if ($sel[0] == "attrib")
			{
				$dsOptions->Add(array(
					'date' => SqlUnquote('NOW()'),
					'attrib' => $sel[1],
					'name' => GetVar("name"),
					'formula' => GetVar("formula")));
				xslog($_d, "Added option " . GetVar("name") . " to {$sel[1]}");
			}
			else
			{
				$name = GetVar("name");
				$dsAtrgs->Add(array(SqlUnquote("NULL"), $name, $cl->company->id));
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

		if (@$_d['q'][0] != 'attribute') return;

		if (@$_d['q'][1] == 'prepare')
		{
			$frm = ModAttribute::GetFormAttribute(null, 'Create');
			return GetBox('box_create_attrib', 'Create Attribute',
				$frm->Get('action="{{app_abs}}/attribute/create"'));
		}
		else
		{
			$edAtrs = new EditorData('atrs', $_d['attribute.ds']);
			$edAtrs->Behavior->Search = false;
			$edAtrs->Prepare();
			$ret = $edAtrs->GetUI();

			if (GetVar('atrs_action') == 'edit')
			{
				$ci = GetVar('atrs_ci');
				$ed = new EditorData('opts', $_d['option.ds'],
					array('opt_attrib' => $ci));
				$ed->Behavior->Search = false;
				$ed->Behavior->Target = $_d['app_abs'].'/attribute/view/'.$ci;
				$ed->Prepare();
				$ret .= $ed->GetUI();
			}

			return $ret;
		}
	}

	/**
	* Returns a create or edit form for a given attribute.
	*
	* @param array $a Attribute to populate this form with.
	* @param string $sub_text Text to be displayed on the submit button.
	* @return Form
	*/
	static function GetFormAttribute($a, $sub_text)
	{
		$frm = new Form('frmCreateAttrib');
		$frm->AddInput(new FormInput('Name', 'text', 'name', @$a['atr_name']));
		$frm->AddInput(new FormInput('Type', 'select', 'type',
			ArrayToSelOptions(ModAttribute::GetTypes(), @$a['atr_type'])));
		$frm->AddInput(new FormInput(null, 'submit', null, $sub_text));
		return $frm;
	}

	static function GetTypes()
	{
		return array(0 => 'Select', 1 => 'Numeric');
	}

	# Queries

	static function QueryAttribute($aid)
	{
		global $_d;

		return $_d['attribute.ds']->GetOne(array(
			'match' => array(
				'atr_id' => $aid
			)
		));
	}

	static function QueryAttributes($pid = null)
	{
		global $_d;

		$match = array();
		if (!empty($pid))
			$joins[] = new Join($_d['a2p.ds'], "a2p_attribute = atr_id AND a2p_product = $pid",
				'LEFT JOIN');

		$joins[] = new Join($_d['option.ds'], 'opt_attrib = atr_id',
			'LEFT JOIN');

		return $_d['attribute.ds']->Get(array(
			'match' => $match,
			'joins' => $joins
		));
	}

	# Tags

	function TagAttribs($t, $g)
	{
		global $_d;

		$this->_attribs = $_d['attribute.ds']->Get();
		if (!empty($this->_attribs))
		{
			$t->ReWrite('attrib', array(&$this, 'TagAttrib'));
			return $t->GetString($g);
		}
	}

	function TagAttrib($t, $g, $a)
	{
		$tt = new Template();
		$tt->ReWrite('option', array(&$this, 'TagOption'));
		$tt->ReWrite('selatr', array(&$this, 'TagSelAtr'));
		$ret = null;

		foreach ($this->_attribs as $atr)
		{
			$tt->Set($atr);
			$this->atr = $atr;
			$ret .= $tt->GetString($g);
		}
		return $ret;
	}

	function TagOptions($t, $g)
	{
		if (!empty($this->_options))
		{
			$t->ReWrite('option', array(&$this, 'TagOption'));
			return $t->GetString($g);
		}
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

	# Product

	function ProductFields($form, $prod = null)
	{
		$form->AddInput('Attribute Related');
		$atrs = ModAttribute::QueryAttributes($prod['prod_id']);
		$sels = DataToSel($atrs, 'atr_name', 'atr_id', null, 'None');
		foreach ($atrs as $atr)
			$sels[$atr['atr_id']]->selected = !empty($atr['a2p_product']);
		$form->AddInput(new FormInput('Attribute Set(s)', 'checks', 'atr[]',
			$sels));
	}

	function ProductUpdate($_d, $prod, $id)
	{
		$atrs = GetVar('atr');
		$_d['a2p.ds']->Remove(array('a2p_product' => $id));
		foreach ($atrs as $atr)
		 $_d['a2p.ds']->Add(array(
			'a2p_attribute' => $atr,
			'a2p_product' => $id
		), true);
	}

	function ProductProps(&$_d, $prod)
	{
		$price_offset = 0;

		$outprops = null;

		if (!empty($prod['atr_id']))
		{
			$fp = new CFormulaParser();

			if ($_d['q'][0] == 'cart')
			{
				$dsCart = $_d['cart.ds'];
				$dsCartItem = $_d['cartitem.ds'];
				$dsCartOption = $_d['cartoption.ds'];

				$_d['attribute.joins'][$dsCart->table] =
					new Join($dsCart, "cart.user = {$_d['cl']['usr_id']}");
				$_d['attribute.joins'][$dsCartItem->table] =
					new Join($dsCartItem, "ci.cart = cart.id");
				$_d['attribute.joins'][$dsCartOption->table] =
					new Join($dsCartOption, 'co.cart = cart.id AND co.cartitem
						= ci.id AND co.attribute = attrib.id');

				$_d['attribute.columns']['co.option'] = 'selected';
			}

			$atrs = ModAttribute::QueryAttributes($prod['prod_id']);

			$aid = -1;
			$oid = -1;

			if (!empty($atrs))
			foreach ($atrs as $atr)
			{
				if (empty($atr['a2p_product'])) continue;

				$selname = "atrs[{$atr['atr_id']}]";

				if ($aid != $atr['atr_id'])
				{
					if ($aid != -1)
					{
						$options .= "</select>\n";
						$outprops[$atext] = $options;
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
				$atext = $atr['atr_text'];
			}
			if ($aid != -1)
			{
				$options .= "</select>\n";
				$outprops[$atext] = $options;
			}

			if (!isset($_d['product.totalprice']))
				$_d['product.totalprice'] = $prod['prod_price']+$price_offset;
			else $_d['product.totalprice'] += $price_offset;

			if ($_d['q'][0] == 'cart')
			{
				$outprops['Total Price'] = '<b>$'.($prod['prod_price']+$price_offset).'</b>';
			}
		}
		return $outprops;
	}

	# Cart

	function CartAdd(&$_d, $ciid)
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

?>
