<?

RegisterModule('ModSearch');

/**
 * A simple interface to a search engine.
 * @todo Apply category.
 * @todo Search recursively.
 * @todo Support multiple words.
 */
class ModSearch extends Module
{
	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if ($_d['ca'] == 'search')
		{
			$query = GetVar('query');
			$_d['product.ds.match'][] = "(prod_name LIKE '%{$query}%' OR
				p.description LIKE '%{$query}%')";
		}
	}

	function Get()
	{
		//$cats = QueryCatsAll();

		//if (count($cats) < 1) return null;
		$formSearch = new Form("formSearch");
		$formSearch->AddHidden("ca", "search");
		//$formSearch->AddInput(new FormInput("Category:", "select", "blah", DataToSel($cats, $cc, "Home")));
		$formSearch->AddInput(new FormInput('Keywords', 'text', 'query', null,
			'style="width: 100%"'));
		$formSearch->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Search'));
		$out = $formSearch->Get('action="{{me}}" method="post"', 'class="form"');
		return GetBox('box_search', 'Search', $out);
	}
}

?>
