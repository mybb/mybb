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

function tools_meta()
{
	global $page;
	$page->add_menu_item("Tools and Maintenance", "tools", "index.php?".SID."&module=tools", 50);
	return true;
}

function tools_action_handler($action)
{
	global $page;
	$page->active_module = "tools";
	switch($action)
	{
		case "php_info":
			$page->active_action = "php_info";
			$action_file = "php_info.php";
			break;
		case "tasks":
			$page->active_action = "tasks";
			$action_file = "tasks.php";
			break;
		case "backupdb":
			$page->active_action = "backupdb";
			$action_file = "backupdb.php";
			break;
		case "optimizedb":
			$page->active_action = "optimizedb";
			$action_file = "optimizedb.php";
			break;
		case "cache":
			$page->active_action = "cache";
			$action_file = "cache.php";
			break;
		case "recount_rebuild":
			$page->active_action = "recount_rebuild";
			$action_file = "recount_rebuild.php";
			break;
		default:
			$page->active_action = "stats";
			$action_file = "index.php";
	}
	
	$sub_menu = array();
	$sub_menu['10'] = array("id" => "stats", "title" => "System Health", "link" => "index.php?".SID."&module=tools/stats");
	$sub_menu['20'] = array("id" => "adminlog", "title" => "Administrator Log", "link" => "index.php?".SID."&module=tools/adminlog");
	$sub_menu['30'] = array("id" => "modlog", "title" => "Moderator Log", "link" => "index.php?".SID."&module=tools/modlog");
	$sub_menu['40'] = array("id" => "maillogs", "title" => "User Email Log", "link" => "index.php?".SID."&module=tools/maillogs");
	$sub_menu['50'] = array("id" => "mailerrors", "title" => "System Mail Log", "link" => "index.php?".SID."&module=tools/mailerrors");
	$sub_menu['60'] = array("id" => "cache", "title" => "Cache Manager", "link" => "index.php?".SID."&module=tools/cache");
	$sub_menu['70'] = array("id" => "tasks", "title" => "Task Manager", "link" => "index.php?".SID."&module=tools/tasks");
	$sub_menu['80'] = array("id" => "recould_rebuild", "title" => "Recount &amp; Rebuild", "link" => "index.php?".SID."&module=tools/recount_rebuild");
	$sub_menu['90'] = array("id" => "php_info", "title" => "View PHP Info", "link" => "index.php?".SID."&module=tools/php_info");
	$sub_menu['100'] = array("id" => "backupdb", "title" => "Database Backups", "link" => "index.php?".SID."&module=tools/backupdb");
	$sub_menu['110'] = array("id" => "optimizedb", "title" => "Optimize Database", "link" => "index.php?".SID."&module=tools/optimizedb");

	$sidebar = new sideBarItem("Maintenance");
	$sidebar->add_menu_items($sub_menu, $page->active_action);
	
	$page->sidebar .= $sidebar->get_markup();
	return $action_file;
}

function tools_admin_log_data()
{
	global $mybb;
	switch($page->active_action)
	{
		case "tasks":
			if($mybb->input['action'] == "edit" || $mybb->input['action'] == "delete" || $mybb->input['action'] == "enable" || $mybb->input['action'] == "disable")
			{
				return array(
					"data" => array("action" => $mybb->input['action'], "tid" => intval($mybb->input['tid']))
				);
			}
			break;
	}
}

function tools_format_admin_log_data($action, $data)
{
	switch($action)
	{
		case "tasks":
			if($data['action'] == "edit")
			{
				return "Edited task #{$data['tid']}";
			}
			else if($data['action'] == "delete")
			{
				return "Deleted task #{$data['tid']}'";
			}
			else if($data['action'] == "enable")
			{
				return "Enabled task #{$data['tid']}'";
			}
			else if($data['action'] == "disable")
			{
				return "Disabled task #{$data['tid']}'";
			}
			break;
	}
}
?>