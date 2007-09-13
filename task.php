<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: css.php 2685 2007-02-01 05:44:58Z Tikitiki $
 */
 
ignore_user_abort(true);
@set_time_limit(0);

define("IN_MYBB", 1);

define("NO_ONLINE", 1);

require_once "./inc/init.php";

require_once MYBB_ROOT."inc/functions_task.php";

// Are tasks set to run via cron instead & are we accessing this file via the CLI?
// php task.php [tid]
if(PHP_SAPI == "cli")
{
	// Passing a specific task ID
	if($_SERVER['argc'] == 2)
	{
		$query = $db->simple_select("tasks", "tid", "tid='".intval($_SERVER['argv'][1])."'");
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
	echo base64_decode("R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==");
	
	// If the use shutdown functionality is turned off, run any shutdown related items now.
	if($mybb->settings['useshutdownfunc'] != "no" || $mybb->use_shutdown == true)
	{
		add_shutdown("run_task");
	}
	else
	{
		run_task();
	}
}
?>