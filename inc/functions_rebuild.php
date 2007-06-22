<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/eula.html
 *
 * $Id$
 */

/**
 * Completely recount the board statistics (useful if they become out of sync)
 */
function rebuild_stats()
{
	global $db;

	$query = $db->simple_select(TABLE_PREFIX."threads", "COUNT(tid) AS threads", "visible='1' AND closed NOT LIKE 'moved|%'");
	$stats['numthreads'] = $db->fetch_field($query, 'threads');
	
	$query = $db->simple_select(TABLE_PREFIX."posts", "COUNT(pid) AS posts", "visible='1'");
	$stats['numposts'] = $db->fetch_field($query, 'posts');

	$query = $db->simple_select(TABLE_PREFIX."users", "COUNT(uid) AS users");
	$stats['numusers'] = $db->fetch_field($query, 'users');

	update_stats($stats);
}

/**
 * Completely rebuild the counters for a particular forum (useful if they become out of sync)
 */
function rebuild_forum_counters($fid)
{
	global $db;

	// Fetch the number of threads and replies in this forum (Approved only)
	$query = $db->query("
		SELECT COUNT(tid) AS threads, SUM(replies) AS replies
		FROM ".TABLE_PREFIX."threads
		WHERE fid='$fid' AND visible='1' AND closed	NOT LIKE 'moved|%'
	");
	$count = $db->fetch_array($query);
	$count['posts'] = $count['threads'] + $count['replies'];

	// Fetch the number of threads and replies in this forum (Unapproved only)
	$query = $db->query("
		SELECT COUNT(tid) AS threads, SUM(replies) AS impliedunapproved
		FROM ".TABLE_PREFIX."threads
		WHERE fid='$fid' AND visible='0' AND closed NOT LIKE 'moved|%'
	");
	$count2 = $db->fetch_array($query);
	$count['unapprovedthreads'] = $count2['threads'];
	$count['unapprovedposts'] = $count2['impliedunapproved'];

	$query = $db->query("
		SELECT SUM(unapprovedposts) AS posts
		FROM ".TABLE_PREFIX."threads
		WHERE fid='$fid' AND closed NOT LIKE 'moved|%'
	");
	$count['unapprovedposts'] += $db->fetch_field($query, "posts");

	update_forum_counters($fid, $count);
}

/**
 * Completely rebuild the counters for a particular thread (useful if they become out of sync)
 *
 * @param int The thread ID
 * @param array Optional thread array so we don't have to query it
 */
function rebuild_thread_counters($tid, $thread=array())
{
	global $db;

	if(!$thread['tid'])
	{
		$thread = get_thread($tid);
	}

	$query = $db->simple_select(TABLE_PREFIX."posts", "COUNT(*) AS replies", "tid='{$tid}' AND pid!='{$thread['firstpost']}' AND visible='1'");
	$count['replies'] = $db->fetch_field($query, "replies");
	if($count['replies'] < 0)
	{
		$count['replies'] = 0;
	}

	// Unapproved posts
	$query = $db->simple_select(TABLE_PREFIX."posts", "COUNT(*) AS totunposts", "tid='{$tid}' AND pid!='{$thread['firstpost']}' AND visible='0'");
	$count['unapprovedposts'] = $db->fetch_field($query, "totunposts");

	// Attachment count
	$query = $db->query("
			SELECT COUNT(*) AS attachment_count
			FROM ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
			WHERE p.tid='$tid'
	");
	$count['attachmentcount'] = $db->fetch_field($query, "attachment_count");

	update_thread_counters($tid, $count);
}
?>