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
 * Upgrade Script: 1.8.5
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade34_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($mybb->settings['username_method'] == 1 || $mybb->settings['username_method'] == 2)
	{
		$query = $db->simple_select('users', 'email, COUNT(email) AS duplicates', "email!=''", array('group_by' => 'email HAVING duplicates>1'));
		if($db->num_rows($query))
		{
			$db->update_query('settings', array('value' => 0), "name='username_method'");
		}
		else
		{
			$db->update_query('settings', array('value' => 0), "name='allowmultipleemails'");
		}
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("34_done");
}