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

class Support implements Modules
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
		
		$project['name'] = htmlspecialchars_uni($project['name']);
		
		if ($project['support_link'] == '')
			$mods->error();
		
		$title .= ' - '.$lang->mods_support;
		
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
		$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=support&amp;pid=".$project['pid'].'">'.$lang->mods_support.'</a>';
		
		eval("\$navigation = \"".$templates->get("mods_nav")."\";");
		
		// Force Navigation here
		$mods->buildNavHighlight($cat['parent']);
		
		$notice = $lang->mods_support_notice;
		$link = htmlspecialchars_uni($project['support_link']);
		
		eval("\$content = \"".$templates->get("mods_redirect")."\";");
	}
}

?>