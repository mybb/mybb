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
	global $page, $lang, $plugins;
	
	$sub_menu = array();
	$sub_menu['10'] = array("id" => "management", "title" => $lang->forum_management, "link" => "index.php?module=forum/management");
	$sub_menu['20'] = array("id" => "announcements", "title" => $lang->forum_announcements, "link" => "index.php?module=forum/announcements");
	$sub_menu['30'] = array("id" => "moderation_queue", "title" => $lang->moderation_queue, "link" => "index.php?module=forum/moderation_queue");
	$sub_menu['40'] = array("id" => "attachments", "title" => $lang->attachments, "link" => "index.php?module=forum/attachments");
	
	$plugins->run_hooks_by_ref("admin_forum_menu", $sub_menu);

	$page->add_menu_item($lang->forums_and_posts, "forum", "index.php?module=forum", 20, $sub_menu);

	return true;
}

function forum_action_handler($action)
{
	global $page, $lang, $plugins;
	
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
	
	$plugins->run_hooks_by_ref("admin_forum_action_handler", $action);
	
	return $action_file;
}

function forum_admin_permissions()
{
	global $lang, $plugins;
	
	$admin_permissions = array(
		"management" => $lang->can_manage_forums,
		"announcements" => $lang->can_manage_forum_announcements,
		"moderation_queue" => $lang->can_moderate,
		"attachments" => $lang->can_manage_attachments,
	);
	
	$plugins->run_hooks_by_ref("admin_forum_permissions", $admin_permissions);
	
	return array("name" => $lang->forums_and_posts, "permissions" => $admin_permissions, "disporder" => 20);
}

?>