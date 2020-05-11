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
 * Upgrade Script: 1.8.22
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade50_dbchanges()
{
	global $output, $cache, $db;

	$output->print_header("Updating Database");

	echo "<p>Updating cache...</p>";

	$cache->delete("banned");

	$db->update_query('settings', array('value' => 1), "name='nocacheheaders'");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("50_done");
}

function upgrade50_verify_adminemail()
{
	global $output, $cache, $db, $mybb;

	$output->print_header("Verifying Admin Email");
	if(empty($mybb->settings['adminemail']))
	{
		echo "<p>Updating admin email settings...</p>";
		echo "<p><small>P.D: Field can not be empty</small></p>";
		$db->update_query('settings', array('value' => $mybb->user['email']), "name='adminemail'");
	}
	else
	{
		echo "<p>Admin email verified success...</p>";		
	}
	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("50_done");
}
