<?php

Module::RegisterModule('ModCPanel');

class ModCPanel extends Module
{
	function QueryPackages($match)
	{
		global $_d;
		/** @var DataSet */
		$ds = $_d['package.ds'];
		return $ds->Get($match, null, null, $_d['package.ds.joins']);
	}

	function Link()
	{
		global $_d;

		// Attach to Navigation.

		if (!empty($_d['cl']))
		{
			$_d['page.links']["Control Panel"]['Personal'] = '{{app_abs}}/cpanel';
			//$_d['page.links']["Control Panel"]["Financial"] = '{{app_abs}}/cpanel/financial';
		}

		if (isset($_d['package.ds']))
		{
			$_d['package.ds.joins']['user'] =
				new Join($_d['user.ds'], 'usr_id = pkg_user', 'LEFT JOIN');
			$_d['package.ds.joins']['package_prod'] =
				new Join($_d['packageprod.ds'], 'pp_package = pkg_id', 'LEFT JOIN');
			$_d['package.ds.joins']['ppo'] =
				new Join($_d['packageprodoption.ds'], 'ppo_pprod = pp_id', 'LEFT JOIN');
		}
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		$ca = @$_d['q'][1];

		if ($ca == 'update')
		{
			$id = $_d['cl']['usr_id'];
			$pass1 = GetVar("pass1");
			$pass2 = GetVar("pass2");

			$columns = array(
				'usr_email'   => GetVar("email"),
				'usr_name'    => GetVar("name"),
				'usr_address' => GetVar("address"),
				'usr_city'    => GetVar("city"),
				'usr_state'   => GetVar("state"),
				'usr_zip'     => GetVar("zip"),
				'usr_phone'   => GetVar("phone")
			);

			$pass1 = GetVar("pass1");
			$pass2 = GetVar("pass2");

			if (strlen($pass1) > 0)
			{
				if (strcmp($pass1, $pass2) == 0)
				{
					$columns['usr_pass'] = md5($pass1);
				}
				else die("Passwords did not match.");
			}

			$_d['user.ds']->Update(array('usr_id' => $id), $columns);

			ModLog::Log("Updated profile");

			//Redirect("$me?ct=$ct&cs=$cs");
		}

		else if ($ca == "comp_update")
		{
			$fields = GetVar("company");
			$dsCompanies->Update(array('id' => $cl['company']), array(
				"name" => $fields[0],
				"email" => $fields[1],
				"contact" => $fields[2],
				"address" => $fields[3],
				"city" => $fields[4],
				"state" => $fields[5],
				"zip" => $fields[6],
				"phone" => $fields[7]));
			xslog($_d, "Updated company profile");
			//Redirect("$me?cs=cpanel&ca=view_company");
		}

		else if ($ca == "comp_desc_update")
		{
			$dsCompanies->Update(array('id' => $cl['company']), array("about" => GetVar("body")));
			xslog($_d, "Updated company description.");
			//Redirect("$me?cs=cpanel&ca=view_company");
		}

		else if ($ca == "comp_uplogo")
		{
			$comp = $dsCompanies->GetOne('id', $cl['company']);

			$file = GetVar("logo");
			if (!file_exists("compimages/{$comp->id}")) mkdir("compimages/{$comp->id}");

			$tempfile = $file["tmp_name"];
			//$filename = $file["name"];
			$destfile1 = "compimages/{$comp->id}/l.png";
			$destfile2 = "compimages/{$comp->id}/m.png";
			$destfile3 = "compimages/{$comp->id}/s.png";

			$filetype = $file["type"];
			switch ($filetype)
			{
				case 'image/jpeg':
				case 'image/pjpeg':
					$img = imagecreatefromjpeg($tempfile);
				break;
				case 'image/x-png':
				case 'image/png':
					$img = imagecreatefrompng($tempfile);
				break;
				case 'image/gif':
					$img = imagecreatefromgif($tempfile);
				break;
				default:
					die("Unknown image type: $filetype<br>\n");
				break;
			}

			imagepng($img, $destfile1);
			$img2 = ResizeImg($img, 100, 100);
			imagepng($img2, $destfile2);
			$img3 = ResizeImg($img, 16, 16);
			imagepng($img3, $destfile3);

			xslog($_d, "Uploaded company logo.");
		}

		else if ($ca == "comp_logo_rem")
		{
			unlink("compimages/{$cu->company->id}/logo.gif");
			xslog($_d, "Removed company logo.");
		}
	}

	function Get()
	{
		global $_d;

		$ca = GetVar('ca');

		if ($ca == "financial")
		{
			$cl = $_d['cl'];

			$GLOBALS['page_section'] = 'Financial';

			$this->packs = StackData($this->QueryPackages(
				array('usr_id' => $cl['usr_id'])
			), array('pkg_id', 'pp_id', 'ppo_id'));

			$t = new Template();
			$t->ReWrite('package', array(&$this, 'TagPackage'));
			return $t->ParseFile($_d['tempath'].'cpanel/financial.xml');
		}
		else
		{
			if ($_d['q'][0] != 'cpanel') return;
			$cl = $_d['cl'];

			$frmProfile = new Form("frmProfile");

			$frmProfile->AddInput(new FormInput('Password', 'password', 'pass1',
				null, 'size="30"', "Only specify if you wish to change."));
			$frmProfile->AddInput(new FormInput('Verify', 'password', 'pass2',
				null, 'size="30"'));
			$frmProfile->AddInput(new FormInput('Email', 'text', 'email',
				$cl['usr_email'], 'size="30"'));
			$frmProfile->AddInput(new FormInput('Real Name', 'text', 'name',
				$cl['usr_name'], 'size="30"'));
			$frmProfile->AddInput(new FormInput('Address', 'text', 'address',
				$cl['usr_address'], 'size="30"'));
			$frmProfile->AddInput(new FormInput('City', 'text', 'city',
				$cl['usr_city'], 'size="30"'));
			$frmProfile->AddInput(new FormInput('State', 'text', 'state',
				$cl['usr_state'], 'size="30"'));
			$frmProfile->AddInput(new FormInput('Zip', 'text', 'zip',
				$cl['usr_zip'], 'size="30"'));
			$frmProfile->AddInput(new FormInput('Phone', 'text', 'phone',
				$cl['usr_phone'], 'size="30"'));
			$frmProfile->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Update'));

			return GetBox("box_profile", "Your Profile",
				$frmProfile->Get('action="{{app_abs}}/cpanel/update" method="post"'));
		}
	}

	function TagPackage($t, $g)
	{
		$ret = null;

		$tt = new Template();
		$tt->ReWrite('packageprod', array(&$this, 'TagPackageProd'));

		foreach ($this->packs->children as $p)
		{
			$this->pack = $p;
			$tt->Set($p->data);
			$ret .= $tt->GetString($g);
		}

		return $ret;
	}

	function TagPackageProd($t, $g)
	{
		$ret = null;

		$tt = new Template();

		foreach ($this->pack->children as $pprod)
		{
			$tt->Set($pprod->data);
			$ret .= $tt->GetString($g);
		}

		return $ret;
	}
}

?>
