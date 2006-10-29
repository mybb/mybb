<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

function update_import_session()
{
	global $import_session, $cache;

	// TEMPORARY
	global $mybb;
	if($mybb->input['debug'])
	{
		echo "<pre>";
		print_r($import_session);
		echo "</pre>";
	}

	$cache->update("import_cache", $import_session);
}

/**
 * Convert an integer 1/0 into text yes/no
 * @param int Integer to be converted
 * @return string Correspondig yes or no
 */
function int_to_yesno($var)
{
	$var = intval($var);
	
	if($var == 1)
	{
		return 'yes';
	}
	else
	{
		return 'no';
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


/** TODO: This function should actually DROP those columns if they exist and then recreate them **/
function create_import_fields()
{
	global $db;
	
	if(!$db->field_exists('importuid', "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD importuid int NOT NULL default '0' AFTER uid");
	}
	
	if(!$db->field_exists('importfid', "forums"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD importfid int NOT NULL default '0' AFTER fid");
	}
	
	if(!$db->field_exists('importtid', "threads"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD importtid int NOT NULL default '0' AFTER tid");
	}
	
	if(!$db->field_exists('importpid', "posts"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."posts ADD importpid int NOT NULL default '0' AFTER pid");
	}
	
	if(!$db->field_exists('importaid', "attachments"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."attachments ADD importaid int NOT NULL default '0' AFTER aid");
	}
	
	if(!$db->field_exists('importgid', "usergroups"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD importgid int NOT NULL default '0' AFTER gid");
	}
}
?>