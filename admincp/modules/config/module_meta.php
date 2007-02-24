<?php
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
		default:
			$page->active_action = "settings";
			$action_file = "settings.php";
	}

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "settings", "title" => "Settings", "link" => "index.php?".SID."&module=config/settings");
	$sub_menu['20'] = array("id" => "profile_fields", "title" => "Custom Profile Fields", "link" => "index.php?".SID."&module=config/profile_fields");
	$sub_menu['30'] = array("id" => "smilies", "title" => "Smilies", "link" => "index.php?".SID."&module=config/smilies");
	$sub_menu['40'] = array("id" => "badwords", "title" => "Bad Words", "link" => "index.php?".SID."&module=config/badwords");
	$sub_menu['50'] = array("id" => "mycode", "title" => "MyCode", "link" => "index.php?".SID."&module=config/mycode");
	$sub_menu['60'] = array("id" => "languages", "title" => "Languages", "link" => "index.php?".SID."&module=config/languages");
	$sub_menu['70'] = array("id" => "post_icons", "title" => "Post Icons", "link" => "index.php?".SID."&module=config/post_icons");
	$sub_menu['80'] = array("id" => "help_documents", "title" => "Help Documents", "link" => "index.php?".SID."&module=config/help_documents");
	$sub_menu['90'] = array("id" => "plugins", "title" => "Plugins", "link" => "index.php?".SID."&module=config/plugins");
	$sub_menu['100'] = array("id" => "attachment_types", "title" => "Attachment Types", "link" => "index.php?".SID."&module=config/attachment_types");

	$sidebar = new sideBarItem("Configuration");
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
?>