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
 * Upgrade Script: 1.6.6
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade23_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";

	if($db->field_exists('canusecustomtools', 'moderators'))
	{
		$db->drop_column('moderators', 'canusecustomtools');
	}

	if($db->field_exists('cansendemailoverride', 'usergroups'))
	{
		$db->drop_column('usergroups', 'cansendemailoverride');
	}

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$db->add_column('moderators', 'canusecustomtools', "int NOT NULL default '0'");
			$db->add_column('usergroups', 'cansendemailoverride', "int NOT NULL default '0'");
			break;
		default:
			$db->add_column('moderators', 'canusecustomtools', "int(1) NOT NULL default '0'");
			$db->add_column('usergroups', 'cansendemailoverride', "int(1) NOT NULL default '0'");
			break;
	}

	$db->update_query('moderators', array('canusecustomtools' => 1), "canmanagethreads = '1'");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("23_done");
}
