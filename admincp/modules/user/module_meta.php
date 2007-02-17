<?php
function user_meta()
{
	global $page;
	$page->add_menu_item("Users and Groups", "user", "index.php?".SID."&module=user", 30);
	return true;
}

function user_action_handler($action)
{
	global $page;
	$page->active_module = "user";
	switch($action)
	{
		default:
			$page->active_action = "dashboard";
			return "index.php";
	}
}

function user_admin_log_data()
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

function user_format_admin_log_data($action, $data)
{
	switch($action)
	{
		case "dashboard":
			return "Edit profile of {$data['username']} ({$data['uid']})";
			break;
	}
}
?>