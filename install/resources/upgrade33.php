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

@set_time_limit(0);

function upgrade33_dbchanges()
{
	global $db, $output;

	$query = $db->simple_select('settings', 'value', "name='username_method'");
	while($setting = $db->fetch_array($query))
	{
		if($setting['value'] == 1 || $setting['value'] == 2)
		{
			$query = $db->simple_select('users', 'email, COUNT(email) AS duplicates', "email!=''", array('group_by' => 'email HAVING duplicates>1'));
			if($db->num_rows($query))
			{
				$db->update_query('settings', array('value' => 0), "name='username_method'");
			}
			else
			{
				$db->update_query('settings', array('value' => 0), "name='allowmultipleemails'");
			}
		}
	}
	
	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("33_done");
}