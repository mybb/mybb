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

class View implements Modules
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
		
		// Does the project exist? Must be approved in order to be viewed
		$pid = (int)$mybb->input['pid'];
		$project = $mods->projects->getByID($pid,true);
		if (empty($project))
		{
			$mods->error($lang->mods_invalid_pid);
		}
		
		// If the project hasn't been approved and we're not moderators we can't view the project
		if ($project['approved'] != 1 && !$mods->check_permissions($mybb->settings['mods_mods']))
		{
			$mods->error($lang->mods_invalid_pid);
		}
		
		if (!empty($project['collaborators']))
			$project['collaborators'] = explode(',', $project['collaborators']);
			
		if (!empty($project['testers']))
			$project['testers'] = explode(',', $project['testers']);
		
		// If the project has been hidden from public view, we must make sure that we can view it.
		if ($project['hidden'] == 1)
		{
			if ($project['uid'] != $mybb->user['uid'] && !$mods->check_permissions($mybb->settings['mods_mods']))
			{
				// check collaborators
				if ((is_array($project['collaborators']) && !in_array($mybb->user['uid'], $project['collaborators'])) && (is_array($project['collaborators']) && !in_array($mybb->user['uid'], $project['testers'])))
				{
					error_no_permission();
				}
			}
		}
		
		// Ok we passed the check, now prepare data to be output
		$project['name'] = htmlspecialchars_uni($project['name']);
		$project['description'] = htmlspecialchars_uni($project['description']);
		
		// Get category so we can build the breadcrumb
		$cat = $mods->categories->getByID($project['cid']);
		
		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
		
		$parser_options = array(
			'allow_mycode' => 1,
			'allow_smilies' => 1,
			'allow_imgcode' => 0,
			'allow_html' => 0,
			'filter_badwords' => 1
		);
		$project['information'] = $parser->parse_message($project['information'], $parser_options);
		
		/*if (!empty($project['testers']))
			$project['testers'] = explode(',', $project['testers']);*/
			
		if ($project['bugtracker_link'] != '')	
		{
			// Display bug tracker link and bug tracking status
			$bugtracker = "<li class=\"bugs\"><a href=\"{$mybb->settings['bburl']}/mods.php?action=bugtracker&amp;pid={$project['pid']}\">{$lang->mods_bugtracker}</a></li>";
				
			$project['testers'] = $lang->mods_bugtracking_external;
		}
		else {
			// Display bug tracker link and bug tracking status
			$bugtracker = "<li class=\"bugs\"><a href=\"{$mybb->settings['bburl']}/mods.php?action=bugtracker&amp;pid={$project['pid']}\">{$lang->mods_bugtracker}</a></li>";
				
			if ($project['bugtracking_collabs'] == 1 && $project['bugtracking'] == 1)
				$project['testers'] = $lang->mods_bugtracking_restricted;
			elseif ($project['bugtracking_collabs'] == 0 && $project['bugtracking'] == 1)
				$project['testers'] = $lang->mods_bugtracking_open;
			elseif ($project['bugtracking'] == 0) {
				$project['testers'] = $lang->mods_bugtracking_disabled;
				$bugtracker = ''; // erase link
			}
		}
		
		if ($project['support_link'] != '')	
		{
			// Display support link
			$support = "<li class=\"support\"><a href=\"{$mybb->settings['bburl']}/mods.php?action=support&amp;pid={$project['pid']}\">{$lang->mods_support}</a></li>";
		}
		else {
			// Display support link to the respective MyBB forum
			switch ($cat['parent'])
			{
				case 'plugins':
					$link = "http://community.mybb.com/forum-72.html";
					break;
				case 'themes':
					$link = "http://community.mybb.com/forum-10.html";
					break;
				case 'resources':
					$link = "http://community.mybb.com/forum-127.html";
					break;
				case 'graphics':
					$link = "http://community.mybb.com/forum-127.html";
					break;
			}
			
			$support = "<li class=\"support\"><a href=\"{$link}\">{$lang->mods_support}</a></li>";
		}
		
		// Display suggestions link
		if ($project['suggestions'] == 1)
		{
			$suggestions = "<li class=\"suggestions\"><a href=\"{$mybb->settings['bburl']}/mods.php?action=suggestions&amp;pid={$project['pid']}\">{$lang->mods_suggestions}</a></li>";
		}
		else
			$suggestions = '';
			
		// Recommend link
		$query = $db->simple_select('mods_recommended', '*', 'uid=\''.intval($mybb->user['uid']).'\' AND pid=\''.intval($project['pid']).'\'');
		$recommend = $db->fetch_array($query);
		if (empty($recommend))
		{
			$recommend = "<li class=\"recommend\"><a href=\"{$mybb->settings['bburl']}/mods.php?action=recommend&amp;pid={$project['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_recommend}</a></li>";
		}
		else
			$recommend = "<li class=\"unrecommend\"><a href=\"{$mybb->settings['bburl']}/mods.php?action=unrecommend&amp;pid={$project['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_unrecommend}</a></li>";
		
		// If we're the owner we can access all permissions
		if ($project['uid'] == $mybb->user['uid'])
		{
			eval("\$owneroptions = \"".$templates->get("mods_view_owneroptions")."\";");
			eval("\$devoptions = \"".$templates->get("mods_view_devoptions")."\";");
			// eval("\$testeroptions = \"".$templates->get("mods_view_testeroptions")."\";");
		}
		elseif (!empty($project['collaborators']) && in_array($mybb->user['uid'], $project['collaborators']))
		{
			eval("\$devoptions = \"".$templates->get("mods_view_devoptions")."\";");
			
			// we're a collaborator so we need to see the "leave" link
			$leave = "<li class=\"leave\"><a href=\"{$mybb->settings['bburl']}/mods.php?action=panel&amp;panelaction=leave&amp;pid={$project['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_leave_project}</a></li>";
			
			// eval("\$testeroptions = \"".$templates->get("mods_view_testeroptions")."\";");
		}
		/*elseif (!empty($project['testers']) &&  (in_array($mybb->user['uid'], $project['testers']) || $project['openreports'] == 1))
		{
			eval("\$testeroptions = \"".$templates->get("mods_view_testeroptions")."\";");
		}*/
		
		if (empty($project['collaborators']))
		{
			$project['collaborators'] = $lang->mods_none;
		}
		
		/*if (empty($project['testers']))
		{
			$project['testers'] = $lang->mods_none;
		}*/
		
		// Author Name and Collabs name
		if (is_array($project['collaborators']))
		{
			$collaborators = $project['collaborators'];
		}
		else
			$collaborators = array();
		
		/*if (is_array($project['testers']))
		{
			$testers = $project['testers'];
		}
		else
			$testers = array();
			
		$collabs = array_merge($testers, $collaborators);
		if (!empty($collabs))
		{
			$uids = array_merge(array($project['uid']), $collabs);
			$uids = implode('\',\'', array_map('intval', $uids));
		}
		else
			$uids = $project['uid'];*/
		
		$collabs = $collaborators;
		if (!empty($collabs))
		{
			$uids = array_merge(array($project['uid']), $collabs);
			$uids = implode('\',\'', array_map('intval', $uids));
		}
		else
			$uids = $project['uid'];
		
		$query = $db->simple_select('users', 'username,uid', 'uid IN (\''.$uids.'\')');
		while ($user = $db->fetch_array($query))
		{
			// Owner?
			if ($user['uid'] == $project['uid'])
				$project['author'] = build_profile_link(htmlspecialchars_uni($user['username']), $user['uid']);
			
			// Collaborator?
			if (in_array($user['uid'], $collaborators))
			{
				if (empty($project_collaborators))
					$project_collaborators = build_profile_link(htmlspecialchars_uni($user['username']), $user['uid']);
				else
					$project_collaborators .= ', '.build_profile_link(htmlspecialchars_uni($user['username']), $user['uid']);
			}
			
			// Tester?
			/*if (in_array($user['uid'], $testers))
			{
				if (empty($project_testers))
					$project_testers = build_profile_link($user['username'], $user['uid']);
				else
					$project_testers .= ','.build_profile_link($user['username'], $user['uid']);
			}*/
		}
		
		if (!empty($project_collaborators))
			$project['collaborators'] = $project_collaborators;
		
		/*if (!empty($project_testers))
			$project['testers'] = $project_testers;*/
			
		// Licence
		$project['licence_name'] = htmlspecialchars_uni($project['licence_name']);
		$project['licence'] = nl2br(htmlspecialchars_uni($project['licence']));
		
		// Previews
		$previews = $mods->projects->previews->getAll('project=\''.intval($project['pid']).'\'');
		if (empty($previews))
		{
			$project['previews'] = $lang->mods_no_previews;
		}
		else {
			$project['previews'] = '';
			$count = 1;
		
			foreach ($previews as $preview)
			{
				if ($count == 4)
					$project['previews'] .= '<br />';
			
				$image = htmlspecialchars_uni($preview['filename']);
				$thumbnail = htmlspecialchars_uni($preview['thumbnail']);
				
				eval("\$project['previews'] .= \"".$templates->get("mods_view_preview")."\";");
				
				$count++;
			}
			
			$project['previews'] .= '';
		}
		
		// Versions
		$vs = explode(',', $project['versions']);
		$project['versions'] = '';
		$comma = '';
		foreach ($vs as $version)
		{
			if (!empty($project['versions']))
				$comma = ', ';
			$project['versions'] .= $comma.htmlspecialchars_uni(substr($version, 0, 1).'.'.substr($version, 1, 1).'.'.substr($version, 2));
		}
		
		// Date and Time
		$project['submitted'] = my_date($mybb->settings['dateformat'], $project['submitted'], '', false).', '.my_date($mybb->settings['timeformat'], $project['submitted']);
		if ($project['lastupdated'] != 0)
			$project['lastupdated'] = my_date($mybb->settings['dateformat'], $project['lastupdated'], '', false).', '.my_date($mybb->settings['timeformat'], $project['lastupdated']);
		else
			$project['lastupdated'] = $lang->mods_never;
		
		$show_notice = true;
		
		// Latest 5 builds
		$query = $db->simple_select('mods_builds', '*', 'pid=\''.intval($project['pid']).'\'', array('order_by' => 'dateuploaded', 'order_dir' => 'desc', 'limit' => 5));
		while ($build = $db->fetch_array($query))
		{
			// If the project was last updated more than 6 months ago, show old notice
			if ($build['dateuploaded'] > TIME_NOW-15778458)
				$show_notice = false;
			
			$builds .= '<li><a href="'.$mybb->settings['bburl'].'/mods.php?action=download&amp;pid='.intval($project['pid']).'&amp;bid='.intval($build['bid']).'">#'.$build['number'].' ('.$build['status'].')</a></li>';
		}
		
		// Similar projects
		/*$query = $db->query("
			SELECT p.*, u.username, MATCH (p.name) AGAINST ('".$db->escape_string($project['name'])."') AS relevance
			FROM ".TABLE_PREFIX."projects p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = p.uid)
			WHERE p.approved='1' AND pid!='{$project['pid']}' AND MATCH (p.name) AGAINST ('".$db->escape_string($project['name'])."') >= ".RELEVANCE."
			ORDER BY p.lastupdated DESC
			LIMIT 0, 5
		");*/
		$query = $db->query("
			SELECT name, pid, MATCH (name) AGAINST ('".$db->escape_string($project['name'])."') AS relevance
			FROM ".TABLE_PREFIX."mods_projects
			WHERE approved='1' AND hidden='0' AND pid!='{$project['pid']}' AND MATCH (name) AGAINST ('".$db->escape_string($project['name'])."') >= ".RELEVANCE."
			ORDER BY lastupdated DESC
			LIMIT 0, 5
		");
		
		$simprojects = '';
		while ($simproject = $db->fetch_array($query))
		{
			$simproject['name'] = htmlspecialchars_uni($simproject['name']);
			
			$simprojects .= '<li><a href="'.$mybb->settings['bburl'].'/mods.php?action=view&amp;pid='.intval($simproject['pid']).'">'.$simproject['name'].'</a></li>';
		}
		
		if ($simprojects == '')
		{
			$simprojects = '<li>'.$lang->mods_similar_projects_not_found.'</li>';
		}
		
		// If the project's latest build was submitted more than 6 months ago, show old notice
		if ($show_notice === true)
		{
			$oldnotice = '<div class="notice">'.$lang->mods_old_notice.'</div>';
		}
		else
			$oldnotice = '';
			
		// Donate button
		if (isset($project['paypal_email']))
		{
			$project['paypal_email'] = htmlspecialchars_uni($project['paypal_email']);
			$mybb->user['username'] = htmlspecialchars_uni($mybb->user['username']);
			eval("\$donate = \"".$templates->get("mods_view_donate")."\";");
		}
		
		// Navigation
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
		$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=view&amp;pid=".$project['pid'].'">'.$project['name'].'</a>';
		
		eval("\$navigation = \"".$templates->get("mods_nav")."\";");
		
		// Force Navigation here
		$mods->buildNavHighlight($cat['parent']);
		
		$meta_description = $project['description'];
		
		// Title
		$title .= ' - '.htmlspecialchars_uni($project['name']);
		
		eval("\$rightblock = \"".$templates->get("mods_view_rightblock")."\";");
		eval("\$content = \"".$templates->get("mods_view")."\";");
	}
}

?>