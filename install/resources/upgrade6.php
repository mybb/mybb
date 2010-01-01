<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: upgrade6.php 4304 2009-01-02 01:11:56Z chris $
 */

/**
 * Upgrade Script: 1.2
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0,
	"requires_deactivated_plugins" => 0,
);

@set_time_limit(0);

function upgrade6_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."mycode CHANGE regex regex text NOT NULL");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."mycode CHANGE replacement replacement text NOT NULL");

	$contents = "Done</p>";
	$contents .= "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("6_done");
}

?>