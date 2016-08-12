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

/**
 * @return bool true
 */
function config_meta()
{
	global $page, $lang, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "settings", "title" => $lang->bbsettings, "link" => "index.php?module=config-settings");
	$sub_menu['20'] = array("id" => "banning", "title" => $lang->banning, "link" => "index.php?module=config-banning");
	$sub_menu['30'] = array("id" => "profile_fields", "title" => $lang->custom_profile_fields, "link" => "index.php?module=config-profile_fields");
	$sub_menu['40'] = array("id" => "smilies", "title" => $lang->smilies, "link" => "index.php?module=config-smilies");
	$sub_menu['50'] = array("id" => "badwords", "title" => $lang->word_filters, "link" => "index.php?module=config-badwords");
	$sub_menu['60'] = array("id" => "mycode", "title" => $lang->mycode, "link" => "index.php?module=config-mycode");
	$sub_menu['70'] = array("id" => "languages", "title" => $lang->languages, "link" => "index.php?module=config-languages");
	$sub_menu['80'] = array("id" => "post_icons", "title" => $lang->post_icons, "link" => "index.php?module=config-post_icons");
	$sub_menu['90'] = array("id" => "help_documents", "title" => $lang->help_documents, "link" => "index.php?module=config-help_documents");
	$sub_menu['100'] = array("id" => "plugins", "title" => $lang->plugins, "link" => "index.php?module=config-plugins");
	$sub_menu['110'] = array("id" => "attachment_types", "title" => $lang->attachment_types, "link" => "index.php?module=config-attachment_types");
	$sub_menu['120'] = array("id" => "mod_tools", "title" => $lang->moderator_tools, "link" => "index.php?module=config-mod_tools");
	$sub_menu['130'] = array("id" => "spiders", "title" => $lang->spiders_bots, "link" => "index.php?module=config-spiders");
	$sub_menu['140'] = array("id" => "calendars", "title" => $lang->calendars, "link" => "index.php?module=config-calendars");
	$sub_menu['150'] = array("id" => "warning", "title" => $lang->warning_system, "link" => "index.php?module=config-warning");
	$sub_menu['160'] = array("id" => "thread_prefixes", "title" => $lang->thread_prefixes, "link" => "index.php?module=config-thread_prefixes");
	$sub_menu['170'] = array("id" => "questions", "title" => $lang->security_questions, "link" => "index.php?module=config-questions");
	$sub_menu['180'] = array("id" => "report_reasons", "title" => $lang->report_reasons, "link" => "index.php?module=config-report_reasons");

	$sub_menu = $plugins->run_hooks("admin_config_menu", $sub_menu);

	$page->add_menu_item($lang->configuration, "config", "index.php?module=config", 10, $sub_menu);

	return true;
}

/**
 * @param string $action
 *
 * @return string
 */
function config_action_handler($action)
{
	global $page, $plugins;

	$page->active_module = "config";

	$actions = array(
		'plugins' => array('active' => 'plugins', 'file' => 'plugins.php'),
		'smilies' => array('active' => 'smilies', 'file' => 'smilies.php'),
		'banning' => array('active' => 'banning', 'file' => 'banning.php'),
		'badwords' => array('active' => 'badwords', 'file' => 'badwords.php'),
		'profile_fields' => array('active' => 'profile_fields', 'file' => 'profile_fields.php'),
		'spiders' => array('active' => 'spiders', 'file' => 'spiders.php'),
		'attachment_types' => array('active' => 'attachment_types', 'file' => 'attachment_types.php'),
		'languages' => array('active' => 'languages', 'file' => 'languages.php'),
		'post_icons' => array('active' => 'post_icons', 'file' => 'post_icons.php'),
		'help_documents' => array('active' => 'help_documents', 'file' => 'help_documents.php'),
		'calendars' => array('active' => 'calendars', 'file' => 'calendars.php'),
		'warning' => array('active' => 'warning', 'file' => 'warning.php'),
		'mod_tools' => array('active' => 'mod_tools', 'file' => 'mod_tools.php'),
		'mycode' => array('active' => 'mycode', 'file' => 'mycode.php'),
		'settings' => array('active' => 'settings', 'file' => 'settings.php'),
		'thread_prefixes' => array('active' => 'thread_prefixes', 'file' => 'thread_prefixes.php'),
		'questions' => array('active' => 'questions', 'file' => 'questions.php'),
		'report_reasons' => array('active' => 'report_reasons', 'file' => 'report_reasons.php')
	);

	$actions = $plugins->run_hooks("admin_config_action_handler", $actions);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}
	else
	{
		$page->active_action = "settings";
		return "settings.php";
	}
}

/**
 * @return array
 */
function config_admin_permissions()
{
	global $lang, $plugins;

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
		"mod_tools" => $lang->can_manage_mod_tools,
		"thread_prefixes" => $lang->can_manage_thread_prefixes,
		"questions" => $lang->can_manage_security_questions,
		"report_reasons" => $lang->can_manage_report_reasons
	);

	$admin_permissions = $plugins->run_hooks("admin_config_permissions", $admin_permissions);

	return array("name" => $lang->configuration, "permissions" => $admin_permissions, "disporder" => 10);
}
