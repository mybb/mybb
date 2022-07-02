<?php
/**
 * MyBB 1.9
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Upgrade Script: 1.9.0
 */

$upgrade_detail = array(
    'revert_all_templates' => 0,
    'revert_all_themes' => 0,
    'revert_all_settings' => 0
);

@set_time_limit(0);

function upgrade54_dbchanges()
{
	global $output, $db;

	$output->print_header('Updating Database');

	echo '<p>Performing necessary upgrade queries...</p>';
	flush();

	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'users CHANGE `style` `style` varchar(30) NOT NULL DEFAULT \'\'');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'forums CHANGE `style` `style` varchar(30) NOT NULL DEFAULT \'\'');
	$db->drop_table('themes');
	$db->drop_table('themestylesheets');
	$db->drop_table('templategroups');
	$db->drop_table('templates');
	$db->drop_table('templatesets');

	$output->print_contents('<p>Click next to continue with the upgrade process.</p>');
	$output->print_footer('54_done');
}
