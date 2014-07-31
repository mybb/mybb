<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
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
		global $db, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$plugins->run_hooks("class_moderation_close_threads", $tids);

		$tid_list = implode(',', $tids);

		$openthread = array(
			"closed" => 1,
		);
		$db->update_query("threads", $openthread, "tid IN ($tid_list) AND closed NOT LIKE 'moved|%'");

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
		global $db, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$plugins->run_hooks("class_moderation_open_threads", $tids);

		$tid_list = implode(',', $tids);

		$closethread = array(
			"closed" => 0,
		);
		$db->update_query("threads", $closethread, "tid IN ($tid_list)");

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
		global $db, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$plugins->run_hooks("class_moderation_stick_threads", $tids);

		$tid_list = implode(',', $tids);

		$stickthread = array(
			"sticky" => 1,
		);
		$db->update_query("threads", $stickthread, "tid IN ($tid_list)");

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
		global $db, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$plugins->run_hooks("class_moderation_unstick_threads", $tids);

		$tid_list = implode(',', $tids);

		$unstickthread = array(
			"sticky" => 0,
		);
		$db->update_query("threads", $unstickthread, "tid IN ($tid_list)");

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
		global $db, $plugins;

		$plugins->run_hooks("class_moderation_remove_redirects", $tid);

		// Delete the redirects
		$tid = intval($tid);
		$db->delete_query("threads", "closed='moved|$tid'");

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

		$tid = intval($tid);
		$plugins->run_hooks("class_moderation_delete_thread_start", $tid);
		
		$thread = get_thread($tid);

		$userposts = array();

		// Find the pid, uid, visibility, and forum post count status
		$query = $db->query("
			SELECT p.pid, p.uid, p.visible, f.usepostcounts
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
			WHERE p.tid='{$tid}'
		");
		$pids = array();
		$num_unapproved_posts = $num_approved_posts = 0;
		while($post = $db->fetch_array($query))
		{
			$pids[] = $post['pid'];
			$usepostcounts = $post['usepostcounts'];

			if(!function_exists("remove_attachments"))
			{
				require MYBB_ROOT."inc/functions_upload.php";
			}

			// Remove attachments
			remove_attachments($post['pid']);

			// If the post is unapproved, count it!
			if($post['visible'] == 0 || $thread['visible'] == 0)
			{
				$num_unapproved_posts++;
			}
			else
			{
				$num_approved_posts++;
				
				// Count the post counts for each user to be subtracted
				++$userposts[$post['uid']];
			}
		}

		// Remove post count from users
		if($usepostcounts != 0)
		{
			if(is_array($userposts))
			{
				foreach($userposts as $uid => $subtract)
				{
					$db->update_query("users", array('postnum' => "postnum-{$subtract}"), "uid='".intval($uid)."'", 1, true);
				}
			}
		}
		// Delete posts and their attachments
		if($pids)
		{
			$pids = implode(',', $pids);
			$db->delete_query("posts", "pid IN ($pids)");
			$db->delete_query("attachments", "pid IN ($pids)");
			$db->delete_query("reportedposts", "pid IN ($pids)");
		}

		// Implied counters for unapproved thread
		if($thread['visible'] == 0)
 		{
 			$num_unapproved_posts += $num_approved_posts;
 		}

		// Delete threads, redirects, subscriptions, polls, and poll votes
		$db->delete_query("threads", "tid='$tid'");
		$db->delete_query("threads", "closed='moved|$tid'");
		$db->delete_query("threadsubscriptions", "tid='$tid'");
		$db->delete_query("polls", "tid='$tid'");
		$db->delete_query("pollvotes", "pid='".$thread['poll']."'");
		$db->delete_query("threadsread", "tid='$tid'");
		$db->delete_query("threadratings", "tid='$tid'");

		$updated_counters = array(
			"posts" => "-{$num_approved_posts}",
			"unapprovedposts" => "-{$num_unapproved_posts}"
		);

		if($thread['visible'] == 1)
		{
			$updated_counters['threads'] = -1;
		}
		else
		{
			$updated_counters['unapprovedthreads'] = -1;
		}

		if(substr($thread['closed'], 0, 5) != "moved")
		{
			// Update forum count
			update_forum_counters($thread['fid'], $updated_counters);
		}

		$plugins->run_hooks("class_moderation_delete_thread", $tid);

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
		global $db, $plugins;

		$pid = intval($pid);

		$plugins->run_hooks("class_moderation_delete_poll", $pid);

		$db->delete_query("polls", "pid='$pid'");
		$db->delete_query("pollvotes", "pid='$pid'");
		$pollarray = array(
			'poll' => '0',
		);
		$db->update_query("threads", $pollarray, "poll='$pid'");

		return true;
	}

	/**
	 * Approve one or more threads
	 *
	 * @param array Thread IDs
	 * @return boolean true
	 */
	function approve_threads($tids)
	{
		global $db, $cache, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		foreach($tids as $tid)
		{
			$thread = get_thread($tid);
			if($thread['visible'] == 1 || !$thread['tid'])
			{
				continue;
			}
			$tid_list[] = $thread['tid'];

			$forum = get_forum($thread['fid']);
			
			$forum_counters[$forum['fid']]['num_threads']++;
			$forum_counters[$forum['fid']]['num_posts'] += $thread['replies']+1; // Remove implied visible from count

			if($forum['usepostcounts'] != 0)
			{
				// On approving thread restore user post counts
				$query = $db->simple_select("posts", "COUNT(pid) as posts, uid", "tid='{$tid}' AND (visible='1' OR pid='{$thread['firstpost']}') AND uid > 0 GROUP BY uid");
				while($counter = $db->fetch_array($query))
				{
					$db->update_query("users", array('postnum' => "postnum+{$counter['posts']}"), "uid='".$counter['uid']."'", 1, true);
				}
			}
			$posts_to_approve[] = $thread['firstpost'];
		}
		
		if(is_array($tid_list))
		{
			$tid_moved_list = "";
			$comma = "";
			foreach($tid_list as $tid)
			{
				$tid_moved_list .= "{$comma}'moved|{$tid}'";
				$comma = ",";
			}
			$tid_list = implode(',', $tid_list);
			$approve = array(
				"visible" => 1
			);
			$db->update_query("threads", $approve, "tid IN ($tid_list) OR closed IN ({$tid_moved_list})");
			$db->update_query("posts", $approve, "pid IN (".implode(',', $posts_to_approve).")");

			$plugins->run_hooks("class_moderation_approve_threads", $tids);

			if(is_array($forum_counters))
			{
				foreach($forum_counters as $fid => $counters)
				{
					// Update stats
					$update_array = array(
						"threads" => "+{$counters['num_threads']}",
						"unapprovedthreads" => "-{$counters['num_threads']}",
						"posts" => "+{$counters['num_posts']}",
						"unapprovedposts" => "-{$counters['num_posts']}"
					);
					update_forum_counters($fid, $update_array);
				}
			}
		}
		return true;
	}

	/**
	 * Unapprove one or more threads
	 *
	 * @param array Thread IDs
	 * @return boolean true
	 */
	function unapprove_threads($tids)
	{
		global $db, $cache, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$tid_list = implode(',', $tids);
		$tid_moved_list = "";
		$comma = "";
		foreach($tids as $tid)
		{
			$tid_moved_list .= "{$comma}'moved|{$tid}'";
			$comma = ",";
		}

		foreach($tids as $tid)
		{
			$thread = get_thread($tid);
			$forum = get_forum($thread['fid']);

			if($thread['visible'] == 1)
			{
				$forum_counters[$forum['fid']]['num_threads']++;
				$forum_counters[$forum['fid']]['num_posts'] += $thread['replies']+1; // Add implied invisible to count

				// On unapproving thread update user post counts
				if($forum['usepostcounts'] != 0)
				{
					$query = $db->simple_select("posts", "COUNT(pid) AS posts, uid", "tid='{$tid}' AND (visible='1' OR pid='{$thread['firstpost']}') AND uid > 0 GROUP BY uid");
					while($counter = $db->fetch_array($query))
					{
						$db->update_query("users", array('postnum' => "postnum-{$counter['posts']}"), "uid='".$counter['uid']."'", 1, true);
					}
				}
			}
			$posts_to_unapprove[] = $thread['firstpost'];
		}

		$approve = array(
			"visible" => 0
		);
		$db->update_query("threads", $approve, "tid IN ($tid_list) OR closed IN ({$tid_moved_list})");
		$db->update_query("posts", $approve, "pid IN (".implode(',', $posts_to_unapprove).")");

		$plugins->run_hooks("class_moderation_unapprove_threads", $tids);
		
		if(is_array($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				// Update stats
				$update_array = array(
					"threads" => "-{$counters['num_threads']}",
					"unapprovedthreads" => "+{$counters['num_threads']}",
					"posts" => "-{$counters['num_posts']}",
					"unapprovedposts" => "+{$counters['num_posts']}"
				);
				update_forum_counters($fid, $update_array);
			}
		}

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

		$pid = $plugins->run_hooks("class_moderation_delete_post_start", $pid);
		// Get pid, uid, fid, tid, visibility, forum post count status of post
		$pid = intval($pid);
		$query = $db->query("
			SELECT p.pid, p.uid, p.fid, p.tid, p.visible, f.usepostcounts, t.visible as threadvisible
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
			WHERE p.pid='$pid'
		");
		$post = $db->fetch_array($query);
		// If post counts enabled in this forum and it hasn't already been unapproved, remove 1
		if($post['usepostcounts'] != 0 && $post['visible'] != 0 && $post['threadvisible'] != 0)
		{
			$db->update_query("users", array("postnum" => "postnum-1"), "uid='{$post['uid']}'", 1, true);
		}
		
		if(!function_exists("remove_attachments"))
		{
			require MYBB_ROOT."inc/functions_upload.php";
		}

		// Remove attachments
		remove_attachments($pid);

		// Delete the post
		$db->delete_query("posts", "pid='$pid'");

		// Remove any reports attached to this post
		$db->delete_query("reportedposts", "pid='$pid'");

		$num_unapproved_posts = $num_approved_posts = 0;
		// Update unapproved post count
		if($post['visible'] == 0 || $post['threadvisible'] == 0)
		{
			++$num_unapproved_posts;
		}
		else
		{
			++$num_approved_posts;
		}
		$plugins->run_hooks("class_moderation_delete_post", $post['pid']);

		// Update stats
		$update_array = array(
			"replies" => "-{$num_approved_posts}",
			"unapprovedposts" => "-{$num_unapproved_posts}"
		);
		update_thread_counters($post['tid'], $update_array);

		// Update stats
		$update_array = array(
			"posts" => "-{$num_approved_posts}",
			"unapprovedposts" => "-{$num_unapproved_posts}"
		);

		update_forum_counters($post['fid'], $update_array);

		return true;
	}

	/**
	 * Merge posts within thread
	 *
	 * @param array Post IDs to be merged
	 * @param int Thread ID (Set to 0 if posts from multiple threads are
	 * selected)
	 * @return int ID of the post into which all other posts are merged
	 */
	function merge_posts($pids, $tid=0, $sep="new_line")
	{
		global $db, $plugins;

		// Make sure we only have valid values
		$pids = array_map('intval', $pids);
		$tid = intval($tid);

		$pidin = implode(',', $pids);
		$attachment_count = 0;

		$first = 1;
		// Get the messages to be merged
		$query = $db->query("
			SELECT p.pid, p.uid, p.fid, p.tid, p.visible, p.message, f.usepostcounts, t.visible AS threadvisible, t.replies AS threadreplies, t.firstpost AS threadfirstpost, t.unapprovedposts AS threadunapprovedposts
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
			WHERE p.pid IN($pidin)
			ORDER BY p.dateline ASC
		");
		$num_unapproved_posts = $num_approved_posts = 0;
		$message = '';
		$threads = array();
		while($post = $db->fetch_array($query))
		{
			$threads[$post['tid']] = $post['tid'];
			if($first == 1)
			{ // all posts will be merged into this one
				$masterpid = $post['pid'];
				$message = $post['message'];
				$fid = $post['fid'];
				$mastertid = $post['tid'];
				$first = 0;
			}
			else
			{
			 	// these are the selected posts
				if($sep == "new_line")
				{
					$message .= "\n\n {$post['message']}";
				}
				else
				{
					$message .= "[hr]{$post['message']}";
				}

				if($post['visible'] == 1 && $post['threadvisible'] == 1)
				{
					// Subtract 1 approved post from post's thread
					if(!$thread_counters[$post['tid']]['replies'])
					{
						$thread_counters[$post['tid']]['replies'] = $post['threadreplies'];
					}
					--$thread_counters[$post['tid']]['replies'];
					// Subtract 1 approved post from post's forum
					if(!isset($forum_counters[$post['fid']]['num_posts']))
					{
						$forum_counters[$post['fid']]['num_posts'] = 0;
					}
					--$forum_counters[$post['fid']]['num_posts'];
					// Subtract 1 from user's post count
					if($post['usepostcounts'] != 0)
					{
						// Update post count of the user of the merged posts
						$db->update_query("users", array("postnum" => "postnum-1"), "uid='{$post['uid']}'", 1, true);
					}
				}
				elseif($post['visible'] == 0)
				{
					// Subtract 1 unapproved post from post's thread
					if(!$thread_counters[$post['tid']]['unapprovedposts'])
					{
						$thread_counters[$post['tid']]['unapprovedposts'] = $post['threadunapprovedposts'];
					}
					--$thread_counters[$post['tid']]['unapprovedposts'];
					// Subtract 1 unapproved post from post's forum
					if(!isset($forum_counters[$post['fid']]['unapprovedposts']))
					{
						$forum_counters[$post['fid']]['unapprovedposts'] = 0;
					}
					--$forum_counters[$post['fid']]['unapprovedposts'];
				}
			}
		}

		// Update the message
		$mergepost = array(
			"message" => $db->escape_string($message),
		);
		$db->update_query("posts", $mergepost, "pid = '{$masterpid}'");
		
		// Delete the extra posts
		$db->delete_query("posts", "pid IN({$pidin}) AND pid != '{$masterpid}'");
		// Update pid for attachments
		
		$mergepost2 = array(
			"pid" => $masterpid,
		);
		$db->update_query("attachments", $mergepost2, "pid IN({$pidin})");
		
		// If the first post of a thread is merged out, the thread should be deleted
		$query = $db->simple_select("threads", "tid, fid, visible", "firstpost IN({$pidin}) AND firstpost != '{$masterpid}'");
		while($thread = $db->fetch_array($query))
		{
			$this->delete_thread($thread['tid']);
			// Subtract 1 thread from the forum's stats
			if($thread['visible'])
			{
				if(!isset($forum_counters[$thread['fid']]['threads']))
				{
					$forum_counters[$thread['fid']]['threads'] = 0;
				}
				--$forum_counters[$thread['fid']]['threads'];
			}
			else
			{
				if(!isset($forum_counters[$thread['fid']]['unapprovedthreads']))
				{
					$forum_counters[$thread['fid']]['unapprovedthreads'] = 0;
				}
				--$forum_counters[$thread['fid']]['unapprovedthreads'];
			}
		}

		$arguments = array("pids" => $pids, "tid" => $tid);
		$plugins->run_hooks("class_moderation_merge_posts", $arguments);

		if(is_array($thread_counters))
		{
			foreach($thread_counters as $tid => $counters)
			{
				$db->update_query("threads", $counters, "tid='{$tid}'");

				update_thread_data($tid);
			}
		}
		
		update_thread_data($mastertid);
		
		update_forum_lastpost($fid);
		
		foreach($threads as $tid)
		{
			$count = array();
			// Attachment count
			$query = $db->query("
					SELECT COUNT(aid) AS attachment_count
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

		if(is_array($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				$updated_forum_stats = array(
					'posts' => signed($counters['num_posts']),
					'unapprovedposts' => signed($counters['unapprovedposts']),
					'threads' => signed($counters['threads']),
				);
				update_forum_counters($fid, $updated_forum_stats);
			}
		}

		return $masterpid;
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
		$tid = intval($tid);
		$new_fid = intval($new_fid);
		$redirect_expire = intval($redirect_expire);

		$thread = get_thread($tid, true);
		$newforum = get_forum($new_fid);
		$fid = $thread['fid'];
		$forum = get_forum($fid);

		$num_threads = $num_unapproved_threads = $num_posts = $num_unapproved_threads = 0;
		switch($method)
		{
			case "redirect": // move (and leave redirect) thread
				$arguments = array("tid" => $tid, "new_fid" => $new_fid);
				$plugins->run_hooks("class_moderation_move_thread_redirect", $arguments);

				if($thread['visible'] == 1)
				{
					$num_threads++;
					$num_posts = $thread['replies']+1;
				}
				else
				{
					$num_unapproved_threads++;
					// Implied forum unapproved count for unapproved threads
 					$num_unapproved_posts = $thread['replies']+1;
				}
				
				$num_unapproved_posts += $thread['unapprovedposts'];

				$db->delete_query("threads", "closed='moved|$tid' AND fid='$new_fid'");
				$changefid = array(
					"fid" => $new_fid,
				);
				$db->update_query("threads", $changefid, "tid='$tid'");
				$db->update_query("posts", $changefid, "tid='$tid'");
				
				// If the thread has a prefix and the destination forum doesn't accept that prefix, remove the prefix
				if($thread['prefix'] != 0)
				{
					$query = $db->simple_select("threadprefixes", "COUNT(*) as num_prefixes", "(CONCAT(',',forums,',') LIKE '%,$new_fid,%' OR forums='-1') AND pid='".$thread['prefix']."'");
					if($db->fetch_field($query, "num_prefixes") == 0)
					{
						$sqlarray = array(
							"prefix" => 0,
						);
						$db->update_query("threads", $sqlarray, "tid='$tid'");
					}
				}
				
				$threadarray = array(
					"fid" => $thread['fid'],
					"subject" => $db->escape_string($thread['subject']),
					"icon" => $thread['icon'],
					"uid" => $thread['uid'],
					"username" => $db->escape_string($thread['username']),
					"dateline" => $thread['dateline'],
					"lastpost" => $thread['lastpost'],
					"lastposteruid" => $thread['lastposteruid'],
					"lastposter" => $db->escape_string($thread['lastposter']),
					"views" => 0,
					"replies" => 0,
					"closed" => "moved|$tid",
					"sticky" => $thread['sticky'],
					"visible" => intval($thread['visible']),
					"notes" => ''
				);
				$redirect_tid = $db->insert_query("threads", $threadarray);
				if($redirect_expire)
				{
					$this->expire_thread($redirect_tid, $redirect_expire);
				}
				
				// If we're moving back to a forum where we left a redirect, delete the rediect
				$query = $db->simple_select("threads", "tid", "closed LIKE 'moved|".intval($tid)."' AND fid='".intval($new_fid)."'");
				while($movedthread = $db->fetch_array($query))
				{
					$db->delete_query("threads", "tid='".intval($movedthread['tid'])."'");
				}				
 				break;
			case "copy":// copy thread

				$threadarray = array(
					"fid" => $new_fid,
					"subject" => $db->escape_string($thread['subject']),
					"icon" => $thread['icon'],
					"uid" => $thread['uid'],
					"username" => $db->escape_string($thread['username']),
					"dateline" => $thread['dateline'],
					"firstpost" => 0,
					"lastpost" => $thread['lastpost'],
					"lastposteruid" => $thread['lastposteruid'],
					"lastposter" => $db->escape_string($thread['lastposter']),
					"views" => $thread['views'],
					"replies" => $thread['replies'],
					"closed" => $thread['closed'],
					"sticky" => $thread['sticky'],
					"visible" => intval($thread['visible']),
					"unapprovedposts" => $thread['unapprovedposts'],
					"attachmentcount" => $thread['attachmentcount'],
					"prefix" => $thread['prefix'],
					"notes" => ''
				);

				if($thread['visible'] == 1)
				{
					++$num_threads;
					$num_posts = $thread['replies']+1;

					// Fetch count of unapproved posts in this thread
					$query = $db->simple_select("posts", "COUNT(pid) AS unapproved", "tid='{$thread['tid']}' AND visible=0");
					$num_unapproved_posts = $db->fetch_field($query, "unapproved");

				}
				else
				{
					$num_unapproved_threads++;
					$num_unapproved_posts = $thread['replies']+1;
				}

				$arguments = array("tid" => $tid, "new_fid" => $new_fid);
				$plugins->run_hooks("class_moderation_copy_thread", $arguments);
				
				// If the thread has a prefix and the destination forum doesn't accept that prefix, don't copy the prefix
				if($threadarray['prefix'] != 0)
				{
					$query = $db->simple_select("threadprefixes", "COUNT(*) as num_prefixes", "(CONCAT(',',forums,',') LIKE '%,$new_fid,%' OR forums='-1') AND pid='".$thread['prefix']."'");
					if($db->fetch_field($query, "num_prefixes") == 0)
					{
						$threadarray['prefix'] = 0;
					}
				}

				$newtid = $db->insert_query("threads", $threadarray);

				if($thread['poll'] != 0)
				{
					$query = $db->simple_select("polls", "*", "tid = '{$thread['tid']}'");
					$poll = $db->fetch_array($query);

					$poll_array = array(
						'tid' => $newtid,
						'question' => $db->escape_string($poll['question']),
						'dateline' => $poll['dateline'],
						'options' => $db->escape_string($poll['options']),
						'votes' => $poll['votes'],
						'numoptions' => $poll['numoptions'],
						'numvotes' => $poll['numvotes'],
						'timeout' => $poll['timeout'],
						'closed' => $poll['closed'],
						'multiple' => $poll['multiple'],
						'public' => $poll['public']
					);
					$new_pid = $db->insert_query("polls", $poll_array);

					$query = $db->simple_select("pollvotes", "*", "pid = '{$poll['pid']}'");
					while($pollvote = $db->fetch_array($query))
					{
						$pollvote_array = array(
							'pid' => $new_pid,
							'uid' => $pollvote['uid'],
							'voteoption' => $pollvote['voteoption'],
							'dateline' => $pollvote['dateline'],
						);
						$db->insert_query("pollvotes", $pollvote_array);
					}

					$db->update_query("threads", array('poll' => $new_pid), "tid='{$newtid}'");
				}

				$query = $db->simple_select("posts", "*", "tid = '{$thread['tid']}'");
				while($post = $db->fetch_array($query))
				{
					$post_array = array(
						'tid' => $newtid,
						'fid' => $new_fid,
						'subject' => $db->escape_string($post['subject']),
						'icon' => $post['icon'],
						'uid' => $post['uid'],
						'username' => $db->escape_string($post['username']),
						'dateline' => $post['dateline'],
						'ipaddress' => $post['ipaddress'],
						'includesig' => $post['includesig'],
						'smilieoff' => $post['smilieoff'],
						'edituid' => $post['edituid'],
						'edittime' => $post['edittime'],
						'visible' => $post['visible'],
						'message' => $db->escape_string($post['message']),
					);
					$pid = $db->insert_query("posts", $post_array);
					
					// Properly set our new firstpost in our new thread
					if($thread['firstpost'] == $post['pid'])
					{
						$db->update_query("threads", array('firstpost' => $pid), "tid='{$newtid}'");
					}

					// Insert attachments for this post
					$query2 = $db->simple_select("attachments", "*", "pid = '{$post['pid']}'");
					while($attachment = $db->fetch_array($query2))
					{
						$attachment_array = array(
							'pid' => $pid,
							'uid' => $attachment['uid'],
							'filename' => $db->escape_string($attachment['filename']),
							'filetype' => $attachment['filetype'],
							'filesize' => $attachment['filesize'],
							'attachname' => $attachment['attachname'],
							'downloads' => $attachment['downloads'],
							'visible' => $attachment['visible'],
							'thumbnail' => $attachment['thumbnail']
						);
						$new_aid = $db->insert_query("attachments", $attachment_array);
						
						$post['message'] = str_replace("[attachment={$attachment['aid']}]", "[attachment={$new_aid}]", $post['message']);
					}
					
					if(strpos($post['message'], "[attachment=") !== false)
					{
						$db->update_query("posts", array('message' => $db->escape_string($post['message'])), "pid='{$pid}'");
					}
				}

				update_thread_data($newtid);

				$the_thread = $newtid;
				break;
			default:
			case "move": // plain move thread
				$arguments = array("tid" => $tid, "new_fid" => $new_fid);
				$plugins->run_hooks("class_moderation_move_simple", $arguments);

				if($thread['visible'] == 1)
				{
					$num_threads++;
					$num_posts = $thread['replies']+1;
				}
				else
				{
					$num_unapproved_threads++;
					// Implied forum unapproved count for unapproved threads
 					$num_unapproved_posts = $thread['replies']+1;
				}

				$num_unapproved_posts = $thread['unapprovedposts'];

				$sqlarray = array(
					"fid" => $new_fid,
				);
				$db->update_query("threads", $sqlarray, "tid='$tid'");
				$db->update_query("posts", $sqlarray, "tid='$tid'");
				
				// If the thread has a prefix and the destination forum doesn't accept that prefix, remove the prefix
				if($thread['prefix'] != 0)
				{
					$query = $db->simple_select("threadprefixes", "COUNT(*) as num_prefixes", "(CONCAT(',',forums,',') LIKE '%,$new_fid,%' OR forums='-1') AND pid='".$thread['prefix']."'");
					if($db->fetch_field($query, "num_prefixes") == 0)
					{
						$sqlarray = array(
							"prefix" => 0,
						);
						$db->update_query("threads", $sqlarray, "tid='$tid'");
					}
				}
				
				// If we're moving back to a forum where we left a redirect, delete the rediect
				$query = $db->simple_select("threads", "tid", "closed LIKE 'moved|".intval($tid)."' AND fid='".intval($new_fid)."'");
				while($movedthread = $db->fetch_array($query))
				{
					$db->delete_query("threads", "tid='".intval($movedthread['tid'])."'");
				}
				break;
		}

		// Do post count changes if changing between countable and non-countable forums
		$query = $db->query("
			SELECT COUNT(p.pid) AS posts, u.uid, p.visible
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE tid='$tid'
			GROUP BY u.uid, p.visible
			ORDER BY posts DESC
		");
		while($posters = $db->fetch_array($query))
		{
			$pcount = "";
			if($forum['usepostcounts'] == 1 && $newforum['usepostcounts'] == 0 && $posters['visible'] == 1)
			{
				$pcount = "-{$posters['posts']}";
			}
			else if($forum['usepostcounts'] == 0 && $newforum['userpostcounts'] == 1 && $posters['visible'] == 1)
			{
				$pcount = "+{$posters['posts']}";
			}
			
			if(!empty($pcount))
			{
				$db->update_query("users", array("postnum" => "postnum{$pcount}"), "uid='{$posters['uid']}'", 1, true);
			}
		}

		// Update forum counts
		$update_array = array(
			"threads" => "+{$num_threads}",
			"unapprovedthreads" => "+{$num_unapproved_threads}",
			"posts" => "+{$num_posts}",
			"unapprovedposts" => "+{$num_unapproved_posts}"
		);
		update_forum_counters($new_fid, $update_array);

		if($method != "copy")
		{
			$update_array = array(
				"threads" => "-{$num_threads}",
				"unapprovedthreads" => "-{$num_unapproved_threads}",
				"posts" => "-{$num_posts}",
				"unapprovedposts" => "-{$num_unapproved_posts}"
			);
			update_forum_counters($fid, $update_array);
		}

		if(isset($newtid))
		{
			return $newtid;
		}
		else
		{
			// Remove thread subscriptions for the users who no longer have permission to view the thread
			$this->remove_thread_subscriptions($tid, false, $new_fid);

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
		global $db, $mybb, $mergethread, $thread, $plugins;

		$mergetid = intval($mergetid);
		$tid = intval($tid);

		if(!isset($mergethread['tid']) || $mergethread['tid'] != $mergetid)
		{
			$query = $db->simple_select("threads", "*", "tid='{$mergetid}'");
			$mergethread = $db->fetch_array($query);
		}
		if(!isset($thread['tid']) || $thread['tid'] != $tid)
		{
			$query = $db->simple_select("threads", "*", "tid='{$tid}'");
			$thread = $db->fetch_array($query);
		}

		$pollsql = '';
		if($mergethread['poll'])
		{
			$pollsql['poll'] = $mergethread['poll'];
			$sqlarray = array(
				"tid" => $tid,
			);
			$db->update_query("polls", $sqlarray, "tid='".intval($mergethread['tid'])."'");
		}
		else
		{
			$query = $db->simple_select("threads", "*", "poll='{$mergethread['poll']}' AND tid != '{$mergetid}'");
			$pollcheck = $db->fetch_array($query);
			if(!$pollcheck['poll'])
			{
				$db->delete_query("polls", "pid='{$mergethread['poll']}'");
				$db->delete_query("pollvotes", "pid='{$mergethread['poll']}'");
			}
		}

		$subject = $db->escape_string($subject);

		$sqlarray = array(
			"tid" => $tid,
			"fid" => $thread['fid'],
			"replyto" => 0,
		);
		$db->update_query("posts", $sqlarray, "tid='{$mergetid}'");

		$pollsql['subject'] = $subject;
		$db->update_query("threads", $pollsql, "tid='{$tid}'");
		$sqlarray = array(
			"closed" => "moved|{$tid}",
		);
		$db->update_query("threads", $sqlarray, "closed='moved|{$mergetid}'");
		$sqlarray = array(
			"tid" => $tid,
		);

		// Update the thread ratings
		$new_numrating = $thread['numratings'] + $mergethread['numratings'];
		$new_threadrating = $thread['totalratings'] + $mergethread['totalratings'];

		$sqlarray = array(
			"numratings" => $new_numrating,
			"totalratings" => $new_threadrating
		);

		$db->update_query("threads", $sqlarray, "tid = '{$tid}'");

		// Check if we have a thread subscription already for our new thread
		$subscriptions = array(
			$tid => array(),
			$mergetid => array()
		);

		$query = $db->simple_select("threadsubscriptions", "tid, uid", "tid='{$mergetid}' OR tid='{$tid}'");
		while($subscription = $db->fetch_array($query))
		{
			$subscriptions[$subscription['tid']][] = $subscription['uid'];
		}

		// Update any subscriptions for the merged thread
		if(is_array($subscriptions[$mergetid]))
 		{
			$update_users = array();
			foreach($subscriptions[$mergetid] as $user)
			{
				if(!in_array($user, $subscriptions[$tid]))
				{
					// User doesn't have a $tid subscription
					$update_users[] = $user;
				}
			}
 
			if(!empty($update_users))
			{				
				$update_array = array(
					"tid" => $tid
				);

				$update_users = implode(",", $update_users);
				$db->update_query("threadsubscriptions", $update_array, "tid = '{$mergetid}' AND uid IN ({$update_users})");
			}
 		}
 
		// Remove source thread subscriptions
		$db->delete_query("threadsubscriptions", "tid = '{$mergetid}'");

		update_first_post($tid);

		$arguments = array("mergetid" => $mergetid, "tid" => $tid, "subject" => $subject);
		$plugins->run_hooks("class_moderation_merge_threads", $arguments);

		$this->delete_thread($mergetid);
		
		// In some cases the thread we may be merging with may cause us to have a new firstpost if it is an older thread
		// Therefore resync the visible field to make sure they're the same if they're not
		$query = $db->simple_select("posts", "pid, visible", "tid='{$tid}'", array('order_by' => 'dateline', 'order_dir' => 'asc', 'limit' => 1));
		$new_firstpost = $db->fetch_array($query);
		if($thread['visible'] != $new_firstpost['visible'])
		{
			$db->update_query("posts", array('visible' => $thread['visible']), "pid='{$new_firstpost['pid']}'");
			$mergethread['visible'] = $thread['visible'];
		}

		$updated_stats = array(
			"replies" => '+'.($mergethread['replies']+1),
			"attachmentcount" => "+{$mergethread['attachmentcount']}",
			"unapprovedposts" => "+{$mergethread['unapprovedposts']}"
		);
		update_thread_counters($tid, $updated_stats);

		// Thread is not in current forum
		if($mergethread['fid'] != $thread['fid'])
		{
			// If new thread is unapproved, implied counter comes in to effect
			if($thread['visible'] == 0 || $mergethread['visible'] == 0)
			{
				$updated_stats = array(
					"unapprovedposts" => '+'.($mergethread['replies']+1+$mergethread['unapprovedposts'])
				);
			}
			else
			{
				$updated_stats = array(
					"posts" => '+'.($mergethread['replies']+1),
					"unapprovedposts" => "+{$mergethread['unapprovedposts']}"
				);
			}
			update_forum_counters($thread['fid'], $updated_stats);
			
			// If old thread is unapproved, implied counter comes in to effect
			if($mergethread['visible'] == 0)
			{
				$updated_stats = array(
					"unapprovedposts" => '-'.($mergethread['replies']+1+$mergethread['unapprovedposts'])
				);
			}
			else
			{
				$updated_stats = array(
					"posts" => '-'.($mergethread['replies']+1),
					"unapprovedposts" => "-{$mergethread['unapprovedposts']}"
				);
			}
			update_forum_counters($mergethread['fid'], $updated_stats);
		}
		// If we're in the same forum we need to at least update the last post information
		else
		{
			update_forum_lastpost($thread['fid']);
		}
		return true;
	}

	/**
	 * Split posts into a new/existing thread
	 *
	 * @param array PIDs of posts to split
	 * @param int Original thread ID (this is only used as a base for the new
	 * thread; it can be set to 0 when the posts specified are coming from more
	 * than 1 thread)
	 * @param int Destination forum
	 * @param string New thread subject
	 * @param int TID if moving into existing thread
	 * @return int New thread ID
	 */
	function split_posts($pids, $tid, $moveto, $newsubject, $destination_tid=0)
	{
		global $db, $thread, $plugins;

		$tid = intval($tid);
		$moveto = intval($moveto);
		$newtid = intval($destination_tid);
		
		// Get forum infos
		$query = $db->simple_select("forums", "fid, usepostcounts, posts, threads, unapprovedposts, unapprovedthreads");
		while($forum = $db->fetch_array($query))
		{
			$forum_cache[$forum['fid']] = $forum;
		}

		// Make sure we only have valid values
		$pids = array_map('intval', $pids);

		$pids_list = implode(',', $pids);

		// Get the icon for the first split post
		$query = $db->simple_select("posts", "icon, visible", "pid=".intval($pids[0]));
		$post_info = $db->fetch_array($query);

		$icon = $post_info['icon'];
		$visible = $post_info['visible'];

		if($destination_tid == 0)
		{
			// Splitting into a new thread
			$thread = get_thread($tid);
			// Create the new thread
			$newsubject = $db->escape_string($newsubject);
			$query = array(
				"fid" => $moveto,
				"subject" => $newsubject,
				"icon" => intval($icon),
				"uid" => intval($thread['uid']),
				"username" => $db->escape_string($thread['username']),
				"dateline" => intval($thread['dateline']),
				"lastpost" => intval($thread['lastpost']),
				"lastposter" => $db->escape_string($thread['lastposter']),
				"replies" => count($pids)-1,
				"visible" => intval($visible),
				"notes" => ''
			);
			$newtid = $db->insert_query("threads", $query);
			
			$forum_counters[$moveto]['threads'] = $forum_cache[$moveto]['threads'];
			$forum_counters[$moveto]['unapprovedthreads'] = $forum_cache[$moveto]['unapprovedthreads'];
			if($visible)
			{
				++$forum_counters[$moveto]['threads'];
			}
			else
			{
				// Unapproved thread?
				++$forum_counters[$moveto]['unapprovedthreads'];
			}
		}

		// Get attachment counts for each post
		/*$query = $db->simple_select("attachments", "COUNT(aid) as count, pid", "pid IN ($pids_list)");
		$query = $db->query("
			SELECT COUNT(aid) as count, p.pid,
			");
		$attachment_sum = 0;
		while($attachment = $db->fetch_array($query))
		{
			$attachments[$attachment['pid']] = $attachment['count'];
			$attachment_sum += $attachment['count'];
		}
		$thread_counters[$newtid]['attachmentcount'] = '+'.$attachment_sum;*/

		// Get selected posts before moving forums to keep old fid
		//$original_posts_query = $db->simple_select("posts", "fid, visible, pid", "pid IN ($pids_list)");
		$original_posts_query = $db->query("
			SELECT p.pid, p.tid, p.fid, p.visible, p.uid, t.visible as threadvisible, t.replies as threadreplies, t.unapprovedposts as threadunapprovedposts, t.attachmentcount as threadattachmentcount, COUNT(a.aid) as postattachmentcount
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (p.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."attachments a ON (a.pid=p.pid)
			WHERE p.pid IN ($pids_list)
			GROUP BY p.pid, p.tid, p.fid, p.visible, p.uid, t.visible, t.replies, t.unapprovedposts,t.attachmentcount
		");

		// Move the selected posts over
		$sqlarray = array(
			"tid" => $newtid,
			"fid" => $moveto,
			"replyto" => 0
		);
		$db->update_query("posts", $sqlarray, "pid IN ($pids_list)");
		$db->update_query("reportedposts", array('tid' => $newtid), "pid IN ($pids_list)");

		// Get posts being merged
		while($post = $db->fetch_array($original_posts_query))
		{
			if($post['visible'] == 1)
			{
				// Modify users' post counts
				if($forum_cache[$post['fid']]['usepostcounts'] == 1 && $forum_cache[$moveto]['usepostcounts'] == 0)
				{
					// Moving into a forum that doesn't count post counts
					if(!isset($user_counters[$post['uid']]))
					{
						$user_counters[$post['uid']] = 0;
					}
					--$user_counters[$post['uid']];
				}
				elseif($forum_cache[$post['fid']]['usepostcounts'] == 0 && $forum_cache[$moveto]['usepostcounts'] == 1)
				{
					// Moving into a forum that does count post counts
					if(!isset($user_counters[$post['uid']]))
					{
						$user_counters[$post['uid']] = 0;
					}
					++$user_counters[$post['uid']];
				}

				// Subtract 1 from the old thread's replies
				if(!isset($thread_counters[$post['tid']]['replies']))
				{
					$thread_counters[$post['tid']]['replies'] = $post['threadreplies'];
				}
				--$thread_counters[$post['tid']]['replies'];

				// Add 1 to the new thread's replies
				++$thread_counters[$newtid]['replies'];

				if($moveto != $post['fid'])
				{
					// Only need to change forum info if the old forum is different from new forum
					// Subtract 1 from the old forum's posts
					if(!isset($forum_counters[$post['fid']]['posts']))
					{
						$forum_counters[$post['fid']]['posts'] = $forum_cache[$post['fid']]['posts'];
					}
					--$forum_counters[$post['fid']]['posts'];
					// Add 1 to the new forum's posts
					if(!isset($forum_counters[$moveto]['posts']))
					{
						$forum_counters[$moveto]['posts'] = $forum_cache[$moveto]['posts'];
					}
					++$forum_counters[$moveto]['posts'];
				}

			}
			elseif($post['visible'] == 0)
			{
				// Unapproved post
				// Subtract 1 from the old thread's unapproved posts
				if(!isset($thread_counters[$post['tid']]['unapprovedposts']))
				{
					$thread_counters[$post['tid']]['unapprovedposts'] = $post['threadunapprovedposts'];
				}
				--$thread_counters[$post['tid']]['unapprovedposts'];

				// Add 1 to the new thread's unapproved posts
				++$thread_counters[$newtid]['unapprovedposts'];

				if($moveto != $post['fid'])
				{
					// Only need to change forum info if the old forum is different from new forum
					// Subtract 1 from the old forum's unapproved posts
					if(!isset($forum_counters[$post['fid']]['unapprovedposts']))
					{
						$forum_counters[$post['fid']]['unapprovedposts'] = $forum_cache[$post['fid']]['unapprovedposts'];
					}
					--$forum_counters[$post['fid']]['unapprovedposts'];
					// Add 1 to the new forum's unapproved posts
					if(!isset($forum_counters[$moveto]['unapprovedposts']))
					{
						$forum_counters[$moveto]['unapprovedposts'] = $forum_cache[$moveto]['unapprovedposts'];
					}
					++$forum_counters[$moveto]['unapprovedposts'];
				}
			}

			// Subtract attachment counts from old thread and add to new thread (which are counted regardless of post or attachment unapproval at time of coding)
			if(!isset($thread_counters[$post['tid']]['attachmentcount']))
			{
				$thread_counters[$post['tid']]['attachmentcount'] = $post['threadattachmentcount'];
			}
			$thread_counters[$post['tid']]['attachmentcount'] -= $post['postattachmentcount'];
			$thread_counters[$newtid]['attachmentcount'] += $post['postattachmentcount'];
		}
		if($destination_tid == 0 && $thread_counters[$newtid]['replies'] > 0)
		{
			// If splitting into a new thread, subtract one from the thread's reply count to compensate for the original post
			--$thread_counters[$newtid]['replies'];
		}

		$arguments = array("pids" => $pids, "tid" => $tid, "moveto" => $moveto, "newsubject" => $newsubject, "destination_tid" => $destination_tid);
		$plugins->run_hooks("class_moderation_split_posts", $arguments);

		// Update user post counts
		if(is_array($user_counters))
		{
			foreach($user_counters as $uid => $change)
			{
				if($change >= 0)
				{
					$change = '+'.$change; // add the addition operator for query
				}
				$db->update_query("users", array("postnum" => "postnum{$change}"), "uid='{$uid}'", 1, true);
			}
		}

		// Update thread counters
		if(is_array($thread_counters))
		{
			foreach($thread_counters as $tid => $counters)
			{
				if($tid == $newtid)
				{
					// Update the subject of the first post in the new thread
					$query = $db->simple_select("posts", "pid", "tid='$newtid'", array('order_by' => 'dateline', 'limit' => 1));
					$newthread = $db->fetch_array($query);
					$sqlarray = array(
						"subject" => $newsubject,
						"replyto" => 0
					);
					$db->update_query("posts", $sqlarray, "pid='{$newthread['pid']}'");
				}
				else
				{
					// Update the subject of the first post in the old thread
					$query = $db->query("
						SELECT p.pid, t.subject
						FROM ".TABLE_PREFIX."posts p
						LEFT JOIN ".TABLE_PREFIX."threads t ON (p.tid=t.tid)
						WHERE p.tid='{$tid}'
						ORDER BY p.dateline ASC
						LIMIT 1
					");
					$oldthread = $db->fetch_array($query);
					$sqlarray = array(
						"subject" => $db->escape_string($oldthread['subject']),
						"replyto" => 0
					);
					$db->update_query("posts", $sqlarray, "pid='{$oldthread['pid']}'");
				}

				$db->update_query("threads", $counters, "tid='{$tid}'");

				update_thread_data($tid);

				// Update first post columns
				update_first_post($tid);
			}
		}
		update_thread_data($newtid);
		
		update_first_post($newtid);

		// Update forum counters
		if(is_array($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				update_forum_counters($fid, $counters);
			}
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
		global $db, $plugins;

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$tid_list = implode(',', $tids);

		$moveto = intval($moveto);

		$newforum = get_forum($moveto);

		$total_posts = $total_unapproved_posts = $total_threads = $total_unapproved_threads = 0;
		$query = $db->simple_select("threads", "fid, visible, replies, unapprovedposts, tid", "tid IN ($tid_list) AND closed NOT LIKE 'moved|%'");
		while($thread = $db->fetch_array($query))
		{
			$forum = get_forum($thread['fid']);

			$total_posts += $thread['replies']+1;
			$total_unapproved_posts += $thread['unapprovedposts'];

			$forum_counters[$thread['fid']]['posts'] += $thread['replies']+1;
			$forum_counters[$thread['fid']]['unapprovedposts'] += $thread['unapprovedposts'];

			if($thread['visible'] == 1)
			{
				$forum_counters[$thread['fid']]['threads']++;
				++$total_threads;
			}
			else
			{
				$forum_counters[$thread['fid']]['unapprovedthreads']++;
				$forum_counters[$thread['fid']]['unapprovedposts'] += $thread['replies']; // Implied unapproved posts counter for unapproved threads
				++$total_unapproved_threads;
			}

			$query1 = $db->query("
				SELECT COUNT(p.pid) AS posts, p.visible, u.uid
				FROM ".TABLE_PREFIX."posts p
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
				WHERE p.tid = '{$thread['tid']}'
				GROUP BY p.visible, u.uid
				ORDER BY posts DESC
			");
			while($posters = $db->fetch_array($query1))
			{
				$pcount = "";
				if($newforum['usepostcounts'] != 0 && $forum['usepostcounts'] == 0 && $posters['visible'] != 0)
				{
					$pcount = "+{$posters['posts']}";
				}
				else if($newforum['usepostcounts'] == 0 && $forum['usepostcounts'] != 0 && $posters['visible'] != 0)
				{
					$pcount = "-{$posters['posts']}";
				}

				if(!empty($pcount))
				{
					$db->update_query("users", array("postnum" => "postnum{$pcount}"), "uid='{$posters['uid']}'", 1, true);
				}
			}
		}

		$sqlarray = array(
			"fid" => $moveto,
		);
		$db->update_query("threads", $sqlarray, "tid IN ($tid_list)");
		$db->update_query("posts", $sqlarray, "tid IN ($tid_list)");
		
		// If any of the thread has a prefix and the destination forum doesn't accept that prefix, remove the prefix
		$query = $db->simple_select("threads", "tid, prefix", "tid IN ($tid_list) AND prefix != 0");
		while($thread = $db->fetch_array($query))
		{
			$query = $db->simple_select("threadprefixes", "COUNT(*) as num_prefixes", "(CONCAT(',',forums,',') LIKE '%,$moveto,%' OR forums='-1') AND pid='".$thread['prefix']."'");
			if($db->fetch_field($query, "num_prefixes") == 0)
			{
				$sqlarray = array(
					"prefix" => 0,
				);
				$db->update_query("threads", $sqlarray, "tid = '{$thread['tid']}'");
			}
		}

		$arguments = array("tids" => $tids, "moveto" => $moveto);
		$plugins->run_hooks("class_moderation_move_threads", $arguments);
		
		if(is_array($forum_counters))
		{
			foreach($forum_counters as $fid => $counter)
			{
				$updated_count = array(
					"posts" => "-{$counter['posts']}",
					"unapprovedposts" => "-{$counter['unapprovedposts']}"
				);
				if($counter['threads'])
				{
					$updated_count['threads'] = "-{$counter['threads']}";
				}
				if($counter['unapprovedthreads'])
				{
					$updated_count['unapprovedthreads'] = "-{$counter['unapprovedthreads']}";
				}
				update_forum_counters($fid, $updated_count);
			}
		}

		$updated_count = array(
			"threads" => "+{$total_threads}",
			"unapprovedthreads" => "+{$total_unapproved_threads}",
			"posts" => "+{$total_posts}",
			"unapprovedposts" => "+{$total_unapproved_posts}"
		);

		update_forum_counters($moveto, $updated_count);

		// Remove thread subscriptions for the users who no longer have permission to view the thread
		$this->remove_thread_subscriptions($tid_list, false, $moveto);

		return true;
	}

	/**
	 * Approve multiple posts
	 *
	 * @param array PIDs
	 * @return boolean true
	 */
	function approve_posts($pids)
	{
		global $db, $cache;

		$num_posts = 0;

		// Make sure we only have valid values
		$pids = array_map('intval', $pids);

		$pid_list = implode(',', $pids);
		$pids = $threads_to_update = array();

		// Make visible
		$approve = array(
			"visible" => 1,
		);

		// We have three cases we deal with in these code segments:
		// 1) We're approving specific unapproved posts
		// 1.1) if the thread is approved
		// 1.2) if the thread is unapproved
		// 2) We're approving the firstpost of the thread, therefore approving the thread itself
		// 3) We're doing both 1 and 2
		$query = $db->query("
			SELECT p.tid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.pid IN ($pid_list) AND p.visible = '0' AND t.firstpost = p.pid AND t.visible = 0
		");
		while($post = $db->fetch_array($query))
		{
			// This is the first post in the thread so we're approving the whole thread.
			$threads_to_update[] = $post['tid'];
		}
		
		if(!empty($threads_to_update))
		{
			$this->approve_threads($threads_to_update);
		}
		
		$query = $db->query("
			SELECT p.pid, p.tid, f.fid, f.usepostcounts, p.uid, t.visible AS threadvisible
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
			WHERE p.pid IN ($pid_list) AND p.visible = '0' AND t.firstpost != p.pid
		");
		while($post = $db->fetch_array($query))
		{
			$pids[] = $post['pid'];
			
			++$thread_counters[$post['tid']]['unapprovedposts'];
			++$thread_counters[$post['tid']]['replies'];
			
			// If the thread of this post is unapproved then we've already taken into account this counter as implied.
			// Updating it again would cause it to double count
			if($post['threadvisible'] != 0)
			{
				++$forum_counters[$post['fid']]['num_posts'];
			}
			
			// If post counts enabled in this forum and the thread is approved, add 1
			if($post['usepostcounts'] != 0 && $post['threadvisible'] == 1)
			{
				$db->update_query("users", array("postnum" => "postnum+1"), "uid='{$post['uid']}'", 1, true);
			}
		}
		
		if(empty($pids) && empty($threads_to_update))
		{
			return false;
		}

		if(!empty($pids))
		{
			$where = "pid IN (".implode(',', $pids).")";
			$db->update_query("posts", $approve, $where);
		}
		
		if(is_array($thread_counters))
		{
			foreach($thread_counters as $tid => $counters)
			{
				$counters_update = array(
					"unapprovedposts" => "-".$counters['unapprovedposts'],
					"replies" => "+".$counters['replies']
				);
				update_thread_counters($tid, $counters_update);

				update_thread_data($tid);
			}
		}
		
		if(is_array($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				$updated_forum_stats = array(
					"posts" => "+{$counters['num_posts']}",
					"unapprovedposts" => "-{$counters['num_posts']}",
					"threads" => "+{$counters['num_threads']}",
					"unapprovedthreads" => "-{$counters['num_threads']}"
				);
				update_forum_counters($fid, $updated_forum_stats);
			}
		}
		
		return true;
	}

	/**
	 * Unapprove multiple posts
	 *
	 * @param array PIDs
	 * @return boolean true
	 */
	function unapprove_posts($pids)
	{
		global $db, $cache;

		// Make sure we only have valid values
		$pids = array_map('intval', $pids);

		$pid_list = implode(',', $pids);
		$pids = $threads_to_update = array();

		// Make invisible
		$approve = array(
			"visible" => 0,
		);
		
		// We have three cases we deal with in these code segments:
		// 1) We're unapproving specific approved posts
		// 1.1) if the thread is approved
		// 1.2) if the thread is unapproved
		// 2) We're unapproving the firstpost of the thread, therefore unapproving the thread itself
		// 3) We're doing both 1 and 2
		$query = $db->query("
			SELECT p.tid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.pid IN ($pid_list) AND p.visible = '1' AND t.firstpost = p.pid AND t.visible = 1
		");
		while($post = $db->fetch_array($query))
		{
			// This is the first post in the thread so we're unapproving the whole thread.
			$threads_to_update[] = $post['tid'];
		}
		
		if(!empty($threads_to_update))
		{
			$this->unapprove_threads($threads_to_update);
		}
		
		$thread_counters = array();
		$forum_counters = array();
		
		$query = $db->query("
			SELECT p.pid, p.tid, f.fid, f.usepostcounts, p.uid, t.visible AS threadvisible
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
			WHERE p.pid IN ($pid_list) AND p.visible = '1' AND t.firstpost != p.pid
		");
		while($post = $db->fetch_array($query))
		{
			$pids[] = $post['pid'];
			
			++$thread_counters[$post['tid']]['unapprovedposts'];
			++$thread_counters[$post['tid']]['replies'];
			
			// If the thread of this post is unapproved then we've already taken into account this counter as implied.
			// Updating it again would cause it to double count
			if($post['threadvisible'] != 0)
			{
				++$forum_counters[$post['fid']]['num_posts'];
			}
			
			// If post counts enabled in this forum and the thread is approved, subtract 1
			if($post['usepostcounts'] != 0 && $post['threadvisible'] == 1)
			{
				$db->update_query("users", array("postnum" => "postnum-1"), "uid='{$post['uid']}'", 1, true);
			}
		}
		
		if(empty($pids) && empty($threads_to_update))
		{
			return false;
		}

		if(!empty($pids))
		{
			$where = "pid IN (".implode(',', $pids).")";
			$db->update_query("posts", $approve, $where);
		}
		
		if(is_array($thread_counters))
		{
			foreach($thread_counters as $tid => $counters)
			{
				$counters_update = array(
					"unapprovedposts" => "+".$counters['unapprovedposts'],
					"replies" => "-".$counters['replies']
				);
				
				update_thread_counters($tid, $counters_update);

				update_thread_data($tid);
			}
		}

		if(is_array($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				$updated_forum_stats = array(
					"posts" => "-{$counters['num_posts']}",
					"unapprovedposts" => "+{$counters['num_posts']}",
					"threads" => "-{$counters['num_threads']}",
					"unapprovedthreads" => "+{$counters['num_threads']}"
				);
				
				update_forum_counters($fid, $updated_forum_stats);
			}
		}
		
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
		global $db, $mybb, $plugins;

		// Get tids into list
		if(!is_array($tids))
		{
			$tids = array(intval($tids));
		}


		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$tid_list = implode(',', $tids);

		// Get original subject
		$query = $db->simple_select("threads", "subject, tid", "tid IN ($tid_list)");
		while($thread = $db->fetch_array($query))
		{
			// Update threads and first posts with new subject
			$subject = str_replace('{username}', $mybb->user['username'], $format);
			$subject = str_replace('{subject}', $thread['subject'], $subject);
			$new_subject = array(
				"subject" => $db->escape_string($subject)
			);
			$db->update_query("threads", $new_subject, "tid='{$thread['tid']}'");
			$db->update_query("posts", $new_subject, "tid='{$thread['tid']}' AND replyto='0'");
		}

		$arguments = array("tids" => $tids, "format" => $format);
		$plugins->run_hooks("class_moderation_change_thread_subject", $arguments);

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
		global $db, $plugins;

		$tid = intval($tid);

		$update_thread = array(
			"deletetime" => intval($deletetime)
		);
		$db->update_query("threads", $update_thread, "tid='{$tid}'");

		$arguments = array("tid" => $tid, "deletetime" => $deletetime);
		$plugins->run_hooks("class_moderation_expire_thread", $arguments);

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
	function toggle_post_visibility($pids)
	{
		global $db;

		// Make sure we only have valid values
		$pids = array_map('intval', $pids);

		$pid_list = implode(',', $pids);
		$query = $db->simple_select("posts", 'pid, visible', "pid IN ($pid_list)");
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
			$this->unapprove_posts($unapprove);
		}
		if(is_array($approve))
		{
			$this->approve_posts($approve);
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

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);
		$fid = intval($fid);

		$tid_list = implode(',', $tids);
		$query = $db->simple_select("threads", 'tid, visible', "tid IN ($tid_list)");
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

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$tid_list = implode(',', $tids);
		$query = $db->simple_select("threads", 'tid, closed', "tid IN ($tid_list)");
		while($thread = $db->fetch_array($query))
		{
			if($thread['closed'] == 1)
			{
				$open[] = $thread['tid'];
			}
			elseif($thread['closed'] == 0)
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

	/**
	 * Remove thread subscriptions (from one or multiple threads in the same forum)
	 *
	 * @param int $tids Thread ID, or an array of thread IDs from the same forum.
	 * @param boolean $all True (default) to delete all subscriptions, false to only delete subscriptions from users with no permission to read the thread
	 * @param int $fid (Only applies if $all is false) The forum ID of the thread
	 * @return boolean true
	 */
	function remove_thread_subscriptions($tids, $all = true, $fid = 0)
	{
		global $db, $plugins;

		// Format thread IDs
		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);
		$fid = intval($fid);

		$tids_csv = implode(',', $tids);

		// Delete only subscriptions from users who no longer have permission to read the thread.
		if(!$all)
		{
			// Get groups that cannot view the forum or its threads
			$forum_parentlist = get_parent_list($fid);
			$query = $db->simple_select("forumpermissions", "gid", "fid IN ({$forum_parentlist}) AND (canview=0 OR canviewthreads=0)");
			$groups = array();
			while($group = $db->fetch_array($query))
			{
				$groups[] = $group['gid'];
				switch($db->type)
				{
					case "pgsql":
					case "sqlite":
						$additional_groups .= " OR ','||u.additionalgroups||',' LIKE ',{$group['gid']},'";
						break;
					default:
						$additional_groups .= " OR CONCAT(',',u.additionalgroups,',') LIKE ',{$group['gid']},'";
				}
			}
			// If there are groups found, delete subscriptions from users in these groups
			if(count($groups) > 0)
			{
				$groups_csv = implode(',', $groups);
				$query = $db->query("
					SELECT s.tid, u.uid
					FROM ".TABLE_PREFIX."threadsubscriptions s
					LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=s.uid)
					WHERE s.tid IN ({$tids_csv})
					AND (u.usergroup IN ({$groups_csv}){$additional_groups})
				");
				while($subscription = $db->fetch_array($query))
				{
					$db->delete_query("threadsubscriptions", "uid='{$subscription['uid']}' AND tid='{$subscription['tid']}'");
				}
			}
		}
		// Delete all subscriptions of this thread
		else
		{
			$db->delete_query("threadsubscriptions", "tid IN ({$tids_csv})");
		}
	
		$arguments = array("tids" => $tids, "all" => $all, "fid" => $fid);
		$plugins->run_hooks("class_moderation_remove_thread_subscriptions", $arguments);

		return true;
	}
	
	/**
	 * Apply a thread prefix (to one or multiple threads in the same forum)
	 * 
	 * @param int $tids Thread ID, or an array of thread IDs from the same forum.
	 * @param int $prefix Prefix ID to apply to the threads
	 */
	function apply_thread_prefix($tids, $prefix = 0)
	{
		global $db, $plugins;
		
		// Format thread IDs
		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);
		$tids_csv = implode(',', $tids);
		
		$update_thread = array('prefix' => intval($prefix));
		$db->update_query('threads', $update_thread, "tid IN ({$tids_csv})");
		
		$arguments = array('tids' => $tids, 'prefix' => $prefix);
		
		$plugins->run_hooks('class_moderation_apply_thread_prefix', $arguments);
		
		return true;
	}
}
?>
