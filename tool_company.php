<?php

require_once('h_main.php');

$hname = GetVar('hname');
$aname = GetVar('aname');

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<title> Select Company </title>
<script type="text/javascript">
function selectCompany(id, name)
{
	doc = window.opener.document;
	hid = doc.getElementById('<?=$hname?>');
	anc = doc.getElementById('<?=$aname?>');
	hid.value = id;
	anc.innerHTML = name;
	window.close();
}
</script>
<link href="template/<?=$_d['tempath']?>main.css" rel="stylesheet" type="text/css"/>
</head>
<body>

<?php

$form = <<<EOF
<form action="{$data['me']}" method="post">
<input type="hidden" name="hname" value="{$hname}" />
<input type="hidden" name="aname" value="{$aname}" />
	<table>
	<tr>
		<td><input type="text" name="search" /></td>
		<td><input type="submit" value="search" /></td>
	</tr>
	</table>

	<input type="hidden" name="ca" value="search" />
</form>
EOF;

$results = null;
$ca = $data['ca'];
if ($ca == 'search')
{
	$comps = $data['company.ds']->GetSearch(array('id', 'name', 'contact'), GetVar('search'));
	foreach($comps as $comp)
	{
		$results .= "<a href=\"#\" onclick=\"javascript:selectCompany('{$comp['id']}', '".addslashes($comp['name'])."');\">{$comp['name']}</a><br/>\n";
	}
}

echo GetBox('box_search_comps', 'Search', $form.$results);
?>
</body>
</html>
