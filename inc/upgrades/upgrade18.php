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
 * Upgrade Script: 1.6.0
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

function upgrade18_dbchanges()
{
	global $db, $output, $mybb;

	// Performing Queries

	// Update the usergroup sequence for pgSQL - #1094
	if($mybb->config['database']['type'] == "pgsql")
	{
		$query = $db->simple_select("usergroups", "COUNT(gid) AS group_count");
		$group_count = $db->fetch_field($query, "group_count");

		++$group_count;
		$db->query("ALTER SEQUENCE ".$mybb->config['database']['table_prefix']."usergroups_gid_seq RESTART WITH ".$group_count."");
	}
}

function upgrade18_updatecache()
{
	global $cache, $output;

	// Updating Cache

	// Updating cache...

	// Update the Moderator cache - #1200
	$cache->update_moderators();
}
