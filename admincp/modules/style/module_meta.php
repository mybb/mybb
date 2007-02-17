<?php
function style_meta()
{
	global $page;
	$page->add_menu_item("Templates and Style", "style", "index.php?".SID."&module=style", 40);
	return true;
}

function style_action_handler($action)
{
	global $page;
	$page->active_module = "style";
	switch($action)
	{
		default:
			$page->active_action = "dashboard";
			return "index.php";
	}
}

function style_admin_log_data()
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

function style_format_admin_log_data($action, $data)
{
	switch($action)
	{
		case "dashboard":
			return "Edit profile of {$data['username']} ({$data['uid']})";
			break;
	}
}
?>