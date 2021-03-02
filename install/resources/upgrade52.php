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
 * Upgrade Script: 1.8.24
 */

 $upgrade_detail = array(
     "revert_all_templates" => 0,
     "revert_all_themes" => 0,
     "revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade51_dbchanges()
{
	global $output, $cache, $db, $mybb;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	switch($db->type)
	{
		// Add new settings for (a) new usergroup permission if group members can hide online status & (b) attachments force download
		case "pgsql":
			$db->add_column("usergroups", "canbeinvisible", "smallint NOT NULL default '1' AFTER canusercp");
			$db->add_column("attachtypes", "forcedownload", "smallint NOT NULL default '0' AFTER enabled");
			break;

		default:
			$db->add_column("usergroups", "canbeinvisible", "tinyint(1) NOT NULL default '1' AFTER canusercp");
			$db->add_column("attachtypes", "forcedownload", "tinyint(1) NOT NULL default '0' AFTER enabled");
			break;
	}
  
	$added_tasks = sync_tasks();

	echo "<p>Added {$added_tasks} new tasks.</p>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("52_done");
}
