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
 * Upgrade Script: 1.6.4
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade21_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";

	$db->delete_query("settings", "name = 'standardheaders'");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("21_done");
}

?>