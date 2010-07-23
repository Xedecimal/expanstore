<?php

class ModAttribute extends Module
{
	function __construct($installed)
	{
		if (!$installed) return;

		global $_d;

		# attribute_product

		$_d['a2p.ds'] = new DataSet($_d['db'], 'attribute_product', 'a2p_id');

		# attribute

		$dsAttrib = new DataSet($_d['db'], 'attribute', 'atr_id');
		$dsAttrib->Shortcut = 'attrib';
		$dsAttrib->Description = 'Attribute';
		$dsAttrib->DisplayColumns = array(
			'atr_name' => new DisplayColumn('Name'),
			'atr_text' => new DisplayColumn('Text')
		);
		$dsAttrib->FieldInputs = array(
			'atr_name' => new FormInput('Name'),
			'atr_type' => new FormInput('Type', 'select', 'type',
				ArrayToSelOptions(ModAttribute::GetTypes(), @$a['atr_type'])),
			'atr_text' => new FormInput('Text')
		);
		$_d['attribute.ds'] = $dsAttrib;

		# option

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

		# cart_option

		$dsCartOption = new DataSet($_d['db'], "cart_option");
		$dsCartOption->Shortcut = 'co';
		$_d['cartoption.ds'] = $dsCartOption;

		# pack_prod_option

		$_d['pack_prod_option.ds'] = new DataSet($_d['db'], 'pack_prod_option');
	}

	function Link()
	{
		global $_d;

		# Attach to Navigation

		if (ModUser::RequestAccess(500))
		{
			$_d['page.links']["Control Panel"]["Attributes"] =
				'{{app_abs}}/attribute';
		}

		# Attach to template

		$_d['template.cb.head'][] = array(&$this, 'cb_template_head');

		# Attach to Product.

		$_d['product.ds.query']['joins']['a2p'] = new Join(
			$_d['a2p.ds'], 'a2p_product = prod_id', 'LEFT JOIN'
		);
		$_d['product.ds.query']['joins']['attribute'] = new Join(
			$_d['attribute.ds'], 'a2p_attribute = atr_id', 'LEFT JOIN'
		);
		$_d['product.ds.query']['joins']['option'] = new Join(
			$_d['option.ds'], 'opt_attrib = atr_id', 'LEFT JOIN'
		);

		$_d['product.ds.order']['atr_text'] = 'ASC';

		$_d['product.ds.query']['columns'][] = 'atr_id';
		$_d['product.ds.query']['columns'][] = 'atr_name';
		$_d['product.ds.query']['columns'][] = 'atr_text';
		$_d['product.ds.query']['columns'][] = 'atr_type';
		$_d['product.ds.query']['columns'][] = 'opt_id';
		$_d['product.ds.query']['columns'][] = 'opt_name';
		$_d['product.ds.query']['columns'][] = 'opt_formula';

		$_d['product.callbacks.addfields'][] = array(&$this, 'ProductFields');
		$_d['product.callbacks.editfields'][] = array(&$this, 'ProductFields');
		$_d['product.callbacks.update']['attribute'] = array(&$this, 'ProductUpdate');
		$_d['product.callbacks.props'][] = array(&$this, 'product_props');

		$_d['product.cb.result'][] = array(&$this, 'cb_product_result');

		$_d['cart.query']['columns']['selected'] = 'carto_option';

		# Attach to Cart.

		$_d['cart.query']['joins']['cartoption'] =
			new Join($_d['cartoption.ds'], 'carto_cart = cart_id
				AND carto_item = ci_id
				AND carto_attribute = atr_id', 'LEFT JOIN');

		$_d['cart.callbacks.add'][] = array(&$this, 'cb_cart_add');
		$_d['cart.callbacks.update'][] = array(&$this, 'cb_cart_update');
		$_d['cart.callbacks.remove'][] = array(&$this, 'cb_cart_remove');

		$_d['cart.cb.product.head'][] = array(&$this, 'cb_cart_product_head');
		$_d['cart.cb.product.foot'][] = array(&$this, 'cb_cart_product_foot');

		$dsCartOption = &$_d['cartoption.ds'];
		$dsOption = &$_d['option.ds'];

		$_d['cart.ds.query']['joins'][$dsOption->table] =
			new Join($dsOption, "opt_id = carto_option", 'LEFT JOIN');

		$_d['cart.ds.query']['joins']['attribute'] = new Join(
			$_d['attribute.ds'], 'carto_attribute = atr_id', 'LEFT JOIN');

		$_d['cart.columns'][] = 'opt_formula';
		$_d['cart.columns'][] = 'carto_option';

		# Attach to Payment

		$_d['payment.cb.checkout.item'][] = array(&$this, 'cb_payment_checkout_item');
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
			$ret = null;

			$atrs_action = GetVar('atrs_action');

			$edAtrs = new EditorData('atrs', $_d['attribute.ds']);
			$edAtrs->AddHandler(new EdAtrHandler);
			$edAtrs->Behavior->Search = false;
			$edAtrs->Prepare();
			$ret .= $edAtrs->GetUI();

			if ($atrs_action == 'edit')
			{
				$ci = GetVar('atrs_ci');
				$item = $_d['attribute.ds']->Get(array('match' => array(
					'atr_id' => $ci
				)));

				if ($item[0]['atr_type'] == 0) // Select
				{
					$ed = new EditorData('opts', $_d['option.ds'],
						array('opt_attrib' => $ci));
					$ed->Behavior->Search = false;
					$ed->Behavior->Target = "{$_d['app_abs']}/attribute/view/$ci" ;
					$ed->Prepare();
					$ret .= $ed->GetUI();
				}
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

		if (!empty($_d['attribute.ds.joins']))
			$joins += $_d['attribute.ds.joins'];

		return $_d['attribute.ds']->Get(array(
			'match' => $match,
			'joins' => $joins,
			'columns' => @$_d['attribute.ds.columns'],
			'sort' => 'atr_text'
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

	### Template

	function cb_template_head()
	{
		return '<script type="text/javascript" src="'.p('attribute/js.js').'"></script>';
	}

	### Product

	# Collapse all attributes to avoid duplicate products due to left joins.
	function cb_product_result($items)
	{
		$ret = array();

		foreach ($items as $i)
		{
			# Create a single copy of this product for return.

			if (empty($ret[$i['prod_id']])) $ret[$i['prod_id']] = $i;

			if (empty($ret[$i['prod_id']]['atrs'][$i['atr_id']]))
				$ret[$i['prod_id']]['atrs'][$i['atr_id']] = $i;

			if (empty($ret[$i['prod_id']]['atrs'][$i['atr_id']]['opts'][$i['opt_id']]))
				$ret[$i['prod_id']]['atrs'][$i['atr_id']]['opts'][$i['opt_id']] = $i;
		}

		foreach ($ret as &$v)
			$v = $this->product_props($v);

		return $ret;
	}

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
		if (!empty($atrs))
		foreach ($atrs as $atr)
		 $_d['a2p.ds']->Add(array(
			'a2p_attribute' => $atr,
			'a2p_product' => $id
		), true);
	}

	function product_props($prod)
	{
		global $_d;

		$price_offset = 0;

		if (!empty($prod['atr_id']))
		{
			$fp = new CFormulaParser();

			if (!empty($prod['atrs']))
			foreach ($prod['atrs'] as $atr)
			{
				if ($atr['atr_type'] == 0) // Select
				{
					$selname = "atrs[{$atr['atr_id']}]";
					$options = '<select name="'.$selname.'" class="product_value">'."\n";

					foreach ($atr['opts'] as $opt)
					{
						if (empty($opt['opt_formula'])) $result = 0;
						else $result = $fp->GetFormula($prod, $opt['opt_formula']);

						$selected = null;
						if (isset($opt['selected']))
						{
							if ($opt['selected'] == $opt['opt_id'])
							{
								$price_offset += $result;
								$selected = ' selected="true"';
							}
						}

						$options .= "<option value=\"{$opt['opt_id']}\"$selected>"
							.htmlspecialchars($opt['opt_name']);
						if ($result != 0) $options .= " $".($result > -1 ? '+' : null)
							."$result</option>";
						else $options .= "</option>";
					}

					$options .= "</select>\n";
					$prod['props'][$atr['atr_text']] = $options;
				}
				else if ($atr['atr_type'] == 1) // Numeric
				{
					if (empty($atr['opt_formula'])) $result = 0;
					else
					{
						$prod['value'] = @$atr['selected'];
						$result = $fp->GetFormula($prod, $atr['opt_formula']);
					}

					$val = '';
					if (!empty($atr['selected'])) $val = ' value="'.$atr['selected'].'"';
					if (!empty($result)) $price_offset += $result;

					$prod['props'][$atr['atr_name']] = '<input type="text" name="atrs['.$atr['atr_id'].']" class="product_value"'.$val.' />';
				}
			}

			if (!isset($_d['product.totalprice'])) $_d['product.totalprice'] = $price_offset;
			else $_d['product.totalprice'] += $prod['prod_price']+$price_offset;
			if (empty($price_offset)) $price_offset = $prod['prod_price'];

			$prod['prod_price'] = number_format($price_offset, 2);
		}
		return $prod;
	}

	### Cart

	function cb_cart_product_head()
	{
		global $_d;

		$id = $_d['cart.item']['ci_id'];
		return '<form method="post" action="{{app_abs}}/cart/update/'.$id
			.'" id="frmCart_'.$id.'" class="form">';
	}

	function cb_cart_product_foot()
	{
		return '</form>';
	}

	function cb_cart_add($cid, $ciid)
	{
		global $_d;

		$atrs = GetVar("atrs");

		if (!empty($atrs))
		{
			$dsCartOptions = $_d['cartoption.ds'];

			foreach ($atrs as $atr => $opt)
			{
				$dsCartOptions->Add(array(
					'carto_cart' => $cid,
					'carto_item' => $ciid,
					'carto_attribute' => $atr,
					'carto_option' => $opt));
			}
		}
	}

	function cb_cart_update($cid, $ciid)
	{
		global $_d;

		$atrs = GetVar('atrs');

		if (!empty($atrs))
		{
			$dsCartOptions = $_d['cartoption.ds'];
			$ci = $_d['q'][2];

			foreach ($atrs as $atr => $opt)
			{
				$dsCartOptions->Add(array(
					'carto_cart' => $cid,
					'carto_option' => $opt,
					'carto_item' => $ci,
					'carto_attribute' => $atr
				), true);
			}
		}
	}

	function cb_cart_remove($id)
	{
		global $_d;

		$_d['cartoption.ds']->Remove(array('carto_item' => $id));
	}

	### Payment

	function cb_payment_checkout_item($pid, $item)
	{
		global $_d;

		foreach ($item['atrs'] as $atr)
		{
			if ($atr['atr_type'] == 0) # Select
				$val = $atr['opts'][$atr['selected']]['opt_name'];
			else
				$val = $atr['selected'];

			$_d['pack_prod_option.ds']->Add(array(
				'ppo_pprod' => $pid,
				'ppo_attribute' => $atr['atr_text'],
				'ppo_value' => $val
			));
		}
	}

	function AttributesToTree($atrs)
	{
		$ret = array();
		foreach ($atrs as $atr)
		{
			if (!isset($ret[$atr['atr_id']])) $ret[$atr['atr_id']] = $atr;

			if ($atr['atr_type'] == 0) // Select
			{
				$ret[$atr['atr_id']]['options'][$atr['opt_id']] = $atr;
			}
		}
		return $ret;
	}
}

Module::Register('ModAttribute');

class EdAtrHandler extends EditorHandler
{
	function GetFields($s, &$form, $id, $data)
	{
		$form->AddInput(new FormInput('Formula', 'text', 'formula',
			$data[0]['opt_formula']));
	}

	function Update($s, $id, &$original, &$update)
	{
		global $_d;

		if ($update['atr_type'] == 1)
		{
			$_d['option.ds']->Remove(array('opt_attrib' => $id));
			$_d['option.ds']->Add(array(
				'opt_date' => SqlUnquote('NOW()'),
				'opt_attrib' => $id,
				'opt_formula' => GetVar('formula')
			), true);
		}
	}

	function GetJoins()
	{
		global $_d;
		return array('option' => new Join($_d['option.ds'],
			'opt_attrib = atr_id', 'LEFT JOIN'));
	}
}

?>
