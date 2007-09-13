<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

function task_threadviews($task)
{
	global $mybb, $db;
	
	$threadviews = array();

	if($mybb->settings['delayedthreadviews'] != "on")
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
		$db->write_query("UPDATE ".TABLE_PREFIX."threads SET views=views+{$threadview['views']} WHERE tid='{$threadview['tid']}' LIMIT 1");
	}
	
	$db->write_query("TRUNCATE TABLE ".TABLE_PREFIX."threadviews");
}
?>