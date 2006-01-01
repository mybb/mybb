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
 * Upgrade Script: 1.0 Final
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
	);

@set_time_limit(0);

function upgrade5_dbchanges()
{
	global $db, $output;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";

	$db->query("ALTER TABLE ".TABLE_PREFIX."users CHANGE avatartype avatartype varchar(10) NOT NULL;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD totalpms int(10) NOT NULL default '0' AFTER showcodebuttons;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD newpms int(10) NOT NULL default '0' AFTER totalpms;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD unreadpms int(10) NOT NULL default '0' AFTER newpms;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD unapprovedposts INT(10) unsigned NOT NULL default '0' AFTER visible;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD unapprovedthreads INT(10) unsigned NOT NULL default '0' AFTER rules;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD unapprovedposts INT(10) unsigned NOT NULL default '0' AFTER rules;");

	$db->query("UPDATE ".TABLE_PREFIX."users SET totalpms='-1', newpms='-1', unreadpms='-1'");

	echo "Done</p>";
	
	$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."mycodes");	
	$db->query("CREATE TABLE ".TABLE_PREFIX."mycodes (
			cid int unsigned NOT NULL auto_increment,
			regex varchar(255) NOT NULL default '',
			replacement varchar(255) NOT NULL default '',
			PRIMARY KEY(cid)
		) TYPE=MyISAM;");
	
	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("5_done");
}

?>