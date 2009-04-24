<?php

RegisterModule('ModReview');

function QueryReviews($_d, $id)
{
	/** @var DataSet */
	$dsReviews = $_d['review.ds'];
	/** @var DataSet */
	$dsUsers = $_d['user.ds'];

	$joins = array(new Join($dsUsers, "rev_user = usr_id"));

	return $dsReviews->Get(array('rev_prod' => $id), null, null, $joins);
}

class ModReview extends Module
{
	function __construct()
	{
		global $_d;

		$dsReviews = new DataSet($_d['db'], "ype_review");
		$dsReviews->Shortcut = 'r';
		$_d['review.ds'] = $dsReviews;
	}

	function Link()
	{
		global $_d;

		// Attach to Product.

		$_d['product.ds.columns']['rating'] = SqlUnquote('AVG(rev_rating)');
		$_d['product.ds.joins']['review'] =
			new Join($_d['review.ds'], "rev_prod = prod_id", 'LEFT JOIN');
		$_d['product.callbacks.details']['review'] = array(&$this, 'Details');
		$_d['product.callbacks.props']['review'] = array(&$this, 'Props');
	}

	function Prepare()
	{
		parent::Prepare();

		global $_d;

		if ($_d['ca'] == 'add_review')
		{
			$rating = GetVar("formReview_rating");
			if ($rating < 0 || $rating > 5)
			{
				xslog($_d, "Review hack attempt!");
				die("You've been logged, you risk suspension.<br/>\n");
			}

			$_d['review.ds']->Add(array(
				'rev_date' => SqlUnquote("NOW()"),
				'rev_prod' => $_d['ci'],
				'rev_user' => $_d['cl']['usr_id'],
				'rev_rating' => $rating,
				'rev_subject' => GetVar("formReview_subject"),
				'rev_review' => GetVar("formReview_review")));

			xslog($_d, "Added review to {$_d['ci']}, rating it $rating");

			$_d['cs'] = 'product';
			$_d['ca'] = 'view';
		}

		if ($_d['ca'] == 'delete_review')
		{
			$_d['review.ds']->Remove(array('rev_id' => $_d['ci']));

			$_d['cs'] = 'product';
			$_d['ca'] = 'view';
			$_d['ci'] = GetVar('prod');
		}
	}

	function Details(&$_d, $prod)
	{
		$cl = $_d['cl'];

		$revs = QueryReviews($_d, $prod['prod_id']);

		$ret = '';
		if (!empty($revs))
		foreach ($revs as $rev)
		{
			$tblReviews = new Table('tblReviews', array(null, null),
				array(null, 'width="100%"'));
			$tblReviews->AddRow(array('From', $rev['usr_name']));
			$tblReviews->AddRow(array('Rating',
				str_repeat('<img src="images/rate.gif" alt="rate"
				align="middle" /> ', number_format($rev['rev_rating']))));
			$tblReviews->AddRow(array(null, $rev['rev_review']));
			$title = $rev['rev_subject'];
			if ($cl['usr_access'] > 500)
			{
				$title .= " <a href=\"".URL($_d['me'],
					array('ca' => 'delete_review', 'ci' => $rev['rev_id'],
					'prod' => $prod['prod_id'])).'" title="Delete">
					<img src="template/new/catalog/delete.png"
					alt="Delete" title="Delete" /></a>';
			}
			$ret .= GetBox('box_rev', $title, $tblReviews->Get());
			$ret .= '<br/>';
		}

		if (isset($cl) && $cl['usr_access'] > 0)
		{
			$ratings = array(
				1 => new SelOption('1', false),
				2 => new SelOption('2', false),
				3 => new SelOption('3', false, 1),
				4 => new SelOption('4', false),
				5 => new SelOption('5', false)
			);
			$formReview = new Form('formReview');
			$formReview->AddHidden('ca', 'add_review');
			$formReview->AddHidden('ci', $_d['ci']);
			$formReview->AddInput('From '.$cl['usr_user']);
			$formReview->AddInput(new FormInput('Rating', 'select', 'rating',
				$ratings));
			$formReview->AddInput(new FormInput('Subject', 'text', 'subject',
				null, 'size="50"'));
			$formReview->AddInput(new FormInput('Review', 'area', 'review',
				null, array('ROWS' => 5, 'COLS' => 50)));
			$formReview->AddInput(new FormInput(null, 'submit', 'butSubmit',
				'Post it'));
			$ret .= GetBox('box_review', 'Write a Review',
				$formReview->Get('action="{{me}}" method="post"',
				'width="100%"'));
		}
		return $ret;
	}

	function Props(&$_d, $prod)
	{
		$t = new Template($_d);
		if (!empty($prod['rating']))
		{
			$t->Set(array('prop' => "Rating", 'value' =>
				str_repeat('<img src="images/rate.gif" alt="rate"
				align="middle" /> ', number_format($prod['rating']))."\n"));
			return $t->ParseFile($_d['tempath'].'catalog/product_property.html');
		}
	}
}

?>
