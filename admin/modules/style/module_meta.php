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

function style_meta()
{
	global $page, $lang, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "themes", "title" => $lang->themes, "link" => "index.php?module=style-themes");
	$sub_menu['20'] = array("id" => "templates", "title" => $lang->templates, "link" => "index.php?module=style-templates");

	$sub_menu = $plugins->run_hooks("admin_style_menu", $sub_menu);

	$page->add_menu_item($lang->templates_and_style, "style", "index.php?module=style", 40, $sub_menu);
	return true;
}

function style_action_handler($action)
{
	global $page, $lang, $plugins;

	$page->active_module = "style";

	$actions = array(
		'templates' => array('active' => 'templates', 'file' => 'templates.php'),
		'themes' => array('active' => 'themes', 'file' => 'themes.php')
	);

	$actions = $plugins->run_hooks("admin_style_action_handler", $actions);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}
	else
	{
		$page->active_action = "themes";
		return "themes.php";
	}
}

function style_admin_permissions()
{
	global $lang, $plugins;

	$admin_permissions = array(
		"themes" => $lang->can_manage_themes,
		"templates" => $lang->can_manage_templates,
	);

	$admin_permissions = $plugins->run_hooks("admin_style_permissions", $admin_permissions);

	return array("name" => $lang->templates_and_style, "permissions" => $admin_permissions, "disporder" => 40);
}
