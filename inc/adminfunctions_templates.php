<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: adminfunctions_templates.php 5297 2010-12-28 22:01:14Z Tomm $
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
	
	$template_sets = array(-2, -1);
	
	// Select all global with that title
	$query = $db->simple_select("templates", "tid, template", "title = '".$db->escape_string($title)."' AND sid='-1'");
	while($template = $db->fetch_array($query))
	{
		// Update the template if there is a replacement term or a change
		$new_template = preg_replace($find, $replace, $template['template']);
		if($new_template == $template['template'])
		{
			continue;
		}
		
		// The template is a custom template.  Replace as normal.
		$updated_template = array(
			"template" => $db->escape_string($new_template)
		);
		$db->update_query("templates", $updated_template, "tid='{$template['tid']}'");
	}
	
	// Select all other modified templates with that title
	$query = $db->simple_select("templates", "tid, sid, template", "title = '".$db->escape_string($title)."' AND sid > 0");
	while($template = $db->fetch_array($query))
	{
		// Keep track of which templates sets have a modified version of this template already
		$template_sets[] = $template['sid'];
		
		// Update the template if there is a replacement term or a change
		$new_template = preg_replace($find, $replace, $template['template']);
		if($new_template == $template['template'])
		{
			continue;
		}
		
		// The template is a custom template.  Replace as normal.
		$updated_template = array(
			"template" => $db->escape_string($new_template)
		);
		$db->update_query("templates", $updated_template, "tid='{$template['tid']}'");
		
		$return = true;
	}
	
	// Add any new templates if we need to and are allowed to
	if($autocreate != 0)
	{
		// Select our master template with that title
		$query = $db->simple_select("templates", "title, template", "title='".$db->escape_string($title)."' AND sid='-2'", array('limit' => 1));
		$master_template = $db->fetch_array($query);
		$master_template['new_template'] = preg_replace($find, $replace, $master_template['template']);
		
		if($master_template['new_template'] != $master_template['template'])
		{
			// Update the rest of our template sets that are currently inheriting this template from our master set			
			$query = $db->simple_select("templatesets", "sid", "sid NOT IN (".implode(',', $template_sets).")");
			while($template = $db->fetch_array($query))
			{
				$insert_template = array(
					"title" => $db->escape_string($master_template['title']),
					"template" => $db->escape_string($master_template['new_template']),
					"sid" => $template['sid'],
					"version" => $mybb->version_code,
					"status" => '',
					"dateline" => TIME_NOW
				);
				$db->insert_query("templates", $insert_template);
				
				$return = true;
			}
		}
	}
	
	return $return;
}
?>