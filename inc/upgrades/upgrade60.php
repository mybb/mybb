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
 * Upgrade Script: 1.8.38
 */

$upgrade_detail = array(
    "revert_all_templates" => 0,
    "revert_all_themes" => 0,
    "revert_all_settings" => 0
);

function upgrade60_dbchanges()
{
	global $output, $mybb, $db, $cache;

	if($db->field_exists('icq', 'users'))
	{
		$db->drop_column('users', 'icq');
	}
	$db->delete_query("settings", "name='allowicqfield'");

	$db->modify_column("posts", "username", "varchar(120)", "set", "''");
	$db->modify_column("threads", "username", "varchar(120)", "set", "''");
}
