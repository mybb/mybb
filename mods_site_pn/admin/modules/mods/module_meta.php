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

function mods_meta()
{
	global $page, $lang, $plugins;

	$sub_menu = array();
	$sub_menu['5'] = array("id" => "categories", "title" => $lang->nav_mods_categories, "link" => "index.php?module=mods-categories");
	$sub_menu['10'] = array("id" => "projects", "title" => $lang->nav_mods_projects, "link" => "index.php?module=mods-projects");
	$sub_menu['15'] = array("id" => "logs", "title" => $lang->nav_mods_logs, "link" => "index.php?module=mods-logs");
	$sub_menu['20'] = array("id" => "approved", "title" => $lang->nav_mods_approved, "link" => "index.php?module=mods-approved");
	
	$plugins->run_hooks_by_ref("admin_mods_menu", $sub_menu);
	
	$page->add_menu_item($lang->mods, "mods", "index.php?module=mods", 60, $sub_menu);
	
	return true;
}

function mods_action_handler($action)
{
	global $page, $lang, $plugins;
	
	$page->active_module = "mods";
	
	$actions = array(
		'categories' => array('active' => 'categories', 'file' => 'categories.php'),
		'projects' => array('active' => 'projects', 'file' => 'projects.php'),
		'logs' => array('active' => 'logs', 'file' => 'logs.php'),
		'approved' => array('active' => 'approved', 'file' => 'approved.php'),
	);
	
	$plugins->run_hooks_by_ref("admin_tools_action_handler", $actions);

	if(!isset($actions[$action]))
	{
		$page->active_action = "categories";
		return "categories.php";
	}
	else
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}
}

function mods_admin_permissions()
{
	global $lang, $plugins;
	
	$admin_permissions = array(
		"mods"			=> $lang->can_manage_mods,
		"categories"	=> $lang->can_manage_mods_categories,
		"projects"		=> $lang->can_manage_mods_projects,
		"logs"			=> $lang->can_manage_mods_logs,
		"approved"		=> $lang->can_manage_mods_approved,
	);
	
	$plugins->run_hooks_by_ref("admin_mods_permissions", $admin_permissions);
	
	return array("name" => $lang->mods, "permissions" => $admin_permissions, "disporder" => 60);
}
?>
