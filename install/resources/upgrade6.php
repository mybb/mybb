<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/**
 * Upgrade Script: 1.2
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
	);

@set_time_limit(0);

function upgrade6_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	
	$db->query("ALTER TABLE ".TABLE_PREFIX."mycode CHANGE regex regex text NOT NULL default ''");
	$db->query("ALTER TABLE ".TABLE_PREFIX."mycode CHANGE replacement replacement text NOT NULL default ''");
	$db->query("ALTER TABLE `".TABLE_PREFIX."privatemessages` ADD INDEX ( `uid` )");

	echo "Done</p>";
	echo "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents();
	$output->print_footer("6_done");
}

?>