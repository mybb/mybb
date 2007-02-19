<?php
function home_meta()
{
	global $page;
	$page->add_menu_item("Home", "home", "index.php?".SID, 1);
	return true;
}

function home_action_handler($action)
{
	global $page;
	$page->active_module = "home";
	switch($action)
	{
		default:
			$page->active_action = "dashboard";
			$action_file = "index.php";
	}
	
	$sub_menu = array();
	$sub_menu['10'] = array("id" => "dashboard", "title" => "Dashboard", "link" => "index.php?".SID."&module=home/dashboard");
	$sub_menu['20'] = array("id" => "preferences", "title" => "Preferences", "link" => "index.php?".SID."&module=home/preferences");
	$sub_menu['30'] = array("id" => "admin_notes", "title" => "Admin Notes", "link" => "index.php?".SID."&module=home/admin_notes");
	$sub_menu['40'] = array("id" => "version_check", "title" => "Version Check", "link" => "index.php?".SID."&module=home/version_check");
	$sub_menu['50'] = array("id" => "credits", "title" => "MyBB Credits", "link" => "index.php?".SID."&module=home/credits");

	$sidebar = new sideBarItem("Home");
	$sidebar->add_menu_items($sub_menu, $page->active_action);
	
	$page->sidebar .= $sidebar->get_markup();
	
	// Quick Access
	$sub_menu = array();
	$sub_menu['10'] = array("id" => "add_forum", "title" => "Add New Forum", "link" => "index.php?".SID."&module=forum/add_forum");
	$sub_menu['20'] = array("id" => "search", "title" => "Search for Users", "link" => "index.php?".SID."&module=user/search");
	$sub_menu['30'] = array("id" => "dork", "title" => "(what else could we put here?)", "link" => "index.php?".SID."&module=tools/dork");
	
	$sidebar = new sideBarItem("Quick Access");
	$sidebar->add_menu_items($sub_menu, $page->active_action);
	
	$page->sidebar .= $sidebar->get_markup();
	return $action_file;
}

function home_admin_log_data()
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

function home_format_admin_log_data($action, $data)
{
	switch($action)
	{
		case "dashboard":
			return "Edit profile of {$data['username']} ({$data['uid']})";
			break;
	}
}

?>