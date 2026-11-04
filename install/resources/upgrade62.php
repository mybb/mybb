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
 * Upgrade Script: 1.8.40 or 1.8.41
 */

$upgrade_detail = array(
	'revert_all_templates' => 0,
	'revert_all_themes' => 0,
	'revert_all_settings' => 0
);

my_set_time_limit();

function upgrade62_dbchanges()
{
	global $output, $db;

	$output->print_header('Updating Database');

	echo '<p>Performing necessary upgrade queries...</p>';

	flush();

	if(in_array($db->type, array('mysql', 'mysqli', 'mysql_pdo')) && !$db->index_exists('attachments', 'visible'))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments ADD INDEX visible (visible)");
	}

	$output->print_contents('<p>Click next to continue with the upgrade process.</p>');

	$output->print_footer('62_done');
}
