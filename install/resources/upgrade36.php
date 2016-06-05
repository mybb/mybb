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
	global $db, $output;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	$query = $db->simple_select("templategroups", "COUNT(*) as numexists", "prefix='mycode'");
	if($db->fetch_field($query, "numexists") == 0)
	{
		$db->insert_query("templategroups", array('prefix' => 'mycode', 'title' => '<lang:group_mycode>', 'isdefault' => '1'));
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("36_done");
}