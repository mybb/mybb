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
 * Upgrade Script: 1.2.2
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

if(!$db->field_exists('oldadditionalgroups', "banned"))
{	
	$db->query("ALTER TABLE ".TABLE_PREFIX."banned ADD oldadditionalgroups text NOT NULL AFTER oldgroup");
}

if(!$db->field_exists('olddisplaygroup', "banned"))
{
	$db->query("ALTER TABLE ".TABLE_PREFIX."banned ADD olddisplaygroup int NOT NULL default '0' AFTER oldadditionalgroups");
}

?>