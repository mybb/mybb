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
 * Upgrade Script: 1.8.32
 */

$upgrade_detail = array(
    "revert_all_templates" => 0,
    "revert_all_themes" => 0,
    "revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade56_dbchanges()
{
	global $output, $cache, $db, $mybb;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	// Add missing PostgreSQL indexes expected for DB_Base::replace_query()
	if($db->type == 'pgsql')
	{
		$parameters = '';

		if(version_compare($db->get_version(), '9.5.0', '>='))
		{
			$parameters = "IF NOT EXISTS";
		}

		$db->write_query("CREATE UNIQUE INDEX {$parameters} fid_uid ON ".TABLE_PREFIX."forumsread (fid, uid)");
		$db->write_query("CREATE UNIQUE INDEX {$parameters} tid_uid ON ".TABLE_PREFIX."threadsread (tid, uid)");
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("56_done");
}
