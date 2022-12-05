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

function upgrade56_dbchanges()
{
	global $output, $db;

	$output->print_header('Updating Database');

	echo '<p>Performing necessary upgrade queries...</p>';
	flush();

	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'users CHANGE `style` `style` varchar(30) NOT NULL DEFAULT \'\'');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'forums CHANGE `style` `style` varchar(30) NOT NULL DEFAULT \'\'');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'users ADD COLUMN `password_algorithm` varchar(30) NOT NULL DEFAULT \'\'');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'users CHANGE `password` `password` varchar(500) NOT NULL default \'\'');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'users DROP COLUMN `icq`');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'users DROP COLUMN `skype`');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'users DROP COLUMN `google`');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'profilefields ADD COLUMN `contact` tinyint(1) NOT NULL default \'0\'');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'threads ADD COLUMN `moved` int unsigned NOT NULL default \'0\'');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'threads CHANGE `closed` `closed` tinyint(1) NOT NULL default \'0\'');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'userfields ADD COLUMN `fid4` text NOT NULL');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'userfields ADD COLUMN `fid5` text NOT NULL');
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'userfields ADD COLUMN `fid6` text NOT NULL');

	$db->drop_table('themes');
	$db->drop_table('themestylesheets');
	$db->drop_table('templategroups');
	$db->drop_table('templates');
	$db->drop_table('templatesets');

	$output->print_contents('<p>Click next to continue with the upgrade process.</p>');
	$output->print_footer('56_done');
}
