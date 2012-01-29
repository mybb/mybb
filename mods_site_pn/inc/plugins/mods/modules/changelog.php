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

class Changelog implements Modules
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
		
		// Parser
		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
		
		$parser_options = array(
			'allow_mycode' => 1,
			'allow_smilies' => 0,
			'allow_imgcode' => 0,
			'allow_html' => 0,
			'filter_badwords' => 1
		);
		
		// Retreive all builds so we can create a change log
		$query = $db->simple_select('mods_builds', 'number,status,changes', 'pid=\''.$pid.'\'', array('order_by' => 'dateuploaded', 'order_dir' => 'desc'));
		while ($build = $db->fetch_array($query))
		{
			$build['number'] = (int)$build['number'];
			$build['status'] = htmlspecialchars_uni($build['status']);
			if ($build['number'] == 1)
				$build['changes'] = $lang->mods_first_build_notice;
			else
				$build['changes'] = $parser->parse_message($build['changes'], $parser_options);
			
			eval("\$project['changelog'] .= \"".$templates->get("mods_changelog_build")."\";");
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
		$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=changelog&amp;pid=".$project['pid'].'">'.$lang->mods_changelog.'</a>';
		
		eval("\$navigation = \"".$templates->get("mods_nav")."\";");
		
		// Force Navigation here
		$mods->buildNavHighlight($cat['parent']);
		
		// Title
		$title .= ' - '.$lang->mods_changelog.' - '.htmlspecialchars_uni($project['name']);
		
		eval("\$content = \"".$templates->get("mods_changelog")."\";");
	}
}

?>