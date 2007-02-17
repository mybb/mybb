<?php
function forum_meta()
{
	global $page;
	$page->add_menu_item("Forums and Posts", "forum", "index.php?".SID."&module=forum", 20);

	return true;
}

function forum_action_handler($action)
{
	global $page;
	$page->active_module = "forum";
	switch($action)
	{
		default:
			$page->active_action = "dashboard";
			return "index.php";
	}
}

function forum_admin_log_data()
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

function forum_format_admin_log_data($action, $data)
{
	switch($action)
	{
		case "dashboard":
			return "Edit profile of {$data['username']} ({$data['uid']})";
			break;
	}
}

?>