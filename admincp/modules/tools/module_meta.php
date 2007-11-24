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

function tools_meta()
{
	global $page, $lang;

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "system_health", "title" => $lang->system_health, "link" => "index.php?".SID."&module=tools/system_health");
	$sub_menu['20'] = array("id" => "cache", "title" => $lang->cache_manager, "link" => "index.php?".SID."&module=tools/cache");
	$sub_menu['30'] = array("id" => "tasks", "title" => $lang->task_manager, "link" => "index.php?".SID."&module=tools/tasks");
	$sub_menu['40'] = array("id" => "recount_rebuild", "title" => $lang->recount_and_rebuild, "link" => "index.php?".SID."&module=tools/recount_rebuild");
	$sub_menu['50'] = array("id" => "php_info", "title" => $lang->view_php_info, "link" => "index.php?".SID."&module=tools/php_info");
	$sub_menu['60'] = array("id" => "backupdb", "title" => $lang->database_backups, "link" => "index.php?".SID."&module=tools/backupdb");
	$sub_menu['70'] = array("id" => "optimizedb", "title" => $lang->optimize_database, "link" => "index.php?".SID."&module=tools/optimizedb");
	
	$page->add_menu_item($lang->tools_and_maintenance, "tools", "index.php?".SID."&module=tools", 50, $sub_menu);
	
	return true;
}

function tools_action_handler($action)
{
	global $page, $lang;
	
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
		case "maillogs":
			$page->active_action = "maillogs";
			$action_file = "maillogs.php";
			break;
		case "mailerrors":
			$page->active_action = "mailerrors";
			$action_file = "mailerrors.php";
			break;
		case "modlog":
			$page->active_action = "modlog";
			$action_file = "modlog.php";
			break;
		case "warninglog":
			$page->active_action = "warninglog";
			$action_file = "warninglog.php";
			break;
		default:
			$page->active_action = "system_health";
			$action_file = "index.php";
	}

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "adminlog", "title" => $lang->administrator_log, "link" => "index.php?".SID."&module=tools/adminlog");
	$sub_menu['20'] = array("id" => "modlog", "title" => $lang->moderator_log, "link" => "index.php?".SID."&module=tools/modlog");
	$sub_menu['30'] = array("id" => "maillogs", "title" => $lang->user_email_log, "link" => "index.php?".SID."&module=tools/maillogs");
	$sub_menu['40'] = array("id" => "mailerrors", "title" => $lang->system_mail_log, "link" => "index.php?".SID."&module=tools/mailerrors");
	$sub_menu['50'] = array("id" => "warninglog", "title" => $lang->user_warning_log, "link" => "index.php?".SID."&module=tools/warninglog");
	
	$sidebar = new SidebarItem($lang->logs);
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

function tools_admin_permissions()
{
	global $lang;
	
	$admin_permissions = array(
		"system_health" => $lang->can_access_system_health,
		"cache" => $lang->can_manage_cache,
		"tasks" => $lang->can_manage_tasks,
		"backupdb" => $lang->can_manage_db_backup,
		"optimizedb" => $lang->can_optimize_db,
		"recount_rebuild" => $lang->can_recount_and_rebuild,
		"adminlog" => $lang->can_manage_admin_logs,
		"modlog" => $lang->can_manage_mod_logs,
		"maillogs" => $lang->can_manage_user_mail_log,
		"mailerrors" => $lang->can_manage_system_mail_log,
		"warninglog" => $lang->can_manage_user_warning_log,
		"phpinfo" => $lang->can_view_php_info
	);
	return array("name" => $lang->tools_and_maintenance, "permissions" => $admin_permissions);
}
?>