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

function config_meta()
{
	global $page, $lang;
	
	$page->add_menu_item($lang->configuration, "config", "index.php?".SID."&module=config", 10);
	
	return true;
}

function config_action_handler($manage)
{
	global $page, $lang;
	
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
		case "languages":
			$page->active_action = "languages";
			$action_file = "languages.php";
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
		case "warning":
			$page->active_action = "warning";
			$action_file = "warning.php";
			break;
		case "mod_tools":
			$page->active_action = "mod_tools";
			$action_file = "mod_tools.php";
			break;
		case "mycode":
			$page->active_action = "mycode";
			$action_file = "mycode.php";
			break;
		default:
			$page->active_action = "settings";
			$action_file = "settings.php";
	}

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "settings", "title" => $lang->settings, "link" => "index.php?".SID."&module=config/settings");
	$sub_menu['20'] = array("id" => "banning", "title" => $lang->banning, "link" => "index.php?".SID."&module=config/banning");
	$sub_menu['30'] = array("id" => "profile_fields", "title" => $lang->custom_profile_fields, "link" => "index.php?".SID."&module=config/profile_fields");
	$sub_menu['40'] = array("id" => "smilies", "title" => $lang->smilies, "link" => "index.php?".SID."&module=config/smilies");
	$sub_menu['50'] = array("id" => "badwords", "title" => $lang->word_filters, "link" => "index.php?".SID."&module=config/badwords");
	$sub_menu['60'] = array("id" => "mycode", "title" => $lang->mycode, "link" => "index.php?".SID."&module=config/mycode");
	$sub_menu['70'] = array("id" => "languages", "title" => $lang->languages, "link" => "index.php?".SID."&module=config/languages");
	$sub_menu['80'] = array("id" => "post_icons", "title" => $lang->post_icons, "link" => "index.php?".SID."&module=config/post_icons");
	$sub_menu['90'] = array("id" => "help_documents", "title" => $lang->help_documents, "link" => "index.php?".SID."&module=config/help_documents");
	$sub_menu['100'] = array("id" => "plugins", "title" => $lang->plugins, "link" => "index.php?".SID."&module=config/plugins");
	$sub_menu['110'] = array("id" => "attachment_types", "title" => $lang->attachment_types, "link" => "index.php?".SID."&module=config/attachment_types");
	$sub_menu['120'] = array("id" => "mod_tools", "title" => $lang->moderator_tools, "link" => "index.php?".SID."&module=config/mod_tools");
	$sub_menu['130'] = array("id" => "spiders", "title" => $lang->spiders_bots, "link" => "index.php?".SID."&module=config/spiders");
	$sub_menu['140'] = array("id" => "calendars", "title" => $lang->calendars, "link" => "index.php?".SID."&module=config/calendars");
	$sub_menu['150'] = array("id" => "warning", "title" => $lang->warning_system, "link" => "index.php?".SID."&module=config/warning");

	$sidebar = new SidebarItem($lang->configuration);
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
	global $lang;
	
	$admin_permissions = array(
		"settings" => $lang->can_manage_settings,
		"banning" => $lang->can_manage_banned_accounts,
		"profile_fields" => $lang->can_manage_custom_profile_fields,
		"smilies" => $lang->can_manage_smilies,
		"badwords" => $lang->can_manage_bad_words,
		"mycode" => $lang->can_manage_custom_mycode,
		"languages" => $lang->can_manage_language_packs,
		"post_icons" => $lang->can_manage_post_icons,
		"help_documents" => $lang->can_manage_help_documents,
		"plugins" => $lang->can_manage_plugins,
		"attachment_types" => $lang->can_manage_attachment_types,
		"spiders" => $lang->can_manage_spiders_bots,
		"calendars" => $lang->can_manage_calendars,
		"warning" => $lang->can_manage_warning_system,
		"mod_tools" => $lang->can_manage_mod_tools
	);
	return array("name" => "Configuration", "permissions" => $admin_permissions);
}
?>