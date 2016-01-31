<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

function task_dailycleanup($task)
{
	global $mybb, $db, $cache, $lang, $plugins;

	require_once MYBB_ROOT."inc/functions_user.php";

	$time = array(
		'sessionstime' => TIME_NOW-60*60*24,
		'threadreadcut' => TIME_NOW-(((int)$mybb->settings['threadreadcut'])*60*60*24),
		'privatemessages' => TIME_NOW-(60*60*24*7),
		'deleteinvite' => TIME_NOW-(((int)$mybb->settings['deleteinvites'])*60*60*24),
		'stoppmtracking' => TIME_NOW-(60*60*24*180)
	);

	if(is_object($plugins))
	{
		$args = array(
			'task' => &$task,
			'time' => &$time
		);
		$plugins->run_hooks('task_dailycleanup_start', $args);
	}

	// Clear out sessions older than 24h
	$db->delete_query("sessions", "time < '".(int)$time['sessionstime']."'");

	// Delete old read topics
	if($mybb->settings['threadreadcut'] > 0)
	{
		$db->delete_query("threadsread", "dateline < '".(int)$time['threadreadcut']."'");
		$db->delete_query("forumsread", "dateline < '".(int)$time['threadreadcut']."'");
	}

	// Check PMs moved to trash over a week ago & delete them
	$query = $db->simple_select("privatemessages", "pmid, uid, folder", "deletetime<='".(int)$time['privatemessages']."' AND folder='4'");
	while($pm = $db->fetch_array($query))
	{
		$user_update[$pm['uid']] = 1;
		$pm_update[] = $pm['pmid'];
	}

	// Delete old group invites
	if($mybb->settings['deleteinvites'] > 0)
	{
		$db->delete_query("joinrequests", "dateline < '".(int)$time['deleteinvite']."' AND invite='1'");
	}

	// Stop tracking read PMs after 6 months
	$sql_array = array(
		"receipt" => 0
	);
	$db->update_query("privatemessages", $sql_array, "receipt='2' AND folder!='3' AND status!='0' AND readtime < '".(int)$time['stoppmtracking']."'");

	if(is_object($plugins))
	{
		$args = array(
			'user_update' => &$user_update,
			'pm_update' => &$pm_update
		);
		$plugins->run_hooks('task_dailycleanup_end', $args);
	}

	if(!empty($pm_update))
	{
		$db->delete_query("privatemessages", "pmid IN(".implode(',', $pm_update).")");
	}

	if(!empty($user_update))
	{
		foreach($user_update as $uid => $data)
		{
			update_pm_count($uid);
		}
	}

	$cache->update_most_replied_threads();
	$cache->update_most_viewed_threads();
	$cache->update_birthdays();
	$cache->update_forumsdisplay();

	add_task_log($task, $lang->task_dailycleanup_ran);
}
