<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/**
 * Upgrade Script: Preview Release 2
 */

$upgrade_detail = array(
	"revert_all_templates" => 1,
	"revert_all_themes" => 1,
	"revert_all_settings" => 1
	);

@set_time_limit(0);

function upgrade3_dbchanges()
{
	global $db, $output;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";

	$db->query("UPDATE ".TABLE_PREFIX."users SET style='0' WHERE style='-1';");
	$db->query("UPDATE ".TABLE_PREFIX."users SET displaygroup='0' WHERE displaygroup='-1';");
	$db->query("UPDATE ".TABLE_PREFIX."adminoptions SET uid='0' WHERE uid='-1';");
	$db->query("UPDATE ".TABLE_PREFIX."forums SET style='0' WHERE style='-1';");

	$db->query("ALTER TABLE ".TABLE_PREFIX."threads DROP messageindex;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."threads DROP subjectindex;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums DROP moderators;");

	echo "Done</p>";
	
	$contents .= "<font color=\red\"><b>WARNING:</font> The next step will delete any custom themes or templates you have! Please back them up before continuing!</p>";
	$output->print_contents($contents);
	$output->print_footer("4_done");
}

?>
