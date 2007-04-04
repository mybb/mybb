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

function user_meta()
{
	global $page;
	$page->add_menu_item("Users &amp; Groups", "user", "index.php?".SID."&module=user", 30);
	return true;
}

function user_action_handler($action)
{
	global $page;
	$page->active_module = "user";
	switch($action)
	{
		case "group_promotions":
			$page->active_action = "group_promotions";
			$action_file = "group_promotions.php";
			break;
		case "admin_permissions":
			$page->active_action = "admin_permissions";
			$action_file = "admin_permissions.php";
			break;
		default:
			$page->active_action = "view";
			$action_file = "index.php";
	}
	
	$sub_menu = array();
	$sub_menu['10'] = array("id" => "view", "title" => "Users", "link" => "index.php?".SID."&module=user/view");
	$sub_menu['20'] = array("id" => "groups", "title" => "Groups", "link" => "index.php?".SID."&module=user/groups");
	$sub_menu['30'] = array("id" => "titles", "title" => "User Titles", "link" => "index.php?".SID."&module=user/titles");
	$sub_menu['40'] = array("id" => "banning", "title" => "Banning", "link" => "index.php?".SID."&module=user/banning");
	$sub_menu['50'] = array("id" => "admin_permissions", "title" => "Admin Permissions", "link" => "index.php?".SID."&module=user/admin_permissions");
	$sub_menu['60'] = array("id" => "mass_mail", "title" => "Mass Mail", "link" => "index.php?".SID."&module=user/mass_mail");
	$sub_menu['70'] = array("id" => "group_promotions", "title" => "Group Promotions", "link" => "index.php?".SID."&module=user/group_promotions");
	$sub_menu['80'] = array("id" => "stats_and_logging", "title" => "Statistics and Logging", "link" => "index.php?".SID."&module=user/stats_and_logging");

	$sidebar = new SidebarItem("Users and Groups");
	$sidebar->add_menu_items($sub_menu, $page->active_action);
	
	$page->sidebar .= $sidebar->get_markup();
	return $action_file;
}

function user_admin_log_data()
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

function user_format_admin_log_data($action, $data)
{
	switch($action)
	{
		case "dashboard":
			return "Edit profile of {$data['username']} ({$data['uid']})";
			break;
	}
}

function user_admin_permissions()
{
	$admin_permissions = array(
		"view" => "Can Manage Users?",
		"groups" => "Can Manage User Groups?",
		"titles" => "Can Manage User Titles?",
		"banning" => "Can Manage User Bans?",
		"admin_permissions" => "Can Manage Admin Permissoins?",
		"mass_mail" => "Can Send Mass Mail?",
		"group_promotions" => "Can Manage Group Promotoins?",
		"stats_and_logging" => "Can Manage Statistics and Logs?",
	);
	return array("name" => "Users &amp; Groups", "permissions" => $admin_permissions);
}
?>