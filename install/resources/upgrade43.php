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
 * Upgrade Script: 1.8.15
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade43_dbchanges()
{
	global $output, $mybb, $db, $cache;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->field_exists('regex', 'badwords'))
	{
		$db->drop_column('badwords', 'regex');
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("badwords", "regex", "smallint NOT NULL default '0'");
			break;
		default:
			$db->add_column("badwords", "regex", "tinyint(1) NOT NULL default '0'");
			break;
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("43_done");
}