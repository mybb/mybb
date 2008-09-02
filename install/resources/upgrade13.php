<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

/**
 * Upgrade Script: MyBB 1.4 or 1.4.1
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade13_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminsessions ADD INDEX ( `uid` )");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminsessions ADD INDEX ( `dateline` )");

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("13_done");
}

?>