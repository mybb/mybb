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
 * Upgrade Script: 1.8.4
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

function upgrade33_dbchanges()
{
	global $db, $output;

	// Updating Database

	if($db->field_exists('2fasecret', 'adminoptions') && !$db->field_exists('authsecret', 'adminoptions'))
	{
		$db->rename_column('adminoptions', '2fasecret', 'authsecret', "varchar(16) NOT NULL default ''");
	}
}
