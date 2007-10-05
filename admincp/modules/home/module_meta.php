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

function home_meta()
{
	global $page, $lang;
	
	$page->add_menu_item($lang->home, "home", "index.php?".SID, 1);
	
	return true;
}

function home_action_handler($action)
{
	global $page, $db, $lang;
	
	$page->active_module = "home";
	
	switch($action)
	{
		case "preferences":
			$page->active_action = "preferences";
			$action_file = "preferences.php";
			break;
		case "credits":
			$page->active_action = "credits";
			$action_file = "credits.php";
			break;
		case "version_check":
			$page->active_action = "version_check";
			$action_file = "version_check.php";
			break;
		default:
			$page->active_action = "dashboard";
			$action_file = "index.php";
	}
	
	$sub_menu = array();
	$sub_menu['10'] = array("id" => "dashboard", "title" => $lang->dashboard, "link" => "index.php?".SID."&module=home/dashboard");
	$sub_menu['20'] = array("id" => "preferences", "title" => $lang->preferences, "link" => "index.php?".SID."&module=home/preferences");
	$sub_menu['30'] = array("id" => "version_check", "title" => $lang->version_check, "link" => "index.php?".SID."&module=home/version_check");
	$sub_menu['40'] = array("id" => "credits", "title" => $lang->mybb_credits, "link" => "index.php?".SID."&module=home/credits");

	$sidebar = new SidebarItem($lang->home);
	$sidebar->add_menu_items($sub_menu, $page->active_action);
	
	$page->sidebar .= $sidebar->get_markup();

	if($page->active_action == "dashboard")
	{
		// Quick Access
		$sub_menu = array();
		$sub_menu['10'] = array("id" => "add_forum", "title" => $lang->add_new_forum, "link" => "index.php?".SID."&module=forum/add_forum");
		$sub_menu['20'] = array("id" => "search", "title" => $lang->search_for_users, "link" => "index.php?".SID."&module=user/search");
		$sub_menu['30'] = array("id" => "themes", "title" => $lang->themes, "link" => "index.php?".SID."&module=style/themes");
		$sub_menu['40'] = array("id" => "templates", "title" => $lang->templates, "link" => "index.php?".SID."&module=style/templates");
		$sub_menu['50'] = array("id" => "plugins", "title" => $lang->plugins, "link" => "index.php?".SID."&module=config/plugins");
		$sub_menu['60'] = array("id" => "backupdb", "title" => $lang->database_backups, "link" => "index.php?".SID."&module=tools/backupdb");
		
		$sidebar = new SidebarItem($lang->quick_access);
		$sidebar->add_menu_items($sub_menu, $page->active_action);
		
		$page->sidebar .= $sidebar->get_markup();

		// Online Administrators
		$timecut = time()-60*60*15;
		$query = $db->query("
			SELECT u.uid, u.username, s.ip
			FROM ".TABLE_PREFIX."adminsessions s
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=s.uid)
			WHERE s.lastactive>{$timecut}
			ORDER BY u.username
		");
		$online_users = "<ul class=\"menu online_admins\">";
		while($user = $db->fetch_array($query))
		{
			if(!$done_users["{$user['uid']}.{$user['ip']}"])
			{
				if($user['type'] == "mobile")
				{
					$class = " class=\"mobile_user\"";
				}
				else
				{
					$class = "";
				}
				$online_users .= "<li{$class}>".build_profile_link($user['username'], $user['uid'])."</li>";
				$done_users["{$user['uid']}.{$user['ip']}"] = 1;
			}
		}
		$online_users .= "</ul>";
		$sidebar = new SidebarItem($lang->online_admins);
		$sidebar->contents = $online_users;

		$page->sidebar .= $sidebar->get_markup();
	}

	return $action_file;
}

function home_admin_log_data()
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

function home_format_admin_log_data($action, $data)
{
	switch($action)
	{
		case "dashboard":
			return "Edit profile of {$data['username']} ({$data['uid']})";
			break;
	}
}

?>