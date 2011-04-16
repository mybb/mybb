<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: hourlycleanup.php 5379 2011-02-21 11:06:42Z Tomm $
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