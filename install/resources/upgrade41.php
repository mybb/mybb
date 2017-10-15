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
 * Upgrade Script: 1.8.12
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

function upgrade41_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");

	// Remove Blekko Spider, Insert Modern Spiders
	$db->delete_query('spiders', "name IN('Blekko', 'Discord', 'Applebot', 'CheckHost', 'Pingdom', 'DuckDuckGo', 'UptimeRobot')");

	$db->insert_query_multiple('spiders', array(
		array("name" => "Discord", "useragent" => "Discordbot"),
		array("name" => "Applebot", "useragent" => "Applebot"),
		array("name" => "CheckHost", "useragent" => "CheckHost"),
		array("name" => "Pingdom", "useragent" => "Pingdom.com_bot"),
		array("name" => "DuckDuckGo", "useragent" => "DuckDuckBot"),
		array("name" => "UptimeRobot", "useragent" => "UptimeRobot"),
	));

	// Remove backslashes from last 1,000 log files
	$query = $db->simple_select('moderatorlog', 'tid, action', "action LIKE '%\\\\\\\\%'", array(
		"order_by" => 'tid',
		"order_dir" => 'DESC',
		"limit" => 1000
	));

	while($row = $db->fetch_array($query))
	{
		$original = $row['action'];
		$stripped = stripslashes($original);

		if($stripped !== $original)
		{
			$db->update_query("moderatorlog", array(
				"action" => $db->escape_string($stripped),
			), "WHERE tid = '".$row['tid']."'");
		}
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("41_done");
}
