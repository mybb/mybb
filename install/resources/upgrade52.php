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
 * Upgrade Script: 1.8.24, 1.8.25 or 1.8.26
 */

 $upgrade_detail = array(
     "revert_all_templates" => 0,
     "revert_all_themes" => 0,
     "revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade52_dbchanges()
{
	global $output, $cache, $db, $mybb;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	// Add new setting for new usergroup permission if group members can hide online status
	if(!$db->field_exists('canbeinvisible', 'usergroups'))
	{
		switch($db->type)
		{
			case "pgsql":
				$db->add_column("usergroups", "canbeinvisible", "smallint NOT NULL default '1' AFTER canusercp");
				break;

			default:
				$db->add_column("usergroups", "canbeinvisible", "tinyint(1) NOT NULL default '1' AFTER canusercp");
				break;
		}
	}

	// Add new setting for attachments force download
	if(!$db->field_exists('forcedownload', 'attachtypes'))
	{
		switch($db->type)
		{
			case "pgsql":
				$db->add_column("attachtypes", "forcedownload", "smallint NOT NULL default '0' AFTER enabled");
				break;

			default:
				$db->add_column("attachtypes", "forcedownload", "tinyint(1) NOT NULL default '0' AFTER enabled");
				break;
		}
	}

	// Change default values for these settings for the guest group only
	$db->update_query('usergroups', array(
		'caneditposts' => 0,
		'candeleteposts' => 0,
		'candeletethreads' => 0,
		'caneditattachments' => 0,
		'canviewdeletionnotice' => 0
	), "gid = 1");

	$cache->update_usergroups();
  
	$added_tasks = sync_tasks();

	echo "<p>Added {$added_tasks} new tasks.</p>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("52_done");
}
