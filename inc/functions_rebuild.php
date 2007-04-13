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

/**
 * Completely recount the board statistics (useful if they become out of sync)
 */
function rebuild_stats()
{
	global $db;

	$query = $db->simple_select("threads", "COUNT(tid) AS threads", "visible='1' AND closed NOT LIKE 'moved|%'");
	$stats['numthreads'] = $db->fetch_field($query, 'threads');
	
	if(!$stats['numthreads'])
	{
		$stats['numthreads'] = 0;
	}
	
	$query = $db->simple_select("posts", "COUNT(pid) AS posts", "visible='1'");
	$stats['numposts'] = $db->fetch_field($query, 'posts');
	
	if(!$stats['numposts'])
	{
		$stats['numposts'] = 0;
	}

	$query = $db->simple_select("users", "COUNT(uid) AS users");
	$stats['numusers'] = $db->fetch_field($query, 'users');
	
	if(!$stats['numusers'])
	{
		$stats['numusers'] = 0;
	}

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
		SELECT COUNT(*) AS threads, SUM(replies) AS replies
		FROM ".TABLE_PREFIX."threads
		WHERE fid='$fid' AND visible='1' AND closed	NOT LIKE 'moved|%'
	");
	$count = $db->fetch_array($query);
	$count['posts'] = $count['threads'] + $count['replies'];
	
	if(!$count['posts'])
	{
		$count['posts'] = 0;
	}

	// Fetch the number of threads and replies in this forum (Unapproved only)
	$query = $db->query("
		SELECT COUNT(*) AS threads
		FROM ".TABLE_PREFIX."threads
		WHERE fid='$fid' AND visible='0' AND closed NOT LIKE 'moved|%'
	");
	$count['unapprovedthreads'] = $db->fetch_field($query, "threads");
	
	if(!$count['unapprovedthreads'])
	{
		$count['unapprovedthreads'] = 0;
	}

	$query = $db->query("
		SELECT SUM(unapprovedposts) AS posts
		FROM ".TABLE_PREFIX."threads
		WHERE fid='$fid' AND closed NOT LIKE 'moved|%'
	");
	$count['unapprovedposts'] = $db->fetch_field($query, "posts");
	
	if(!$count['unapprovedposts'])
	{
		$count['unapprovedposts'] = 0;
	}

	update_forum_counters($fid, $count);
}

/**
 * Completely rebuild the counters for a particular thread (useful if they become out of sync)
 */
function rebuild_thread_counters($tid)
{
	global $db;

	$query = $db->simple_select("posts", "COUNT(*) AS replies", "tid='{$tid}' AND visible='1'");
	$count['replies'] = $db->fetch_field($query, "replies") -1;
	if($count['replies'] < 0)
	{
		$count['replies'] = 0;
	}

	// Unapproved posts
	$query = $db->simple_select("posts", "COUNT(*) AS totunposts", "tid='{$tid}' AND visible='0'");
	$count['unapprovedposts'] = $db->fetch_field($query, "totunposts");
	
	if(!$count['unapprovedposts'])
	{
		$count['unapprovedposts'] = 0;
	}

	// Attachment count
	$query = $db->query("
			SELECT COUNT(*) AS attachment_count
			FROM ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
			WHERE p.tid='$tid'
	");
	$count['attachmentcount'] = $db->fetch_field($query, "attachment_count");
	
	if(!$count['attachmentcount'])
	{
		$count['attachmentcount'] = 0;
	}

	update_thread_counters($tid, $count);
}
?>