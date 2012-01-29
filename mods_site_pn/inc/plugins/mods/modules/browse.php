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

class Browse implements Modules
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
		
		$where = '';
	
		if ($mods->categories->validateParent($mybb->input['category']))
		{
			switch ($mybb->input['category'])
			{
				case 'plugins':
					$where = 'parent=\'plugins\'';
					$place = $lang->mods_plugins;
					
					$primer['title'] = $lang->mods_primer_plugins_title;
					$primer['content'] = $lang->mods_primer_plugins_content;
					$meta_description = $primer['content'];
					$primer['content'] = '<p>'.$primer['content'].'</p>';
					break;
				case 'themes':
					$where = 'parent=\'themes\'';
					$place = $lang->mods_themes;
					
					$primer['title'] = $lang->mods_primer_themes_title;
					$primer['content'] = $lang->mods_primer_themes_content;
					$meta_description = $primer['content'];
					$primer['content'] = '<p>'.$primer['content'].'</p>';
					break;
				case 'resources':
					$where = 'parent=\'resources\'';
					$place = $lang->mods_resources;
					
					$primer['title'] = $lang->mods_primer_resources_title;
					$primer['content'] = $lang->mods_primer_resources_content;
					$meta_description = $primer['content'];
					$primer['content'] = '<p>'.$primer['content'].'</p>';
					break;
				case 'graphics':
					$where = 'parent=\'graphics\'';
					$place = $lang->mods_graphics;
					
					$primer['title'] = $lang->mods_primer_graphics_title;
					$primer['content'] = $lang->mods_primer_graphics_content;
					$meta_description = $primer['content'];
					$primer['content'] = '<p>'.$primer['content'].'</p>';
					break;
				default:
					$mods->error($lang->mods_invalid_cid);
			}
			
			if (empty($where))
				$mods->error();
			
			$lang->mods_categories = $lang->sprintf($lang->mods_categories, $place);
			
			$title .= ' - '.$place;
			
			$categories = '';
			
			// Display the subcategories of the top one we're browsing
			$cats = $mods->categories->getAll($where);
			foreach ($cats as $category)
			{
				$bgcolor = $mods->alternative_trow();
				
				$category['name'] = htmlspecialchars_uni($category['name']);
				$category['downloads'] = intval($category['counter']);
				$category['description'] = nl2br(htmlspecialchars_uni($category['description']));
				
				eval("\$categories .= \"".$templates->get("mods_categories_category")."\";");
			}
			
			if (empty($categories))
			{
				$colspan = 2;
				eval("\$categories .= \"".$templates->get("mods_no_data")."\";");
			}
			
			eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
			eval("\$content = \"".$templates->get("mods_categories")."\";");
		}
		else
		{
			$cid = intval($mybb->input['category']);
			
			$cat = $mods->categories->getByID($cid);
			if (empty($cat))
			{
				$mods->error($lang->mods_invalid_cid);
			}
			
			// Force Navigation here
			$mods->buildNavHighlight($cat['parent']);
			
			switch ($cat['parent'])
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
			
			$projects = '';
			
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
			
			if ($mods->check_permissions($mybb->settings['mods_mods']))
			{
				$hidden = '';
			}
			else
				$hidden = ' AND p.hidden=\'0\'';
			
			// total projects
			$query = $db->simple_select('mods_projects', 'COUNT(*) as projects', 'cid=\''.intval($id).'\' AND approved=1'.str_replace('p.', '', $hidden));
			$total_rows = $db->fetch_field($query, 'projects');
			
			// multi-page
			if ($total_rows > $per_page)
				$multipage = multipage($total_rows, $per_page, $mybb->input['page'], $mybb->settings['bburl']."/mods.php?action=browse&amp;category={$cid}");	
			
			// Get list of projects in the selected category
			// We can't use our fantastic Mods class
			$query = $db->query("
				SELECT u.*, p.*
				FROM ".TABLE_PREFIX."mods_projects p
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
				WHERE p.cid={$cid} AND p.approved=1 {$hidden}
				ORDER BY p.lastupdated DESC, p.submitted DESC LIMIT {$start}, {$per_page}
			");
			
			while($project = $db->fetch_array($query))
			{
				$project['name'] = htmlspecialchars_uni($project['name']);
				if ($project['hidden'] == 1)
				{
					$project['hidden'] = $lang->mods_hidden_project;
				}
				else
					$project['hidden'] = '';
				
				$project['description'] = htmlspecialchars_uni($project['description']);
				
				$project['author'] = build_profile_link(htmlspecialchars_uni($project['username']), $project['uid']);
				$project['downloads'] = (int)$project['downloads'];
				
				eval("\$projects .= \"".$templates->get("mods_projects_project")."\";");
			}
			
			if (empty($projects))
			{
				$colspan = 4;
				eval("\$projects = \"".$templates->get("mods_no_data")."\";");
			}
			
			// Primer
			$primer['title'] = htmlspecialchars_uni($cat['name']);
			$primer['content'] = htmlspecialchars_uni($cat['description']);
			$meta_description = $primer['content'];
			$primer['content'] = '<p>'.$primer['content'].'</p>';
			
			// Navigation
			$navplugins = $lang->mods_plugins;
			$navthemes = $lang->mods_themes;
			$navresources = $lang->mods_resources;
			$navgraphics = $lang->mods_graphics;
			
			$title .= ' - '.htmlspecialchars_uni(${'nav'.$cat['parent']});
			$title .= ' - '.htmlspecialchars_uni($cat['name']);
			
			switch ($cat['parent'])
			{
				case 'plugins':
					$parent = $lang->mods_plugins;
					break;
				case 'themes':
					$parent = $lang->mods_themes;
					break;
				case 'resources':
					$parent = $lang->mods_resources;
					break;
				case 'graphics':
					$parent = $lang->mods_graphics;
					break;
			}
			
			$breadcrumb = '<a href="'.$mybb->settings['bburl']."/mods.php?action=browse&amp;category=".$cat['parent'].'">'.$parent.'</a>';
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=browse&amp;category=".$cat['cid'].'">'.htmlspecialchars_uni($cat['name']).'</a>';
		
			eval("\$navigation = \"".$templates->get("mods_nav")."\";");
			
			eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
			eval("\$content = \"".$templates->get("mods_projects")."\";");
		}
	}
}

?>