<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
 *
 * $Id$
 */

function update_import_session()
{
	global $import_session, $cache, $board, $db, $querytime;

	// TEMPORARY
	global $mybb;
	if($mybb->input['debug'])
	{
		echo "<pre>";
		print_r($import_session);
		echo "</pre>";
	}

	if(!$import_session['completed'])
	{
		$import_session['completed'] = array();
	}
	
	// Stats
	if(!empty($board->old_db->query_count))
	{
		$import_session['olddb_query_count'] += $board->old_db->query_count;
	}
	$import_session['newdb_query_count'] += $db->query_count;
	$import_session['total_query_time'] += $querytime;

	$import_session['completed'] = array_unique($import_session['completed']);

	$cache->update("import_cache", $import_session);
}

/**
 * Convert an integer 1/0 into text yes/no
 * @param int Integer to be converted
 * @return string Correspondig yes or no
 */
function int_to_yesno($var)
{
	return int_to_yes_no($var, 1);
}

/**
 * Convert an integer 1/0 into text no/yes
 * @param int Integer to be converted
 * @return string Correspondig no or yes
 */
function int_to_noyes($var)
{
	return int_to_yes_no($var, 0);
}

function int_to_yes_no($setting, $yes=1)
{
	$setting = intval($setting);

	if($setting == 0 && $yes == 1)
	{
		return "no";
	}
	elseif($setting == 1 && $yes == 1)
	{
		return "yes";
	}
	elseif($setting == 0 && $yes == 0)
	{
		return "yes";
	}
	elseif($setting == 1 && $yes == 0)
	{
		return "no";
	}
	else
	{
		return "yes";
	}
}

/**
 * Convert an integer 1/0 into text on/off
 * @param int Integer to be converted
 * @return string Correspondig on or off
 */
function int_to_on_off($setting, $on=1)
{
	$setting = intval($setting);

	if($setting == 0 && $on == 1)
	{
		return "off";
	}
	elseif($setting == 1 && $on == 1)
	{
		return "on";
	}
	elseif($setting == 0 && $on == 0)
	{
		return "on";
	}
	elseif($setting == 1 && $on == 0)
	{
		return "off";
	}
	else
	{
		return "on";
	}
}

/**
 * Return a formatted list of errors
 * 
 * @param array Errors
 * @return string Formatted errors list
 */
function error_list($array)
{
	$string = "<ul>\n";
	foreach($array as $error)
	{
		$string .= "<li>{$error}</li>\n";
	}
	$string .= "</ul>\n";
	return $string;
}

/**
 * Remove the temporary importing data fields
 */
function delete_import_fields()
{
	global $db;

	if($db->field_exists('import_uid', "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP import_uid");
	}

	if($db->field_exists('import_usergroup', "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP import_usergroup");
	}

	if($db->field_exists('import_additionalgroups', "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP import_additionalgroups");
	}

	if($db->field_exists('import_displaygroup', "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP import_displaygroup");
	}

	if($db->field_exists('import_fid', "forums"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."forums DROP import_fid");
	}

	if($db->field_exists('import_tid', "threads"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."threads DROP import_tid");
	}

	if($db->field_exists('import_uid', "threads"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."threads DROP import_uid");
	}

	if($db->field_exists('import_poll', "threads"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."threads DROP import_poll");
	}

	if($db->field_exists('import_pid', "posts"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."posts DROP import_pid");
	}

	if($db->field_exists('import_tid', "polls"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."polls DROP import_tid");
	}

	if($db->field_exists('import_uid', "posts"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."posts DROP import_uid");
	}

	if($db->field_exists('import_gid', "usergroups"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP import_gid");
	}

	if($db->field_exists('import_pmid', "privatemessages"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages DROP import_pmid");
	}

	if($db->field_exists('import_pid', "polls"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."polls DROP import_pid");
	}

	if($db->field_exists('import_gid', "settinggroups"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."settinggroups DROP import_gid");
	}

	if($db->field_exists('import_eid', "events"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."events DROP import_eid");
	}

	if($db->field_exists('import_aid', "attachments"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."attachments DROP import_aid");
	}

	if($db->field_exists('import_atid', "attachtypes"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."attachtypes DROP import_atid");
	}
}

/**
 * Create the temporary importing data fields
 */
function create_import_fields()
{
	global $db;

	// First clear all.
	delete_import_fields();

	// Add to our heart's content
	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD import_uid int NOT NULL default '0' AFTER uid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD import_usergroup int NOT NULL default '0' AFTER usergroup");
	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD import_additionalgroups text NOT NULL AFTER additionalgroups");
	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD import_displaygroup int NOT NULL default '0' AFTER displaygroup");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD import_fid int NOT NULL default '0' AFTER fid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD import_tid int NOT NULL default '0' AFTER tid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD import_uid int NOT NULL default '0' AFTER uid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD import_poll int NOT NULL default '0' AFTER poll");
	$db->query("ALTER TABLE ".TABLE_PREFIX."posts ADD import_pid int NOT NULL default '0' AFTER pid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."polls ADD import_tid int NOT NULL default '0' AFTER tid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."posts ADD import_uid int NOT NULL default '0' AFTER uid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."attachments ADD import_aid int NOT NULL default '0' AFTER aid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD import_gid int NOT NULL default '0' AFTER gid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD import_pmid int NOT NULL default '0' AFTER pmid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."polls ADD import_pid int NOT NULL default '0' AFTER pid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."settinggroups ADD import_gid int NOT NULL default '0' AFTER gid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."events ADD import_eid int NOT NULL default '0' AFTER eid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."attachtypes ADD import_atid int NOT NULL default '0' AFTER atid");
}
?>