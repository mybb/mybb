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
function find_replace_templatesets($title, $find, $replace, $autocreate=1)
{
	global $db;
	if($autocreate != 0)
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='$title' AND sid='-2'");
		$master = $db->fetch_array($query);
		$oldmaster = $master['template'];
		$master['template'] = preg_replace($find, $replace, $master['template']);
		if($oldmaster == $master['template'])
		{
			return false;
		}
		$master['template'] = addslashes($master['template'];
	}
	$query = $db->query("SELECT s.sid, t.template, t.tid FROM ".TABLE_PREFIX."templatesets s LEFT JOIN ".TABLE_PREFIX."templates t ON (t.title='$title' AND t.sid=s.sid)");
	while($template = $db->fetch_array($query))
	{
		if($template['template']) // Custom template exists for this group
		{
			$newtemplate = preg_replace($find, $replace, $template['template']);
			if($newtemplate == $template['template'])
			{
				return false;
			}
			$template['template'] = $newtemplate;
			$update[] = $template;
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
	if(is_array($update))
	{
		foreach($update as $template)
		{
			$updatetemp = array("template" => addslashes($template['template']));
			$db->update_query(TABLE_PREFIX."templates", $updatetemp, "tid='".$template['tid']."'");
		}
	}
	return true;
}