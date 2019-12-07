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
 * Upgrade Script: 1.8.21
 */

$upgrade_detail = array(
    "revert_all_templates" => 0,
    "revert_all_themes" => 0,
    "revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade49_dbchanges()
{
	global $output, $db;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->field_exists('yahoo', 'users'))
	{
		$db->drop_column('users', 'yahoo');
	}

	$db->delete_query("settings", "name='allowyahoofield'");

	$db->modify_column('attachments', 'filename', 'varchar(255)', true, "''");
	$db->modify_column('attachments', 'attachname', 'varchar(255)', true, "''");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("49_done");
}
