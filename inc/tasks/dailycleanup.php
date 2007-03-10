<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

function task_dailycleanup($task)
{
	global $mybb, $db;

	// Clear out sessions older than 24h
	$cut = time()-60*60*24;
	$db->delete_query("sessions", "uid=0 AND time < '{$cut}'");

	// Delete old read topics
	if($mybb->settings['threadreadcut'] > 0)
	{
		$cut = time()-($mybb->settings['threadreadcut']*60*60*24);
		$db->delete_query("threadsread", "dateline < '{$cut}'");
	}
}
?>