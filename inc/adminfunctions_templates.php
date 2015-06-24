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
 * Find and replace a string in a particular template through every template set.
 *
 * @param string $title The name of the template
 * @param string $find The regular expression to match in the template
 * @param string $replace The replacement string
 * @param int $autocreate Set to 1 to automatically create templates which do not exist for sets with SID > 0 (based off master) - defaults to 1
 * @param mixed $sid Template SID to modify, false for every SID > 0 and SID = -1
 * @param int $limit The maximum possible replacements for the regular expression
 * @return boolean true if updated one or more templates, false if not.
 */

function find_replace_templatesets($title, $find, $replace, $autocreate=1, $sid=false, $limit=-1)
{
	global $db, $mybb;

	$return = false;
	$template_sets = array(-2, -1);
	
	// Select all templates with that title (including global) if not working on a specific template set
	$sqlwhere = '>0 OR sid=-1';
	$sqlwhere2 = '>0';

	// Otherwise select just templates from that specific set
	if($sid !== false)
	{
		$sid = (int)$sid;
		$sqlwhere2 = $sqlwhere = "=$sid";
	}

	// Select all other modified templates with that title
	$query = $db->simple_select("templates", "tid, sid, template", "title = '".$db->escape_string($title)."' AND (sid{$sqlwhere})");
	while($template = $db->fetch_array($query))
	{
		// Keep track of which templates sets have a modified version of this template already
		$template_sets[] = $template['sid'];

		// Update the template if there is a replacement term or a change
		$new_template = preg_replace($find, $replace, $template['template'], $limit);
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
		$master_template['new_template'] = preg_replace($find, $replace, $master_template['template'], $limit);

		if($master_template['new_template'] != $master_template['template'])
		{
			// Update the rest of our template sets that are currently inheriting this template from our master set
			$query = $db->simple_select("templatesets", "sid", "sid NOT IN (".implode(',', $template_sets).") AND (sid{$sqlwhere2})");
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
