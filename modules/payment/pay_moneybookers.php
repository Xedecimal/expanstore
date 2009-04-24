<?

class PayMoneybookers
{
	function GetName() { return "MoneyBookers"; }
	function GetSafe() { return true; }
}

/*include_once("h_main.php");

if ($ac == "checkout")
{
	if (isset($cart->items))
	{
		$totalcost = 0;
		$total = 0;

		$off = 0;
		foreach ($cart->items as $x => $item)
		{
			echo "Cart Item<br>\n";
			$prod = $products->GetOne($item[0]);
			$optout = "";
			if ($prod->atrg > 0)
			{
				$atrg = $atrgs->GetOne($prod->atrg);
				$selitems = $cart->items[$x][1];
				foreach ($atrg->attribs as $y => $atr)
				{
					echo "Name: " . $atr->name . "<br>\n";
					$optout .= $atr->name;
					$cost = $prod->price + $atr->options[$selitems[$y]]->offset;
					$off++;
				}
			}
			else
			{
				$cost = $prod->price;
			}
			$totalcost += $cost;
			$total++;
		}
	}

	ShowHeader(STR_NAME . " - " . PM_NAME . " Gateway");

	echo $optout;

	$frmContinue = new xlForm("Preparing information...", 'action="https://www.moneybookers.com/app/payment.pl" method="post"', "Continue");
	$frmContinue->AddHidden("pay_to_email", "xed@spiritone.com");
	$frmContinue->AddHidden("language", "EN");
	$frmContinue->AddHidden("amount", $totalcost);
	$frmContinue->AddHidden("currency", "USD");
	$frmContinue->AddHidden("detail1_description", "Items purchased from " . STR_NAME);
	$frmContinue->AddHidden("detail1_text", STR_NAME . " Purchase");
	$frmContinue->AddHidden("confirmation_note", "Enjoy your purchase, if you have any questions stop back by.");
	$frmContinue->AddRow(array("<b>Complete, please click Continue</b>"));
	echo $frmContinue->Get();
}*/

?>
