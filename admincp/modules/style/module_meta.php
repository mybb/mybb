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

function style_meta()
{
	global $page, $lang, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "themes", "title" => $lang->themes, "link" => "index.php?module=style/themes");
	$sub_menu['20'] = array("id" => "templates", "title" => $lang->templates, "link" => "index.php?module=style/templates");
	
	$plugins->run_hooks_by_ref("admin_style_menu", $sub_menu);

	$page->add_menu_item($lang->templates_and_style, "style", "index.php?module=style", 40, $sub_menu);
	return true;
}

function style_action_handler($action)
{
	global $page, $lang, $plugins;
	
	$page->active_module = "style";
	switch($action)
	{
		case "templates":
			$page->active_action = "templates";
			$action_file = "templates.php";
			break;
		default:
			$page->active_action = "themes";
			$action_file = "themes.php";
	}
	
	$plugins->run_hooks_by_ref("admin_style_action_handler", $action);
	
	return $action_file;
}

function style_admin_permissions()
{
	global $lang, $plugins;
	
	$admin_permissions = array(
		"themes" => $lang->can_manage_themes,
		"templates" => $lang->can_manage_templates,
	);
	
	$plugins->run_hooks_by_ref("admin_style_permissions", $admin_permissions);
	
	return array("name" => $lang->templates_and_style, "permissions" => $admin_permissions, "disporder" => 40);
}
?>