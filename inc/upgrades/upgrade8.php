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
 * Upgrade Script: 1.2.2
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0,
	"requires_deactivated_plugins" => 0,
);

@set_time_limit(0);

function upgrade8_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";

	if($db->field_exists('oldadditionalgroups', "banned"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."banned DROP oldadditionalgroups;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."banned ADD oldadditionalgroups TEXT NOT NULL AFTER oldgroup");


	if($db->field_exists('olddisplaygroup', "banned"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."banned DROP olddisplaygroup;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."banned ADD olddisplaygroup int NOT NULL default '0' AFTER oldadditionalgroups");

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("8_done");
}

