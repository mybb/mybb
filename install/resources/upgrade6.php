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
	$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD INDEX ( `uid` )");
	
	if(!$db->field_exists('recipients', "privatemessages"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD recipients text NOT NULL default '' AFTER fromid");
	}
	
	if(!$db->field_exists('maxpmrecipients', "usergroups"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD maxpmrecipients int(4) NOT NULL default '5' AFTER pmquota");
	}
	
	if(!$db->field_exists('oldadditionalgroups', "banned"))
	{	
		$db->query("ALTER TABLE ".TABLE_PREFIX."banned ADD oldadditionalgroups text NOT NULL default '' AFTER oldgroup");
	}
	
	if(!$db->field_exists('olddisplaygroup', "banned"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."banned ADD olddisplaygroup int NOT NULL default '0' AFTER oldadditionalgroups");
	}
	
	if($db->field_exists('newpms', "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP newpms;");
	}
	
	if(!$db->field_exists('keywords', "searchlog"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."searchlog ADD keywords text NOT NULL default '' AFTER querycache");
	}

	$contents = "Done</p>";
	$contents .= "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("6_done");
}

?>