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
 * Upgrade Script: 1.8.1, 1.8.2 or 1.8.3
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade32_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	// delete forumpermissions belonging to a deleted forum
	$db->delete_query("forumpermissions", "fid NOT IN(SELECT fid FROM {$db->table_prefix}forums)");

	$cache->update_awaitingactivation();
	
	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("32_done");
}