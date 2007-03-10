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

function style_meta()
{
	global $page;
	$page->add_menu_item("Templates and Style", "style", "index.php?".SID."&module=style", 40);
	return true;
}

function style_action_handler($action)
{
	global $page;
	$page->active_module = "style";
	switch($action)
	{
		default:
			$page->active_action = "themes";
			$action_file = "index.php";
	}
	
	$sub_menu = array();
	$sub_menu['10'] = array("id" => "themes", "title" => "Themes", "link" => "index.php?".SID."&module=style/themes");
	$sub_menu['20'] = array("id" => "templates", "title" => "Templates", "link" => "index.php?".SID."&module=style/templates");

	$sidebar = new sideBarItem("Templates and Style");
	$sidebar->add_menu_items($sub_menu, $page->active_action);
	
	$page->sidebar .= $sidebar->get_markup();
	return $action_file;
}

function style_admin_log_data()
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

function style_format_admin_log_data($action, $data)
{
	switch($action)
	{
		case "dashboard":
			return "Edit profile of {$data['username']} ({$data['uid']})";
			break;
	}
}
?>