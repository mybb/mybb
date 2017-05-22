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
 * Upgrade Script: 1.8.13
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

function upgrade41_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");
	
	$db->delete_query('spiders', 'name=\'Blekko\'');

  	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("41_done");
}

