<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

//
// A function to search through all template sets for a particular template
// replace something in that template, and then update it. It can also create
// (by default) templates for sets which arent customised, based off the master
//
function find_replace_templatesets($title, $find, $replace, $autocreate=1, $casesensitive=1)
{
	global $db;
	if($casesensitive != 1)
	{
		$function = "stri_replace";
	}
	else
	{
		$function = "str_replace";
	}
	if($autocreate != 0)
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='$title' AND sid='-2'");
		$master = $db->fetch_array($query);
		$master['template'] = addslashes($function($find, $replace, $master['template']));
	}
	$query = $db->query("SELECT s.sid, t.template, t.tid FROM ".TABLE_PREFIX."templatesets s LEFT JOIN ".TABLE_PREFIX."templates t ON (t.title='$title' AND t.sid=s.sid)");
	while($template = $db->fetch_array($query))
	{
		if($template['template']) // Custom template exists for this group
		{
			$template['template'] = $function($find, $replace, $template['template']);
			$updatetemp = array("template" => addslashes($template['template']));
			$db->update_query(TABLE_PREFIX."templates", $updatetemp, "tid='".$template['tid']."'");
		}
		elseif($autocreate != 0) // No template exists, create it based off master
		{
			$newtemp = array(
				"tid" => "NULL",
				"title" => $title,
				"template" => $master['template'],
				"sid" => $template['sid']
				);
			$db->insert_query(TABLE_PREFIX."templates", $newtemp);
		}
	}
}