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

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function user_meta()
{
	global $page, $lang;
	
	$page->add_menu_item($lang->users_and_groups, "user", "index.php?".SID."&module=user", 30);
	return true;
}

function user_action_handler($action)
{
	global $page, $lang;
	
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
			$page->active_action = "users";
			$action_file = "users.php";
	}
	
	$sub_menu = array();
	$sub_menu['10'] = array("id" => "users", "title" => $lang->users, "link" => "index.php?".SID."&module=user/users");
	$sub_menu['20'] = array("id" => "groups", "title" => $lang->groups, "link" => "index.php?".SID."&module=user/groups");
	$sub_menu['30'] = array("id" => "titles", "title" => $lang->user_titles, "link" => "index.php?".SID."&module=user/titles");
	$sub_menu['40'] = array("id" => "banning", "title" => $lang->banning, "link" => "index.php?".SID."&module=user/banning");
	$sub_menu['50'] = array("id" => "admin_permissions", "title" => $lang->admin_permissions, "link" => "index.php?".SID."&module=user/admin_permissions");
	$sub_menu['60'] = array("id" => "mass_mail", "title" => $lang->mass_mail, "link" => "index.php?".SID."&module=user/mass_mail");
	$sub_menu['70'] = array("id" => "group_promotions", "title" => $lang->group_promotions, "link" => "index.php?".SID."&module=user/group_promotions");
	$sub_menu['80'] = array("id" => "stats_and_logging", "title" => $lang->stats_and_logging, "link" => "index.php?".SID."&module=user/stats_and_logging");

	$sidebar = new SidebarItem($lang->users_and_groups);
	$sidebar->add_menu_items($sub_menu, $page->active_action);
	
	$page->sidebar .= $sidebar->get_markup();
	return $action_file;
}

function user_admin_log_data()
{
	global $mybb;
	
	switch($page->active_action)
	{
		case "dashboard":
			return array(
				"data" => array("uid" => $mybb->user['uid'], "username" => $mybb->user['username'])
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
	global $lang;
	
	$admin_permissions = array(
		"users" => $lang->can_manage_users,
		"groups" => $lang->can_manage_user_groups,
		"titles" => $lang->can_manage_user_titles,
		"banning" => $lang->can_manage_user_bans,
		"admin_permissions" => $lang->can_manage_admin_permissions,
		"mass_mail" => $lang->can_send_mass_mail,
		"group_promotions" => $lang->can_manage_group_permissions,
		"stats_and_logging" => $lang->can_manage_stats_and_logging,
	);
	return array("name" => $lang->users_and_groups, "permissions" => $admin_permissions);
}
?>