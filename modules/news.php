<?php

RegisterModule('ModNews');

function QueryNewsLatest(&$_d, $start = 0, $amount = 5)
{
	return $_d['news.ds']->GetCustom("SELECT news_id, news_date, news_subject,
		news_message, comp_id, comp_name
		FROM {$_d['news.ds']->table} n
		LEFT JOIN {$_d['company.ds']->table} c ON(news_company = comp_id)
		ORDER BY news_date DESC"
	);
}

class ModNews extends Module
{
	function __construct()
	{
		global $_d;

		$ds = new DataSet($_d['db'], 'ype_news');
		$ds->ErrorHandler = array($this, 'DataError');
		$_d['news.ds'] = $ds;
	}

	function Link()
	{
		global $_d;

		// Attach to CPanel.

		$_d['cpanel.callbacks.company'][] =
			array(&$this, 'Company');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		$ca = GetVar('ca');

		if ($ca == 'delete_news')
		{
			$_d['news.ds']->Remove(array('id' => $_d['ci']));
		}
		if ($ca == 'news_post')
		{
			$_d['news.ds']->Add(array(
				'news_company' => $_d['cl']['company'],
				'news_date' => DeString('NOW()'),
				'news_subject' => GetVar("subject"),
				'news_message' => GetVar("body")
			));
			xslog($_d, 'Posted news.');
		}
	}

	function DataError($errno)
	{
		global $_d;

		if ($errno == ER_NO_SUCH_TABLE)
		{
			$_d['db']->Query("CREATE TABLE `news` (
  `id` int(11) NOT NULL auto_increment,
  `company` bigint(20) unsigned NOT NULL default '0',
  `date` datetime default NULL,
  `subject` varchar(255) NOT NULL default '',
  `message` mediumtext NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `idxCompany` (`company`)
) ENGINE=MyISAM");
		}
	}

	function Company(&$_d)
	{
		$frmPost = new Form("formPost", array(null, 'width="100%"'));
		$frmPost->AddHidden("ca", "news_post");
		$frmPost->AddHidden('cs', $_d['cs']);
		$frmPost->AddInput(new FormInput("Subject:", "text", "subject", null, 'style="width: 100%"'));
		$frmPost->AddInput(new FormInput("Body:", "area", "body", null, 'rows="5" style="width: 100%"'));
		$frmPost->AddInput(new FormInput("", "submit", "butSubmit", "Post"));
		return GetBox("box_post", "Post News",
			$frmPost->Get('action="{{me}}" method="post"', 'width="100%"'));
	}
}

class ModNewsLatest extends DisplayObject
{
	function Prepare()
	{
		parent::Prepare();

		global $_d;

		$ca = GetVar('ca');

		if ($ca == 'news_del')
		{
			$dsNews->Remove(array('id' => $ci));
		}
	}

	function Get()
	{
		global $_d;

		if (GetVar('cc') != 0) return;

		$rnews = QueryNewsLatest($_d);
		$news = GetFlatPage($rnews, GetVar('cp'), 10);
		$pages = GetPages(count($rnews), 10, array('ix' => 'test'));
		if (empty($news)) return null;

		$t = new Template($_d);
		$t->ReWrite('relativedate', 'TagRelativeDate');
		$t->ReWrite('chomp', 'TagChomp');
		$t->ReWrite('nl2br', 'TagNL2BR');
		$t->ReWrite('htmlspecialchars', 'TagHSC');

		$newsout = null;
		foreach ($news as $nws)
		{
			$nws['comp_name'] = stripslashes($nws['comp_name']);
			$this->item = $nws;

			$t->Set($nws);
			$t->ReWrite('admin', array(&$this, 'TagAdmin'));
			$newsout .= $t->ParseFile($_d['tempath'].'catalog/news_item.xml');
		}
		$t->Set('news', $newsout);

		$start = isset($_d['view.start']) ? $_d['view.start'] : 0;
		$amount = isset($_d['view.amount']) ? $_d['view.amount'] : 5;

		$t->Set('pages', $pages);
		return $t->ParseFile($_d['tempath'].'catalog/news.xml');
	}

	function TagAdmin($t, $g, $a)
	{
		global $user;
	}
}

?>
