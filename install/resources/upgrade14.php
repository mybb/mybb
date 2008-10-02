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
 * Upgrade Script: MyBB 1.4.2
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
	flush();
	
	// TODO: Need to check for PostgreSQL / SQLite support

	if($db->field_exists('codepress', "adminoptions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions DROP codepress;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions ADD codepress int(1) NOT NULL default '1' AFTER cpstyle");
	
	$query = $db->query("SHOW INDEX FROM ".TABLE_PREFIX."users");
	while($ukey = $db->fetch_array($query))
	{
		if($ukey['Key_name'] == "longregip")
		{
			$longregip_index = true;
			continue;
		}
		
		if($ukey['Key_name'] == "longlastip")
		{
			$longlastip_index = true;
			continue;
		}
	}
	if($longlastip_index == true)
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP KEY longlastip");
	}
	
	if($longregip_index == true)
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP KEY longregip");
	}
	
	$query = $db->query("SHOW INDEX FROM ".TABLE_PREFIX."posts");
	while($pkey = $db->fetch_array($query))
	{
		if($pkey['Key_name'] == "longipaddress")
		{
			$longipaddress_index = true;
			break;
		}
	}
	if($longipaddress_index == true)
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts DROP KEY longipaddress");
	}
	
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts ADD INDEX longipaddress (longipaddress)");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD INDEX longregip (longregip)");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD INDEX longlastip (longlastip)");

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("13_dbchanges1");
}

?>
