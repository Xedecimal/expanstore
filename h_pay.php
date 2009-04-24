<?

/**
* Incomplete.
*/
class PaymentModule
{
	/** Returns the name for this payment module. */
	function GetName() { return 'Class '.get_class($this).' does not implement method GetName().'; }
	/** Returns whether or not this payment module is safe. */
	function GetSafe() { return false; }
	/** Returns the output from checking this payment module. */
	function GetCheck() { return 'Class '.get_class($this).' does not implement method GetCheck().'; }

	static function GetSelect()
	{
		$tblPayment = new Table('tablePayment', null);
		$dp = opendir('.');
		while (($file = readdir($dp)))
		{
			if (substr($file, 0, 4) == 'pay_')
			{
				$module = substr($file, 4, strlen($file) - 8);
				require_once($file);
				$name = 'Pay' . $module;
				$mod = new $name();
				$tblPayment->AddRow(array("<input type=\"radio\" name=\"paytype\" id=\"$module\" value=\"$module\"/>",'<label for="'.$module.'">'.$mod->GetName().'</label>',""));
			}
		}
		return $tblPayment->Get();
	}
}

?>
