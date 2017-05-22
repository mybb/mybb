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
 * Upgrade Script: 1.8.12
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

function upgrade40_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");

  $db->write_query("DELETE FROM mybb_spiders WHERE name = 'Blekko';");

  $output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("40_done");
}

@set_time_limit(0);

/* Nothing to do for 1.8.12 */
