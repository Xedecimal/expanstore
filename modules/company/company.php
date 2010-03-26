<?php

function RequestCompany($comp)
{
	global $_d;

	if (empty($_d['cl']['comp_id'])) return false;
	if ($_d['cl']['comp_id'] == $comp) return true;
	return false;
}

function eCompany_Email_Callback($ds, $item, $column)
{
	return '<a href="mailto:'.$item[$column].'">'.$item[$column].'</a>';
}

function QueryCompanies($match = null)
{
	global $_d;

	return $_d['company.ds']->Get($match);
}

function QueryCompany($id)
{
	$res = QueryCompanies(array('comp_id' => $id));
	return $res[0];
}

/**
* The main interface to manage your Company.
*/
class ModCompany extends Module
{
	function __construct($inst)
	{
		global $_d;

		if (!$inst) return;

		$dsCompany = new DataSet($_d['db'], 'company', 'comp_id');
		$dsCompany->Shortcut = 'c';
		$dsCompany->Description = 'Company';
		$dsCompany->ErrorHandler = array(&$this, 'DataError');

		$dsCompany->DisplayColumns = array(
			'comp_name' => new DisplayColumn('Name'),
			'comp_contact' => new DisplayColumn('Contact'),
			'comp_email' => new DisplayColumn('Email', 'eCompany_Email_Callback')
		);

		$dsCompany->FieldInputs = array(
			'comp_name' => new FormInput('Name'),
			'comp_email' => new FormInput('EMail'),
			'comp_contact' => new FormInput('Contact'),
			'comp_address' => new FormInput('Address'),
			'comp_city' => new FormInput('City'),
			'comp_state' => new FormInput('State'),
			'comp_zip' => new FormInput('Zip'),
			'comp_phone' => new FormInput('Phone')
		);

		$_d['company.ds'] = $dsCompany;

		// dsCompProd

		$dsCompProd = new DataSet($_d['db'], 'comp_prod');
		$_d['compprod.ds'] = $dsCompProd;

		// dsC2U

		$dsCompUser = new DataSet($_d['db'], 'comp_user');
		$_d['compuser.ds'] = $dsCompUser;
	}

	function Install()
	{
		$data = <<<EOF
CREATE TABLE IF NOT EXISTS `company` (
  `comp_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `comp_name` varchar(100) NOT NULL,
  `comp_email` varchar(100) NOT NULL,
  `comp_contact` varchar(100) NOT NULL,
  `comp_address` varchar(100) NOT NULL,
  `comp_city` varchar(100) NOT NULL,
  `comp_state` varchar(100) NOT NULL,
  `comp_zip` varchar(100) NOT NULL,
  `comp_phone` varchar(100) NOT NULL,
  `comp_about` mediumtext NOT NULL,
  PRIMARY KEY (`comp_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `comp_prod` (
  `cp_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cp_comp` bigint(20) unsigned NOT NULL,
  `cp_prod` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`cp_id`),
  KEY `fk_cp_comp` (`cp_comp`),
  KEY `fk_cp_prod` (`cp_prod`),
  CONSTRAINT `fk_cp_comp` FOREIGN KEY (`cp_comp`) REFERENCES `company` (`comp_id`),
  CONSTRAINT `fk_cp_prod` FOREIGN KEY (`cp_prod`) REFERENCES `product` (`prod_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `comp_user` (
  `c2u_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `c2u_company` bigint(20) unsigned NOT NULL,
  `c2u_user` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`c2u_id`),
  UNIQUE KEY `idxUnique` (`c2u_company`,`c2u_user`),
  KEY `idxCompany` (`c2u_company`),
  KEY `idxUser` (`c2u_user`),
  CONSTRAINT `fk_c2u_company` FOREIGN KEY (`c2u_company`) REFERENCES `company` (`comp_id`),
  CONSTRAINT `fk_c2u_user` FOREIGN KEY (`c2u_user`) REFERENCES `user` (`usr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
			$_d['page.links']['Admin']['Companies'] =
				'{{app_abs}}/company';
		}

		if (isset($_d['cl']['company']))
		{
			$_d['page.links']["Control Panel"]["Company"] =
				'{{me}}?cs=company&amp;ca=view_company';
		}

		// Connect to User.

		$_d['user.ds.joins']['compuser'] =
			new Join($_d['compuser.ds'], 'c2u_user = usr_id', 'LEFT JOIN');

		/*if ($_d['cl']['usr_access'] > 500)
		{
			$_d['user.ds.handlers']['company'] = new CompanyUserHandler();
			$sels = DataToSel(QueryCompanies(), 'comp_name', 'comp_id', 0, 'None');
			$_d['user.ds']->FieldInputs['c2u_company'] =
				new FormInput('Company', 'select', null, $sels);
		}*/

		// Connect to Product.

		$_d['product.ds.columns'][] = 'comp_id';
		$_d['product.ds.columns'][] = 'comp_name';

		$_d['product.callbacks.props']['company'] =
			array(&$this, 'CallbackProductProps');

		$_d['product.ds.joins']['compprod'] =
			new Join($_d['compprod.ds'], 'cp_prod = prod_id', 'LEFT JOIN');

		$_d['product.ds.joins']['company'] =
			new Join($_d['company.ds'], 'cp_comp = comp_id', 'LEFT JOIN');

		$_d['product.callbacks.admin']['company'] =
			array(&$this, 'product_admin');
	}

	function Prepare()
	{
		parent::Prepare();
		global $_d;
	}

	function product_admin($prod)
	{
		global $_d;

		if ($_d['cl']['usr_access'] > 500) return 1;
		if ($prod['comp_id'] == $_d['cl']['comp_id']) return 1;
	}

	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'company') return;

		$ca = GetVar('ca');

		if ($ca == 'view')
		{
			$ci = GetVar('ci');

			$comp = QueryCompany($_d, $ci);

			$ret = null;

			$tblcomp = new Table("tableDetails", array("", ""), array('align="right" valign="top"', ""));
			if (isset($comp->logo)) $tblcomp->AddRow(array("<img src=\"{$comp->logo}\" alt=\"{$comp->name}\">"));
			$tblcomp->AddRow(array("Contact:", $comp['contact']));
			$tblcomp->AddRow(array("Address:", $comp['address']));
			$tblcomp->AddRow(array("City:", $comp['city']));
			$tblcomp->AddRow(array("State:", $comp['state']));
			$tblcomp->AddRow(array("Zip:", $comp['zip']));
			$tblcomp->AddRow(array("Phone:", $comp['phone']));

			$ret .= GetBox("box_comp", "Company Details - {$comp['name']}", $tblcomp->Get());

			if (strlen($comp['about']) > 0)
			{
				$ret .= GetBox("box_sum", "Company Summary", $comp['about']);
			}

			$frmContact = new Form('formContact');
			$frmContact->AddHidden('ca', 'comp_mail');
			$frmContact->AddHidden('ci', $ci);
			$frmContact->AddInput(new FormInput('From', 'text', 'from',
				isset($_d['cl']) ? $_d['cl']['email'] : null, 'size="50"'));
			$frmContact->AddInput(new FormInput('Subject', 'text', 'subject',
				null, 'size="50"'));
			$frmContact->AddInput(new FormInput('Body', 'area', 'body', null,
				'rows="5" cols="40"'));
			$frmContact->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Send'));
			$ret .= GetBox("box_contact",
				"Contact {$comp['name']}",
				$frmContact->Get("action=\"{{me}}\" method=\"post\""));

			return $ret;
		}

		$edCompany = new EditorData('company', $_d['company.ds']);
		$edCompany->Behavior->Target = 'company';
		$edCompany->Prepare();
		return $edCompany->GetUI('cs');

		return $ret;
	}

	function CallbackProductProps($_d, $prod)
	{
		if (!empty($prod['company']))
		{
			return array('Company' =>
				'<a href="{{me}}?cs=company&amp;ca=view&amp;ci='.
					$prod['company'].'">'.stripslashes($prod['cname']).'</a>');
		}
	}
}

Module::RegisterModule('ModCompany');

class CompanyUserHandler extends EditorHandler
{
	function Update($s, $id, &$original, &$update)
	{
		global $_d;

		$_d['compuser.ds']->Add(array(
			'c2u_user' => $update['usr_id'],
			'c2u_company' => $update['c2u_company']
		), true);
	}
}

class ModCompanyDisplay extends DisplayObject
{
	function Get()
	{
		if ($ca == 'view_company')
		{
			$GLOBALS['page_section'] = "Company Details";

			$comp = $_d['company.ds']->GetOne(array('id' => $_d['cl']['company']));

			//Company Attributes
			$frmCompany = new Form("formCompany");
			$frmCompany->AddHidden("cs", $_d['cs']);
			$frmCompany->AddHidden("ca", "comp_update");
			$frmCompany->AddInput(new FormInput('Name', 'text', 'name',
				$comp['name'], 'size="50"'));
			$frmCompany->AddInput(new FormInput('EMail', 'text', 'email',
				$comp['email'], 'size="50"'));
			$frmCompany->AddInput(new FormInput('Contact Name', 'text',
				'contact', $comp['contact'], 'size="50"'));
			$frmCompany->AddInput(new FormInput('Address', 'text', 'address',
				$comp['address'], 'size="50"'));
			$frmCompany->AddInput(new FormInput('City', 'text', 'city',
				$comp['city'], 'size="50"'));
			$frmCompany->AddInput(new FormInput('State', 'text', 'state',
				$comp['state'], 'size="50"'));
			$frmCompany->AddInput(new FormInput('Zip', 'text', 'zip',
				$comp['zip'], 'size="50"'));
			$frmCompany->AddInput(new FormInput('Phone', 'text', 'phone',
				$comp['phone'], 'size="50"'));
			$frmCompany->AddInput(new FormInput('', 'submit', 'butSubmit',
				"Update"));
			$body = GetBox("box_comp",
				"Your Company",
				$frmCompany->Get('action="{{me}}" method="post"'));

			//Display Summary
			if (isset($comp->summary) && strlen($comp->summary) > 0)
			{
				$body .= GetBox("box_summary", "Company Summary",
					$comp->summary);
			}

			//Update Summary
			$frmUpdate = new Form("formUpdate");
			$frmUpdate->AddHidden("cs", $_d['cs']);
			$frmUpdate->AddHidden("ca", "comp_desc_update");
			$frmUpdate->AddInput(new FormInput('area', 'body', $comp['about'],
				'rows="5" style="width: 100%"'));
			$frmUpdate->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Update'));
			$body .= GetBox('box_update_sum', 'Update Company Summary',
			$frmUpdate->Get('action="{{me}}" method="post"', 'width="100%"'));

			//Company Logo
			$frmLogo = new Form("formLogo");
			$frmLogo->AddHidden("cs", $_d['cs']);
			$frmLogo->AddHidden("ca", "comp_uplogo");

			if (file_exists("compimages/{$comp['id']}/l.png"))
			{
				$butrem = '<a href="{{me}}?action=comp_logo_rem"
					OnClick="return confirm(\'Are you sure?\');">
					<img src="images/bs_rem_item.gif" border="0" alt="Remove" />
					</a>';
				$frmLogo->AddRow(array("Current:", "<img src=\"compimages/{$comp['id']}/l.png\" alt=\"{$comp['name']}\" title=\"{$comp['name']}\" /> $butrem", "&nbsp;"));
			}
			$frmLogo->AddInput(new FormInput('Upload', 'file', 'logo', null,
				'size="50"'));
			$frmLogo->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Upload'));
			$body .= GetBox("box_logo",
			"Company Logo",
			$frmLogo->Get('action="{{me}}" method="post" enctype="multipart/form-data"'));

			//Latest News
			$dsNews = $_d['news.ds'];
			$news = $dsNews->GetCustom("SELECT UNIX_TIMESTAMP(n.date) as date,
				n.subject subject, n.message body, n.id id
				FROM {$dsNews->table} n
				ORDER BY date DESC
				LIMIT 0, 5");

			if (is_array($news))
			{
				$tblNews = new Table("Your Latest News", 0);
				foreach ($news as $nws)
				{
					$tblNews->AddRow(array(gmdate("M d Y G:i", $nws['date'])));
					$tblNews->AddRow(array("Subject: {$nws['subject']}"));
					$tblNews->AddRow(array($nws['body']));
					$tblNews->AddRow(array("<a href=\"{{me}}?cs=cpanel&amp;ca=delete_news&amp;ci={$nws['id']}\" OnClick=\"return confirm('Are you sure?')\"> Remove </a><br /><br />"));
					//print_r($nws);
				}
				$body .= GetBox("box_news", "Your Latest News", $tblNews->Get());
			}

			$body .= RunCallbacks($_d['cpanel.callbacks.company'], $_d);

			return $body;
		}
	}
}

?>
