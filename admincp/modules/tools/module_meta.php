<?php
function tools_meta()
{
	global $page;
	$page->add_menu_item("Maintenance", "tools", "index.php?".SID."&module=tools", 50);
	return true;
}

function tools_action_handler($action)
{
	global $page;
	$page->active_module = "tools";
	switch($action)
	{
		default:
			$page->active_action = "dashboard";
			return "index.php";
	}
}

function tools_admin_log_data()
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

function tools_format_admin_log_data($action, $data)
{
	switch($action)
	{
		case "dashboard":
			return "Edit profile of {$data['username']} ({$data['uid']})";
			break;
	}
}
?>