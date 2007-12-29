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
	global $page, $lang;

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "themes", "title" => $lang->themes, "link" => "index.php?".SID."&module=style/themes");
	$sub_menu['20'] = array("id" => "templates", "title" => $lang->templates, "link" => "index.php?".SID."&module=style/templates");

	$page->add_menu_item($lang->templates_and_style, "style", "index.php?".SID."&module=style", 40, $sub_menu);
	return true;
}

function style_action_handler($action)
{
	global $page, $lang;
	
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
	
	return $action_file;
}

function style_admin_permissions()
{
	global $lang;
	
	$admin_permissions = array(
		"themes" => $lang->can_manage_themes,
		"templates" => $lang->can_manage_templates,
	);
	return array("name" => $lang->templates_and_style, "permissions" => $admin_permissions, "disporder" => 40);
}
?>