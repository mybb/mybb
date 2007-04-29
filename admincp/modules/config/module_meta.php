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

function config_meta()
{
	global $page;
	$page->add_menu_item("Configuration", "config", "index.php?".SID."&module=config", 10);
	return true;
}

function config_action_handler($manage)
{
	global $page;
	$page->active_module = "config";

	switch($manage)
	{
		case "plugins":
			$page->active_action = "plugins";
			$action_file = "plugins.php";
			break;
		case "smilies":
			$page->active_action = "smilies";
			$action_file = "smilies.php";
			break;
		case "banning":
			$page->active_action = "banning";
			$action_file = "banning.php";
			break;
		case "badwords":
			$page->active_action = "badwords";
			$action_file = "badwords.php";
			break;
		case "profile_fields":
			$page->active_action = "profile_fields";
			$action_file = "profile_fields.php";
			break;
		case "spiders":
			$page->active_action = "spiders";
			$action_file = "spiders.php";
			break;
		case "attachment_types":
			$page->active_action = "attachment_types";
			$action_file = "attachment_types.php";
			break;
		case "post_icons":
			$page->active_action = "post_icons";
			$action_file = "post_icons.php";
			break;
		case "help_documents":
			$page->active_action = "help_documents";
			$action_file = "help_documents.php";
			break;
		case "calendars":
			$page->active_action = "calendars";
			$action_file = "calendars.php";
			break;
		default:
			$page->active_action = "settings";
			$action_file = "settings.php";
	}

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "settings", "title" => "Settings", "link" => "index.php?".SID."&module=config/settings");
	$sub_menu['20'] = array("id" => "banning", "title" => "Banning", "link" => "index.php?".SID."&module=config/banning");
	$sub_menu['30'] = array("id" => "profile_fields", "title" => "Custom Profile Fields", "link" => "index.php?".SID."&module=config/profile_fields");
	$sub_menu['40'] = array("id" => "smilies", "title" => "Smilies", "link" => "index.php?".SID."&module=config/smilies");
	$sub_menu['50'] = array("id" => "badwords", "title" => "Bad Words", "link" => "index.php?".SID."&module=config/badwords");
	$sub_menu['60'] = array("id" => "mycode", "title" => "MyCode", "link" => "index.php?".SID."&module=config/mycode");
	$sub_menu['70'] = array("id" => "languages", "title" => "Languages", "link" => "index.php?".SID."&module=config/languages");
	$sub_menu['80'] = array("id" => "post_icons", "title" => "Post Icons", "link" => "index.php?".SID."&module=config/post_icons");
	$sub_menu['90'] = array("id" => "help_documents", "title" => "Help Documents", "link" => "index.php?".SID."&module=config/help_documents");
	$sub_menu['100'] = array("id" => "plugins", "title" => "Plugins", "link" => "index.php?".SID."&module=config/plugins");
	$sub_menu['110'] = array("id" => "attachment_types", "title" => "Attachment Types", "link" => "index.php?".SID."&module=config/attachment_types");
	$sub_menu['120'] = array("id" => "spiders", "title" => "Spiders / Bots", "link" => "index.php?".SID."&module=config/spiders");
	$sub_menu['130'] = array("id" => "calendars", "title" => "Calendars", "link" => "index.php?".SID."&module=config/calendars");

	$sidebar = new SidebarItem("Configuration");
	$sidebar->add_menu_items($sub_menu, $page->active_action);

	$page->sidebar .= $sidebar->get_markup();
	return $action_file;
}

function config_admin_log_data()
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

function config_format_admin_log_data($action, $data)
{
	switch($action)
	{
		case "dashboard":
			return "Edit profile of {$data['username']} ({$data['uid']})";
			break;
	}
}

function config_admin_permissions()
{
	$admin_permissions = array(
		"settings" => "Can Manage Settings?",
		"banning" => "Can Manage Banned Accounts?",
		"profile_fields" => "Can Manage Custom Profile Fields?",
		"smilies" => "Can Manage Smilies?",
		"badwords" => "Can Manage Bad Words?",
		"mycode" => "Can Manage Custom MyCode?",
		"languages" => "Can Manage Language Packs?",
		"post_icons" => "Can Manage Post Icons?",
		"help_documents" => "Can Manage Help Documents?",
		"plugins" => "Can Manage Plugins?",
		"attachment_types" => "Can Manage Attachment Types?",
		"spiders" => "Can Manage Spiders / Bots?",
		"calendars" => "Can Manage Calendars?"
	);
	return array("name" => "Configuration", "permissions" => $admin_permissions);
}
?>