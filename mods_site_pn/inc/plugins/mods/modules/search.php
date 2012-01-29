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

class Search implements Modules
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
		
		if ($mybb->request_method == "post")
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);

			$search_data = array(
				"keywords" => $mybb->input['keywords'],
				"author" => $mybb->input['author'],
				"categories" => $mybb->input['categories'],
				"versions" => $mybb->input['versions']
			);
			
			require_once MYBB_ROOT."inc/functions_search.php";
			
			if($db->can_search != true)
			{
				$mods->error($lang->error_no_search_support);
			}
			
			//							//
			// Get our query conditions //
			// 							//
			
			if($mybb->settings['minsearchword'] < 1)
			{
				$mybb->settings['minsearchword'] = 3;
			}
			
			$querystr = '';
			
			// are we searching by author?
			if (!empty($mybb->input['author']))
			{
				$author = $mybb->input['author'];
				
				// Get user ID
				$query = $db->simple_select("users", "uid", "username='".$db->escape_string(trim($author))."'");
				$uid = $db->fetch_field($query, 'uid');
				if (!$uid)
					$mods->error($lang->mods_error_nosearchresults);
				
				$querystr .= 'p.uid=\''.intval($uid).'\'';
			}
			else {
				$querystr .= "p.uid!=0"; // just put this here so we can use AND for sure below when making queries
			}
			
			// are we searching the title?
			if (!empty($mybb->input['title']))
			{
				$keywords = clean_keywords($mybb->input['title']);
				if(!$keywords)
				{
					$mods->error($lang->mods_error_nosearchterms);
				}
				
				$querystr .= $mods->search($keywords, 1);
			}
			
			// are we searching the description?
			if (!empty($mybb->input['description']))
			{
				$keywords2 = clean_keywords($mybb->input['description']);
				if(!$keywords2)
				{
					$mods->error($lang->mods_error_nosearchterms);
				}
				
				$querystr .= $mods->search($keywords2, 2);
			}
			
			if (!$keywords && !$keywords2 && !$author)
				$mods->error($lang->mods_error_nosearchterms);
				
			// Selected any MyBB versions? Only matters if we didn't select the all versions option
			if (!empty($mybb->input['versions']) && !in_array(0, $mybb->input['versions']) && is_array($mybb->input['versions']))
			{
				// Validate versions
				$error = false;
				
				$querystr .= ' AND (';
				$prefix = '';
				
				$vs = explode(',', MYBB_VERSIONS);
				foreach ($mybb->input['versions'] as $version)
				{
					// If the version we selected doesn't exist we need to inform the user of that.
					if (!in_array($version, $vs))
					{
						$error = true;
						break;
					}
					
					$querystr .= $prefix." p.versions LIKE '%".$db->escape_string($version)."%'";
					$prefix = ' OR ';
				}
				
				$querystr .= ')';
				
				if ($error)
				{
					$mods->error($lang->mods_create_project_invalid_version);
				}
			}
			
			// Category and sub category selection
			switch ($mybb->input['category'])
			{
				case 'plugins':
					if (intval($mybb->input['pluginscid']) > 0)
						$querystr .= ' AND cid=\''.intval($mybb->input['pluginscid']).'\'';
					else {
						// we selected all sub categories
						$cats = $mods->categories->getAll("parent='plugins'");
						
						$categories = array();
						foreach ($cats as $cid => $cat)
							$categories[] = (int)$cid;
							
						$querystr .= ' AND cid IN (\''.implode('\',\'', $categories).'\')';
					}
				break;
				case 'themes':
					if (intval($mybb->input['themescid']) > 0)
						$querystr .= ' AND cid=\''.intval($mybb->input['themescid']).'\'';
					else {
						// we selected all sub categories
						$cats = $mods->categories->getAll("parent='themes'");
						
						$categories = array();
						foreach ($cats as $cid => $cat)
							$categories[] = (int)$cid;
							
						$querystr .= ' AND cid IN (\''.implode('\',\'', $categories).'\')';
					}
				break;
				case 'resources':
					if (intval($mybb->input['resourcescid']) > 0)
						$querystr .= ' AND cid=\''.intval($mybb->input['resourcescid']).'\'';
					else {
						// we selected all sub categories
						$cats = $mods->categories->getAll("parent='resources'");
						
						$categories = array();
						foreach ($cats as $cid => $cat)
							$categories[] = (int)$cid;
							
						$querystr .= ' AND cid IN (\''.implode('\',\'', $categories).'\')';
					}
				break;
				case 'graphics':
					if (intval($mybb->input['graphicscid']) > 0)
						$querystr .= ' AND cid=\''.intval($mybb->input['graphicscid']).'\'';
					else {
						// we selected all sub categories
						$cats = $mods->categories->getAll("parent='graphics'");
						
						$categories = array();
						foreach ($cats as $cid => $cat)
							$categories[] = (int)$cid;
							
						$querystr .= ' AND cid IN (\''.implode('\',\'', $categories).'\')';
					}
				break;
				default:
					// Category not selected, so just skip this process
			}
			
			$sid = md5(uniqid(microtime(), 1));
			$searcharray = array(
				"sid" => $db->escape_string($sid),
				"uid" => $mybb->user['uid'],
				"date" => $db->escape_string(TIME_NOW),
				"ipaddress" => $db->escape_string($session->ipaddress),
				'querywhere' => $db->escape_string($querystr));

			$db->insert_query("mods_searchlog", $searcharray);
			
			// temporarily disable redirects
			$mybb->settings['redirects'] = 0;
			
			redirect("mods.php?action=search&amp;searchid=".$sid);
		}
		
		// Showing results?
		if ($mybb->input['searchid'])
		{
			// Does our sid exist?
			$sid = $mybb->input['searchid'];
			
			$query = $db->simple_select('mods_searchlog', '*', 'sid=\''.$db->escape_string(trim($sid)).'\'');
			$search = $db->fetch_array($query);
			if (!$search['sid'])
			{
				$mods->error($lang->mods_invalid_search);
			}
			
			// Our query
			$where = $search['querywhere'];
			
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
				
			if (!empty($where))
				$where = ' AND '.$where;
			
			$query = $db->simple_select("mods_projects", "COUNT(pid) as projects", 'approved=1 '.str_replace('p.', '', $hidden).' '.str_replace('p.', '', $where));
			$total_rows = $db->fetch_field($query, "projects");
			
			// multi-page
			if ($total_rows > $per_page)
				$multipage = multipage($total_rows, $per_page, $mybb->input['page'], $mybb->settings['bburl']."/mods.php?action=search&amp;searchid={$sid}");	
			
			// Get list of categories
			$cats = $mods->categories->getAll();
			
			// Search!
			$query = $db->query("
				SELECT u.*, p.*
				FROM ".TABLE_PREFIX."mods_projects p
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
				WHERE p.approved=1 {$hidden} {$where} 
				ORDER BY p.lastupdated DESC, p.submitted DESC LIMIT {$start}, {$per_page}
			");
			
			$results = '';
			$found = 0;
			
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
				$project['description'] = htmlspecialchars_uni($project['description']);
				
				$project['author'] = build_profile_link(htmlspecialchars_uni($project['username']), $project['uid']);
				
				if ($project['lastupdated'] == 0)
					$project['lastupdated'] = $lang->mods_never;
				else
					$project['lastupdated'] = my_date($mybb->settings['dateformat'], $project['lastupdated'], '', false).', '.my_date($mybb->settings['timeformat'], $project['lastupdated']);
				
				$found++;
				
				eval("\$results .= \"".$templates->get("mods_search_results_result")."\";");
			}
			
			if (empty($results))
			{
				$colspan = 4;
				eval("\$results = \"".$templates->get("mods_no_data")."\";");
			}
			
			$title .= ' - '.$lang->mods_search_results;
		
			$primer['title'] = $lang->mods_primer_search_title;
			$primer['content'] = $lang->mods_primer_search_content;
			$meta_description = $primer['content'];
			$primer['content'] = '<p>'.$primer['content'].'</p>';
			
			eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
			eval("\$content = \"".$templates->get("mods_search_results")."\";");
		}
		else {
			// Build versions select box
			$versions = '';
			$vs = explode(',', MYBB_VERSIONS);
			rsort($vs);
			$selvs = explode(',', $data['versions']);
			
			foreach ($vs as $version)
			{
				if (in_array($version, $selvs))
					$versions .= '<option value="'.$version.'" selected="selected">'.substr($version, 0, 1).'.'.substr($version, 1, 1).'.'.substr($version, 2).'</option>';
				else
					$versions .= '<option value="'.$version.'">'.substr($version, 0, 1).'.'.substr($version, 1, 1).'.'.substr($version, 2).'</option>';
			}
			
			// Get list of parent categories
			$parents = $mods->categories->getParents();
			
			$categories = '';
			foreach ($parents as $cat)
			{
				if ($cat == $data['category'])
					$categories .= '<option value="'.$cat.'" selected="selected">'.ucfirst($cat).'</option>';
				else
					$categories .= '<option value="'.$cat.'">'.ucfirst($cat).'</option>';
			}
			
			// Build sub categories select boxes
			$cats = $mods->categories->getAll();
			
			$plugins_subcategories = $themes_subcategories = $resources_subcategories = $graphics_subcategories = '';
			foreach ($cats as $cat)
			{
				if ($cat['cid'] == $data['cid'])
					$selected = ' selected="selected"';
				else
					$selected = '';
				
				switch ($cat['parent'])
				{
					case 'plugins':
						
						$plugins_subcategories .= '<option value="'.$cat['cid'].'" '.$selected.'>'.htmlspecialchars_uni($cat['name']).'</option>';
					break;
					case 'themes':
						$themes_subcategories .= '<option value="'.$cat['cid'].'" '.$selected.'>'.htmlspecialchars_uni($cat['name']).'</option>';
					break;
					case 'resources':
						$resources_subcategories .= '<option value="'.$cat['cid'].'" '.$selected.'>'.htmlspecialchars_uni($cat['name']).'</option>';
					break;
					case 'graphics':
						$graphics_subcategories .= '<option value="'.$cat['cid'].'" '.$selected.'>'.htmlspecialchars_uni($cat['name']).'</option>';
					break;
				}
			}
			
			$title .= ' - '.$lang->mods_search;
		
			$primer['title'] = $lang->mods_primer_search_title;
			$primer['content'] = $lang->mods_primer_search_content;
			$meta_description = $primer['content'];
			$primer['content'] = '<p>'.$primer['content'].'</p>';
			
			eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
			eval("\$content = \"".$templates->get("mods_search")."\";");
		}
	}
}

?>