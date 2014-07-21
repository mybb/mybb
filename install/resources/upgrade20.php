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
 * Upgrade Script: 1.6.3
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade20_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Repairing Database Sequences");

	echo "<p>Performing necessary upgrade queries...</p>";

	// Update the sequences for pgSQL - #1094, #1248
	if($mybb->config['database']['type'] == "pgsql")
	{
		$db->query("SELECT setval('{$mybb->config['database']['table_prefix']}attachtypes_atid_seq', (SELECT max(atid) FROM {$mybb->config['database']['table_prefix']}attachtypes));");
		$db->query("SELECT setval('{$mybb->config['database']['table_prefix']}forums_fid_seq', (SELECT max(fid) FROM {$mybb->config['database']['table_prefix']}forums));");
		$db->query("SELECT setval('{$mybb->config['database']['table_prefix']}helpdocs_hid_seq', (SELECT max(hid) FROM {$mybb->config['database']['table_prefix']}helpdocs));");
		$db->query("SELECT setval('{$mybb->config['database']['table_prefix']}helpsections_sid_seq', (SELECT max(sid) FROM {$mybb->config['database']['table_prefix']}helpsections));");
		$db->query("SELECT setval('{$mybb->config['database']['table_prefix']}icons_iid_seq', (SELECT max(iid) FROM {$mybb->config['database']['table_prefix']}icons));");
		$db->query("SELECT setval('{$mybb->config['database']['table_prefix']}profilefields_fid_seq', (SELECT max(fid) FROM {$mybb->config['database']['table_prefix']}profilefields));");
		$db->query("SELECT setval('{$mybb->config['database']['table_prefix']}smilies_sid_seq', (SELECT max(sid) FROM {$mybb->config['database']['table_prefix']}smilies));");
		$db->query("SELECT setval('{$mybb->config['database']['table_prefix']}spiders_sid_seq', (SELECT max(sid) FROM {$mybb->config['database']['table_prefix']}spiders));");
		$db->query("SELECT setval('{$mybb->config['database']['table_prefix']}templategroups_gid_seq', (SELECT max(gid) FROM {$mybb->config['database']['table_prefix']}templategroups));");
		$db->query("SELECT setval('{$mybb->config['database']['table_prefix']}usergroups_gid_seq', (SELECT max(gid) FROM {$mybb->config['database']['table_prefix']}usergroups));");
	}

	$db->add_column("adminviews", "custom_profile_fields", "text NOT NULL AFTER conditions");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("20_done");
}

