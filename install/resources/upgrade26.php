<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */

/**
 * Upgrade Script: 1.6.9
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade26_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";

	switch($db->type)
	{
		case 'pgsql':
		case 'sqlite':
			$db->modify_column("polls", "numvotes", "int NOT NULL default '0'");
			break;
		default:
			$db->modify_column("polls", "numvotes", "int unsigned NOT NULL default '0'");
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("26_done");
}
?>