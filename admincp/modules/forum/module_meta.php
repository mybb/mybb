<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

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

?>