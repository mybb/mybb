<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: hourlycleanup.php 5297 2010-12-28 22:01:14Z Tomm $
 */

function task_hourlycleanup($task)
{
	global $db, $lang;
	
	$threads = array();
	$posts = array();

	// Delete moved threads with time limits
	$db->delete_query("threads", "deletetime != '0' AND deletetime < '".TIME_NOW."'");
	
	// Delete old searches
	$cut = TIME_NOW-(60*60*24);
	$db->delete_query("searchlog", "dateline < '{$cut}'");

	// Delete old captcha images
	$cut = TIME_NOW-(60*60*24*7);
	$db->delete_query("captcha", "dateline < '{$cut}'");
	
	add_task_log($task, $lang->task_hourlycleanup_ran);
}
?>