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

class Stats implements Modules
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
		
		$query = $db->simple_select("mods_projects", "COUNT(pid) as projects");
		$total_projects = $db->fetch_field($query, "projects");
		
		$query = $db->simple_select("mods_builds", "COUNT(bid) as builds");
		$total_builds = $db->fetch_field($query, "builds");
		
		// Get list of categories
		$cats = $mods->categories->getAll();
		
		// Most Downloaded Projects
		$query = $db->query("
			SELECT u.*, p.*
			FROM ".TABLE_PREFIX."mods_projects p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.approved=1 AND p.hidden='0'
			ORDER BY p.downloads DESC, p.lastupdated DESC, p.submitted DESC LIMIT 10
		");
		
		$most_downloaded = '';
		
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
			
			// replace date with total downloads
			$project['lastupdated'] = $project['downloads'];
			
			eval("\$most_downloaded .= \"".$templates->get("mods_profile_project")."\";");
		}
		
		if (empty($most_downloaded))
		{
			$colspan = 4;
			eval("\$most_downloaded = \"".$templates->get("mods_no_data")."\";");
		}
		
		// Most recommended projects
		$query = $db->query("
			SELECT r.*, p.*, u.uid AS useruid, u.username, COUNT(r.pid) as total_recommends
			FROM ".TABLE_PREFIX."mods_recommended r
			LEFT JOIN ".TABLE_PREFIX."mods_projects p ON (p.pid=r.pid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.approved=1 AND p.hidden='0'
			GROUP BY p.pid
			ORDER BY total_recommends DESC
			LIMIT 5
		");
		
		$most_recommended = '';
		
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
			
			// replace date with total recommendations
			$project['lastupdated'] = $project['total_recommends'].'x';
			
			eval("\$most_recommended .= \"".$templates->get("mods_profile_project")."\";");
		}
		
		if (empty($most_recommended))
		{
			$colspan = 4;
			eval("\$most_recommended = \"".$templates->get("mods_no_data")."\";");
		}
		
		// Title
		$title .= ' - '.$lang->mods_statistics;
		
		$primer['title'] = $lang->mods_primer_statistics_title;
		$primer['content'] = $lang->mods_primer_statistics_content;
		$meta_description = $primer['content'];
		$primer['content'] = '<p>'.$primer['content'].'</p>';
		
		eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
		eval("\$content = \"".$templates->get("mods_statistics")."\";");
	}
}

?>