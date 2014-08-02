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
 * Upgrade Script: Preview Release 2
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 1,
	"requires_deactivated_plugins" => 1,
);

@set_time_limit(0);

function upgrade4_dbchanges()
{
	global $db, $output;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";

	$db->write_query("UPDATE ".TABLE_PREFIX."users SET style='0' WHERE style='-1';");
	$db->write_query("UPDATE ".TABLE_PREFIX."users SET displaygroup='0' WHERE displaygroup='-1';");
	$db->write_query("UPDATE ".TABLE_PREFIX."forums SET style='0' WHERE style='-1';");
	$query = $db->simple_select("adminoptions", "uid='0'");
	$test = $db->fetch_array($query);
	if(!isset($test['uid']))
	{
		$db->write_query("UPDATE ".TABLE_PREFIX."adminoptions SET uid='0' WHERE uid='-1';");
	}

	if($db->field_exists('messageindex', "threads"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP messageindex;");
	}
	if($db->field_exists('subjectindex', "threads"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP subjectindex;");
	}
	if($db->field_exists('moderators', "forums"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP moderators;");
	}

	if($db->field_exists('version', "templates"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."templates DROP version;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."templates ADD version varchar(20) NOT NULL default '0';");

	if($db->field_exists('status', "templates"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."templates DROP status;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."templates ADD status varchar(10) NOT NULL default '';");

	if($db->field_exists('dateline', "templates"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."templates DROP dateline;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."templates ADD dateline int(10) NOT NULL default '0';");

	$db->write_query("UPDATE ".TABLE_PREFIX."templates SET version='100.06' WHERE sid>0");

	echo "Done</p>";

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("4_done");
}

