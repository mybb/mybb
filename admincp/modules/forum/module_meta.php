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
	global $page;
	$page->add_menu_item("Forums &amp; Posts", "forum", "index.php?".SID."&module=forum", 20);

	return true;
}

function forum_action_handler($action)
{
	global $page;
	$page->active_module = "forum";
	switch($action)
	{
		case "attachments":
			$page->active_action = "attachments";
			$action_file = "attachments.php";
			break;
		default:
			$page->active_action = "management";
			$action_file = "index.php";
	}
	
	$sub_menu = array();
	$sub_menu['10'] = array("id" => "management", "title" => "Forum Management", "link" => "index.php?".SID."&module=forum/management");
	$sub_menu['20'] = array("id" => "announcements", "title" => "Forum Announcements", "link" => "index.php?".SID."&module=forum/announcements");
	$sub_menu['30'] = array("id" => "moderation_queue", "title" => "Moderation Queue", "link" => "index.php?".SID."&module=forum/moderation_queue");
	$sub_menu['40'] = array("id" => "attachments", "title" => "Attachments", "link" => "index.php?".SID."&module=forum/attachments");

	$sidebar = new SidebarItem("Forums and Posts");
	$sidebar->add_menu_items($sub_menu, $page->active_action);
	
	$page->sidebar .= $sidebar->get_markup();
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
	$admin_permissions = array(
		"management" => "Can Manage Forums?",
		"announcements" => "Can Manage Forum Announcements?",
		"moderation_queue" => "Can Moderate Posts, Threads, and Attachments?",
		"attachments" => "Can Manage Attachments?",
	);
	return array("name" => "Forum &amp; Posts", "permissions" => $admin_permissions);
}

?>