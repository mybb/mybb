<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

class Moderation
{
	/**
	 * Close one or more threads
	 *
	 * @param array Thread IDs
	 * @return boolean true
	 */
	function close_threads($tids)
	{
		global $db;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}
		$tid_list = implode(",", $tids);

		$openthread = array(
			"closed" => "yes",
			);
		$db->update_query(TABLE_PREFIX."threads", $openthread, "tid IN ($tid_list)");

		return true;
	}

	/**
	 * Open one or more threads
	 *
	 * @param int Thread IDs
	 * @return boolean true
	 */

	function open_threads($tids)
	{
		global $db;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}
		$tid_list = implode(",", $tids);

		$closethread = array(
			"closed" => "no",
			);
		$db->update_query(TABLE_PREFIX."threads", $closethread, "tid IN ($tid_list)");

		return true;
	}

	/**
	 * Stick one or more threads
	 *
	 * @param int Thread IDs
	 * @return boolean true
	 */
	function stick_threads($tids)
	{
		global $db;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}
		$tid_list = implode(",", $tids);

		$stickthread = array(
			"sticky" => 1,
			);
		$db->update_query(TABLE_PREFIX."threads", $stickthread, "tid IN ($tid_list)");

		return true;
	}

	/**
	 * Unstick one or more thread
	 *
	 * @param int Thread IDs
	 * @return boolean true
	 */
	function unstick_threads($tids)
	{
		global $db;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}
		$tid_list = implode(",", $tids);

		$unstickthread = array(
			"sticky" => 0,
			);
		$db->update_query(TABLE_PREFIX."threads", $unstickthread, "tid IN ($tid_list)");

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
		if(is_array($fids))
		{
			foreach($fids as $fid)
			{
				update_forum_count($fid);
			}
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
		$query = $db->query("
			SELECT p.pid, p.uid, p.visible, f.usepostcounts
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
			WHERE p.tid='$tid'
		");
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

		// Delete threads, redirects, favorites, polls, and poll votes
		$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
		$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE closed='moved|$tid'");
		$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE tid='$tid'");
		$db->query("DELETE FROM ".TABLE_PREFIX."polls WHERE tid='$tid'");
		$db->query("DELETE FROM ".TABLE_PREFIX."pollvotes WHERE pid='".$thread['poll']."'");
		$cache->updatestats();
		update_forum_count($thread['fid']);
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
	 * Approve one or more threads
	 *
	 * @param array Thread IDs
	 * @param int Forum ID
	 * @return boolean true
	 */
	function approve_threads($tids, $fid)
	{
		global $db, $cache;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}
		$tid_list = implode(",", $tids);

		$approve = array(
			"visible" => 1,
			);
		$db->update_query(TABLE_PREFIX."threads", $approve, "tid IN ($tid_list)");
		$db->update_query(TABLE_PREFIX."posts", $approve, "tid IN ($tid_list) AND replyto='0'", 1);
		
		// Update stats
		$cache->updatestats();
		update_forum_count($fid);
		$db->query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts-1 WHERE tid IN ($tid_list)");

		return true;
	}

	/**
	 * Unapprove one or more threads
	 *
	 * @param array Thread IDs
	 * @param int Forum ID
	 * @return boolean true
	 */
	function unapprove_threads($tids, $fid)
	{
		global $db, $cache;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}
		$tid_list = implode(",", $tids);

		$approve = array(
			"visible" => 0,
			);
		$db->update_query(TABLE_PREFIX."threads", $approve, "tid IN ($tid_list)");
		$db->update_query(TABLE_PREFIX."posts", $approve, "tid IN ($tid_list) AND replyto='0'", 1);

		// Update stats
		$cache->updatestats();
		update_forum_count($fid);
		$db->query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts+1 WHERE tid IN ($tid_list)");

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
		$query = $db->query("
			SELECT p.pid, p.uid, p.fid, p.tid, p.visible, f.usepostcounts
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
			WHERE p.pid='$pid'
		");
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
		update_thread_count($post['tid']);
		update_forum_count($post['fid']);

		return true;
	}

	/**
	 * Merge posts within thread
	 *
	 * @param array Post IDs to be merged
	 * @param int Thread ID
	 * @return boolean true
	 */
	function merge_posts($pids, $tid, $sep="new_line")
	{
		global $db;

		$pidin = implode(",", $pids);
		$first = 1;
		// Get the messages to be merged
		$query = $db->query("
			SELECT *
			FROM ".TABLE_PREFIX."posts
			WHERE tid='$tid' AND pid IN($pidin)
			ORDER BY dateline ASC
		");
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
				if($sep == "new_line")
				{
					$message .= "\n\n $post[message]";
				}
				else
				{
					$message .= "[hr]$post[message]";
				}
			}
		}
		
		$fid = $post['fid'];

		// Update the message
		$mergepost = array(
			"message" => $db->escape_string($message),
			);
		$db->update_query(TABLE_PREFIX."posts", $mergepost, "pid='$masterpid'");
		$db->query("DELETE FROM ".TABLE_PREFIX."posts WHERE pid IN($pidin) AND pid!='$masterpid'");
		// Update pid for attachments
		$mergepost2 = array(
			"pid" => $masterpid,
			);
		$db->update_query(TABLE_PREFIX."posts", $mergepost2, "pid IN($pidin)");
		$db->update_query(TABLE_PREFIX."attachments", $mergepost2, "pid IN($pidin)");

		// Update stats
		update_thread_count($tid);
		update_forum_count($fid);

		return true;
	}

	/**
	 * Move/copy thread
	 *
	 * @param int Thread to be moved
	 * @param int Destination forum
	 * @param string Method of movement (redirect, copy, move)
	 * @param int Expiry timestamp for redirect
	 * @return int Thread ID
	 */
	function move_thread($tid, $new_fid, $method="redirect", $redirect_expire=0)
	{
		global $db, $plugins;

		// Get thread info
		$query = $db->simple_select(TABLE_PREFIX."threads", "*", "tid='$tid'");
		$thread = $db->fetch_array($query);
		$fid = $thread['fid'];
		switch($method)
		{
			case "redirect": // move (and leave redirect) thread
				$plugins->run_hooks("moderation_do_move_redirect");
	
				$db->delete_query(TABLE_PREFIX."threads", "closed='moved|$tid' AND fid='$moveto'");
				$changefid = array(
					"fid" => $new_fid,
					);
				$db->update_query(TABLE_PREFIX."threads", $changefid, "tid='$tid'");
				$db->update_query(TABLE_PREFIX."posts", $changefid, "tid='$tid'");
				$threadarray = array(
					"fid" => $thread['fid'],
					"subject" => $db->escape_string($thread['subject']),
					"icon" => $thread['icon'],
					"uid" => $thread['uid'],
					"username" => $db->escape_string($thread['username']),
					"dateline" => $thread['dateline'],
					"lastpost" => $thread['lastpost'],
					"lastposter" => $db->escape_string($thread['lastposter']),
					"views" => 0,
					"replies" => 0,
					"closed" => "moved|$tid",
					"sticky" => $thread['sticky'],
					"visible" => $thread['visible'],
					);
				$db->insert_query(TABLE_PREFIX."threads", $threadarray);
				if($redirect_expire)
				{
					$redirect_tid = $db->insert_id();
					$this->expire_thread($redirect_tid, $redirect_expire);
				}
 				break;
			case "copy":// copy thread
				// we need to add code to copy attachments(?), polls, etc etc here
				$threadarray = array(
					"fid" => $new_fid,
					"subject" => $db->escape_string($thread['subject']),
					"icon" => $thread['icon'],
					"uid" => $thread['uid'],
					"username" => $db->escape_string($thread['username']),
					"dateline" => $thread['dateline'],
					"lastpost" => $thread['lastpost'],
					"lastposter" => $db->escape_string($thread['lastposter']),
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
					$post['message'] = $db->escape_string($post['message']);
					$postssql .= "('$newtid','$new_fid','$post[subject]','$post[icon]','$post[uid]','$post[username]','$post[dateline]','$post[message]','$post[ipaddress]','$post[includesig]','$post[smilieoff]','$post[edituid]','$post[edittime]','$post[visible]')";
				}
				$db->query("INSERT INTO ".TABLE_PREFIX."posts (tid,fid,subject,icon,uid,username,dateline,message,ipaddress,includesig,smilieoff,edituid,edittime,visible) VALUES $postssql");
	
				update_first_post($newtid);
				update_thread_count($newtid);
	
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
				break;
		}

		// Do post count changes if changing between countable and non-countable forums
		$query = $db->query("
			SELECT COUNT(p.pid) AS posts, u.uid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE tid='$tid'
			GROUP BY u.uid
			ORDER BY posts DESC
		");
		while($posters = $db->fetch_array($query))
		{
			if($method == "copy" && $newforum['usepostcounts'] != "no")
			{
				$pcount = "+$posters[posts]";
			}
			elseif($method != "copy" && ($newforum['usepostcounts'] != "no" && $forum['usepostcounts'] == "no"))
			{
				$pcount = "+$posters[posts]";
			}
			elseif($method != "copy" && ($newforum['usepostcounts'] == "no" && $forum['usepostcounts'] != "no"))
			{
				$pcount = "-$posters[posts]";
			}
			if(!empty($pcount))
			{
				$db->query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum$pcount WHERE uid='$posters[uid]'");
			}
		}

		// Update forum counts
		update_forum_count($new_fid);
		update_forum_count($fid);

		if(isset($newtid))
		{
			return $newtid;
		}
		else
		{
			return $tid;
		}
	}

	/**
	 * Merge one thread into another
	 *
	 * @param int Thread that will be merged into destination
	 * @param int Destination thread
	 * @param string New thread subject
	 * @return boolean true
	 */
	function merge_threads($mergetid, $tid, $subject)
	{
		global $db, $mybb, $mergethread, $thread;

		if(!isset($mergethread['tid']) || $mergethread['tid'] != $mergetid)
		{
			$mergetid = intval($mergetid);
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='".intval($mergetid)."'");
			$mergethread = $db->fetch_array($query);
		}
		if(!isset($thread['tid']) || $thread['tid'] != $tid)
		{
			$tid = intval($tid);
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='".intval($tid)."'");
			$thread = $db->fetch_array($query);
		}
		$pollsql = '';
		if($mergethread['poll'])
		{
			$pollsql = ", poll='$mergethread[poll]'";
			$sqlarray = array(
				"tid" => $tid,
				);
			$db->update_query(TABLE_PREFIX."polls", $sqlarray, "tid='".intval($mergethread['tid'])."'");
		}
		else
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE poll='$mergethread[poll]' AND tid!='".intval($mergetid)."'");
			$pollcheck = $db->fetch_array($query);
			if(!$pollcheck['poll'])
			{
				$db->query("DELETE FROM ".TABLE_PREFIX."polls WHERE pid='$mergethread[poll]'");
				$db->query("DELETE FROM ".TABLE_PREFIX."pollvotes WHERE pid='$mergethread[poll]'");
			}
		}

		$subject = $db->escape_string($subject);

		$sqlarray = array(
			"tid" => $tid,
			"fid" => $thread['fid'],
			);
		$db->update_query(TABLE_PREFIX."posts", $sqlarray, "tid='$mergetid'");
		$db->query("UPDATE ".TABLE_PREFIX."threads SET subject='$subject' $pollsql WHERE tid='$tid'");
		$sqlarray = array(
			"closed" => "moved|$tid",
			);
		$db->update_query(TABLE_PREFIX."threads", $sqlarray, "closed='moved|$mergetid'");
		$sqlarray = array(
			"tid" => $tid,
			);
		$db->update_query(TABLE_PREFIX."favorites", $sqlarray, "tid='$mergetid'");
		update_first_post($tid);

		$this->delete_thread($mergetid);
		update_thread_count($tid);
		if($thread['fid'] != $mergethread['fid'])
		{
			update_forum_count($mergethread['fid']);
		}
		update_forum_count($fid);

		return true;
	}

	/**
	 * Split posts into a new/existing thread
	 *
	 * @param array PIDs of posts to split
	 * @param int Original thread
	 * @param int Destination forum
	 * @param string New thread subject
	 * @param int TID if moving into existing thread
	 * @return int New thread ID
	 */
	function split_posts($pids, $tid, $moveto, $newsubject, $destination_tid=0)
	{
		global $db, $thread;

		if(!isset($thread['tid']) || $thread['tid'] != $tid)
		{
			$tid = intval($tid);
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='".intval($tid)."'");
			$thread = $db->fetch_array($query);
		}

		// Create the new thread
		$newsubject = $db->escape_string($newsubject);
		$query = array(
			"fid" => $moveto,
			"subject" => $newsubject,
			"icon" => $thread['icon'],
			"uid" => $thread['uid'],
			"username" => $thread['username'],
			"dateline" => $thread['dateline'],
			"lastpost" => $thread['lastpost'],
			"lastposter" => $thread['lastposter'],
			"replies" => count($pids)-1,
			"visible" => "1",
		);
		$db->insert_query(TABLE_PREFIX."threads", $query);
		$newtid = $db->insert_id();

		// move the selected posts over
		$pids_list = implode(",", $pids);
		$sqlarray = array(
			"tid" => $newtid,
			"fid" => $moveto,
			);
		$db->update_query(TABLE_PREFIX."posts", $sqlarray, "pid IN ($pids_list)");

		// adjust user post counts accordingly
		$query = $db->query("SELECT usepostcounts FROM ".TABLE_PREFIX."forums WHERE fid='$thread[fid]'");
		$oldusepcounts = $db->fetch_field($query, "usepostcounts");
		$query = $db->query("SELECT usepostcounts FROM ".TABLE_PREFIX."forums WHERE fid='$moveto'");
		$newusepcounts = $db->fetch_field($query, "usepostcounts");
		$query = $db->query("SELECT COUNT(p.pid) AS posts, u.uid FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE tid='$tid' GROUP BY u.uid ORDER BY posts DESC");
		while($posters = $db->fetch_array($query))
		{
			if($oldusepcounts == "yes" && $newusepcounts == "no")
			{
				$pcount = "-$posters[posts]";
			}
			elseif($oldusepcounts == "no" && $newusepcounts == "yes")
			{
				$pcount = "+$posters[posts]";
			}

			if(!empty($pcount))
			{
				$db->query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum$pcount WHERE uid='$posters[uid]'");
			}
		}

		// Update the subject of the first post in the new thread
		$query = $db->query("SELECT pid FROM ".TABLE_PREFIX."posts WHERE tid='$newtid' ORDER BY dateline ASC LIMIT 1");
		$newthread = $db->fetch_array($query);
		$sqlarray = array(
			"subject" => $newsubject,
			);
		$db->update_query(TABLE_PREFIX."posts", $sqlarray, "pid='$newthread[pid]'");

		// Update the subject of the first post in the old thread
		$query = $db->query("SELECT pid FROM ".TABLE_PREFIX."posts WHERE tid='$tid' ORDER BY dateline ASC LIMIT 1");
		$oldthread = $db->fetch_array($query);
		$sqlarray = array(
			"subject" => $db->escape_string($thread['subject']),
			);
		$db->update_query(TABLE_PREFIX."posts", $sqlarray, "pid='$oldthread[pid]'");

		update_first_post($tid);
		update_first_post($newtid);
		update_thread_count($tid);
		update_thread_count($newtid);
		if($moveto != $thread['fid'])
		{
			update_forum_count($moveto);
		}
		update_forum_count($thread['fid']);

		// Merge new thread with destination thread if specified
		if($destination_tid)
		{
			$this->merge_threads($newtid, $destination_tid, $subject);
		}

		return $newtid;
	}

	/**
	 * Move multiple threads to new forum
	 *
	 * @param array Thread IDs
	 * @param int Destination forum
	 * @return boolean true
	 */
	function move_threads($tids, $moveto)
	{
		global $db;

		$tid_list = implode(",", $tids);

		$sqlarray = array(
			"fid" => $moveto,
			);
		$db->update_query(TABLE_PREFIX."threads", $sqlarray, "tid IN ($tid_list)");
		$db->update_query(TABLE_PREFIX."posts", $sqlarray, "tid IN ($tid_list)");

		update_forum_count($moveto);
		update_forum_count($fid);

		return true;
	}

	/**
	 * Approve multiple posts
	 *
	 * @param array PIDs
	 * @param int Thread ID
	 * @param int Forum ID
	 * @return boolean true
	 */
	function approve_posts($pids, $tid, $fid)
	{
		global $db, $cache;

		$where = "pid IN (".implode(",", $pids).")";

		// Make visible
		$approve = array(
			"visible" => 1,
			);
		$db->update_query(TABLE_PREFIX."posts", $approve, $where);

		// If this is the first post of the thread, also approve the thread
		$query = $db->simple_select(TABLE_PREFIX."posts", "tid", "({$where}) AND replyto='0'", array("limit" => 1));
		while($post = $db->fetch_array($query))
		{
			$db->update_query(TABLE_PREFIX."threads", $approve, "tid='$post[tid]'");
		}

		update_forum_count($fid);
		update_thread_count($tid);
		$cache->updatestats();

		return true;
	}

	/**
	 * Unapprove multiple posts
	 *
	 * @param array PIDs
	 * @param int Thread ID
	 * @param int Forum ID
	 * @return boolean true
	 */
	function unapprove_posts($pids, $tid, $fid)
	{
		global $db, $cache;

		$where = "pid IN (".implode(",", $pids).")";

		// Make visible
		$unapprove = array(
			"visible" => 0,
			);
		$db->update_query(TABLE_PREFIX."posts", $unapprove, $where);

		// If this is the first post of the thread, also unapprove the thread
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE ($where) AND replyto='0' LIMIT 1");
		while($post = $db->fetch_array($query))
		{
			$db->update_query(TABLE_PREFIX."threads", $unapprove, "tid='$post[tid]'");
		}

		update_forum_count($fid);
		update_thread_count($tid);
		$cache->updatestats();

		return true;
	}

	/**
	 * Change thread subject
	 *
	 * @param mixed Thread ID(s)
	 * @param string Format of new subject (with {subject})
	 * @return boolean true
	 */
	function change_thread_subject($tids, $format)
	{
		global $db;

		// Get tids into list
		if(!is_array($tids))
		{
			$tids = array(intval($tids));
		}
		$tid_list = implode(",", $tids);

		// Get original subject
		$query = $db->simple_select(TABLE_PREFIX."threads", "subject, tid", "tid IN ($tid_list)");
		while($thread = $db->fetch_array($query))
		{
			// Update threads and first posts with new subject
			$new_subject = array(
				"subject" => $db->escape_string(str_replace('{subject}', $thread['subject'], $format))
				);
			$db->update_query(TABLE_PREFIX."threads", $new_subject, "tid='$thread[tid]'", 1);
			$db->update_query(TABLE_PREFIX."posts", $new_subject, "tid='$thread[tid]' AND replyto='0'", 1);
		}
	
		return true;
	}

	/**
	 * Add thread expiry
	 *
	 * @param int Thread ID
	 * @param int Timestamp when the thread is deleted
	 * @return boolean true
	 */
	function expire_thread($tid, $deletetime)
	{
		global $db;

		$update_thread = array(
			"deletetime" => intval($deletetime)
			);
		$db->update_query(TABLE_PREFIX."threads", $update_thread, "tid='{$tid}'");

		return true;
	}

	/**
	 * Toggle post visibility (approved/unapproved)
	 *
	 * @param array Post IDs
	 * @param int Thread ID
	 * @param int Forum ID
	 * @return boolean true
	 */
	function toggle_post_visibility($pids, $tid, $fid)
	{
		global $db;
		$pid_list = implode(',', $pids);
		$query = $db->simple_select(TABLE_PREFIX."posts", 'pid, visible', "pid IN ($pid_list)");
		while($post = $db->fetch_array($query))
		{
			if($post['visible'] == 1)
			{
				$unapprove[] = $post['pid'];
			}
			else
			{
				$approve[] = $post['pid'];
			}
		}
		if(is_array($unapprove))
		{
			$this->unapprove_posts($unapprove, $tid, $fid);
		}
		if(is_array($approve))
		{
			$this->approve_posts($approve, $tid, $fid);
		}
		return true;
	}

	/**
	 * Toggle thread visibility (approved/unapproved)
	 *
	 * @param array Thread IDs
	 * @param int Forum ID
	 * @return boolean true
	 */
	function toggle_thread_visibility($tids, $fid)
	{
		global $db;
		$tid_list = implode(',', $tids);
		$query = $db->simple_select(TABLE_PREFIX."threads", 'tid, visible', "tid IN ($tid_list)");
		while($thread = $db->fetch_array($query))
		{
			if($thread['visible'] == 1)
			{
				$unapprove[] = $thread['tid'];
			}
			else
			{
				$approve[] = $thread['tid'];
			}
		}
		if(is_array($unapprove))
		{
			$this->unapprove_threads($unapprove, $fid);
		}
		if(is_array($approve))
		{
			$this->approve_threads($approve, $fid);
		}
		return true;
	}

	/**
	 * Toggle threads open/closed
	 *
	 * @param array Thread IDs
	 * @return boolean true
	 */
	function toggle_thread_status($tids)
	{
		global $db;
		$tid_list = implode(',', $tids);
		$query = $db->simple_select(TABLE_PREFIX."threads", 'tid, closed', "tid IN ($tid_list)");
		while($thread = $db->fetch_array($query))
		{
			if($thread['closed'] == "yes")
			{
				$open[] = $thread['tid'];
			}
			elseif($thread['closed'] == '')
			{
				$close[] = $thread['tid'];
			}
		}
		if(is_array($open))
		{
			$this->open_threads($open);
		}
		if(is_array($close))
		{
			$this->close_threads($close);
		}
		return true;
	}
}
?>