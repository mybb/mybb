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
			return "index.php";
	}
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