<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: adminfunctions_templates.php 5164 2010-08-02 03:35:35Z RyanGordon $
 */

/**
 * Find and replace a string in a particular template through every template set.
 *
 * @param string The name of the template
 * @param string The regular expression to match in the template
 * @param string The replacement string
 * @param int Set to 1 to automatically create templates which do not exist for that set (based off master) - Defaults to 1
 * @return bolean true if updated one or more templates, false if not.
 */

function find_replace_templatesets($title, $find, $replace, $autocreate=1)
{
	global $db, $mybb;
	
	$return = false;
	
	// Select all templates with that title
	$query = $db->query("
		SELECT t.tid, t.title, t.sid, t.template
		FROM ".TABLE_PREFIX."templates t
		LEFT JOIN ".TABLE_PREFIX."templatesets s ON (t.sid=s.sid)
		LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t.title=t2.title AND t2.sid='1')
		WHERE t.title = '".$db->escape_string($title)."' AND NOT (t.sid = -2 AND NOT ISNULL(t2.tid))
		ORDER BY t.title ASC
	");
	while($template = $db->fetch_array($query))
	{
		$new_template = preg_replace($find, $replace, $template['template']);
		if($new_template == $template['template'])
		{
			continue;
		}
		
		// The template is a master template.  We have to make a new custom template.
		if($template['sid'] == -2)
		{
			// If we're allowed
			if($autocreate != 0)
			{
				$insert_template = array(
					"title" => $db->escape_string($template['title']),
					"template" => $db->escape_string($new_template),
					"sid" => 1,
					"version" => $mybb->version_code,
					"status" => '',
					"dateline" => TIME_NOW
				);
				$db->insert_query("templates", $insert_template);
				
				$return = true;
			}
		}
		else if(preg_match($find, $template['template']))
		{
			// The template is a custom template.  Replace as normal.
			// Update the template if there is a replacement term
			$updated_template = array(
				"template" => $db->escape_string($new_template)
			);
			$db->update_query("templates", $updated_template, "tid='{$template['tid']}'");
			
			$return = true;
		}
	}
	
	return $return;
}
?>