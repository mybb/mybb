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
 * Upgrade Script: 1.6.5
 */

$upgrade_detail = array(
	'revert_all_templates' => 0,
	'revert_all_themes' => 0,
	'revert_all_settings' => 0
);

@set_time_limit(0);

function upgrade22_dbchanges()
{
	global $cache, $db, $output, $mybb;

	$output->print_header('Updating Database');

	echo "<p>Performing necessary upgrade queries...</p>";

	if($db->field_exists('canusecustomtools', 'moderators'))
	{
		$db->drop_column('moderators', 'canusecustomtools');
	}

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$db->add_column("moderators", "canusecustomtools", "int NOT NULL default '0'");
			break;
		default:
			$db->add_column("moderators", "canusecustomtools", "int(1) NOT NULL default '0'");
			break;
	}

	// Update existing moderators with the new permissions
	$db->update_query('moderators', array('canusecustomtools' => 1));

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer('22_done');
}

?>