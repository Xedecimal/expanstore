<?php

Module::RegisterModule('ModDetail');

global $prop_types;

$prop_types = array(
	new SelOption("Text", 0),
	new SelOption("Number", 1)
);

/**
 * Add to $form a form input for spec prop $spec_prop.
 *
 * @param Form $form
 * @param Array $spec_prop
 */
function AddSpecProps($form, $spec_props)
{
	foreach ($spec_props as $pid => $prod)
	{
		$sels = array(-1 => new SelOption('None'));

		foreach ($prod as $did => $vals)
		{
			if (strlen($vals['value']) < 1) continue;
			if (isset($vals['sel']) && strlen($vals['sel']) > 0) $sel = true;
			else $sel = false;
			$sels[$did] = new SelOption($vals['value'], false, $sel);
		}

		$form->AddInput(new FormInput("{$vals['name']}", 'select',
			"props[{$pid}]", $sels),
			new FormInput('Create New', 'text', "props_new[{$pid}]"));
	}
}

function QuerySpecProps(&$_d, $prod)
{
	$sp = $_d['specprod.ds'];
	$spp = $_d['specpropprod.ds'];
	$sd = $_d['specprop.ds'];

	$joins = array(
		new Join($sd, 'sprod_prop = sprop_id', 'LEFT JOIN'),
		new Join($spp, 'spp_prod = sprod_id', 'LEFT JOIN')
	);

	return $sp->Get(array('spp_prod' => $prod), null, null, $joins);
}

function QuerySpecProp(&$_d, $id)
{
	return $_d['specprop.ds']->GetOne(array("id" => $id));
}

function QuerySpec(&$_d, $id)
{
	return $_d['spec.ds']->GetOne(array('spec_id' => $id));
}

function QueryProps(&$_d, $spec)
{
	return $_d['specprop.ds']->Get(array('sprop_spec' => $spec));
}

function QueryPropsByCat(&$_d, $cat)
{
	$dsSpec = $_d['spec.ds'];
	$dsProp = $_d['specprop.ds'];
	$dsProd = $_d['specprod.ds'];
	$dsCat = $_d['category.ds'];
	$dsSpecCat = $_d['spec_cat.ds'];

	$q['match'] = array('cat_id' => $cat);

	$q['joins'] = array(
		new Join($dsSpec, 'sprop_spec = spec_id'),
		new Join($dsSpecCat, 'sc_spec = spec_id'),
		new Join($dsCat, 'cat_id = sc_cat'),
		new Join($dsProd, 'sprod_prop = sprop_id', 'LEFT JOIN')
	);

	$q['columns'] = array(
		'sprop_id',
		'sprop_name',
		'sprod_id',
		'sprod_value'
	);

	return $dsProp->Get($q);
}

function QuerySPP(&$_d)
{
	$spec = isset($_d['category.current']) ?
		$_d['category.current']['sc_spec'] : 0;

	$joins = array(
		new Join($_d['specprod.ds'], 'sprod_prop = sprop_id'),
		new Join($_d['specpropprod.ds'], 'spp_prod = sprod_id'),
		new Join($_d['spec.ds'], 'sprop_spec = spec_id'),
		new Join($_d['spec_cat.ds'], 'sc_spec = spec_id'),
		new Join($_d['category.ds'], 'cat_id = sc_cat')
	);

	$cols = array(
		'sprop_id',
		'sprop_name',
		'sprod_id',
		'sprod_value'
	);

	$match = array('cat_id' => GetVar('cc'));

	$dp = $_d['specprop.ds'];

	return $dp->Get($match, array('sprop_id' => 'ASC', 'sprod_id' => 'ASC'), null,
		$joins, $cols);
}

function QueryUnusedSPP(&$_d, $spec)
{
	$match = array('sprop_spec' => $spec, 'sprod_id IS NULL');

	$joins = array(
		new Join($_d['specprop.ds'], 'sprod_prop = sprop_id'),
		new Join($_d['specpropprod.ds'], 'sprod_prop = sprop_id', 'LEFT JOIN')
	);

	$cols = array(
		'sprod_id',
		'sprop_name',
		'sprod_value'
	);

	return $_d['specprod.ds']->Get($match, null, null, $joins, $cols);
}

class ModDetail extends Module
{
	function __construct($inst)
	{
		global $_d;

		if (!$inst) return;

		$dsSpecs = new DataSet($_d['db'], "spec");
		$dsSpecs->Shortcut = 's';
		$_d['spec.ds'] = $dsSpecs;

		$dsSpecProd = new DataSet($_d['db'], 'spec_prod');
		$dsSpecProd->Shortcut = 'sprod';
		$_d['specprod.ds'] = $dsSpecProd;

		$dsSpecProp = new DataSet($_d['db'], "spec_prop");
		$dsSpecProp->Shortcut = 'sprop';
		$_d['specprop.ds'] = $dsSpecProp;

		$dsSPP = new DataSet($_d['db'], 'spec_prop_prod');
		$dsSPP->Shortcut = 'spp';
		$_d['specpropprod.ds'] = $dsSPP;

		$dsSC = new DataSet($_d['db'], 'spec_cat');
		$dsSC->Shortcut = 'sc';
		$_d['spec_cat.ds'] = $dsSC;
	}

	function Install()
	{
		$data = <<<EOF
CREATE TABLE IF NOT EXISTS `spec` (
  `spec_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spec_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `spec_company` bigint(20) unsigned NOT NULL DEFAULT '0',
  `spec_name` varchar(255) NOT NULL,
  `spec_text` varchar(255) NOT NULL,
  PRIMARY KEY (`spec_id`) USING BTREE,
  KEY `idxCompany` (`spec_company`) USING BTREE,
  CONSTRAINT `fkSpec_Comp` FOREIGN KEY (`spec_company`) REFERENCES `company` (`comp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `spec_prop` (
  `sprop_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sprop_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `sprop_spec` bigint(20) unsigned NOT NULL DEFAULT '0',
  `sprop_type` int(11) NOT NULL DEFAULT '0',
  `sprop_name` varchar(255) NOT NULL,
  PRIMARY KEY (`sprop_id`) USING BTREE,
  KEY `idxSpec` (`sprop_spec`) USING BTREE,
  CONSTRAINT `fkSProp_Spec` FOREIGN KEY (`sprop_spec`) REFERENCES `spec` (`spec_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `spec_prod` (
  `sprod_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sprod_prop` bigint(20) unsigned NOT NULL DEFAULT '0',
  `sprod_value` varchar(255) NOT NULL,
  PRIMARY KEY (`sprod_id`) USING BTREE,
  UNIQUE KEY `idxValue` (`sprod_prop`,`sprod_value`) USING BTREE,
  CONSTRAINT `fkSProd_SProp` FOREIGN KEY (`sprod_prop`) REFERENCES `spec_prop` (`sprop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `spec_cat` (
  `sc_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sc_spec` bigint(20) unsigned NOT NULL,
  `sc_cat` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`sc_id`),
  KEY `fk_sc_cat` (`sc_cat`),
  KEY `fk_sc_spec` (`sc_spec`),
  CONSTRAINT `fk_sc_cat` FOREIGN KEY (`sc_cat`) REFERENCES `category` (`cat_id`),
  CONSTRAINT `fk_sc_spec` FOREIGN KEY (`sc_spec`) REFERENCES `spec` (`spec_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `spec_prop_prod` (
  `spp_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spp_sprop` bigint(20) unsigned NOT NULL,
  `spp_sprod` bigint(20) unsigned NOT NULL,
  `spp_prod` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`spp_id`) USING BTREE,
  KEY `idxProp` (`spp_sprop`) USING BTREE,
  KEY `idxProd` (`spp_sprod`) USING BTREE,
  KEY `idxProduct` (`spp_prod`) USING BTREE,
  CONSTRAINT `fkSpp_Prod` FOREIGN KEY (`spp_prod`) REFERENCES `product` (`prod_id`),
  CONSTRAINT `fkSpp_SProd` FOREIGN KEY (`spp_sprod`) REFERENCES `spec_prod` (`sprod_id`),
  CONSTRAINT `fkSpp_SProp` FOREIGN KEY (`spp_sprop`) REFERENCES `spec_prop` (`sprop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=FIXED;
EOF;

		global $_d;
		$_d['db']->Queries($data);
	}

	function Link()
	{
		global $_d;

		if (!isset($_d['category.ds'])) return;

		// Attach to Navigation.

		if (!empty($_d['cl']) && $_d['cl']['usr_access'] >= 500)
		{
			$_d['page.links']['Admin']['Product Details'] =
				htmlspecialchars("{{me}}?cs=detail");
		}

		// Attach to Product.

		$_d['product.callbacks.props'][] = array(&$this, 'ProductProps');
		$_d['product.callbacks.addfields'][] = array(&$this, 'ProductAddFields');
		$_d['product.callbacks.editfields'][] = array(&$this, 'ProductEditFields');
		$_d['product.callbacks.update'][] = array(&$this, 'ProductAddUpdate');
		$_d['product.callbacks.add'][] = array(&$this, 'ProductAddUpdate');
		$_d['product.callbacks.delete'][] = array(&$this, 'ProductDelete');

		// Attach to Category.

		$_d['category.ds.columns'][] = 'sc_spec';

		$_d['category.ds.joins']['sc'] =
			new Join($_d['spec_cat.ds'], 'sc_cat = cat_id', 'LEFT JOIN');

		$_d['category.callbacks.fields'][] = array(&$this, 'CategoryFields');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		$ca = $_d['q'][0];

		if ($ca == 'update_spec')
		{
			$dsSpecs->Update(array('id' => GetVar('ci')),
			array(
				'name' => GetVar('sname'),
				'text' => GetVar('text')
			));
			$_d['ca'] = 'view_spec';
		}

		else if ($ca == 'update_spec_prop')
		{
			$dsSpecProp->Update(array('id' => GetVar('ci')),
			array(
				'type' => GetVar('type'),
				'name' => GetVar('name')
			));
			$_d['ca'] = 'view_spec';
		}

		else if ($ca == 'del_spec_prop')
		{
			$dsSpecProp->Remove(array('id' => GetVar('ci')));
			$_d['ca'] = 'view_spec';
		}

		else if ($ca == 'create_spec')
		{
			$_d['spec.ds']->Add(array(
				'spec_date' => SqlUnquote('NOW()'),
				'spec_company' => $_d['cl']['c2u_company'],
				'spec_name' => GetVar('name')
			));
			$ca = 'view_specs';
		}

		else if ($ca == 'create_spec_prop')
		{
			$dsSpecProp->Add(array(
				'date' => SqlUnquote('NOW()'),
				'spec' => GetVar('ci'),
				'type' => GetVar('type'),
				'name' => GetVar('name')
			));
			$_d['ca'] = 'view_spec';
		}

		else if ($ca == "del_spec")
		{
			$dsSpecs->Remove(array('id' => GetVar('ci')));
			$ca = 'view_specs';
		}

		else if ($ca == 'del_sprod')
		{
			$_d['specprod.ds']->Remove(array('id' => GetVar('ci')));
			$_d['ca'] = 'view_spec';
		}
	}

	function CategoryFields(&$_d, $form, $cat = null)
	{
		$specs = $_d['spec.ds']->Get();
		$default = isset($cat['spec']) ? $cat['spec'] : 0;
		$form->AddInput(new FormInput("Details", "select", "spec",
			DataToSel($specs, 'spec_name', 'spec_id', $default, "None")));
	}

	function ProductProps($_d, $prod)
	{
		$t = new Template($_d);
		$ret = null;

		$props = QuerySpecProps($_d, $prod['prod_id']);
		if (!empty($props))
		foreach ($props as $prop)
		{
			$t->Set(array(
				'prop' => htmlspecialchars($prop['name']),
				'value' => htmlspecialchars($prop['value'])
			));
			$ret .= $t->ParseFile($_d['tempath'].'catalog/product_property.html');
		}
		return $ret;
	}

	function ProductAddFields($_d, $form)
	{
		$sprops = QueryPropsByCat($_d, @$_SESSION['category']);

		$props = GetVar('props');
		if (!empty($sprops))
		{
			foreach ($sprops as $sprop)
				$newprops[$sprop['pid']][$sprop['did']] = $sprop;

			$form->AddRow(array('<div class="form_separator">Details Related</div>'));
			AddSpecProps($form, $newprops);
		}
	}

	function ProductEditFields($_d, $prod, $form)
	{
		#$sprops = QueryPropsByProd($_d, $prod['prod_id']);

		if (!empty($sprops))
		{
			//Build array of specprops...
			foreach ($sprops as $sprop)
				$newprops[$sprop['pid']][$sprop['did']] = $sprop;

			$form->AddInput('Details Related');
			AddSpecProps($form, $newprops);
		}
	}

	function ProductAddUpdate($_d, $prod, $newid = null)
	{
		$props = GetVar('props');
		$newprops = GetVar('props_new');

		//Remove old properties (update)
		if (isset($newid))
			$_d['specpropprod.ds']->Remove(array('spp_prod' => $newid));

		//Create new properties (add/update)
		if (!empty($props))
		{
			foreach ($props as $propid => $prop)
			{
				// Create a new property.
				if (strlen($newprops[$propid]) > 0)
				{
					$insid = $_d['specprod.ds']->Add(array(
						'prop' => $propid,
						'value' => $newprops[$propid]
					));
				}
				else $insid = $prop;

				if ($insid != -1)
				$_d['specpropprod.ds']->Add(array(
					'prop' => $propid,
					'prod' => $insid,
					'product' => $newid,
				), true);
			}
		}
	}

	function ProductDelete()
	{
		global $_d;
		$_d['specpropprod.ds']->Remove(array('spp_prod' => $_d['q'][2]));
	}

	function Get()
	{
		global $me, $_d;

		$ca = GetVar('ca');

		if ($ca == "view_spec")
		{
			$spec = QuerySpec($_d, GetVar('ci'));

			$_d['page_title'] .= ' - View Specification';
			$formProps = new Form('formProps');
			$formProps->AddHidden('cs', GetVar('cs'));
			$formProps->AddHidden('ca', 'update_spec');
			$formProps->AddHidden('ci', GetVar('ci'));
			$formProps->AddInput(new FormInput('Name', 'text', 'sname',
				$spec['spec_name']));
			$formProps->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Update'));
			$body = GetBox('box_props', 'View Specification',
				$formProps->Get('action="{{me}}" method="post"'));

			$props = QueryProps($_d, GetVar('ci'));

			global $prop_types;

			if (is_array($props))
			{
				$tblProps = new Table("tblProps", array('<b>Type</b>',
					'<b>Name</b>'));
				foreach ($props as $prop)
				{
					$urlEdit = URL('{{me}}', array(
						'cs' => GetVar('cs'),
						'ci' => $prop['id'],
						'spec' => GetVar('ci'),
						'ca' => 'view_spec_prop'));

					$urlDel = URL('{{me}}', array(
						'cs' => GetVar('cs'),
						'ci' => $prop['id'],
						'spec' => GetVar('ci'),
						'ca' => "del_spec_prop"));

					$tblProps->AddRow(array(
						$prop_types[$prop['type']]->text,
						$prop['name'],
						GetButton($urlEdit, 'edit.png', 'Edit').' '.
						GetButton($urlDel, 'delete.png', 'Delete')
					));
				}
				$body .= GetBox("box_proplist", "Properties", $tblProps->Get());
			}

			$formCreateProp = new Form('formCreateProp');
			$formCreateProp->AddHidden('cs', GetVar('cs'));
			$formCreateProp->AddHidden('ca', 'create_spec_prop');
			$formCreateProp->AddHidden('ci', GetVar('ci'));
			$formCreateProp->AddInput(new FormInput('Type', 'select', 'type',
				$prop_types, 'style="width: 100%"'));
			$formCreateProp->AddInput(new FormInput('Name', 'text', 'name'));
			$formCreateProp->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Create'));
			$body .= GetBox('box_createProp',
				'Create Property', $formCreateProp->Get('action="{{me}}"
				method="post"'));

			$spps = QueryUnusedSPP($_d, GetVar('ci'));

			if (!empty($spps))
			{
				$tblUnused = new Table('tblUnused', array('<b>Property</b>',
					'<b>Value</b>'));

				foreach ($spps as $spp)
				{
					$but = GetButton(URL('{{me}}', array('cs' => 'detail',
						'ca' => 'del_sprod', 'ci' => $spp['did'],
						'spec' => GetVar('ci'))), 'delete.png', 'Delete');
					$tblUnused->AddRow(array($spp['name'], $spp['value'], $but));
				}

				$body .= GetBox('box_unused', 'Unused Values',
					$tblUnused->Get());
			}
			return $body;
		}
		else if ($ca == "view_spec_prop")
		{
			global $prop_types;
			$prop = QuerySpecProp($_d, GetVar('ci'));
			$_d['page_title'] .= ' - View Property';
			$formProps = new Form('formProps');
			$formProps->AddHidden('cs', GetVar('cs'));
			$formProps->AddHidden('ca', 'update_spec_prop');
			$formProps->AddHidden('ci', GetVar('ci'));
			$formProps->AddHidden('spec', GetVar('spec'));
			$formProps->AddInput(new FormInput('Type', 'select', 'type',
				$prop_types, 'style="width: 100%"'));
			$formProps->AddInput(new FormInput('Name', 'text', 'name',
				$prop['name']));
			$formProps->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Update'));
			return GetBox('box_props', 'View Property',
				$formProps->Get('action="{{me}}" method="post"'));
		}
		else
		{
			if ($_d['q'][0] != 'detail') return;

			$GLOBALS['page_section'] = "Specifications";
			$dsSpecs = $_d['spec.ds'];

			$specs = $dsSpecs->Get('spec_company', $_d['cl']['c2u_company']);
			$out = "";

			//Attribute Groups
			if (is_array($specs))
			{
				$tblSpecs = new Table('tblSpecs', array('<b>Name</b>'));
				foreach($specs as $spec)
				{
					$urlEdit = URL($me, array(
						'cs' => GetVar('cs'),
						'ci' => $spec[0],
						'ca' => 'view_spec'
					));

					$urlDel = URL($me, array(
						'cs' => GetVar('cs'),
						'ci' => $spec[0],
						'ca' => 'del_spec'
					));
					$tblSpecs->AddRow(array($spec[3],
						GetButton($urlEdit, 'edit.png', 'Edit'),
						GetButton($urlDel, 'delete.png', 'Delete')));
				}
				$out .= GetBox('box_groups', "Your Company's Details",
					$tblSpecs->Get());
			}

			//Create attribute group.
			$frmCreateDetail = new Form("formCreateDetail");
			$frmCreateDetail->AddHidden("cs", GetVar('cs'));
			$frmCreateDetail->AddHidden("ca", "create_spec");
			$frmCreateDetail->AddInput(new FormInput("Name", "text", "name", null, 'size="50"'));
			$frmCreateDetail->AddInput(new FormInput("", "submit", "butSubmit", "Create"));
			$out .= GetBox("box_create",
				'Create Detail',
				$frmCreateDetail->Get('action="{{me}}" method="post"'));

			return $out;
		}
	}
}

?>
