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
 * Upgrade Script: 1.8.13 or 1.8.14
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade42_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->field_exists('ipaddress', 'pollvotes'))
	{
		$db->drop_column('pollvotes', 'ipaddress');
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("pollvotes", "ipaddress", "bytea NOT NULL default ''");
			break;
		case "sqlite":
			$db->add_column("pollvotes", "ipaddress", "blob(16) NOT NULL default ''");
			break;
		default:
			$db->add_column("pollvotes", "ipaddress", "varbinary(16) NOT NULL default ''");
			break;
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("42_done");
}
