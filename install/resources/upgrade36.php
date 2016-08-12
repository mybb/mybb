<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Upgrade Script: 1.8.7
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade36_dbchanges()
{
	global $output, $db, $cache;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->field_exists('reasonid', 'reportedcontent'))
	{
		$db->drop_column("reportedcontent", "reasonid");
	}

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$db->add_column("reportedcontent", "reasonid", "smallint NOT NULL default '0' AFTER reportstatus");
			break;
		default:
			$db->add_column("reportedcontent", "reasonid", "smallint unsigned NOT NULL default '0' AFTER reportstatus");
			break;
	}


	if($db->table_exists("reportreasons"))
	{
		$db->drop_table("reportreasons");
	}

	$collation = $db->build_create_table_collation();

	switch($db->type)
	{
		case "pgsql":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."reportreasons (
				 rid serial,
				 title varchar(250) NOT NULL default '',
				 appliesto varchar(250) NOT NULL default '',
				 extra smallint NOT NULL default '0',
				 disporder smallint NOT NULL default '0',
				 PRIMARY KEY (rid)
			);");
			break;
		case "sqlite":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."reportreasons (
				 rid INTEGER PRIMARY KEY,
				 title varchar(250) NOT NULL default '',
				 appliesto varchar(250) NOT NULL default '',
				 extra tinyint(1) NOT NULL default '0',
				 disporder smallint NOT NULL default '0',
			);");
			break;
		default:
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."reportreasons (
				 rid int unsigned NOT NULL auto_increment,
 				 title varchar(250) NOT NULL default '',
 				 appliesto varchar(250) NOT NULL default '',
 				 extra tinyint(1) NOT NULL default '0',
 				 disporder smallint unsigned NOT NULL default '0',
 				 PRIMARY KEY (rid)
			) ENGINE=MyISAM{$collation};");
			break;
	}

	$reportreasons = array(
		array(
			'rid' => 1,
			'title' => "<lang:report_reason_other>",
			'appliesto' => "all",
			'extra' => 1,
			'disporder' => 99
		),
		array(
			'rid' => 2,
			'title' => "<lang:report_reason_rules>",
			'appliesto' => "all",
			'extra' => 0,
			'disporder' => 1
		),
		array(
			'rid' => 3,
			'title' => "<lang:report_reason_bad>",
			'appliesto' => "all",
			'extra' => 0,
			'disporder' => 2
		),
		array(
			'rid' => 4,
			'title' => "<lang:report_reason_spam>",
			'appliesto' => "all",
			'extra' => 0,
			'disporder' => 3
		),
		array(
			'rid' => 5,
			'title' => "<lang:report_reason_wrong>",
			'appliesto' => "post",
			'extra' => 0,
			'disporder' => 4
		)
	);

	$db->insert_query_multiple('reportreasons', $reportreasons);

	$templang = new MyLanguage();
	$templang->set_path(MYBB_ROOT."inc/languages");

	$langs = array_keys($templang->get_languages());

	foreach($langs as $langname)
	{
		unset($templang);
		$templang = new MyLanguage();
		$templang->set_path(MYBB_ROOT."inc/languages");
		$templang->set_language($langname);
		$templang->load("report");

		if(!empty($templang->report_reason_rules) && $templang->report_reason_rules != '')
		{
			$db->update_query("reportedcontent", array("reasonid" => 2, "reason" => ''), "reason = '".$db->escape_string("\n".$templang->report_reason_rules)."'");
		}
		if(!empty($templang->report_reason_bad) && $templang->report_reason_bad != '')
		{
			$db->update_query("reportedcontent", array("reasonid" => 3, "reason" => ''), "reason = '".$db->escape_string("\n".$templang->report_reason_bad)."'");
		}
		if(!empty($templang->report_reason_spam) && $templang->report_reason_spam != '')
		{
			$db->update_query("reportedcontent", array("reasonid" => 4, "reason" => ''), "reason = '".$db->escape_string("\n".$templang->report_reason_spam)."'");
		}
		if(!empty($templang->report_reason_wrong) && $templang->report_reason_wrong != '')
		{
			$db->update_query("reportedcontent", array("reasonid" => 5, "reason" => ''), "reason = '".$db->escape_string("\n".$templang->report_reason_wrong)."'");
		}
	}

	// Any reason not converted is treated as "Other" with extra text specified
	$db->update_query("reportedcontent", array('reasonid' => 1), 'reason != ""');

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("36_done");
}
