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
 * Upgrade Script: 1.6.4
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade21_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";

	$db->delete_query("settings", "name = 'standardheaders'");

	if($db->field_exists('showinbirthdaylist', 'usergroups'))
	{
		$db->drop_column("usergroups", "showinbirthdaylist");
	}

	if($db->field_exists('canoverridepm', 'usergroups'))
	{
		$db->drop_column("usergroups", "canoverridepm");
	}

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$db->add_column("usergroups", "showinbirthdaylist", "int NOT NULL default '0'");
			$db->add_column("usergroups", "canoverridepm", "int NOT NULL default '0'");
			break;
		default:
			$db->add_column("usergroups", "showinbirthdaylist", "int(1) NOT NULL default '0'");
			$db->add_column("usergroups", "canoverridepm", "int(1) NOT NULL default '0'");
			break;
	}

	// Update all usergroups to show in the birthday list
	$db->update_query("usergroups", array("showinbirthdaylist" => 1));

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("21_done");
}

?>