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

class Download implements Modules
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
		
		// Let's make sure we're doing this via POST
		if ($mybb->request_method == 'post')
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			// Verify Build ID
			$bid = (int)$mybb->input['bid'];
			$build = $mods->projects->builds->getByID($bid);
			if (empty($build))
			{
				$mods->error($lang->mods_invalid_bid);
			}
			
			// 
			// Actual downloading process starts here
			// 
			
			// Create the name based on the Project Name and Build Number and Status
			$illegal_characters = array("\\", "/", ":", "*", "?", "\"", "<", ">", "|");
			$name = str_replace($illegal_characters, "_", $project['name']);
			$name .= '_#'.intval($build['number']).'_'.htmlspecialchars_uni($build['status']).'.zip';
			
			header("Content-type: application/force-download");
			
			if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "msie") !== false)
			{
				header("Content-disposition: attachment; filename=\"{$name}\"");
			}
			else
			{
				header("Content-disposition: attachment; filename=\"{$name}\"");
			}
			
			if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "msie 6.0") !== false)
			{
				header("Expires: -1");
			}
			
			header("Content-length: {$build['filesize']}");
			header("Content-range: bytes=0-".($build['filesize']-1)."/".$build['filesize']); 
			
			// Increase counters
			$db->update_query('mods_builds', array('downloads' => ++$build['downloads']), 'bid=\''.$bid.'\'');
			$db->update_query('mods_projects', array('downloads' => ++$project['downloads']), 'pid=\''.$pid.'\'');
			
			echo file_get_contents(MYBB_ROOT.$mybb->settings['uploadspath']."/mods/".$build['filename']);
			
			exit;
		}
		
		// Get the latest 20 builds
		$builds = '';
		$query = $db->simple_select('mods_builds', '*', 'pid=\''.$pid.'\'', array('limit' => '20', 'order_by' => 'dateuploaded', 'order_dir' => 'desc'));
		while ($build = $db->fetch_array($query))
		{
			$build['name'] = htmlspecialchars_uni($build['name']);
			$build['status'] = htmlspecialchars_uni($build['status']);
			$build['dateuploaded'] = my_date($mybb->settings['dateformat'], $build['dateuploaded'], '', false).', '.my_date($mybb->settings['timeformat'], $build['dateuploaded']);
			
			if (!isset($latest) || (isset($latest) && empty($latest['stable']) && $build['status'] == 'stable'))
			{
				if ($build['status'] == 'stable')
					$latest['stable'] = $build;
				else
					$latest['dev'] = $build;
			}
			
			eval("\$builds .= \"".$templates->get("mods_builds_li")."\";");
		}
		
		// Licence
		$project['licence_name'] = htmlspecialchars_uni($project['licence_name']);
		$project['licence'] = nl2br(htmlspecialchars_uni($project['licence']));
		
		// Are we downloading a specific build?
		if (isset($mybb->input['bid']))
		{
			// Verify Build ID
			$bid = (int)$mybb->input['bid'];
			$build = $mods->projects->builds->getByID($bid);
			if (empty($build))
			{
				$mods->error($lang->mods_invalid_bid);
			}
			
			$build['name'] = htmlspecialchars_uni($build['name']);
			$build['status'] = htmlspecialchars_uni($build['status']);
			$build['dateuploaded'] = my_date($mybb->settings['dateformat'], $build['dateuploaded'], '', false).', '.my_date($mybb->settings['timeformat'], $build['dateuploaded']);
		}
		else {
			// Find the latest one instead
			if (isset($latest['stable']))
				$build = $latest['stable'];
			else
				$build = $latest['dev'];
		}
		
		// MD5
		$build['md5'] = htmlspecialchars_uni($build['md5']);
		
		// Versions
		$vs = explode(',', $build['versions']);
		$build['versions'] = '';
		$comma = '';
		foreach ($vs as $version)
		{
			if (!empty($build['versions']))
				$comma = ', ';
			$build['versions'] .= $comma.htmlspecialchars_uni(substr($version, 0, 1).'.'.substr($version, 1, 1).'.'.substr($version, 2));
		}
			
		$build['filesize'] = get_friendly_size($build['filesize']);
			
		// Get author 
		$query = $db->simple_select('users', 'username', 'uid=\''.intval($build['uid']).'\'');
		$build['uploadedby'] = $db->fetch_field($query, 'username');
		
		if ($build['status'] == 'dev')
			$build['status'] = $lang->mods_development;
		else
			$build['status'] = $lang->mods_stable;
			
		$build['name'] = htmlspecialchars_uni($build['name']);
		if ($build['name'] == '')
		{
			$build['name'] = $lang->mods_not_set;
		}
		
		// If we're a developer or project owner we can submit new builds
		if (!empty($project['collaborators']))
			$project['collaborators'] = explode(',', $project['collaborators']);
		
		// Changes
		if ($build['number'] == 1)
			$build['changes'] = $lang->mods_first_build_notice;
		else {
			require_once MYBB_ROOT."inc/class_parser.php";
			$parser = new postParser;
			
			$parser_options = array(
				'allow_mycode' => 1,
				'allow_smilies' => 0,
				'allow_imgcode' => 0,
				'allow_html' => 0,
				'filter_badwords' => 1
			);
			$build['changes'] = $parser->parse_message($build['changes'], $parser_options);
		}
		
		// Get category so we can build the breadcrumb
		$cat = $mods->categories->getByID($project['cid']);
		
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
		$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=download&amp;pid=".$project['pid'].'">'.$lang->mods_download.'</a>';
		
		eval("\$navigation = \"".$templates->get("mods_nav")."\";");
		
		// Force Navigation here
		$mods->buildNavHighlight($cat['parent']);
		
		// Title
		$title .= ' - '.$lang->mods_download.' '.htmlspecialchars_uni($project['name']);
		
		eval("\$content = \"".$templates->get("mods_download")."\";");
	}
}

?>