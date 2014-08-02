<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function user_meta()
{
	global $page, $lang, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "users", "title" => $lang->users, "link" => "index.php?module=user-users");
	$sub_menu['20'] = array("id" => "groups", "title" => $lang->groups, "link" => "index.php?module=user-groups");
	$sub_menu['30'] = array("id" => "titles", "title" => $lang->user_titles, "link" => "index.php?module=user-titles");
	$sub_menu['40'] = array("id" => "banning", "title" => $lang->banning, "link" => "index.php?module=user-banning");
	$sub_menu['50'] = array("id" => "admin_permissions", "title" => $lang->admin_permissions, "link" => "index.php?module=user-admin_permissions");
	$sub_menu['60'] = array("id" => "mass_mail", "title" => $lang->mass_mail, "link" => "index.php?module=user-mass_mail");
	$sub_menu['70'] = array("id" => "group_promotions", "title" => $lang->group_promotions, "link" => "index.php?module=user-group_promotions");

	$sub_menu = $plugins->run_hooks("admin_user_menu", $sub_menu);

	$page->add_menu_item($lang->users_and_groups, "user", "index.php?module=user", 30, $sub_menu);
	return true;
}

function user_action_handler($action)
{
	global $page, $lang, $plugins;

	$page->active_module = "user";

	$actions = array(
		'group_promotions' => array('active' => 'group_promotions', 'file' => 'group_promotions.php'),
		'admin_permissions' => array('active' => 'admin_permissions', 'file' => 'admin_permissions.php'),
		'titles' => array('active' => 'titles', 'file' => 'titles.php'),
		'banning' => array('active' => 'banning', 'file' => 'banning.php'),
		'groups' => array('active' => 'groups', 'file' => 'groups.php'),
		'mass_mail' => array('active' => 'mass_mail', 'file' => 'mass_mail.php'),
		'users' => array('active' => 'users', 'file' => 'users.php')
	);

	$actions = $plugins->run_hooks("admin_user_action_handler", $actions);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}
	else
	{
		$page->active_action = "users";
		return "users.php";
	}
}

function user_admin_permissions()
{
	global $lang, $plugins;

	$admin_permissions = array(
		"users" => $lang->can_manage_users,
		"groups" => $lang->can_manage_user_groups,
		"titles" => $lang->can_manage_user_titles,
		"banning" => $lang->can_manage_user_bans,
		"admin_permissions" => $lang->can_manage_admin_permissions,
		"mass_mail" => $lang->can_send_mass_mail,
		"group_promotions" => $lang->can_manage_group_promotions
	);

	$admin_permissions = $plugins->run_hooks("admin_user_permissions", $admin_permissions);

	return array("name" => $lang->users_and_groups, "permissions" => $admin_permissions, "disporder" => 30);
}
