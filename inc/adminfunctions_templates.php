<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: adminfunctions_templates.php 4304 2009-01-02 01:11:56Z chris $
 */

/**
 * Find and replace a string in a particular template through every template set.
 *
 * @param string The name of the template
 * @param string The regular expression to match in the template
 * @param string The replacement string
 * @param int Set to 1 to automatically create templates which do not exist for that set (based off master) - Defaults to 1
 * @return bolean true if matched template name, false if not.
 */

function find_replace_templatesets($title, $find, $replace, $autocreate=1)
{
	global $db;
	if($autocreate != 0)
	{
		$query = $db->simple_select("templates", "*", "title='$title' AND sid='-2'");
		$master = $db->fetch_array($query);
		$oldmaster = $master['template'];
		$master['template'] = preg_replace($find, $replace, $master['template']);
		if($oldmaster == $master['template'])
		{
			return false;
		}
		$master['template'] = $db->escape_string($master['template']);
	}
	$query = $db->query("
		SELECT s.sid, t.template, t.tid 
		FROM ".TABLE_PREFIX."templatesets s 
		LEFT JOIN ".TABLE_PREFIX."templates t ON (t.title='$title' AND t.sid=s.sid)
	");
	while($template = $db->fetch_array($query))
	{
		if($template['template']) // Custom template exists for this group
		{
			if(!preg_match($find, $template['template']))
			{
				return false;
			}
			$newtemplate = preg_replace($find, $replace, $template['template']);
			$template['template'] = $newtemplate;
			$update[] = $template;
		}
		elseif($autocreate != 0) // No template exists, create it based off master
		{
			$newtemp = array(
				"title" => $title,
				"template" => $master['template'],
				"sid" => $template['sid']
			);
			$db->insert_query("templates", $newtemp);
		}
	}
	
	if(is_array($update))
	{
		foreach($update as $template)
		{
			$updatetemp = array("template" => $db->escape_string($template['template']), "dateline" => TIME_NOW);
			$db->update_query("templates", $updatetemp, "tid='".$template['tid']."'");
		}
	}
	return true;
}
?>