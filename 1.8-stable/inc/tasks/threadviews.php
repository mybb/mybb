<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: threadviews.php 5297 2010-12-28 22:01:14Z Tomm $
 */

function task_threadviews($task)
{
	global $mybb, $db, $lang;
	
	$threadviews = array();

	if($mybb->settings['delayedthreadviews'] != 1)
	{
		return;
	}

	// Update thread views
	$query = $db->query("
		SELECT tid, COUNT(tid) AS views
		FROM ".TABLE_PREFIX."threadviews
		GROUP BY tid
	");
	while($threadview = $db->fetch_array($query))
	{
		$db->update_query("threads", array('views' => "views+{$threadview['views']}"), "tid='{$threadview['tid']}'", 1, true);
	}
	
	$db->write_query("TRUNCATE TABLE ".TABLE_PREFIX."threadviews");
	
	add_task_log($task, $lang->task_threadviews_ran);
}
?>