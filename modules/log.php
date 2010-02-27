<?php

Module::RegisterModule('ModLog');

$GLOBALS['log_types'] = array('Information');

function xslog(&$_d, $msg, $level = 0)
{
	$ins['log_date'] = SqlUnquote('NOW()');
	$ins['log_level'] = $level;
	$ins['log_message'] = $msg;

	if (isset($_D['cl']))
		$ins['log_user'] = $_d['cl']['usr_id'];
	if (isset($_d['cl']['c2u_company']))
		$ins['log_company'] = $_d['cl']['c2u_company'];

	$_d['log.ds']->Add($ins);
}

class ModLog extends Module
{
	function Install()
	{
		$queries = <<<EOF
CREATE IF NOT EXISTS TABLE `log` (
  `log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `log_date` datetime DEFAULT NULL,
  `log_level` int(10) unsigned NOT NULL DEFAULT '0',
  `log_company` bigint(20) unsigned NOT NULL DEFAULT '0',
  `log_user` bigint(20) unsigned NOT NULL DEFAULT '0',
  `log_message` mediumtext NOT NULL,
  PRIMARY KEY (`log_id`) USING BTREE,
  KEY `idxCompany` (`log_company`) USING BTREE,
  KEY `idxUser` (`log_user`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOF;

		$GLOBALS['_d']['db']->Queries($queries);
	}

	function Prepare()
	{
		parent::Prepare();

		if (!empty($_d['cl']) && $_d['cl']['usr_access'] >= 500)
		{
			$_d['page.links']['Admin']['Logs'] =
				htmlspecialchars("{{me}}?cs=log");
		}

		global $_d;

		$_d['log.ds'] = new DataSet($_d['db'], 'ype_log');
		$_d['log.ds']->Shortcut = 'log';
	}

	function Get()
	{
		global $log_types, $me, $_d;

		if ($_d['q'][0] != 'log') return;

		$sort = GetVar("sort", "log_date");
		$order = GetVar("order", "DESC");

		$joins = array(
			new Join($_d['user.ds'], 'log_user = usr_id', 'LEFT JOIN'),
			new Join($_d['company.ds'], 'log_company = comp_id', 'LEFT JOIN')
		);

		$logs = $_d['log.ds']->Get(
			null, array($sort => $order), array(0, 100), $joins, array(
				'log_date',
				'log_level',
				'log_message',
				'usr_id',
				'usr_user',
				'comp_id',
				'comp_name'
			)
		);

		if (count($logs) > 0)
		{
			$tblLogs = new SortTable("tblLogs", array(
				"date" => "Date",
				"type" => "Type",
				"company" => "Company",
				"user" => "User",
				"message" => "Message"));

			foreach ($logs as $log)
			{
				$urlUser = URL($me, array(
					'cs' => 'user',
					'class' => 'userdisplay',
					'ca' => 'ype_user_edit',
					'ci' => $log['uid']
				));

				$urlComp = URL($me, array(
					'cs' => 'admin',
					'ca' => 'view_company',
					'ci' => $log['cid']
				));

				$tblLogs->AddRow(array(
					$log['date'],
					$log_types[$log['level']],
					!empty($log['cname']) ? "<a href=\"{$urlComp}\">{$log['cname']}</a>" : null,
					!empty($log['user']) ? "<a href=\"{$urlUser}\">{$log['user']}</a>" : null,
					$log['message']
				));
			}
			$body = $tblLogs->Get('cellpadding="3"');
		}
		else $body = "No log entries.<br />\n";
		return GetBox("box_logs", "Logs", $body);
	}
}

?>
