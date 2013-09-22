<?php
/**
 * MyBB 1.8
 * Copyright 2013 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * $Id$
 */

/**
 * Completely recount the board statistics (useful if they become out of sync)
 */
function rebuild_stats()
{
	global $db;

	$stats = array();

	$query = $db->simple_select("forums", "SUM(threads) AS numthreads");
	$stats['numthreads'] = $db->fetch_field($query, 'numthreads');

	$query = $db->simple_select("forums", "SUM(posts) AS numposts");
	$stats['numposts'] = $db->fetch_field($query, 'numposts');

	$query = $db->simple_select("forums", "SUM(unapprovedthreads) AS numunapprovedthreads");
	$stats['numunapprovedthreads'] = $db->fetch_field($query, 'numunapprovedthreads');

	$query = $db->simple_select("forums", "SUM(unapprovedposts) AS numunapprovedposts");
	$stats['numunapprovedposts'] = $db->fetch_field($query, 'numunapprovedposts');

	$query = $db->simple_select("users", "COUNT(uid) AS users");
	$stats['numusers'] = $db->fetch_field($query, 'users');

	update_stats($stats);
}

/**
 * Completely rebuild the counters for a particular forum (useful if they become out of sync)
 */
function rebuild_forum_counters($fid)
{
	global $db;

	$count = array();

	// Fetch the number of threads and replies in this forum (Approved only)
	$query = $db->simple_select('threads', 'COUNT(tid) AS threads, SUM(replies) AS replies', "fid='$fid' AND visible='1'");
	$count = $db->fetch_array($query);
	$count['posts'] = $count['threads'] + $count['replies'];

	// Fetch the number of threads and replies in this forum (Unapproved only)
	$query = $db->simple_select('threads', 'COUNT(tid) AS threads, SUM(replies) AS impliedunapproved', "fid='$fid' AND visible='0'");
	$count2 = $db->fetch_array($query);
 	$count['unapprovedthreads'] = $count2['threads'];
	$count['unapprovedposts'] = $count2['impliedunapproved']+$count2['threads'];

	// Fetch the number of threads and replies in this forum (Soft deleted only)
	$query = $db->simple_select('threads', 'COUNT(tid) AS threads', "fid='$fid' AND visible='-1'");
	$count3 = $db->fetch_array($query);
 	$count['deletedthreads'] = $count3['threads'];

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
function rebuild_thread_counters($tid)
{
	global $db;

 	$thread = get_thread($tid);
	$count = array();

 	$query = $db->simple_select("posts", "COUNT(*) AS replies", "tid='{$tid}' AND pid!='{$thread['firstpost']}' AND visible='1'");
 	$count['replies'] = $db->fetch_field($query, "replies");

	// Unapproved posts
	$query = $db->simple_select("posts", "COUNT(pid) AS unapprovedposts", "tid='{$tid}' AND pid != '{$thread['firstpost']}' AND visible='0'");
	$count['unapprovedposts'] = $db->fetch_field($query, "unapprovedposts");

	// Soft deleted posts
	$query = $db->simple_select("posts", "COUNT(pid) AS deletedposts", "tid='{$tid}' AND pid != '{$thread['firstpost']}' AND visible='-1'");
	$count['deletedposts'] = $db->fetch_field($query, "deletedposts");

	// Attachment count
	$query = $db->query("
			SELECT COUNT(aid) AS attachment_count
			FROM ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
			WHERE p.tid='$tid'
	");
	$count['attachmentcount'] = $db->fetch_field($query, "attachment_count");

	update_thread_counters($tid, $count);
}
?>