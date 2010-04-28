<?php

function GetData($data, $name, $default)
{
	if (isset($data[$name])) return $data[$name];
	return $default;
}

/**
* Parses a formula given identifiers from products will be replaced with respective values
* primarily for retrieving prices for COption objects on CAttrib objects on CAttribGroup objects.
*/
class CFormulaParser
{
	/**
	* Parses a forumula and returns the evaluated results.
	* @param $prod The associated product to get data from.
	* @param $formula The actual formula string including identifiers and what not.
	* @return The mathematical result from this evaluated formula.
	*/
	function GetFormula($prod, $formula)
	{
		if (empty($formula)) return 0;
		$this->prod = $prod;
		$ret = preg_replace_callback("/{(.*?)}/", array(&$this, "FormulaReplaceCallback"), $formula);
		$ret2 = @number_format(eval("return floatval($ret);"), 2);
		return $ret2;
	}

	/**
	* Just a callback, don't worry about this, only used by the GetFormula().
	* @param $match Match result from preg_replace_callback().
	* @return String to replace the regex match with.
	*/
	function FormulaReplaceCallback($match)
	{
		if ($match[1] == "price") return $this->prod->price;
		return null;
	}
}

?>
