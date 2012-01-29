<?php

/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class Profiles implements Modules
{
	public function setup()
	{
	}
	
	public function end()
	{
	}

	public function run_pre()
	{
		global $mybb, $lang, $db, $templates, $mods, $inline_errors, $action;
	}
	
	public function run()
	{
		global $mybb, $db, $lang, $mods, $primerblock, $rightblock, $content, $title, $theme, $templates, $navigation, $inline_errors;
		
		$uid = (int)$mybb->input['uid'];
	
		// verify user existance
		$q = $db->simple_select('users', 'uid,username', 'uid=\''.$uid.'\'');
		$user = $db->fetch_array($q);
		if (empty($user))
		{
			$mods->error($lang->mods_invalid_uid);
		}
		
		// pagination
		$per_page = 15;
		$mybb->input['page'] = intval($mybb->input['page']);
		if($mybb->input['page'] && $mybb->input['page'] > 1)
		{
			$mybb->input['page'] = intval($mybb->input['page']);
			$start = ($mybb->input['page']*$per_page)-$per_page;
		}
		else
		{
			$mybb->input['page'] = 1;
			$start = 0;
		}
		
		// If we're the author of the profile or if we're moderators we can view hidden projects
		if ($mybb->user['uid'] == $user['uid'] || $mods->check_permissions($mybb->settings['mods_mods']))
			$hidden = '';
		else
			$hidden = ' AND hidden=\'0\'';
		
		$query = $db->simple_select("mods_projects", "COUNT(pid) as projects", "(uid={$uid} OR (CONCAT(',',testers,',') LIKE '%,{$uid},%' AND bugtracker_link='' AND bugtracking='1') OR CONCAT(',',collaborators,',') LIKE '%,{$uid},%') AND approved=1 {$hidden}");
		$total_rows = $db->fetch_field($query, "projects");
		
		// multi-page
		if ($total_rows > $per_page)
			$multipage = multipage($total_rows, $per_page, $mybb->input['page'], $mybb->settings['bburl']."/mods.php?action=panel&amp;panelaction=projects");	
		
		// Get list of categories
		$cats = $mods->categories->getAll();
		
		// If we're the author of the profile we can view our hidden projects (differs from the above because we set the prefix p. here)
		if ($mybb->user['uid'] == $user['uid'] || $mods->check_permissions($mybb->settings['mods_mods']))
			$hidden = '';
		else
			$hidden = ' AND p.hidden=\'0\'';
		
		// Get list of projects that we're part of and that have been updated recently
		// We can't use our _fantastic_ (lol) Mods class
		$query = $db->query("
			SELECT u.*, p.*
			FROM ".TABLE_PREFIX."mods_projects p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE (p.uid={$uid} OR (CONCAT(',',p.testers,',') LIKE '%,{$uid},%' AND p.bugtracker_link='' AND p.bugtracking='1') OR CONCAT(',',p.collaborators,',') LIKE '%,{$uid},%') AND p.approved=1 {$hidden}
			ORDER BY p.lastupdated DESC, p.submitted DESC LIMIT {$start}, {$per_page}
		");
		
		$projects = '';
		
		while($project = $db->fetch_array($query))
		{
			switch ($cats[$project['cid']]['parent'])
			{
				case 'plugins':
					$icon = 'plugin';
					break;
				case 'themes':
					$icon = 'theme';
					break;
				case 'resources':
					$icon = 'resource';
					break;
				case 'graphics':
					$icon = 'graphic';
					break;
			}
		
			$project['name'] = htmlspecialchars_uni($project['name']);
			if ($project['hidden'] == 1)
			{
				$project['hidden'] = $lang->mods_hidden_project;
			}
			else
				$project['hidden'] = '';
			
			$project['description'] = htmlspecialchars_uni($project['description']);
			
			$project['author'] = build_profile_link(htmlspecialchars_uni($project['username']), $project['uid']);
			
			if ($project['lastupdated'] == 0)
				$project['lastupdated'] = $lang->mods_never;
			else
				$project['lastupdated'] = my_date($mybb->settings['dateformat'], $project['lastupdated'], '', false).', '.my_date($mybb->settings['timeformat'], $project['lastupdated']);
				
			eval("\$projects .= \"".$templates->get("mods_profile_project")."\";");
		}
		
		if (empty($projects))
		{
			$colspan = 4;
			eval("\$projects = \"".$templates->get("mods_no_data")."\";");
		}
		
		$lang->mods_profile_list_of_projects = $lang->sprintf($lang->mods_profile_list_of_projects, htmlspecialchars_uni($user['username']));
		
		// Title
		$title .= ' - '.$lang->mods_profile_of.' - '.htmlspecialchars_uni($user['username']);
		
		$primer['title'] = $lang->mods_primer_profile_title;
		$primer['content'] = $lang->mods_primer_profile_content;
		$meta_description = $primer['content'];
		$primer['content'] = '<p>'.$primer['content'].'</p>';
		
		eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
		eval("\$content = \"".$templates->get("mods_profile")."\";");
	}
}

?>