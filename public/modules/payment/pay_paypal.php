<?php

require_once("h_main.php");

class PayPaypal
{
	function GetName() { return "PayPal"; }
	function GetSafe() { return true; }

	function Checkout($cart)
	{
		//$business = $paymods->GetValue($vars[0]);
		//$logo     = $paymods->GetValue($vars[1]);
		//$success  = $paymods->GetValue($vars[2]);
		//$failure  = $paymods->GetValue($vars[3]);

		echo "<body onLoad=\"formPaypal.submit()\">\n";
		echo "<form name=\"formPaypal\" action=\"https://www.paypal.com/cgi-bin/webscr\" method=\"post\">\n";
		echo "	<input type=\"hidden\" name=\"cmd\"           value=\"_cart\"/>\n";
		echo "	<input type=\"hidden\" name=\"business\"      value=\"".SITE_NAME."\"/>\n";
		//echo "  <input type=\"hidden\" name=\"image_url\"     value=\"$logo\">\n";
		echo "	<input type=\"hidden\" name=\"upload\"        value=\"1\"/>\n";
		//echo "  <input type=\"hidden\" name=\"return\"        value=\"$success\">\n";
		//echo "  <input type=\"hidden\" name=\"cancel_return\" value=\"$failure\">\n";
		foreach ($cart->items as $id => $item)
		{
			echo "	<input type=\"hidden\" name=\"item_name_$id\"     value=\"{$item->product->name}\"/>\n";
			echo "	<input type=\"hidden\" name=\"item_number_$id\"   value=\"{$item->product->model}\"/>\n";
			echo "	<input type=\"hidden\" name=\"amount_$id\"        value=\"{$item->product->price}\"/>\n";
		}
		echo "	<input type=\"hidden\" name=\"add\"               value=\"1\"/>\n";
		echo "</form>\n";
		echo "</body>\n";
		die();
	}
}

$name = "Pay Pal Module";
$vars = array("Business", "Logo URL", "Success URL", "Failure URL");
$type = array("text",     "text",     "text",        "text");

global $data;

$ca = $data['ca'];

if ($ca == "install")
{
	ShowHeader("XStore Administration - PayPal Module Installation");
	for ($x = 0; $x < count($vars); $x++)
	{
		if (!$paymods->VerifyOption($vars[$x]))
		$paymods->AddOption($vars[$x]);
	}
	ShowMenubar();
	ShowFooter();
}

else if ($ca == "configure")
{
	ShowHeader("XStore Administration - PayPal Module Configuration");
	ShowMenubar();

	$paymods->GetAll();

	$frmOpts = new xlForm("$name Options", "action=\"$me\"", "Update");
	$frmOpts->AddHidden("action", "update");

	for ($x = 0; $x < count($paymods->items); $x++)
	{
		$name = $paymods->items[$x][1];
		$valu = $paymods->items[$x][2];
		$frmOpts->AddInput(new FormInput($name, $type[$x],
			'name="vals[]" size="50"', $valu));
	}

	echo $frmOpts->Get();
	ShowFooter();
}

else if ($ca == "update")
{
	$vals = Server::GetVar("vals");

	for ($x = 0; $x < count($vals); $x++)
	{
		DoQuery("UPDATE paymodule SET value = '$vals[$x]' WHERE pamo_name = '" . $vars[$x] . "'");
	}
	//Redirect("$me?action=configure");
}

?>
