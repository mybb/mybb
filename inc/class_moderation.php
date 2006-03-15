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

		$openthread = array(
			"closed" => "yes",
			);
		$db->update_query(TABLE_PREFIX."threads", $openthread, "tid='$tid'");

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

		$closethread = array(
			"closed" => "no",
			);
		$db->update_query(TABLE_PREFIX."threads", $closethread, "tid='$tid'");

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

		$stickthread = array(
			"sticky" => 1,
			);
		$db->update_query(TABLE_PREFIX."threads", $stickthread, "tid='$tid'");

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

		$unstickthread = array(
			"sticky" => 0,
			);
		$db->update_query(TABLE_PREFIX."threads", $unstickthread, "tid='$tid'");

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

		// Find the fids that have these redirects
		$query = $db->simple_select(TABLE_PREFIX."threads", "fid", "closed='moved|$tid'");
		while($forum = $db->fetch_array($query))
		{
			$fids[] = $forum['fid']; 
		}
		// Delete the redirects
		$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE closed='moved|$tid'");
		// Update the forum stats of the fids found above
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

		// Find the pid, uid, visibility, and forum post count status
		$query = $db->query("SELECT p.pid, p.uid, p.visible, f.usepostcounts FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid) WHERE p.tid='$tid'");
		$num_unapproved_posts = 0;
		while($post = $db->fetch_array($query))
		{
			// Count the post counts for each user to be subtracted
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
			// Remove attachments
			remove_attachments($post['pid']);
	
			// If the post is unapproved, count it!
			if($post['visible'] == 0)
			{
				$num_unapproved_posts++;
			}
		}
		// Remove post count from users
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
		// Delete posts and their attachments
		if($pids)
		{
			$pids .= "0";
			$db->query("DELETE FROM ".TABLE_PREFIX."posts WHERE pid IN ($pids)");
			$db->query("DELETE FROM ".TABLE_PREFIX."attachments WHERE pid IN ($pids)");
		}
		// Get thread info
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

		// Delete threads, redirects, favorites, polls, and poll votes
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
		$pollarray = array(
			"poll" => '',
			);
		$db->update_query(TABLE_PREFIX."threads", $pollarray, "poll='$pid'");
		
		return true;
	}

	/**
	 * Approve a thread
	 *
	 * @param int Thread ID of the thread
	 * @return boolean true
	 */
	function approve_thread($tid)
	{
		global $db, $cache;

		// Update unapproved post/thread numbers
		$db->query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts-1 WHERE tid='$tid'");
		$db->query("UPDATE ".TABLE_PREFIX."forums SET unapprovedthreads=unapprovedthreads-1, unapprovedposts=unapprovedposts-1 WHERE fid='$fid'");

		$approve = array(
			"visible" => 1,
			);
		$db->update_query(TABLE_PREFIX."threads", $approve, "tid='$tid'");
		$db->update_query(TABLE_PREFIX."posts", $approve, "tid='$tid' AND replyto='0'", 1);

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

		// Update unapproved post/thread numbers
		$db->query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts+1 WHERE tid='$tid'");
		$db->query("UPDATE ".TABLE_PREFIX."forums SET unapprovedthreads=unapprovedthreads+1, unapprovedposts=unapprovedposts+1 WHERE fid='$fid'");

		$unapprove = array(
			"visible" => 0,
			);
		$db->update_query(TABLE_PREFIX."threads", $unapprove, "tid='$tid'");
		$db->update_query(TABLE_PREFIX."posts", $unapprove, "tid='$tid' AND replyto='0'", 1);

		$cache->updatestats();
		updateforumcount($fid);

		return true;
	}

	/**
	 * Delete a specific post
	 *
	 * @param int Post ID
	 * @return boolean true
	 */
	function delete_post($pid)
	{
		global $db, $cache, $plugins;

		// Get pid, uid, fid, tid, visibility, forum post count status of post
		$query = $db->query("SELECT p.pid, p.uid, p.fid, p.tid, p.visible, f.usepostcounts FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid) WHERE p.pid='$pid'");
		$post = $db->fetch_array($query);
		// If post counts enabled in this forum, remove 1
		if($post['usepostcounts'] != "no")
		{
			$db->query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum-1 WHERE uid='".$post['uid']."'");
		}
		// Delete the post
		$db->query("DELETE FROM ".TABLE_PREFIX."posts WHERE pid='$pid'");
		// Remove attachments
		remove_attachments($pid);
	
		// Update unapproved post count
		if($post['visible'] == 0)
		{
			$db->query("UPDATE ".TABLE_PREFIX."forums SET unapprovedposts=unapprovedposts-1 WHERE fid='$post[fid]'");
			$db->query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts-1 WHERE tid='$post[tid]'");
		}
		$plugins->run_hooks("delete_post", $post['tid']);
		$cache->updatestats();
		updatethreadcount($post['tid']);

		return true;
	}

	/**
	 * Merge posts within thread
	 *
	 * @param array Post IDs to be merged
	 * @param int Thread ID
	 * @return boolean true
	 */
	function merge_posts($pids, $tid)
	{
		global $db;

		// Put the pids into a usable format
		$comma = $pidin = '';
		foreach($pids as $pid => $yes)
		{
			if($yes == "yes")
			{
				$pidin .= $comma.$pid;
				$comma = ",";
				$plist[] = $pid;
			}
		}
		$first = 1;
		// Get the messages to be merged
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid' AND pid IN($pidin) ORDER BY dateline ASC");
		$num_unapproved_posts = 0;
		$message = '';
		while($post = $db->fetch_array($query))
		{
			if($first == 1)
			{ // all posts will be merged into this one
				$masterpid = $post['pid'];
				$message = $post['message'];
				$first = 0;
			}
			else
			{ // these are the selected posts
				$message .= "[hr]$post[message]";

				// If the post is unapproved, count it
				if($post['visible'] == 0)
				{
					$num_unapproved_posts++;
				}
			}
		}
		
		$fid = $post['fid'];

		// Update the message
		$mergepost = array(
			"message" => addslashes($message),
			);
		$db->update_query(TABLE_PREFIX."posts", $mergepost, "pid='$masterpid'");
		$db->query("DELETE FROM ".TABLE_PREFIX."posts WHERE pid IN($pidin) AND pid!='$masterpid'");
		// Update pid for attachments
		$mergepost2 = array(
			"pid" => $masterpid,
			);
		$db->update_query(TABLE_PREFIX."posts", $mergepost2, "pid IN($pidin)");
		$db->update_query(TABLE_PREFIX."attachments", $mergepost2, "pid IN($pidin)");

		// Update unapproved posts count
		if($num_unapproved_posts > 0)
		{
			$db->query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts-$num_unapproved_posts WHERE tid='$tid'");
			$db->query("UPDATE ".TABLE_PREFIX."forums SET unapprovedposts=unapprovedposts-$num_unapproved_posts WHERE fid='$fid'");
		}

		updatethreadcount($tid);
		updateforumcount($fid);

		return true;
	}

	/**
	 * Move/copy thread
	 *
	 * @param int Thread to be moved
	 * @param int Destination forum
	 * @param string Method of movement (redirect, copy, move)
	 * @return boolean true
	 */
	function move_thread($tid, $new_fid, $method="redirect")
	{
		global $db, $plugins;
		$query = $db->simple_select(TABLE_PREFIX."threads", "*", "tid='$tid'");
		$thread = $db->fetch_array($query);
		$fid = $thread['fid'];
		switch($method)
		{
			case "redirect": // move (and leave redirect) thread
				$plugins->run_hooks("moderation_do_move_redirect");
	
				$changefid = array(
					"fid" => $new_fid,
					);
				$db->update_query(TABLE_PREFIX."threads", $changefid, "tid='$tid'");
				$db->update_query(TABLE_PREFIX."posts", $changefid, "tid='$tid'");
				$threadarray = array(
					"fid" => $thread['fid'],
					"subject" => addslashes($thread['subject']),
					"icon" => $thread['icon'],
					"uid" => $thread['uid'],
					"username" => addslashes($thread['username']),
					"dateline" => $thread['dateline'],
					"lastpost" => $thread['lastpost'],
					"lastposter" => addslashes($thread['lastposter']),
					"views" => $thread['views'],
					"replies" => $thread['replies'],
					"closed" => "moved|$tid",
					"sticky" => $thread['sticky'],
					"visible" => $thread['visible'],
					);
				$db->insert_query(TABLE_PREFIX."threads", $threadarray);
	
				logmod($modlogdata, $lang->thread_moved);
 				break;
			case "copy":// copy thread
				// we need to add code to copy attachments(?), polls, etc etc here
				$threadarray = array(
					"fid" => $new_fid,
					"subject" => addslashes($thread['subject']),
					"icon" => $thread['icon'],
					"uid" => $thread['uid'],
					"username" => addslashes($thread['username']),
					"dateline" => $thread['dateline'],
					"lastpost" => $thread['lastpost'],
					"lastposter" => addslashes($thread['lastposter']),
					"views" => $thread['views'],
					"replies" => $thread['replies'],
					"closed" => $thread['closed'],
					"sticky" => $thread['sticky'],
					"visible" => $thread['visible'],
					"unapprovedposts" => $thread['unapprovedposts'],
					);
				$plugins->run_hooks("moderation_do_move_copy");
				$db->insert_query(TABLE_PREFIX."threads", $threadarray);
				$newtid = $db->insert_id();
				$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid'");
				$postsql = '';
				while($post = $db->fetch_array($query))
				{
					if($postssql != '')
					{
						$postssql .= ", ";
					}
					$post['message'] = addslashes($post['message']);
					$postssql .= "('$newtid','$new_fid','$post[subject]','$post[icon]','$post[uid]','$post[username]','$post[dateline]','$post[message]','$post[ipaddress]','$post[includesig]','$post[smilieoff]','$post[edituid]','$post[edittime]','$post[visible]')";
				}
				$db->query("INSERT INTO ".TABLE_PREFIX."posts (tid,fid,subject,icon,uid,username,dateline,message,ipaddress,includesig,smilieoff,edituid,edittime,visible) VALUES $postssql");
				logmod($modlogdata, $lang->thread_copied);
	
				update_first_post($newtid);
	
				$the_thread = $newtid;
				break;
			default:
			case "move": // plain move thread
				$plugins->run_hooks("moderation_do_move_simple");
	
				$sqlarray = array(
					"fid" => $new_fid,
					);
				$db->update_query(TABLE_PREFIX."threads", $sqlarray, "tid='$tid'");
				$db->update_query(TABLE_PREFIX."posts", $sqlarray, "tid='$tid'");
				logmod($modlogdata, $lang->thread_moved);
				break;
		}
		// Update unapproved threads/post counter
		$query = $db->query("SELECT COUNT(*) AS count FROM ".TABLE_PREFIX."posts WHERE tid='$the_thread' AND visible='0'");
		$unapproved_posts = $db->fetch_array($query);
		$unapproved_posts = intval($unapproved_posts['count']);
		if($thread['visible'] == 0)
		{
			$unapproved_threads = 1;
		}
		else
		{
			$unapproved_threads = 0;
		}
		if($unapproved_posts || $unapproved_threads)
		{
			if($method != "copy")
			{
				$db->query("UPDATE ".TABLE_PREFIX."forums SET unapprovedposts=unapprovedposts-$unapproved_posts, unapprovedthreads=unapprovedthreads-$unapproved_threads WHERE fid='$fid'");
			}
			$db->query("UPDATE ".TABLE_PREFIX."forums SET unapprovedposts=unapprovedposts+$unapproved_posts, unapprovedthreads=unapprovedthreads+$unapproved_threads WHERE fid='$new_fid'");
		}

		$query = $db->query("SELECT COUNT(p.pid) AS posts, u.uid FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE tid='$tid' GROUP BY u.uid ORDER BY posts DESC");
		while($posters = $db->fetch_array($query))
		{
			if($method == "copy" && $newforum['usepostcounts'] != "no")
			{
				$pcount = "+$posters[posts]";
			}
			if($method != "copy" && ($newforum['usepostcounts'] != "no" && $forum['usepostcounts'] == "no"))
			{
				$pcount = "+$posters[posts]";
			}
			if($method != "copy" && ($newforum['usepostcounts'] == "no" && $forum['usepostcounts'] != "no"))
			{
				$pcount = "-$posters[posts]";
			}
			$db->query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum$pcount WHERE uid='$posters[uid]')");
		}
		updateforumcount($new_fid);
		updateforumcount($fid);
	}

}
?>