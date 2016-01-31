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

	$db->update_query("helpdocs", array('usetranslation' => 1));
	$db->update_query("helpsections", array('usetranslation' => 1));

	$db->modify_column("polls", "numvotes", "text NOT NULL");

	if($db->field_exists('failedlogin', 'users'))
	{
		$db->drop_column("users", "failedlogin");
	}

	// We don't need the posthash after the post is inserted into the database
	$db->update_query('attachments', "posthash=''", 'pid!=0');

	// Column will be dropped in MyBB 1.8
	$db->update_query('posts', "posthash=''");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("26_done");
}
