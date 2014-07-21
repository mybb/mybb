<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

ignore_user_abort(true);
@set_time_limit(0);

define("IN_MYBB", 1);
define("NO_ONLINE", 1);
define("IN_TASK", 1);
define('THIS_SCRIPT', 'task.php');

require_once dirname(__FILE__)."/inc/init.php";

// Load language
$lang->set_language($mybb->settings['bblanguage']);
$lang->load("global");
$lang->load("messages");

if(function_exists('mb_internal_encoding') && !empty($lang->settings['charset']))
{
	@mb_internal_encoding($lang->settings['charset']);
}

require_once MYBB_ROOT."inc/functions_task.php";

// Are tasks set to run via cron instead & are we accessing this file via the CLI?
// php task.php [tid]
if(PHP_SAPI == "cli")
{
	// Passing a specific task ID
	if($_SERVER['argc'] == 2)
	{
		$query = $db->simple_select("tasks", "tid", "tid='".(int)$_SERVER['argv'][1]."'");
		$tid = $db->fetch_field($query, "tid");
	}

	if($tid)
	{
		run_task($tid);
	}
	else
	{
		run_task();
	}
}
// Otherwise false GIF image, only supports running next available task
else
{
	// Send our fake gif image (clear 1x1 transparent image)
	header("Content-type: image/gif");
	header("Expires: Sat, 1 Jan 2000 01:00:00 GMT");
	header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
	echo base64_decode("R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==");

	// If the use shutdown functionality is turned off, run any shutdown related items now.
	if($mybb->use_shutdown == true)
	{
		add_shutdown("run_task");
	}
	else
	{
		run_task();
	}
}
