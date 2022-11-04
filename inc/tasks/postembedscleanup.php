<?php
/**
 * MyBB 1.8
 * Copyright 2021 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

function task_postembedscleanup($task)
{
	global $mybb, $db, $lang, $plugins;
	
	$dir = trim($mybb->settings['postembedpath'], './');
	$files = glob(MYBB_ROOT."{$dir}/*.{jpg,jpeg,png}", GLOB_BRACE);

	if(is_object($plugins))
	{
		$args = array(
			'task' => &$task,
			'files' => $files,
		);
		$plugins->run_hooks('task_postembedscleanup', $args);
	}

	foreach ($files as $file)
	{
		$link = str_replace(MYBB_ROOT, $mybb->settings['bburl'] . '/', $file);
		$query = $db->simple_select("posts", "COUNT(DISTINCT pid) AS postcount", "message like '%[img%]{$link}[/img]%'");
		if (!$db->fetch_field($query, 'postcount'))
		{
			@unlink($file);
		}
	}
	add_task_log($task, $lang->task_postembedscleanup_ran);
}