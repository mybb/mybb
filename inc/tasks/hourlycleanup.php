<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

function task_hourlycleanup($task)
{
	global $db, $lang, $plugins;

	$time = array(
		'threads' => TIME_NOW,
		'searchlog' => TIME_NOW-(60*60*24),
		'captcha' => TIME_NOW-(60*60*24),
		'question' => TIME_NOW-(60*60*24)
	);

	if(is_object($plugins))
	{
		$args = array(
			'task' => &$task,
			'time' => &$time
		);
		$plugins->run_hooks('task_hourlycleanup', $args);
	}
	
	require_once  MYBB_ROOT."inc/class_moderation.php";
	$moderation = new Moderation;

	// Delete moved threads with time limits
	$query = $db->simple_select('threads', 'tid', "deletetime != '0' AND deletetime < '".(int)$time['threads']."'");
	while($tid = $db->fetch_field($query, 'tid'))
	{
		$moderation->delete_thread($tid);
	}

	// Delete old searches
	$db->delete_query("searchlog", "dateline < '".(int)$time['searchlog']."'");

	// Delete old captcha images
	$cut = TIME_NOW-(60*60*24*7);
	$db->delete_query("captcha", "dateline < '".(int)$time['captcha']."'");

	// Delete old registration questions
	$cut = TIME_NOW-(60*60*24*7);
	$db->delete_query("questionsessions", "dateline < '".(int)$time['question']."'");

	add_task_log($task, $lang->task_hourlycleanup_ran);
}
