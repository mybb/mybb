<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://mybb.com/about/license
 *
 * $Id: $
 */

/**
 * Upgrade Script: 1.6.0
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade18_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";

	// Update the usergroup sequence for pgSQL - #1094
	if($mybb->config['database']['type'] == "pgsql")
	{
		$query = $db->simple_select("usergroups", "COUNT(gid) AS group_count");
		$group_count = $db->fetch_field($query, "group_count");

		++$group_count;
		$db->query("ALTER SEQUENCE ".$config['database']['table_prefix']."usergroups_gid_seq RESTART WITH ".$group_count."");
	}

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("18_done");
}
?>