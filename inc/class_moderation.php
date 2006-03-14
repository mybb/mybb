<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

class Moderation
{
	/**
	 * Close a thread
	 *
	 * @param int Thread ID of the thread
	 * @return boolean true
	 */
	function close_thread($tid)
	{
		global $db;

		$sqlarray = array(
			"closed" => "yes",
			);
		$db->update_query(TABLE_PREFIX."threads", $sqlarray, "tid='$tid'");

		return true;
	}

	/**
	 * Open a thread
	 *
	 * @param int Thread ID of the thread
	 * @return boolean true
	 */
	function open_thread($tid)
	{
		global $db;

		$sqlarray = array(
			"closed" => "no",
			);
		$db->update_query(TABLE_PREFIX."threads", $sqlarray, "tid='$tid'");

		return true;
	}

	/**
	 * Stick a thread
	 *
	 * @param int Thread ID of the thread
	 * @return boolean true
	 */
	function stick_thread($tid)
	{
		global $db;

		$sqlarray = array(
			"sticky" => 1,
			);
		$db->update_query(TABLE_PREFIX."threads", $sqlarray, "tid='$tid'");

		return true;
	}

	/**
	 * Unstick a thread
	 *
	 * @param int Thread ID of the thread
	 * @return boolean true
	 */
	function unstick_thread($tid)
	{
		global $db;

		$sqlarray = array(
			"sticky" => 0,
			);
		$db->update_query(TABLE_PREFIX."threads", $sqlarray, "tid='$tid'");

		return true;
	}

	/**
	 * Remove redirects that redirect to the specified thread
	 *
	 * @param int Thread ID of the thread
	 * @return boolean true
	 */
	function remove_redirects($tid)
	{
		global $db;

		$query = $db->simple_select(TABLE_PREFIX."threads", "fid", "closed='moved|$tid'");
		while($forum = $db->fetch_array($query))
		{
			$fids[] = $forum['fid']; 
		}
		$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE closed='moved|$tid'");
		foreach($fids as $fid)
		{
			updateforumcount($fid);
		}

		return true;
	}

	/**
	 * Delete a thread
	 *
	 * @param int Thread ID of the thread
	 * @return boolean true
	 */
	function delete_thread($tid)
	{
		global $db, $cache, $plugins;

		$query = $db->query("SELECT p.pid, p.uid, p.visible, f.usepostcounts FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid) WHERE p.tid='$tid'");
		$num_unapproved_posts = 0;
		while($post = $db->fetch_array($query))
		{
			if($userposts[$post['uid']])
			{
				$userposts[$post['uid']]--;
			}
			else
			{
				$userposts[$post['uid']] = -1;
			}
			$pids .= $post['pid'].",";
			$usepostcounts = $post['usepostcounts'];
			remove_attachments($post['pid']);
	
			// If the post is unapproved, count it!
			if($post['visible'] == 0)
			{
				$num_unapproved_posts++;
			}
		}
		if($usepostcounts != "no")
		{
			if(is_array($userposts))
			{
				foreach($userposts as $uid => $subtract)
				{
					$db->query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum$subtract WHERE uid='$uid'");
				}
			}
		}
		if($pids)
		{
			$pids .= "0";
			$db->query("DELETE FROM ".TABLE_PREFIX."posts WHERE pid IN ($pids)");
			$db->query("DELETE FROM ".TABLE_PREFIX."attachments WHERE pid IN ($pids)");
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
		$thread = $db->fetch_array($query);
	
		// Update unapproved post/thread numbers
		$update_unapproved = "";
		if($thread['visible'] == 0)
		{
			$update_unapproved .= "unapprovedthreads=unapprovedthreads-1";
		}
		if($num_unapproved_posts > 0)
		{
			if(!empty($update_unapproved))
			{
				$update_unapproved .= ", ";
			}
			$update_unapproved .= "unapprovedposts=unapprovedposts-".$num_unapproved_posts;
		}
		if(!empty($update_unapproved))
		{
			$db->query("UPDATE ".TABLE_PREFIX."forums SET $update_unapproved WHERE fid='$thread[fid]'");
		}
	
		$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
		$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE closed='moved|$tid'");
		$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE tid='$tid'");
		$db->query("DELETE FROM ".TABLE_PREFIX."polls WHERE tid='$tid'");
		$db->query("DELETE FROM ".TABLE_PREFIX."pollvotes WHERE pid='".$thread['poll']."'");
		$cache->updatestats();
		$plugins->run_hooks("delete_thread", $tid);

		return true;
	}
	
	/**
	 * Delete a poll
	 *
	 * @param int Poll id
	 * @return boolean true
	 */
	function delete_poll($pid)
	{
		global $db;

		$db->query("DELETE FROM ".TABLE_PREFIX."polls WHERE pid='$pid'");
		$db->query("DELETE FROM ".TABLE_PREFIX."pollvotes WHERE pid='$pid'");
		$sqlarray = array(
			"poll" => '',
			);
		$db->update_query(TABLE_PREFIX."threads", $sqlarray, "poll='$pid'");
		
		return true;
	}

	/**
	 * Close a thread
	 *
	 * @param int Thread ID of the thread
	 * @return boolean true
	 */
	function approve_thread($tid)
	{
		global $db, $cache;

		$db->query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts-1 WHERE tid='$tid'");
		$db->query("UPDATE ".TABLE_PREFIX."forums SET unapprovedthreads=unapprovedthreads-1, unapprovedposts=unapprovedposts-1 WHERE fid='$fid'");

		$sqlarray = array(
			"visible" => 1,
			);
		$db->update_query(TABLE_PREFIX."threads", $sqlarray, "tid='$tid'");
		$db->update_query(TABLE_PREFIX."posts", $sqlarray, "tid='$tid' AND replyto='0'", 1);

		$cache->updatestats();
		updateforumcount($fid);

		return true;
	}

	/**
	 * Unapprove a thread
	 *
	 * @param int Thread ID of the thread
	 * @return boolean true
	 */
	function unapprove_thread($tid)
	{
		global $db, $cache;

		$db->query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts+1 WHERE tid='$tid'");
		$db->query("UPDATE ".TABLE_PREFIX."forums SET unapprovedthreads=unapprovedthreads+1, unapprovedposts=unapprovedposts+1 WHERE fid='$fid'");

		$sqlarray = array(
			"visible" => 0,
			);
		$db->update_query(TABLE_PREFIX."threads", $sqlarray, "tid='$tid'");
		$db->update_query(TABLE_PREFIX."posts", $sqlarray, "tid='$tid' AND replyto='0'", 1);

		$cache->updatestats();
		updateforumcount($fid);

		return true;
	}
}
?>