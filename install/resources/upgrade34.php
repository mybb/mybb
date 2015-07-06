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
 * Upgrade Script: 1.8.5
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade34_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->type != 'pgsql')
	{
		$db->modify_column('adminsessions', 'useragent', "varchar(200) NOT NULL default ''");
		$db->modify_column('sessions', 'useragent', "varchar(200) NOT NULL default ''");
	}
	else
	{
		$db->modify_column('adminsessions', 'useragent', "varchar(200)", "set", "");
		$db->modify_column('sessions', 'useragent', "varchar(200)", "set", "");
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("33_done");
}
