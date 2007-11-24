<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function forum_meta()
{
	global $page, $lang;
	
	$sub_menu = array();
	$sub_menu['10'] = array("id" => "management", "title" => $lang->forum_management, "link" => "index.php?".SID."&module=forum/management");
	$sub_menu['20'] = array("id" => "announcements", "title" => $lang->forum_announcements, "link" => "index.php?".SID."&module=forum/announcements");
	$sub_menu['30'] = array("id" => "moderation_queue", "title" => $lang->moderation_queue, "link" => "index.php?".SID."&module=forum/moderation_queue");
	$sub_menu['40'] = array("id" => "attachments", "title" => $lang->attachments, "link" => "index.php?".SID."&module=forum/attachments");

	$page->add_menu_item($lang->forums_and_posts, "forum", "index.php?".SID."&module=forum", 20, $sub_menu);

	return true;
}

function forum_action_handler($action)
{
	global $page, $lang;
	
	$page->active_module = "forum";
	
	switch($action)
	{
		case "moderation_queue":
			$page->active_action = "moderation_queue";
			$action_file = "moderation_queue.php";
			break;
		case "announcements":
			$page->active_action = "announcements";
			$action_file = "announcements.php";
			break;
		case "attachments":
			$page->active_action = "attachments";
			$action_file = "attachments.php";
			break;
		default:
			$page->active_action = "management";
			$action_file = "management.php";
	}
	
	return $action_file;
}

function forum_admin_log_data()
{
	switch($page->active_action)
	{
		case "dashboard":
			return array(
				"data" => array("uid" => "1234", "username" => "Test")
			);
			break;
	
	}
}

function forum_format_admin_log_data($action, $data)
{
	switch($action)
	{
		case "dashboard":
			return "Edit profile of {$data['username']} ({$data['uid']})";
			break;
	}
}

function forum_admin_permissions()
{
	global $lang;
	
	$admin_permissions = array(
		"management" => $lang->can_manage_forums,
		"announcements" => $lang->can_manage_forum_announcements,
		"moderation_queue" => $lang->can_moderate,
		"attachments" => $lang->can_manage_attachments,
	);
	return array("name" => $lang->forums_and_posts, "permissions" => $admin_permissions);
}

?>