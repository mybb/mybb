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
 * Upgrade Script: 1.6.4
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade21_dbchanges()
{
	global $cache, $db, $output, $mybb;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";

	$db->delete_query("settings", "name = 'standardheaders'");

	if($db->field_exists('showinbirthdaylist', 'usergroups'))
	{
		$db->drop_column("usergroups", "showinbirthdaylist");
	}

	if($db->field_exists('canoverridepm', 'usergroups'))
	{
		$db->drop_column("usergroups", "canoverridepm");
	}

	if($db->field_exists('canusesig', 'usergroups'))
	{
		$db->drop_column("usergroups", "canusesig");
	}

	if($db->field_exists('canusesigxposts', 'usergroups'))
	{
		$db->drop_column("usergroups", "canusesigxposts");
	}

	if($db->field_exists('signofollow', 'usergroups'))
	{
		$db->drop_column("usergroups", "signofollow");
	}

	if($db->field_exists('postnum', 'profilefields'))
	{
		$db->drop_column("profilefields", "postnum");
	}

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$db->add_column("profilefields", "postnum", "bigint NOT NULL default '0'");
			$db->add_column("usergroups", "showinbirthdaylist", "int NOT NULL default '0'");
			$db->add_column("usergroups", "canoverridepm", "int NOT NULL default '0'");
			$db->add_column("usergroups", "canusesig", "int NOT NULL default '0'");
			$db->add_column("usergroups", "canusesigxposts", "bigint NOT NULL default '0'");
			$db->add_column("usergroups", "signofollow", "int NOT NULL default '0'");
			break;
		default:
			$db->add_column("profilefields", "postnum", "bigint(30) NOT NULL default '0'");
			$db->add_column("usergroups", "showinbirthdaylist", "int(1) NOT NULL default '0'");
			$db->add_column("usergroups", "canoverridepm", "int(1) NOT NULL default '0'");
			$db->add_column("usergroups", "canusesig", "int(1) NOT NULL default '0'");
			$db->add_column("usergroups", "canusesigxposts", "bigint(30) NOT NULL default '0'");
			$db->add_column("usergroups", "signofollow", "int(1) NOT NULL default '0'");
			break;
	}

	// Update all usergroups to show in the birthday list
	$db->update_query("usergroups", array("showinbirthdaylist" => 1));

	// Update our nice usergroups to use a signature
	$groups = $cache->read("usergroups");

	foreach($groups as $group)
	{
		$disallowed_array = array(1, 5, 7);
		if(in_array($group['gid'], $disallowed_array) || $group['isbannedgroup'] == 1)
		{
			continue;
		}

		$db->update_query("usergroups", array("canusesig" => 1), "gid = '{$group['gid']}'");
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("21_done");
}

