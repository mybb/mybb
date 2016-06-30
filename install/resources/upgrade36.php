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
 * Upgrade Script: 1.8.7
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade36_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->field_exists('enabled', 'attachtypes'))
	{
		$db->drop_column('attachtypes', 'enabled');
	}

	if($db->field_exists('groups', 'attachtypes'))
	{
		$db->drop_column('attachtypes', 'groups');
	}

	if($db->field_exists('forums', 'attachtypes'))
	{
		$db->drop_column('attachtypes', 'forums');
	}

	if($db->field_exists('avatarfile', 'attachtypes'))
	{
		$db->drop_column('attachtypes', 'avatarfile');
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column('attachtypes', 'enabled', "smallint NOT NULL default '1'");
			$db->add_column('attachtypes', 'groups', "text NOT NULL default '-1'");
			$db->add_column('attachtypes', 'forums', "text NOT NULL default '-1'");
			$db->add_column('attachtypes', 'avatarfile', "smallint NOT NULL default '0'");
			break;
		default:
			$db->add_column('attachtypes', 'enabled', "tinyint(1) NOT NULL default '1'");
			$db->add_column('attachtypes', 'groups', "TEXT NOT NULL");
			$db->add_column('attachtypes', 'forums', "TEXT NOT NULL");
			$db->add_column('attachtypes', 'avatarfile', "tinyint(1) NOT NULL default '0'");

			$db->update_query('attachtypes', array('groups' => '-1', 'forums' => '-1'));
			break;
	}

	$db->update_query('attachtypes', array('avatarfile' => 1), "atid IN (2, 4, 7, 11)");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("36_done");
}