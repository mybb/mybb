<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */

function task_hourlycleanup($task)
{
	global $db, $lang, $plugins;

	$time = array(
		'threads' => TIME_NOW,
		'searchlog' => TIME_NOW-(60*60*24),
		'captcha' => TIME_NOW-(60*60*24*7)
	);

	if(is_object($plugins))
	{
		$args = array(
			'task' => &$task,
			'time' => &$time
		);
		$plugins->run_hooks('task_hourlycleanup', $args);
	}

	// Delete moved threads with time limits
	$db->delete_query("threads", "deletetime != '0' AND deletetime < '".(int)$time['threads']."'");

	// Delete old searches
	$db->delete_query("searchlog", "dateline < '".(int)$time['searchlog']."'");

	// Delete old captcha images
	$cut = TIME_NOW-(60*60*24*7);
	$db->delete_query("captcha", "dateline < '".(int)$time['captcha']."'");

	add_task_log($task, $lang->task_hourlycleanup_ran);
}
?>