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
 * Upgrade Script: 1.8.39
 */

$upgrade_detail = array(
    'revert_all_templates' => 0,
    'revert_all_themes' => 0,
    'revert_all_settings' => 0
);

my_set_time_limit();

function upgrade61_dbchanges()
{
	global $output, $mybb, $db, $cache;

	$output->print_header('Updating Database');

	echo '<p>Performing necessary upgrade queries...</p>';

	flush();

	if($db->field_exists('skype', 'users'))
	{
		$db->drop_column('users', 'skype');
	}

	if($db->field_exists('google', 'users'))
	{
		$db->drop_column('users', 'google');
	}

	$db->delete_query('settings', "name IN ('allowskypefield', 'allowgooglefield')");

	$db->delete_query('settinggroups', "name IN ('contactdetails')");

	$output->print_contents('<p>Click next to continue with the upgrade process.</p>');

	$output->print_footer('61_done');
}