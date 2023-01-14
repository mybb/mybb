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
 * Upgrade Script: 1.8.8
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade37_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->field_exists('canviewdeletionnotice', 'usergroups'))
	{
		$db->drop_column("usergroups", "canviewdeletionnotice");
	}

	if($db->field_exists('canviewdeletionnotice', 'forumpermissions'))
	{
		$db->drop_column("forumpermissions", "canviewdeletionnotice");
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("forumpermissions", "canviewdeletionnotice", "smallint NOT NULL default '0' AFTER caneditattachments");
			$db->add_column("usergroups", "canviewdeletionnotice", "smallint NOT NULL default '0' AFTER caneditattachments");
			break;
		default:
			$db->add_column("forumpermissions", "canviewdeletionnotice", "tinyint(1) NOT NULL default '0' AFTER caneditattachments");
			$db->add_column("usergroups", "canviewdeletionnotice", "tinyint(1) NOT NULL default '0' AFTER caneditattachments");
			break;
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("37_done");
}
