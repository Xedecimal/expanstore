<?php

class PackageManual
{
	function DeSerialize($data)
	{
		if (count($data) < 1) return null;
		if (is_array($data[0]))
		{
			$ret = array();
			foreach ($data as $item) $ret[] = CManualPackage::DeSerialize($item);
			return $ret;
		}

		$obj = new CManualPackage();
		return $obj;
	}
}

class PayManual
{
	function GetName() { return "Manual Processing"; }

	function GetCheck()
	{
		$ret = "Checking the database... ";
		$var = 1;
		if ($var == -1)
		{
			$ret .= "<span class=\"error\">Missing or malformed.</span><br/><br/>\n";
			$ret .= "<a href=\"$me?cs=admin&ca=pay_repair&ci=$ci\"> Repair </a>\n";
		}
		else
		{
			$ret .= "<span class=\"success\">OK</span><br/><br/>\n";
		}
		return $ret;
	}

	function GetRepair($data)
	{
		$data['db']->Query('DROP TABLE pay_man_pack');

		$data['db']->Query("CREATE TABLE pay_man_pack (" .
			"id int(11) not null auto_increment primary key, " .
			"cart_num varchar(255) not null, " .
			"card_name varchar(255) not null, " .
			"cart_exp date not null);");

		return "All done.";
	}

	function Checkout()
	{
		global $_d;

		$ca = @$_d['q'][2];

		if ($ca == 'finish')
		{
			#$fp = new CFormulaParser();

			$adding = array(
				'user' => $data['cl']['id'],
				'date' => DeString('NOW()'),

				'card_name' => GetVar('card_name'),
				'card_num' => GetVar('card_num'),
				'card_exp' => GetVar('card_exp'),
				'card_verify' => GetVar('card_verify'),
			);

			if (GetVar('yours') == 'yes')
			{
				$additional = array(
					'ship_name' => $data['cl']['name'],
					'ship_address' => $data['cl']['address'],
					'ship_city' => $data['cl']['city'],
					'ship_state' => $data['cl']['state'],
					'ship_zip' => $data['cl']['zip'],
				);
			}
			else
			{
				$additional = array(
					'ship_name' => GetVar('ship_name'),
					'ship_address' => GetVar('ship_address'),
					'ship_city' => GetVar('ship_city'),
					'ship_state' => GetVar('ship_state'),
					'ship_zip' => GetVar('ship_zip'),
				);
			}

			$finished = array_merge($adding, $additional);

			$id = $data['package.ds']->Add($finished);

			if (!empty($cart) > 0)
			{
				$formCart = new Form("formCart");

				$formCart->AddHidden("ca", "checkout");
				$formCart->AddHidden("cs", "cart");

				//Selected options
				$totalprice = 0;

				foreach ($cart as $citem)
				{
					$totalprice = $prodprice = $citem['price'];

//					if ($citem['option'] != null)
//					{
//						foreach ($citem->options as $newopt)
//							$totalprice += $prodprice = $fp->GetFormula($citem->product, $newopt->option->formula);
//					}

					$pprodid = $data['packageprod.ds']->Add(array(
						'package' => $id,
						'name' => $citem['prod_name'],
						'model' => $citem['model'],
						'price' => $totalprice
					));

//					if (!empty($citem->options))
//					{
//						foreach ($citem->options as $option)
//						{
//							$dsPProdOption->Add(array(
//								'pproduct' => $pprodid,
//								'attribute' => $option->attribute['name'],
//								'value' => $option->option->name
//							));
//						}
//					}
				}
			}
		}
		else
		{
			$body =<<<EOF
<script type="text/javascript">
function updateBilling()
{
	if (document.getElementById('billing_yes').checked)
	{
		document.getElementById('tblBilling').style.display = 'none';
		document.getElementById('tblBillingPersonal').style.display = 'block';
	}
	else
	{
		document.getElementById('tblBilling').style.display = 'block';
		document.getElementById('tblBillingPersonal').style.display = 'none';
	}
}
</script>
<form action="{{me}}" method="post">
<input type="hidden" name="cs" value="cart"/>
<input type="hidden" name="ca" value="finish"/>
<input type="hidden" name="paytype" value="manual"/>
<p><b>Payment</b></p>
<table>
	<tr>
		<td>Card Holder's Name </td>
		<td><input type="text" name="card_name" /></td>
		</tr>
	<tr>
		<td>Card Number </td>
		<td><input type="text" name="card_num" /></td>
		</tr>
	<tr>
		<td>Expiration Date </td>
		<td><input type="text" name="card_exp" /></td>
		</tr>
	<tr>
		<td>Verification Number </td>
		<td><input type="text" name="card_verify" /></td>
		</tr>
</table>
<p><b>Billing</b></p>
<p>
	Use your address:
	<label><input name="yours" id="billing_yes" value="yes" checked="checked"
		type="radio" onchange="updateBilling()" /> Yes</label>
	<label><input name="yours" id="billing_no" value="no" type="radio"
		onchange="updateBilling()"/> No</label>
</p>
<table id="tblBilling" cellspacing="0" cellpadding="0" style="display: none;">
	<tr>
		<td align="right"><label for="name"><b>Name</b></label></td>
		<td><input id="name" name="name" type="text" /></td>
		</tr>
	<tr>
		<td align="right"><label for="address"><b>Address</b></label></td>
		<td><input id="address" name="address" type="text" /></td>
		</tr>
	<tr>
		<td align="right"><label for="city"><b>City</b></label></td>
		<td><input id="city" name="city" type="text" /></td>
		</tr>
	<tr>
		<td align="right"><label for="state"><b>State</b></label></td>
		<td><input id="state" name="state" type="text" /></td>
		</tr>
	<tr>
		<td align="right"><label for="zip"><b>Zip</b></label></td>
		<td><input id="zip" name="zip" type="text" /></td>
		</tr>
</table>
<table id="tblBillingPersonal" cellspacing="0" cellpadding="0">
	<tr>
		<td align="right"><b>Name</b>&nbsp;</td>
		<td>{$_d['cl']['usr_name']}</td>
		</tr>
	<tr>
		<td align="right"><b>Address</b>&nbsp;</td>
		<td>{$_d['cl']['usr_address']}</td>
		</tr>
	<tr>
		<td align="right"><b>City</b>&nbsp;</td>
		<td>{$_d['cl']['usr_city']}</td>
		</tr>
	<tr>
		<td align="right"><b>State</b>&nbsp;</td>
		<td>{$_d['cl']['usr_state']}</td>
		</tr>
	<tr>
		<td align="right"><b>Zip</b>&nbsp;</td>
		<td>{$_d['cl']['usr_zip']}</td>
		</tr>
</table>
<p>
	<input id="butSubmit" name="butSubmit" value="Process Order" type="submit" />
</p>
</form>
EOF;
			return GetBox('box_shipping', 'Shipping', $body);
		}
		return true;
	}
}

ModPayment::RegisterPayMod('manual', 'PayManual');

?>
