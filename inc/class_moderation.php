<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

class Moderation
{
	/**
	 * Close one or more threads
	 *
	 * @param array|int $tids Thread ID(s)
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
	 * @param array|int $tids Thread ID(s)
	 * @return boolean
	 */

	function open_threads($tids)
	{
		global $db, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		if(empty($tids))
		{
			return false;
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
	 * @param array|int $tids Thread ID(s)
	 * @return boolean
	 */
	function stick_threads($tids)
	{
		global $db, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		if(empty($tids))
		{
			return false;
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
	 * @param array|int $tids Thread ID(s)
	 * @return boolean
	 */
	function unstick_threads($tids)
	{
		global $db, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		if(empty($tids))
		{
			return false;
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
	 * @param int $tid Thread ID of the thread
	 * @return boolean
	 */
	function remove_redirects($tid)
	{
		global $db, $plugins;

		$plugins->run_hooks("class_moderation_remove_redirects", $tid);

		// Delete the redirects
		$tid = (int)$tid;
		if(empty($tid))
		{
			return false;
		}

		$query = $db->simple_select('threads', 'tid', "closed='moved|$tid'");
		while($redirect_tid = $db->fetch_field($query, 'tid'))
		{
			$this->delete_thread($redirect_tid);
		}

		return true;
	}

	/**
	 * Delete a thread
	 *
	 * @param int $tid Thread ID of the thread
	 * @return boolean
	 */
	function delete_thread($tid)
	{
		global $db, $cache, $plugins;

		$tid = (int)$tid;

		$plugins->run_hooks("class_moderation_delete_thread_start", $tid);

		$thread = get_thread($tid);
		if(!$thread)
		{
			return false;
		}
		$forum = get_forum($thread['fid']);

		$userposts = array();

		// Find the pid, uid, visibility, and forum post count status
		$query = $db->simple_select('posts', 'pid, uid, visible', "tid='{$tid}'");
		$pids = array();
		$num_unapproved_posts = $num_approved_posts = $num_deleted_posts = 0;
		while($post = $db->fetch_array($query))
		{
			$pids[] = $post['pid'];

			if(!function_exists("remove_attachments"))
			{
				require_once MYBB_ROOT."inc/functions_upload.php";
			}

			// Remove attachments
			remove_attachments($post['pid']);

			// If the post is unapproved, count it!
			if(($post['visible'] == 0 && $thread['visible'] != -1) || $thread['visible'] == 0)
			{
				$num_unapproved_posts++;
			}
			elseif($post['visible'] == -1 || $thread['visible'] == -1)
			{
				$num_deleted_posts++;
			}
			else
			{
				$num_approved_posts++;

				// Count the post counts for each user to be subtracted
				if($forum['usepostcounts'] != 0)
				{
					if(!isset($userposts[$post['uid']]['num_posts']))
					{
						$userposts[$post['uid']]['num_posts'] = 0;
					}
					++$userposts[$post['uid']]['num_posts'];
				}
			}
		}

		if($forum['usethreadcounts'] != 0 && substr($thread['closed'], 0, 6) != 'moved|')
		{
			if(!isset($userposts[$thread['uid']]['num_threads']))
			{
				$userposts[$thread['uid']]['num_threads'] = 0;
			}
			++$userposts[$thread['uid']]['num_threads'];
		}

		// Remove post count from users
		if($thread['visible'] == 1)
		{
			if(!empty($userposts))
			{
				foreach($userposts as $uid => $subtract)
				{
					$update_array = array();

					if(isset($subtract['num_posts']))
					{
						$update_array['postnum'] = "-{$subtract['num_posts']}";
					}

					if(isset($subtract['num_threads']))
					{
						$update_array['threadnum'] = "-{$subtract['num_threads']}";
					}

					update_user_counters($uid, $update_array);
				}
			}
		}
		// Delete posts and their attachments
		if(!empty($pids))
		{
			$pids = implode(',', $pids);
			$db->delete_query("posts", "pid IN ($pids)");
			$db->delete_query("attachments", "pid IN ($pids)");
			$db->delete_query("reportedcontent", "id IN ($pids) AND (type = 'post' OR type = '')");
		}

		// Delete threads, redirects, subscriptions, polls, and poll votes
		$db->delete_query("threads", "tid='$tid'");
		$query = $db->simple_select('threads', 'tid', "closed='moved|$tid'");
		while($redirect_tid = $db->fetch_field($query, 'tid'))
		{
			$this->delete_thread($redirect_tid);
		}
		$db->delete_query("threadsubscriptions", "tid='$tid'");
		$db->delete_query("polls", "tid='$tid'");
		$db->delete_query("pollvotes", "pid='".$thread['poll']."'");
		$db->delete_query("threadsread", "tid='$tid'");
		$db->delete_query("threadratings", "tid='$tid'");

		$updated_counters = array(
			"posts" => "-{$num_approved_posts}",
			"unapprovedposts" => "-{$num_unapproved_posts}",
			"deletedposts" => "-{$num_deleted_posts}"
		);

		if($thread['visible'] == 1)
		{
			$updated_counters['threads'] = -1;
		}
		elseif($thread['visible'] == -1)
		{
			$updated_counters['deletedthreads'] = -1;
		}
		else
		{
			$updated_counters['unapprovedthreads'] = -1;
		}

		if(strpos($thread['closed'], 'moved|') !== false)
		{
			// Redirect
			if($thread['visible'] == 1)
			{
				$updated_counters['posts'] = -1;
			}
			elseif($thread['visible'] == -1)
			{
				$updated_counters['deletedposts'] = -1;
			}
			else
			{
				$updated_counters['unapprovedposts'] = -1;
			}
		}

		// Update forum count
		update_forum_counters($thread['fid'], $updated_counters);
		update_forum_lastpost($thread['fid']);
		mark_reports($tid, 'thread');

		$plugins->run_hooks("class_moderation_delete_thread", $tid);

		return true;
	}

	/**
	 * Delete a poll
	 *
	 * @param int $pid Poll id
	 * @return boolean
	 */
	function delete_poll($pid)
	{
		global $db, $plugins;

		$pid = (int)$pid;

		if(empty($pid))
		{
			return false;
		}

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
	 * @param array|int $tids Thread ID(s)
	 * @return boolean
	 */
	function approve_threads($tids)
	{
		global $db, $cache, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		if(empty($tids))
		{
			return false;
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$tid_list = $forum_counters = $user_counters = $posts_to_approve = array();

		$tids_list = implode(",", $tids);
		$query = $db->simple_select("threads", "*", "tid IN ($tids_list)");

		while($thread = $db->fetch_array($query))
		{
			if($thread['visible'] == 1 || $thread['visible'] == -1)
			{
				continue;
			}
			$tid_list[] = $thread['tid'];

			$forum = get_forum($thread['fid']);

			if(!isset($forum_counters[$forum['fid']]))
			{
				$forum_counters[$forum['fid']] = array(
					'num_posts' => 0,
					'num_threads' => 0,
					'num_deleted_posts' => 0,
					'num_unapproved_posts' => 0
				);
			}

			if(!isset($user_counters[$thread['uid']]))
			{
				$user_counters[$thread['uid']] = array(
					'num_posts' => 0,
					'num_threads' => 0
				);
			}

			++$forum_counters[$forum['fid']]['num_threads'];
			$forum_counters[$forum['fid']]['num_posts'] += $thread['replies']+1; // Remove implied visible from count
			$forum_counters[$forum['fid']]['num_deleted_posts'] += $thread['deletedposts'];
			$forum_counters[$forum['fid']]['num_unapproved_posts'] += $thread['deletedposts']+$thread['replies']+1;

			if($forum['usepostcounts'] != 0)
			{
				// On approving thread restore user post counts
				$query2 = $db->simple_select("posts", "COUNT(pid) as posts, uid", "tid='{$thread['tid']}' AND (visible='1' OR pid='{$thread['firstpost']}') AND uid > 0 GROUP BY uid");
				while($counter = $db->fetch_array($query2))
				{
					if(!isset($user_counters[$counter['uid']]))
					{
						$user_counters[$counter['uid']] = array(
							'num_posts' => 0,
							'num_threads' => 0
						);
					}
					$user_counters[$counter['uid']]['num_posts'] += $counter['posts'];
				}
			}

			if($forum['usethreadcounts'] != 0 && substr($thread['closed'], 0, 6) != 'moved|')
			{
				++$user_counters[$thread['uid']]['num_threads'];
			}

			$posts_to_approve[] = $thread['firstpost'];
		}

		if(!empty($tid_list))
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
			$db->update_query("threads", $approve, "tid IN ($tid_list)");
			// Approve redirects, too
			$redirect_tids = array();
			$query = $db->simple_select('threads', 'tid', "closed IN ({$tid_moved_list})");
			while($redirect_tid = $db->fetch_field($query, 'tid'))
			{
				$redirect_tids[] = $redirect_tid;
			}
			if(!empty($redirect_tids))
			{
				$this->approve_threads($redirect_tids);
			}
			if(!empty($posts_to_approve))
			{
				$db->update_query("posts", $approve, "pid IN (".implode(',', $posts_to_approve).")");
			}

			$plugins->run_hooks("class_moderation_approve_threads", $tids);

			if(!empty($forum_counters))
			{
				foreach($forum_counters as $fid => $counters)
				{
					// Update stats
					$update_array = array(
						"threads" => "+{$counters['num_threads']}",
						"unapprovedthreads" => "-{$counters['num_threads']}",
						"posts" => "+{$counters['num_posts']}",
						"unapprovedposts" => "-{$counters['num_unapproved_posts']}",
						"deletedposts" => "+{$counters['num_deleted_posts']}"
					);
					update_forum_counters($fid, $update_array);
					update_forum_lastpost($fid);
				}
			}

			if(!empty($user_counters))
			{
				foreach($user_counters as $uid => $counters)
				{
					$update_array = array(
						"postnum" => "+{$counters['num_posts']}",
						"threadnum" => "+{$counters['num_threads']}",
					);
					update_user_counters($uid, $update_array);
				}
			}
		}
		return true;
	}

	/**
	 * Unapprove one or more threads
	 *
	 * @param array|int $tids Thread ID(s)
	 * @return boolean
	 */
	function unapprove_threads($tids)
	{
		global $db, $cache, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		if(empty($tids))
		{
			return false;
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

		$forum_counters = $user_counters = $posts_to_unapprove = array();

		$tids_list = implode(",", $tids);
		$query = $db->simple_select("threads", "*", "tid IN ($tids_list)");

		while($thread = $db->fetch_array($query))
		{
			$forum = get_forum($thread['fid']);

			if($thread['visible'] == 1 || $thread['visible'] == -1)
			{
				if(!isset($forum_counters[$forum['fid']]))
				{
					$forum_counters[$forum['fid']] = array(
						'num_threads' => 0,
						'num_posts' => 0,
						'num_unapprovedthreads' => 0,
						'num_unapprovedposts' => 0,
						'num_deletedthreads' => 0,
						'num_deletedposts' => 0
					);
				}

				if(!isset($user_counters[$thread['uid']]))
				{
					$user_counters[$thread['uid']] = array(
						'num_posts' => 0,
						'num_threads' => 0
					);
				}

				++$forum_counters[$forum['fid']]['num_unapprovedthreads'];
				$forum_counters[$forum['fid']]['num_unapprovedposts'] += $thread['replies']+$thread['deletedposts']+1;

				if($thread['visible'] == 1)
				{
					++$forum_counters[$forum['fid']]['num_threads'];
					$forum_counters[$forum['fid']]['num_posts'] += $thread['replies']+1; // Add implied invisible to count
					$forum_counters[$forum['fid']]['num_deletedposts'] += $thread['deletedposts'];
				}
				else
				{
					++$forum_counters[$forum['fid']]['num_deletedthreads'];
					$forum_counters[$forum['fid']]['num_deletedposts'] += $thread['replies']+$thread['unapprovedposts']+$thread['deletedposts']+1; // Add implied invisible to count
					$forum_counters[$forum['fid']]['num_unapprovedposts'] += $thread['unapprovedposts'];
				}

				// On unapproving thread update user post counts
				if($thread['visible'] == 1 && $forum['usepostcounts'] != 0)
				{
					$query2 = $db->simple_select("posts", "COUNT(pid) AS posts, uid", "tid='{$thread['tid']}' AND (visible='1' OR pid='{$thread['firstpost']}') AND uid > 0 GROUP BY uid");
					while($counter = $db->fetch_array($query2))
					{
						if(!isset($user_counters[$counter['uid']]))
						{
							$user_counters[$counter['uid']] = array(
								'num_posts' => 0,
								'num_threads' => 0
							);
						}
						$user_counters[$counter['uid']]['num_posts'] += $counter['posts'];
					}
				}

				if($thread['visible'] == 1 && $forum['usethreadcounts'] != 0 && substr($thread['closed'], 0, 6) != 'moved|')
				{
					++$user_counters[$thread['uid']]['num_threads'];
				}

			}
			$posts_to_unapprove[] = $thread['firstpost'];
		}

		$approve = array(
			"visible" => 0
		);
		$db->update_query("threads", $approve, "tid IN ($tid_list)");
		// Unapprove redirects, too
		$redirect_tids = array();
		$query = $db->simple_select('threads', 'tid', "closed IN ({$tid_moved_list})");
		while($redirect_tid = $db->fetch_field($query, 'tid'))
		{
			$redirect_tids[] = $redirect_tid;
		}
		if(!empty($redirect_tids))
		{
			$this->unapprove_threads($redirect_tids);
		}
		if(!empty($posts_to_unapprove))
		{
			$db->update_query("posts", $approve, "pid IN (".implode(',', $posts_to_unapprove).")");
		}

		$plugins->run_hooks("class_moderation_unapprove_threads", $tids);

		if(!empty($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				// Update stats
				$update_array = array(
					"threads" => "-{$counters['num_threads']}",
					"unapprovedthreads" => "+{$counters['num_unapprovedthreads']}",
					"posts" => "-{$counters['num_posts']}",
					"unapprovedposts" => "+{$counters['num_unapprovedposts']}",
					"deletedthreads" => "-{$counters['num_deletedthreads']}",
					"deletedposts" => "-{$counters['num_deletedposts']}"
				);
				update_forum_counters($fid, $update_array);
				update_forum_lastpost($fid);
			}
		}

		if(!empty($user_counters))
		{
			foreach($user_counters as $uid => $counters)
			{
				$update_array = array(
					"postnum" => "-{$counters['num_posts']}",
					"threadnum" => "-{$counters['num_threads']}",
				);
				update_user_counters($uid, $update_array);
			}
		}

		return true;
	}

	/**
	 * Delete a specific post
	 *
	 * @param int $pid Post ID
	 * @return boolean
	 */
	function delete_post($pid)
	{
		global $db, $cache, $plugins;

		$pid = $plugins->run_hooks("class_moderation_delete_post_start", $pid);
		// Get pid, uid, fid, tid, visibility, forum post count status of post
		$pid = (int)$pid;
		$query = $db->query("
			SELECT p.pid, p.uid, p.fid, p.tid, p.visible, t.visible as threadvisible
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.pid='$pid'
		");
		$post = $db->fetch_array($query);
		if(!$post)
		{
			return false;
		}

		$forum = get_forum($post['fid']);
		// If post counts enabled in this forum and it hasn't already been unapproved, remove 1
		if($forum['usepostcounts'] != 0 && $post['visible'] != -1 && $post['visible'] != 0 && $post['threadvisible'] != 0 && $post['threadvisible'] != -1)
		{
			update_user_counters($post['uid'], array('postnum' => "-1"));
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
		$db->delete_query("reportedcontent", "id='{$pid}' AND (type = 'post' OR type = '')");

		// Update unapproved post count
		if($post['visible'] == 0)
		{
			$update_array = array(
				"unapprovedposts" => "-1"
			);
		}
		elseif($post['visible'] == -1)
		{
			$update_array = array(
				"deletedposts" => "-1"
			);
		}
		else
		{
			$update_array = array(
				"replies" => "-1"
			);
		}

		$plugins->run_hooks("class_moderation_delete_post", $post['pid']);

		update_thread_counters($post['tid'], $update_array);
		update_last_post($post['tid']);

		// Update unapproved post count
		if(($post['visible'] == 0 && $post['threadvisible'] != -1) || $post['threadvisible'] == 0)
		{
			$update_array = array(
				"unapprovedposts" => "-1"
			);
		}
		elseif($post['visible'] == -1 || $post['threadvisible'] == -1)
		{
			$update_array = array(
				"deletedposts" => "-1"
			);
		}
		else
		{
			$update_array = array(
				"posts" => "-1"
			);
		}

		update_forum_counters($post['fid'], $update_array);
		update_forum_lastpost($post['fid']);

		return true;
	}

	/**
	 * Merge posts within thread
	 *
	 * @param array $pids Post IDs to be merged
	 * @param int $tid Thread ID (Set to 0 if posts from multiple threads are selected)
	 * @return int ID of the post into which all other posts are merged
	 */
	function merge_posts($pids=array(), $tid=0, $sep="new_line")
	{
		global $db, $plugins;

		// Make sure we only have valid values
		$pids = array_map('intval', $pids);

		if(empty($pids) || count($pids) < 2)
		{
			return false;
		}

		$pidin = implode(',', $pids);
		$attachment_count = 0;

		$first = 1;
		// Get the messages to be merged
		$query = $db->query("
			SELECT p.pid, p.uid, p.fid, p.tid, p.visible, p.message, t.visible AS threadvisible, t.replies AS threadreplies, t.firstpost AS threadfirstpost, t.unapprovedposts AS threadunapprovedposts, COUNT(a.aid) AS attachmentcount
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."attachments a ON (a.pid=p.pid AND a.visible=1)
			WHERE p.pid IN($pidin)
			GROUP BY p.pid
			ORDER BY p.dateline ASC, p.pid ASC
		");
		$message = '';
		$threads = $forum_counters = $thread_counters = $user_counters = array();
		while($post = $db->fetch_array($query))
		{
			$threads[$post['tid']] = $post['tid'];
			if(!isset($thread_counters[$post['tid']]))
			{
				$thread_counters[$post['tid']] = array(
					'replies' => 0,
					'unapprovedposts' => 0,
					'deletedposts' => 0,
					'attachmentcount' => 0
				);
			}
			if($first == 1)
			{ // all posts will be merged into this one
				$masterpid = $post['pid'];
				$message = $post['message'];
				$fid = $post['fid'];
				$mastertid = $post['tid'];
				$first = 0;
				$visible = $post['visible'];
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

				if(!isset($forum_counters[$post['fid']]))
				{
					$forum_counters[$post['fid']] = array(
						'num_posts' => 0,
						'unapprovedposts' => 0,
						'deletedposts' => 0
					);
				}

				if($post['visible'] == 1)
				{
					--$thread_counters[$post['tid']]['replies'];
					$forum = get_forum($post['fid']);
					if(!isset($user_counters[$post['uid']]))
					{
						$user_counters[$post['uid']] = array(
							'num_posts' => 0,
							'num_threads' => 0
						);
					}
					// Subtract 1 from user's post count
					if($forum['usepostcounts'] != 0 && $post['threadvisible'] == 1)
					{
						// Update post count of the user of the merged posts
						--$user_counters[$post['uid']]['num_posts'];
					}
					if($post['threadfirstpost'] == $post['pid'] && $forum['usethreadcounts'] != 0 && $post['threadvisible'] == 1)
					{
						--$user_counters[$post['uid']]['num_threads'];
					}
					$thread_counters[$post['tid']]['attachmentcount'] -= $post['attachmentcount'];
				}
				elseif($post['visible'] == 0)
				{
					// Subtract 1 unapproved post from post's thread
					--$thread_counters[$post['tid']]['unapprovedposts'];
				}
				elseif($post['visible'] == -1)
				{
					// Subtract 1 deleted post from post's thread
					--$thread_counters[$post['tid']]['deletedposts'];
				}

				// Subtract 1 post from post's forum
				if($post['threadvisible'] == 1 && $post['visible'] == 1)
				{
					--$forum_counters[$post['fid']]['num_posts'];
				}
				elseif($post['threadvisible'] == 0 || ($post['visible'] == 0 && $post['threadvisible'] != -1))
				{
					--$forum_counters[$post['fid']]['unapprovedposts'];
				}
				else
				{
					--$forum_counters[$post['fid']]['deletedposts'];
				}

				// Add attachment count to thread
				if($visible == 1)
				{
					$thread_counters[$mastertid]['attachmentcount'] += $post['attachmentcount'];
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

		// If the first post of a thread is merged out, the first should be updated
		$query = $db->simple_select("threads", "tid, uid, fid, visible", "firstpost IN({$pidin}) AND firstpost != '{$masterpid}'");
		while($thread = $db->fetch_array($query))
		{
			// In some cases the first post of a thread changes
			// Therefore resync the visible field to make sure they're the same if they're not
			$query = $db->simple_select("posts", "pid, uid, visible", "tid='{$thread['tid']}'", array('order_by' => 'dateline, pid', 'limit' => 1));
			$new_firstpost = $db->fetch_array($query);
			if($thread['visible'] != $new_firstpost['visible'])
			{
				$db->update_query("posts", array('visible' => $thread['visible']), "pid='{$new_firstpost['pid']}'");
				// Correct counters
				if($new_firstpost['visible'] == 1)
				{
					--$thread_counters[$thread['tid']]['replies'];
				}
				elseif($new_firstpost['visible'] == -1)
				{
					--$thread_counters[$thread['tid']]['deletedposts'];
				}
				else
				{
					--$thread_counters[$thread['tid']]['unapprovedposts'];
				}
				if($thread['visible'] == 1)
				{
					++$thread_counters[$thread['tid']]['replies'];
				}
				elseif($thread['visible'] == -1)
				{
					++$thread_counters[$thread['tid']]['deletedposts'];
				}
				else
				{
					++$thread_counters[$thread['tid']]['unapprovedposts'];
				}
			}

			if($new_firstpost['uid'] != $thread['uid'] && $forum['usethreadcounts'] != 0 && $thread['visible'] == 1)
			{
				if(!isset($user_counters[$new_firstpost['uid']]))
				{
					$user_counters[$new_firstpost['uid']] = array(
						'num_posts' => 0,
						'num_threads' => 0
					);
				}
				++$user_counters[$new_firstpost['uid']]['num_threads'];
			}
			update_first_post($thread['tid']);
		}

		$arguments = array("pids" => $pids, "tid" => $tid);
		$plugins->run_hooks("class_moderation_merge_posts", $arguments);

		if(!empty($thread_counters))
		{
			foreach($thread_counters as $tid => $counters)
			{
				$counters = array(
					'replies' => signed($counters['replies']),
					'unapprovedposts' => signed($counters['unapprovedposts']),
					'deletedposts' => signed($counters['deletedposts']),
					'attachmentcount' => signed($counters['attachmentcount'])
				);
				update_thread_counters($tid, $counters);
				update_last_post($tid);
			}
		}

		if(!empty($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				$updated_forum_stats = array(
					'posts' => signed($counters['num_posts']),
					'unapprovedposts' => signed($counters['unapprovedposts']),
					'deletedposts' => signed($counters['deletedposts'])
				);
				update_forum_counters($fid, $updated_forum_stats);
				update_forum_lastpost($fid);
			}
		}

		if(!empty($user_counters))
		{
			foreach($user_counters as $uid => $counters)
			{
				$update_array = array(
					"postnum" => "+{$counters['num_posts']}",
					"threadnum" => "+{$counters['num_threads']}"
				);
				update_user_counters($uid, $update_array);
			}
		}

		return $masterpid;
	}

	/**
	 * Move/copy thread
	 *
	 * @param int $tid Thread to be moved
	 * @param int $new_fid Destination forum
	 * @param string $method Method of movement (redirect, copy, move)
	 * @param int $redirect_expire Expiry timestamp for redirect
	 * @return int Thread ID
	 */
	function move_thread($tid, $new_fid, $method="redirect", $redirect_expire=0)
	{
		global $db, $plugins;

		// Get thread info
		$tid = (int)$tid;
		$new_fid = (int)$new_fid;
		$redirect_expire = (int)$redirect_expire;

		$thread = get_thread($tid, true);

		$newforum = get_forum($new_fid);
		if(!$thread || !$newforum)
		{
			return false;
		}
		$fid = $thread['fid'];
		$forum = get_forum($fid);

		$num_threads = $num_unapproved_threads = $num_posts = $num_unapproved_posts = $num_deleted_posts = $num_deleted_threads = 0;

		if($thread['visible'] == 1)
		{
			$num_threads++;
			$num_posts = $thread['replies']+1;
			$num_unapproved_posts = $thread['unapprovedposts'];
			$num_deleted_posts = $thread['deletedposts'];
		}
		elseif($thread['visible'] == -1)
		{
			$num_deleted_threads++;
			// Implied forum deleted count for deleted threads
			$num_deleted_posts = $thread['replies']+$thread['deletedposts']+$thread['unapprovedposts']+1;
		}
		else
		{
			$num_unapproved_threads++;
			// Implied forum unapproved count for unapproved threads
			$num_unapproved_posts = $thread['replies']+$thread['unapprovedposts']+$thread['deletedposts']+1;
		}

		switch($method)
		{
			case "redirect": // move (and leave redirect) thread
				$arguments = array("tid" => $tid, "new_fid" => $new_fid);
				$plugins->run_hooks("class_moderation_move_thread_redirect", $arguments);

				$query = $db->simple_select('threads', 'tid', "closed='moved|$tid' AND fid='$new_fid'");
				while($redirect_tid = $db->fetch_field($query, 'tid'))
				{
					$this->delete_thread($redirect_tid);
				}
				$changefid = array(
					"fid" => $new_fid,
				);
				$db->update_query("threads", $changefid, "tid='$tid'");
				$db->update_query("posts", $changefid, "tid='$tid'");

				// If the thread has a prefix and the destination forum doesn't accept that prefix, remove the prefix
				if($thread['prefix'] != 0)
				{
					switch($db->type)
					{
						case "pgsql":
						case "sqlite":
							$query = $db->simple_select("threadprefixes", "COUNT(*) as num_prefixes", "(','||forums||',' LIKE '%,$new_fid,%' OR forums='-1') AND pid='".$thread['prefix']."'");
							break;
						default:
							$query = $db->simple_select("threadprefixes", "COUNT(*) as num_prefixes", "(CONCAT(',',forums,',') LIKE '%,$new_fid,%' OR forums='-1') AND pid='".$thread['prefix']."'");
					}
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
					"visible" => (int)$thread['visible'],
					"notes" => ''
				);
				$redirect_tid = $db->insert_query("threads", $threadarray);
				if($redirect_expire)
				{
					$this->expire_thread($redirect_tid, $redirect_expire);
				}

				// If we're moving back to a forum where we left a redirect, delete the rediect
				$query = $db->simple_select("threads", "tid", "closed LIKE 'moved|".(int)$tid."' AND fid='".(int)$new_fid."'");
				while($redirect_tid = $db->fetch_field($query, 'tid'))
				{
					$this->delete_thread($redirect_tid);
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
					"visible" => (int)$thread['visible'],
					"unapprovedposts" => $thread['unapprovedposts'],
					"deletedposts" => $thread['deletedposts'],
					"attachmentcount" => $thread['attachmentcount'],
					"prefix" => $thread['prefix'],
					"notes" => ''
				);

				$arguments = array("tid" => $tid, "new_fid" => $new_fid);
				$plugins->run_hooks("class_moderation_copy_thread", $arguments);

				// If the thread has a prefix and the destination forum doesn't accept that prefix, don't copy the prefix
				if($threadarray['prefix'] != 0)
				{
					switch($db->type)
					{
						case "pgsql":
						case "sqlite":
							$query = $db->simple_select("threadprefixes", "COUNT(*) as num_prefixes", "(','||forums||',' LIKE '%,$new_fid,%' OR forums='-1') AND pid='".$thread['prefix']."'");
							break;
						default:
							$query = $db->simple_select("threadprefixes", "COUNT(*) as num_prefixes", "(CONCAT(',',forums,',') LIKE '%,$new_fid,%' OR forums='-1') AND pid='".$thread['prefix']."'");
					}
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
						'votes' => $db->escape_string($poll['votes']),
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
						'ipaddress' => $db->escape_binary($post['ipaddress']),
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
							'filetype' => $db->escape_string($attachment['filetype']),
							'filesize' => $attachment['filesize'],
							'attachname' => $db->escape_string($attachment['attachname']),
							'downloads' => $attachment['downloads'],
							'visible' => $attachment['visible'],
							'thumbnail' => $db->escape_string($attachment['thumbnail'])
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

				$sqlarray = array(
					"fid" => $new_fid,
				);
				$db->update_query("threads", $sqlarray, "tid='$tid'");
				$db->update_query("posts", $sqlarray, "tid='$tid'");

				// If the thread has a prefix and the destination forum doesn't accept that prefix, remove the prefix
				if($thread['prefix'] != 0)
				{
					switch($db->type)
					{
						case "pgsql":
						case "sqlite":
							$query = $db->simple_select("threadprefixes", "COUNT(*) as num_prefixes", "(','||forums||',' LIKE '%,$new_fid,%' OR forums='-1') AND pid='".$thread['prefix']."'");
							break;
						default:
							$query = $db->simple_select("threadprefixes", "COUNT(*) as num_prefixes", "(CONCAT(',',forums,',') LIKE '%,$new_fid,%' OR forums='-1') AND pid='".$thread['prefix']."'");
					}
					if($db->fetch_field($query, "num_prefixes") == 0)
					{
						$sqlarray = array(
							"prefix" => 0,
						);
						$db->update_query("threads", $sqlarray, "tid='$tid'");
					}
				}

				// If we're moving back to a forum where we left a redirect, delete the rediect
				$query = $db->simple_select("threads", "tid", "closed LIKE 'moved|".(int)$tid."' AND fid='".(int)$new_fid."'");
				while($redirect_tid = $db->fetch_field($query, 'tid'))
				{
					$this->delete_thread($redirect_tid);
				}
				break;
		}

		// Do post and thread count changes if changing between countable and non-countable forums
		$query = $db->query("
			SELECT COUNT(p.pid) AS posts, u.uid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.tid='$tid' AND p.visible=1
			GROUP BY u.uid
			ORDER BY posts DESC
		");
		while($posters = $db->fetch_array($query))
		{
			$pcount = 0;
			if($forum['usepostcounts'] == 1 && $method != 'copy' && $newforum['usepostcounts'] == 0 && $thread['visible'] == 1)
			{
				$pcount -= $posters['posts'];
			}
			if(($forum['usepostcounts'] == 0 || $method == 'copy') && $newforum['usepostcounts'] == 1 && $thread['visible'] == 1)
			{
				$pcount += $posters['posts'];
			}

			if($pcount > 0)
			{
				update_user_counters($posters['uid'], array('postnum' => "+$pcount"));
			}
			elseif($pcount < 0)
			{
				update_user_counters($posters['uid'], array('postnum' => $pcount));
			}
		}

		if($forum['usethreadcounts'] == 1 && $method != 'copy' && $newforum['usethreadcounts'] == 0 && $thread['visible'] == 1)
		{
			update_user_counters($thread['uid'], array('threadnum' => "-1"));
		}
		elseif(($forum['usethreadcounts'] == 0 || $method == 'copy') && $newforum['usethreadcounts'] == 1 && $thread['visible'] == 1)
		{
			update_user_counters($thread['uid'], array('threadnum' => "+1"));
		}

		// Update forum counts
		$update_array = array(
			"threads" => "+{$num_threads}",
			"unapprovedthreads" => "+{$num_unapproved_threads}",
			"posts" => "+{$num_posts}",
			"unapprovedposts" => "+{$num_unapproved_posts}",
			"deletedthreads" => "+{$num_deleted_threads}",
			"deletedposts" => "+{$num_deleted_posts}"
		);
		update_forum_counters($new_fid, $update_array);
		update_forum_lastpost($new_fid);

		if($method != "copy")
		{
			// The redirect needs to be counted, too
			if($method == "redirect")
			{
				if($thread['visible'] == -1)
				{
					--$num_deleted_threads;
					--$num_deleted_posts;
				}
				elseif($thread['visible'] == 0)
				{
					--$num_unapproved_threads;
					--$num_unapproved_posts;
				}
				else
				{
					--$num_threads;
					--$num_posts;
				}
			}
			$update_array = array(
				"threads" => "-{$num_threads}",
				"unapprovedthreads" => "-{$num_unapproved_threads}",
				"posts" => "-{$num_posts}",
				"unapprovedposts" => "-{$num_unapproved_posts}",
				"deletedthreads" => "-{$num_deleted_threads}",
				"deletedposts" => "-{$num_deleted_posts}"
			);
			update_forum_counters($fid, $update_array);
			update_forum_lastpost($fid);
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
	 * @param int $mergetid Thread that will be merged into destination
	 * @param int $tid Destination thread
	 * @param string $subject New thread subject
	 * @return boolean
	 */
	function merge_threads($mergetid, $tid, $subject)
	{
		global $db, $mybb, $mergethread, $thread, $plugins, $cache;

		$mergetid = (int)$mergetid;
		$tid = (int)$tid;

		if(!isset($mergethread['tid']) || $mergethread['tid'] != $mergetid)
		{
			$mergethread = get_thread($mergetid);
		}
		if(!isset($thread['tid']) || $thread['tid'] != $tid)
		{
			$thread = get_thread($tid);
		}

		if(!$mergethread || !$thread)
		{
			return false;
		}

		$forum_cache = $cache->read('forums');

		$threadarray = array();
		if(!$thread['poll'] && $mergethread['poll'])
		{
			$threadarray['poll'] = $mergethread['poll'];
			$sqlarray = array(
				"tid" => $tid,
			);
			$db->update_query("polls", $sqlarray, "tid='".(int)$mergethread['tid']."'");
		}
		// Both the old and the new thread have polls? Remove one
		elseif($mergethread['poll'])
		{
			$db->delete_query("polls", "pid='{$mergethread['poll']}'");
			$db->delete_query("pollvotes", "pid='{$mergethread['poll']}'");
		}

		$subject = $db->escape_string($subject);
		$threadarray['subject'] = $subject;

		$user_posts = array();
		if($thread['visible'] != $mergethread['visible'] || $forum_cache[$thread['fid']]['usepostcounts'] != $forum_cache[$mergethread['fid']]['usepostcounts'])
		{
			$query = $db->query("
				SELECT uid, COUNT(pid) AS postnum
				FROM ".TABLE_PREFIX."posts
				WHERE tid='{$mergetid}' AND visible=1
				GROUP BY uid
			");
			while($post = $db->fetch_array($query))
			{
				// Update user counters
				if($mergethread['visible'] == 1 && $forum_cache[$mergethread['fid']]['usepostcounts'] == 1)
				{
					$user_posts[$post['uid']]['postnum'] -= $post['postnum'];
				}
				elseif($thread['visible'] == 1 && $forum_cache[$thread['fid']]['usepostcounts'] == 1)
				{
					$user_posts[$post['uid']]['postnum'] += $post['postnum'];
				}
			}
		}

		$sqlarray = array(
			"tid" => $tid,
			"fid" => $thread['fid'],
			"replyto" => 0,
		);
		$db->update_query("posts", $sqlarray, "tid='{$mergetid}'");

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

		$threadarray["numratings"] = $new_numrating;
		$threadarray["totalratings"] = $new_threadrating;
		$db->update_query("threads", $threadarray, "tid = '{$tid}'");

		// Check if we have a thread subscription already for our new thread
		$subscriptions = array();

		$query = $db->simple_select("threadsubscriptions", "tid, uid", "tid='{$mergetid}' OR tid='{$tid}'");
		while($subscription = $db->fetch_array($query))
		{
			if(!isset($subscriptions[$subscription['tid']]))
			{
				$subscriptions[$subscription['tid']] = array();
			}
			$subscriptions[$subscription['tid']][] = $subscription['uid'];
		}

		// Update any subscriptions for the merged thread
		if(!empty($subscriptions[$mergetid]))
 		{
			$update_users = array();
			foreach($subscriptions[$mergetid] as $user)
			{
				if(!isset($subscriptions[$tid]) || !in_array($user, $subscriptions[$tid]))
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

		$arguments = array("mergetid" => $mergetid, "tid" => $tid, "subject" => $subject);
		$plugins->run_hooks("class_moderation_merge_threads", $arguments);

		$this->delete_thread($mergetid);

		// Add the former first post
		if($mergethread['visible'] == 1)
		{
			++$mergethread['replies'];
		}
		elseif($mergethread['visible'] == -1)
		{
			++$mergethread['deletedposts'];
		}
		else
		{
			++$mergethread['unapprovedposts'];
		}

		// In some cases the thread we may be merging with may cause us to have a new firstpost if it is an older thread
		// Therefore resync the visible field to make sure they're the same if they're not
		$query = $db->simple_select("posts", "pid, uid, visible", "tid='{$tid}'", array('order_by' => 'dateline, pid', 'limit' => 1));
		$new_firstpost = $db->fetch_array($query);
		if($thread['visible'] != $new_firstpost['visible'])
		{
			$db->update_query("posts", array('visible' => $thread['visible']), "pid='{$new_firstpost['pid']}'");
			if($new_firstpost['visible'] == 1 && $forum_cache[$thread['fid']]['usepostcounts'] == 1)
			{
				--$user_posts[$post['uid']]['postnum'];
			}
			elseif($thread['visible'] == 1 && $forum_cache[$thread['fid']]['usepostcounts'] == 1)
			{
				++$user_posts[$post['uid']]['postnum'];
			}
		}
		// Update first post if needed
		if($new_firstpost['pid'] != $thread['firstpost'])
		{
			update_first_post($thread['tid']);
		}

		// Update thread count if thread has a new firstpost and is visible
		if($thread['uid'] != $new_firstpost['uid'] && $thread['visible'] == 1 && $forum_cache[$thread['fid']]['usethreadcounts'] == 1)
		{
			if(!isset($user_posts[$thread['uid']]['threadnum']))
			{
				$user_posts[$thread['uid']]['threadnum'] = 0;
			}
			--$user_posts[$thread['uid']]['threadnum'];
			if(!isset($user_posts[$new_firstpost['uid']]['threadnum']))
			{
				$user_posts[$new_firstpost['uid']]['threadnum'] = 0;
			}
			++$user_posts[$new_firstpost['uid']]['threadnum'];
		}

		// Thread is not in current forum
		if($mergethread['fid'] != $thread['fid'])
		{
			// If new thread is unapproved, implied counter comes in to effect
			if($thread['visible'] == 0)
			{
				$updated_stats = array(
					"unapprovedposts" => '+'.($mergethread['replies']+$mergethread['unapprovedposts']+$mergethread['deletedposts'])
				);
			}
			elseif($thread['visible'] == -1)
			{
				$updated_stats = array(
					"deletedposts" => '+'.($mergethread['replies']+$mergethread['deletedposts']+$mergethread['unapprovedposts'])
				);
			}
			else
			{
				$updated_stats = array(
					"posts" => "+{$mergethread['replies']}",
					"unapprovedposts" => "+{$mergethread['unapprovedposts']}",
					"deletedposts" => "+{$mergethread['deletedposts']}"
				);
			}
			update_forum_counters($thread['fid'], $updated_stats);

			// If old thread is unapproved, implied counter comes in to effect
			if($mergethread['visible'] == 0)
			{
				$updated_stats = array(
					"unapprovedposts" => '-'.($mergethread['replies']+$mergethread['unapprovedposts']+$mergethread['deletedposts'])
				);
			}
			elseif($mergethread['visible'] == -1)
			{
				$updated_stats = array(
					"deletedposts" => '-'.($mergethread['replies']+$mergethread['deletedposts']+$mergethread['unapprovedposts'])
				);
			}
			else
			{
				$updated_stats = array(
					"posts" => "-{$mergethread['replies']}",
					"unapprovedposts" => "-{$mergethread['unapprovedposts']}",
					"deletedposts" => "-{$mergethread['deletedposts']}"
				);
			}
			update_forum_counters($mergethread['fid'], $updated_stats);
			update_forum_lastpost($mergethread['fid']);
		}
		// Visibility changed
		elseif($mergethread['visible'] != $thread['visible'])
		{
			$updated_stats = array(
				'posts' => 0,
				'unapprovedposts' => 0,
				'deletedposts' => 0
			);

			// If old thread is unapproved, implied counter comes in to effect
			if($mergethread['visible'] == 0)
			{
				$updated_stats['unapprovedposts'] -= $mergethread['replies']+$mergethread['deletedposts'];
				$updated_stats['posts'] += $mergethread['replies'];
				$updated_stats['deletedposts'] += $mergethread['deletedposts'];
			}
			elseif($mergethread['visible'] == -1)
			{
				$updated_stats['deletedposts'] -= $mergethread['replies']+$mergethread['unapprovedposts'];
				$updated_stats['posts'] += $mergethread['replies'];
				$updated_stats['unapprovedposts'] += $mergethread['unapprovedposts'];
			}

			// If new thread is unapproved, implied counter comes in to effect
			if($thread['visible'] == 0)
			{
				$updated_stats['unapprovedposts'] += $mergethread['replies']+$mergethread['deletedposts'];
				$updated_stats['posts'] -= $mergethread['replies'];
				$updated_stats['deletedposts'] -= $mergethread['deletedposts'];
			}
			elseif($thread['visible'] == -1)
			{
				$updated_stats['deletedposts'] += $mergethread['replies']+$mergethread['unapprovedposts'];
				$updated_stats['posts'] -= $mergethread['replies'];
				$updated_stats['unapprovedposts'] -= $mergethread['unapprovedposts'];
			}

			$new_stats = array();
			if($updated_stats['posts'] < 0)
			{
				$new_stats['posts'] = $updated_stats['posts'];
			}
			elseif($updated_stats['posts'] > 0)
			{
				$new_stats['posts'] = "+{$updated_stats['posts']}";
			}

			if($updated_stats['unapprovedposts'] < 0)
			{
				$new_stats['unapprovedposts'] = $updated_stats['unapprovedposts'];
			}
			elseif($updated_stats['unapprovedposts'] > 0)
			{
				$new_stats['unapprovedposts'] = "+{$updated_stats['unapprovedposts']}";
			}

			if($updated_stats['deletedposts'] < 0)
			{
				$new_stats['deletedposts'] = $updated_stats['deletedposts'];
			}
			elseif($updated_stats['deletedposts'] > 0)
			{
				$new_stats['deletedposts'] = "+{$updated_stats['deletedposts']}";
			}

			if(!empty($new_stats))
			{
				update_forum_counters($mergethread['fid'], $new_stats);
				update_forum_lastpost($mergethread['fid']);
			}
		}

		if($thread['visible'] != $new_firstpost['visible'])
		{
			// Correct counters
			if($new_firstpost['visible'] == 1)
			{
				--$mergethread['replies'];
			}
			elseif($new_firstpost['visible'] == -1)
			{
				--$mergethread['deletedposts'];
			}
			else
			{
				--$mergethread['unapprovedposts'];
			}
			if($thread['visible'] == 1)
			{
				++$mergethread['replies'];
			}
			elseif($thread['visible'] == -1)
			{
				++$mergethread['deletedposts'];
			}
			else
			{
				++$mergethread['unapprovedposts'];
			}
		}

		// Update user counters
		foreach($user_posts as $uid => $counters)
		{
			$update_array = array(
				"postnum" => "+{$counters['postnum']}",
				"threadnum" => "+{$counters['threadnum']}",
			);
			update_user_counters($uid, $update_array);
		}

		$updated_stats = array(
			"replies" => "+{$mergethread['replies']}",
			"attachmentcount" => "+{$mergethread['attachmentcount']}",
			"unapprovedposts" => "+{$mergethread['unapprovedposts']}",
			"deletedposts" => "+{$mergethread['deletedposts']}"
		);
		update_thread_counters($tid, $updated_stats);
		update_last_post($tid);

		// Forum last post has to be updated after thread
		update_forum_lastpost($thread['fid']);
		return true;
	}

	/**
	 * Split posts into a new/existing thread
	 *
	 * @param array $pids PIDs of posts to split
	 * @param int $tid Original thread ID (this is only used as a base for the new
	 * thread; it can be set to 0 when the posts specified are coming from more
	 * than 1 thread)
	 * @param int $moveto Destination forum
	 * @param string $newsubject New thread subject
	 * @param int $destination_tid TID if moving into existing thread
	 * @return int|bool New thread ID or false on failure
	 */
	function split_posts($pids, $tid, $moveto, $newsubject, $destination_tid=0)
	{
		global $db, $thread, $plugins, $cache;

		$tid = (int)$tid;
		$moveto = (int)$moveto;
		$newtid = (int)$destination_tid;

		// Make sure we only have valid values
		$pids = array_map('intval', $pids);

		$pids_list = implode(',', $pids);

		// Get forum infos
		$forum_cache = $cache->read('forums');

		if(empty($pids) || !$forum_cache[$moveto])
		{
			return false;
		}

		// Get the first split post
		$query = $db->simple_select('posts', 'pid,uid,visible,icon,username,dateline', 'pid IN ('.$pids_list.')', array('order_by' => 'dateline, pid', 'limit' => 1));

		$post_info = $db->fetch_array($query);

		$visible = $post_info['visible'];

		$forum_counters[$moveto] = array(
			'threads' => 0,
			'deletedthreads' => 0,
			'unapprovedthreads' => 0,
			'posts' => 0,
			'unapprovedposts' => 0,
			'deletedposts' => 0
		);

		$user_counters = array();

		if($destination_tid == 0)
		{
			// Splitting into a new thread
			// Create the new thread
			$newsubject = $db->escape_string($newsubject);
			$newthread = array(
				"fid" => $moveto,
				"subject" => $newsubject,
				"icon" => (int)$post_info['icon'],
				"uid" => (int)$post_info['uid'],
				"username" => $db->escape_string($post_info['username']),
				"dateline" => (int)$post_info['dateline'],
				"firstpost" => $post_info['pid'],
				"lastpost" => 0,
				"lastposter" => '',
				"visible" => (int)$visible,
				"notes" => ''
			);
			$newtid = $db->insert_query("threads", $newthread);

			if($visible == 1)
			{
				++$forum_counters[$moveto]['threads'];
				if(!isset($user_counters[$newthread['uid']]))
				{
					$user_counters[$newthread['uid']] = array(
						'postnum' => 0,
						'threadnum' => 0
					);
				}
				++$user_counters[$newthread['uid']]['threadnum'];
			}
			elseif($visible == -1)
			{
				++$forum_counters[$moveto]['deletedthreads'];
			}
			else
			{
				// Unapproved thread?
				++$forum_counters[$moveto]['unapprovedthreads'];
			}
		}
		else
		{
			$newthread = get_thread($newtid);
			if(!$newthread)
			{
				return false;
			}
			$moveto = $newthread['fid'];
		}

		// Get selected posts before moving forums to keep old fid
		$original_posts_query = $db->query("
			SELECT p.pid, p.tid, p.fid, p.visible, p.uid, p.dateline, t.visible as threadvisible, t.firstpost, COUNT(a.aid) as postattachmentcount
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (p.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."attachments a ON (a.pid=p.pid AND a.visible=1)
			WHERE p.pid IN ($pids_list)
			GROUP BY p.pid
		");

		// Move the selected posts over
		$sqlarray = array(
			"tid" => $newtid,
			"fid" => $moveto,
			"replyto" => 0
		);
		$db->update_query("posts", $sqlarray, "pid IN ($pids_list)");

		$thread_counters[$newtid] = array(
			'replies' => 0,
			'unapprovedposts' => 0,
			'deletedposts' => 0,
			'attachmentcount' => 0
		);

		// Get posts being merged
		while($post = $db->fetch_array($original_posts_query))
		{
			if(!isset($thread_counters[$post['tid']]))
			{
				$thread_counters[$post['tid']] = array(
					'replies' => 0,
					'unapprovedposts' => 0,
					'deletedposts' => 0,
					'attachmentcount' => 0
				);
			}
			if(!isset($forum_counters[$post['fid']]))
			{
				$forum_counters[$post['fid']] = array(
					'posts' => 0,
					'unapprovedposts' => 0,
					'deletedposts' => 0
				);
			}
			if(!isset($user_counters[$post['uid']]))
			{
				$user_counters[$post['uid']] = array(
					'postnum' => 0,
					'threadnum' => 0
				);
			}
			if($post['visible'] == 1)
			{
				// Modify users' post counts
				if($post['threadvisible'] == 1 && $forum_cache[$post['fid']]['usepostcounts'] == 1 && ($forum_cache[$moveto]['usepostcounts'] == 0 || $newthread['visible'] != 1))
				{
					// Moving into a forum that doesn't count post counts
					--$user_counters[$post['uid']]['postnum'];
				}

				// Subtract 1 from the old thread's replies
				--$thread_counters[$post['tid']]['replies'];
			}
			elseif($post['visible'] == 0)
			{
				// Unapproved post
				// Subtract 1 from the old thread's unapproved posts
				--$thread_counters[$post['tid']]['unapprovedposts'];
			}
			elseif($post['visible'] == -1)
			{
				// Soft deleted post
				// Subtract 1 from the old thread's deleted posts
				--$thread_counters[$post['tid']]['deletedposts'];
			}

			// Subtract 1 from the old forum's posts
			if($post['threadvisible'] == 1 && $post['visible'] == 1)
			{
				--$forum_counters[$post['fid']]['posts'];
			}
			elseif($post['threadvisible'] == 0 || ($post['visible'] == 0 && $post['threadvisible'] == 1))
			{
				--$forum_counters[$post['fid']]['unapprovedposts'];
			}
			else
			{
				--$forum_counters[$post['fid']]['deletedposts'];
			}

			// Subtract attachment counts from old thread and add to new thread (which are counted regardless of post or attachment unapproval at time of coding)
			$thread_counters[$post['tid']]['attachmentcount'] -= $post['postattachmentcount'];
			$thread_counters[$newtid]['attachmentcount'] += $post['postattachmentcount'];

			if($post['firstpost'] == $post['pid'])
			{
				// In some cases the first post of a thread changes
				// Therefore resync the visible field to make sure they're the same if they're not
				$query = $db->simple_select("posts", "pid, visible, uid", "tid='{$post['tid']}'", array('order_by' => 'dateline, pid', 'limit' => 1));
				$new_firstpost = $db->fetch_array($query);

				if(!isset($user_counters[$new_firstpost['uid']]))
				{
					$user_counters[$new_firstpost['uid']] = array(
						'postnum' => 0,
						'threadnum' => 0
					);
				}

				// Update post counters if visibility changes
				if($post['threadvisible'] != $new_firstpost['visible'])
				{
					$db->update_query("posts", array('visible' => $post['threadvisible']), "pid='{$new_firstpost['pid']}'");
					// Subtract new first post
					if($new_firstpost['visible'] == 1)
					{
						--$thread_counters[$post['tid']]['replies'];
						if($post['threadvisible'] == 1 && $forum_cache[$post['fid']]['usepostcounts'] == 1)
						{
							--$user_counters[$new_firstpost['uid']]['postnum'];
						}
					}
					elseif($new_firstpost['visible'] == -1)
					{
						--$thread_counters[$post['tid']]['deletedposts'];
					}
					else
					{
						--$thread_counters[$post['tid']]['unapprovedposts'];
					}
					if($post['threadvisible'] == 0 || ($new_firstpost['visible'] == 0 && $post['threadvisible'] == 1))
					{
						--$forum_counters[$post['fid']]['unapprovedposts'];
					}
					else
					{
						--$forum_counters[$post['fid']]['deletedposts'];
					}

					// Add old first post
					if($post['threadvisible'] == 1)
					{
						++$thread_counters[$post['tid']]['replies'];
						++$forum_counters[$post['fid']]['posts'];
						if($forum_cache[$post['fid']]['usepostcounts'] == 1)
						{
							++$user_counters[$new_firstpost['uid']]['postnum'];
						}
					}
					elseif($post['threadvisible'] == -1)
					{
						++$thread_counters[$post['tid']]['deletedposts'];
						++$forum_counters[$post['fid']]['deletedposts'];
					}
					else
					{
						++$thread_counters[$post['tid']]['unapprovedposts'];
						++$forum_counters[$post['fid']]['unapprovedposts'];
					}
				}

				// Update user thread counter if thread opener changes
				if($post['threadvisible'] == 1 && $forum_cache[$post['fid']]['usethreadcounts'] == 1 && $post['uid'] != $new_firstpost['uid'])
				{
					// Subtract thread from old thread opener
					--$user_counters[$post['uid']]['threadnum'];
					// Add thread to new thread opener
					++$user_counters[$new_firstpost['uid']]['threadnum'];
				}
				update_first_post($post['tid']);
			}

			// This is the new first post of an existing thread?
			if($post['pid'] == $post_info['pid'] && $post['dateline'] < $newthread['dateline'])
			{
				// Update post counters if visibility changes
				if($post['visible'] != $newthread['visible'])
				{
					$db->update_query("posts", array('visible' => $newthread['visible']), "pid='{$post['pid']}'");

					// This is needed to update the forum counters correctly
					$post['visible'] = $newthread['visible'];
				}

				// Update user thread counter if thread opener changes
				if($newthread['visible'] == 1 && $forum_cache[$newthread['fid']]['usethreadcounts'] == 1 && $post['uid'] != $newthread['uid'])
				{
					// Add thread to new thread opener
					++$user_counters[$post['uid']]['threadnum'];
					if(!isset($user_counters[$newthread['uid']]))
					{
						$user_counters[$newthread['uid']] = array(
							'postnum' => 0,
							'threadnum' => 0
						);
					}
					// Subtract thread from old thread opener
					--$user_counters[$newthread['uid']]['threadnum'];
				}
				update_first_post($newtid);
			}

			if($post['visible'] == 1)
			{
				// Modify users' post counts
				if($newthread['visible'] == 1 && ($forum_cache[$post['fid']]['usepostcounts'] == 0 || $post['threadvisible'] != 1) && $forum_cache[$moveto]['usepostcounts'] == 1)
				{
					// Moving into a forum that does count post counts
					++$user_counters[$post['uid']]['postnum'];
				}

				// Add 1 to the new thread's replies
				++$thread_counters[$newtid]['replies'];
			}
			elseif($post['visible'] == 0)
			{
				// Unapproved post
				// Add 1 to the new thread's unapproved posts
				++$thread_counters[$newtid]['unapprovedposts'];
			}
			elseif($post['visible'] == -1)
			{
				// Soft deleted post
				// Add 1 to the new thread's deleted posts
				++$thread_counters[$newtid]['deletedposts'];
			}

			// Add 1 to the new forum's posts
			if($newthread['visible'] == 1 && $post['visible'] == 1)
			{
				++$forum_counters[$moveto]['posts'];
			}
			elseif($newthread['visible'] == 0 || ($post['visible'] == 0 && $newthread['visible'] == 1))
			{
				++$forum_counters[$moveto]['unapprovedposts'];
			}
			else
			{
				++$forum_counters[$moveto]['deletedposts'];
			}
		}

		if($destination_tid == 0 && $newthread['visible'] == 1)
		{
			// If splitting into a new thread, subtract one from the thread's reply count to compensate for the original post
			--$thread_counters[$newtid]['replies'];
		}
		elseif($destination_tid == 0 && $newthread['visible'] == 0)
		{
			// If splitting into a new thread, subtract one from the thread's reply count to compensate for the original post
			--$thread_counters[$newtid]['unapprovedposts'];
		}
		elseif($destination_tid == 0 && $newthread['visible'] == -1)
		{
			// If splitting into a new thread, subtract one from the thread's reply count to compensate for the original post
			--$thread_counters[$newtid]['deletedposts'];
		}

		$arguments = array("pids" => $pids, "tid" => $tid, "moveto" => $moveto, "newsubject" => $newsubject, "destination_tid" => $destination_tid);
		$plugins->run_hooks("class_moderation_split_posts", $arguments);

		// Update user post counts
		if(!empty($user_counters))
		{
			foreach($user_counters as $uid => $counters)
			{
				foreach($counters as $key => $counter)
				{
					if($counter >= 0)
					{
						$counters[$key] = "+{$counter}"; // add the addition operator for query
					}
				}
				update_user_counters($uid, $counters);
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
					$query = $db->simple_select("posts", "pid", "tid='$newtid'", array('order_by' => 'dateline, pid', 'limit' => 1));
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
						ORDER BY p.dateline ASC, p.pid ASC
						LIMIT 1
					");
					$oldthread = $db->fetch_array($query);
					$sqlarray = array(
						"subject" => $db->escape_string($oldthread['subject']),
						"replyto" => 0
					);
					$db->update_query("posts", $sqlarray, "pid='{$oldthread['pid']}'");
				}

				foreach($counters as $key => $counter)
				{
					if($counter >= 0)
					{
						$counters[$key] = "+{$counter}";
					}
				}
				update_thread_counters($tid, $counters);
				update_last_post($tid);
			}
		}

		// Update forum counters
		if(!empty($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				foreach($counters as $key => $counter)
				{
					if($counter >= 0)
					{
						$counters[$key] = "+{$counter}";
					}
				}
				update_forum_counters($fid, $counters);
				update_forum_lastpost($fid);
			}
		}

		return $newtid;
	}

	/**
	 * Move multiple threads to new forum
	 *
	 * @param array $tids Thread IDs
	 * @param int $moveto Destination forum
	 * @return boolean
	 *
	 * @deprecated Iterate over move_thread instead
	 */
	function move_threads($tids, $moveto)
	{
		global $db, $plugins;

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$tid_list = implode(',', $tids);

		$moveto = (int)$moveto;

		$newforum = get_forum($moveto);

		if(empty($tids) || !$newforum)
		{
			return false;
		}

		$total_posts = $total_unapproved_posts = $total_deleted_posts = $total_threads = $total_unapproved_threads = $total_deleted_threads = 0;
		$forum_counters = $user_counters = array();
		$query = $db->simple_select("threads", "fid, visible, replies, unapprovedposts, deletedposts, tid, uid", "tid IN ($tid_list)");
		while($thread = $db->fetch_array($query))
		{
			$forum = get_forum($thread['fid']);

			if(!isset($forum_counters[$thread['fid']]))
			{
				$forum_counters[$thread['fid']] = array(
					'posts' => 0,
					'threads' => 0,
					'unapprovedposts' => 0,
					'unapprovedthreads' => 0,
					'deletedthreads' => 0,
					'deletedposts' => 0
				);
			}

			if(!isset($user_counters[$thread['uid']]))
			{
				$user_counters[$thread['uid']] = array(
					'num_posts' => 0,
					'num_threads' => 0
				);
			}

			if($thread['visible'] == 1)
			{
				$total_posts += $thread['replies']+1;
				$total_unapproved_posts += $thread['unapprovedposts'];
				$total_deleted_posts += $thread['deletedposts'];
				$forum_counters[$thread['fid']]['posts'] += $thread['replies']+1;
				$forum_counters[$thread['fid']]['unapprovedposts'] += $thread['unapprovedposts'];
				$forum_counters[$thread['fid']]['deletedposts'] += $thread['deletedposts'];

				$forum_counters[$thread['fid']]['threads']++;
				++$total_threads;

				if($newforum['usethreadcounts'] == 1 && $forum['usethreadcounts'] == 0)
				{
					++$user_counters[$thread['uid']]['num_threads'];
				}
				else if($newforum['usethreadcounts'] == 0 && $forum['usethreadcounts'] == 1)
				{
					--$user_counters[$thread['uid']]['num_threads'];
				}

				$query1 = $db->query("
					SELECT COUNT(p.pid) AS posts, u.uid
					FROM ".TABLE_PREFIX."posts p
					LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
					WHERE p.tid = '{$thread['tid']}' AND p.visible=1
					GROUP BY u.uid
					ORDER BY posts DESC
				");
				while($posters = $db->fetch_array($query1))
				{
					if(!isset($user_counters[$posters['uid']]))
					{
						$user_counters[$posters['uid']] = array(
							'num_posts' => 0,
							'num_threads' => 0
						);
					}

					if($newforum['usepostcounts'] != 0 && $forum['usepostcounts'] == 0)
					{
						$user_counters[$posters['uid']]['num_posts'] += $posters['posts'];
					}
					else if($newforum['usepostcounts'] == 0 && $forum['usepostcounts'] != 0)
					{
						$user_counters[$posters['uid']]['num_posts'] -= $posters['posts'];
					}
				}
			}
			elseif($thread['visible'] == -1)
			{
				$total_deleted_posts += $thread['replies']+$thread['unapprovedposts']+$thread['deletedposts']+1;

				$forum_counters[$thread['fid']]['deletedposts'] += $thread['replies']+$thread['unapprovedposts']+$thread['deletedposts']+1; // Implied deleted posts counter for deleted threads

				$forum_counters[$thread['fid']]['deletedthreads']++;
				++$total_deleted_threads;
			}
			else
			{
				$total_unapproved_posts += $thread['replies']+$thread['unapprovedposts']+$thread['deletedposts']+1;

				$forum_counters[$thread['fid']]['unapprovedposts'] += $thread['replies']+$thread['unapprovedposts']+$thread['deletedposts']+1; // Implied unapproved posts counter for unapproved threads

				$forum_counters[$thread['fid']]['unapprovedthreads']++;
				++$total_unapproved_threads;
			}

			// Remove old redirects
			$redirects_query = $db->simple_select('threads', 'tid', "closed='moved|{$thread['tid']}' AND fid='$moveto'");
			while($redirect_tid = $db->fetch_field($redirects_query, 'tid'))
			{
				$this->delete_thread($redirect_tid);
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
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					$query = $db->simple_select("threadprefixes", "COUNT(*) as num_prefixes", "(','||forums||',' LIKE '%,$moveto,%' OR forums='-1') AND pid='".$thread['prefix']."'");
					break;
				default:
					$query = $db->simple_select("threadprefixes", "COUNT(*) as num_prefixes", "(CONCAT(',',forums,',') LIKE '%,$moveto,%' OR forums='-1') AND pid='".$thread['prefix']."'");
			}
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

		if(!empty($user_counters))
		{
			foreach($user_counters as $uid => $counters)
			{
				$update_array = array(
					"postnum" => "+{$counters['num_posts']}",
					"threadnum" => "+{$counters['num_threads']}",
				);
				update_user_counters($uid, $update_array);
			}
		}

		if(is_array($forum_counters))
		{
			foreach($forum_counters as $fid => $counter)
			{
				$updated_count = array(
					'posts' => "-{$counter['posts']}",
					'threads' => "-{$counter['threads']}",
					'unapprovedposts' => "-{$counter['unapprovedposts']}",
					'unapprovedthreads' => "-{$counter['unapprovedthreads']}",
					'deletedposts' => "-{$counter['deletedposts']}",
					'deletedthreads' => "-{$counter['deletedthreads']}"

				);
				update_forum_counters($fid, $updated_count);
				update_forum_lastpost($fid);
			}
		}

		$updated_count = array(
			"threads" => "+{$total_threads}",
			"unapprovedthreads" => "+{$total_unapproved_threads}",
			"posts" => "+{$total_posts}",
			"unapprovedposts" => "+{$total_unapproved_posts}",
			'deletedposts' => "+{$total_deleted_posts}",
			"deletedthreads" => "+{$total_deleted_threads}"
		);

		update_forum_counters($moveto, $updated_count);
		update_forum_lastpost($moveto);

		// Remove thread subscriptions for the users who no longer have permission to view the thread
		$this->remove_thread_subscriptions($tid_list, false, $moveto);

		return true;
	}

	/**
	 * Approve multiple posts
	 *
	 * @param array $pids PIDs
	 * @return boolean
	 */
	function approve_posts($pids)
	{
		global $db, $cache, $plugins;

		$num_posts = 0;

		if(empty($pids))
		{
			return false;
		}

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

		$thread_counters = $forum_counters = $user_counters = array();

		$query = $db->query("
			SELECT p.pid, p.tid, p.fid, p.uid, t.visible AS threadvisible
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.pid IN ($pid_list) AND p.visible = '0' AND t.firstpost != p.pid
		");
		while($post = $db->fetch_array($query))
		{
			$pids[] = $post['pid'];

			if(!isset($thread_counters[$post['tid']]))
			{
				$thread_counters[$post['tid']] = array(
					'replies' => 0
				);
			}

			++$thread_counters[$post['tid']]['replies'];

			// If the thread of this post is unapproved then we've already taken into account this counter as implied.
			// Updating it again would cause it to double count
			if($post['threadvisible'] == 1)
			{
				if(!isset($forum_counters[$post['fid']]))
				{
					$forum_counters[$post['fid']] = array(
						'num_posts' => 0
					);
				}
				++$forum_counters[$post['fid']]['num_posts'];
			}

			$forum = get_forum($post['fid']);

			// If post counts enabled in this forum and the thread is approved, add 1
			if($forum['usepostcounts'] != 0 && $post['threadvisible'] == 1)
			{
				if(!isset($user_counters[$post['uid']]))
				{
					$user_counters[$post['uid']] = 0;
				}
				++$user_counters[$post['uid']];
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

		$plugins->run_hooks("class_moderation_approve_posts", $pids);

		if(!empty($thread_counters))
		{
			foreach($thread_counters as $tid => $counters)
			{
				$counters_update = array(
					"unapprovedposts" => "-".$counters['replies'],
					"replies" => "+".$counters['replies']
				);
				update_thread_counters($tid, $counters_update);
				update_last_post($tid);
			}
		}

		if(!empty($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				$updated_forum_stats = array(
					'posts' => "+{$counters['num_posts']}",
					'unapprovedposts' => "-{$counters['num_posts']}",
				);
				update_forum_counters($fid, $updated_forum_stats);
				update_forum_lastpost($fid);
			}
		}

		if(!empty($user_counters))
		{
			foreach($user_counters as $uid => $counter)
			{
				update_user_counters($uid, array('postnum' => "+{$counter}"));
			}
		}

		return true;
	}

	/**
	 * Unapprove multiple posts
	 *
	 * @param array $pids PIDs
	 * @return boolean
	 */
	function unapprove_posts($pids)
	{
		global $db, $cache, $plugins;

		if(empty($pids))
		{
			return false;
		}

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
		// 1.3) if the thread is deleted
		// 2) We're unapproving the firstpost of the thread, therefore unapproving the thread itself
		// 3) We're doing both 1 and 2
		$query = $db->query("
			SELECT p.tid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.pid IN ($pid_list) AND p.visible IN (-1,1) AND t.firstpost = p.pid AND t.visible IN (-1,1)
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

		$thread_counters = $forum_counters = $user_counters = array();

		$query = $db->query("
			SELECT p.pid, p.tid, p.visible, p.fid, p.uid, t.visible AS threadvisible
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.pid IN ($pid_list) AND p.visible IN (-1,1) AND t.firstpost != p.pid
		");
		while($post = $db->fetch_array($query))
		{
			$pids[] = $post['pid'];

			if(!isset($thread_counters[$post['tid']]))
			{
				$thread_counters[$post['tid']] = array(
					'replies' => 0,
					'unapprovedposts' => 0,
					'deletedposts' => 0
				);
			}

			++$thread_counters[$post['tid']]['unapprovedposts'];
			if($post['visible'] == 1)
			{
				++$thread_counters[$post['tid']]['replies'];
			}
			else
			{
				++$thread_counters[$post['tid']]['deletedposts'];
			}

			if(!isset($forum_counters[$post['fid']]))
			{
				$forum_counters[$post['fid']] = array(
					'num_posts' => 0,
					'num_unapproved_posts' => 0,
					'num_deleted_posts' => 0
				);
			}

			// If the thread of this post is unapproved then we've already taken into account this counter as implied.
			// Updating it again would cause it to double count
			if($post['threadvisible'] != 0)
			{
				++$forum_counters[$post['fid']]['num_unapproved_posts'];
				if($post['visible'] == 1)
				{
					++$forum_counters[$post['fid']]['num_posts'];
				}
				else
				{
					++$forum_counters[$post['fid']]['num_deleted_posts'];
				}
			}

			$forum = get_forum($post['fid']);

			// If post counts enabled in this forum and the thread is approved, subtract 1
			if($forum['usepostcounts'] != 0 && $post['visible'] == 1 && $post['threadvisible'] == 1)
			{
				if(!isset($user_counters[$post['uid']]))
				{
					$user_counters[$post['uid']] = 0;
				}
				--$user_counters[$post['uid']];
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

		$plugins->run_hooks("class_moderation_unapprove_posts", $pids);

		if(!empty($thread_counters))
		{
			foreach($thread_counters as $tid => $counters)
			{
				$counters_update = array(
					"unapprovedposts" => "+".$counters['unapprovedposts'],
					"replies" => "-".$counters['replies'],
					"deletedposts" => "-".$counters['deletedposts']
				);

				update_thread_counters($tid, $counters_update);
				update_last_post($tid);
			}
		}

		if(!empty($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				$updated_forum_stats = array(
					'posts' => "-{$counters['num_posts']}",
					'unapprovedposts' => "+{$counters['num_unapproved_posts']}",
					'deletedposts' => "-{$counters['num_deleted_posts']}"
				);
				update_forum_counters($fid, $updated_forum_stats);
				update_forum_lastpost($fid);
			}
		}

		if(!empty($user_counters))
		{
			foreach($user_counters as $uid => $counter)
			{
				update_user_counters($uid, array('postnum' => "{$counter}"));
			}
		}

		return true;
	}

	/**
	 * Change thread subject
	 *
	 * @param int|array $tids Thread ID(s)
	 * @param string $format Format of new subject (with {subject})
	 * @return boolean
	 */
	function change_thread_subject($tids, $format)
	{
		global $db, $mybb, $plugins;

		// Get tids into list
		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		if(empty($tids))
		{
			return false;
		}

		$tid_list = implode(',', $tids);

		// Get original subject
		$query = $db->query("
			SELECT u.uid, u.username, t.tid, t.subject FROM ".TABLE_PREFIX."threads t
			LEFT JOIN ".TABLE_PREFIX."users u ON t.uid=u.uid
			WHERE tid IN ($tid_list)
		");
		while($thread = $db->fetch_array($query))
		{
			// Update threads and first posts with new subject
			$find = array('{username}', 'author', '{subject}');
			$replace = array($mybb->user['username'], $thread['username'], $thread['subject']);

			$new_subject = str_ireplace($find, $replace, $format);

			$args = array(
				'thread' => &$thread,
				'new_subject' => &$new_subject,
			);

			$plugins->run_hooks("class_moderation_change_thread_subject_newsubject", $args);

			$update_subject = array(
				"subject" => $db->escape_string($new_subject)
			);
			$db->update_query("threads", $update_subject, "tid='{$thread['tid']}'");
			$db->update_query("posts", $update_subject, "tid='{$thread['tid']}' AND replyto='0'");
		}

		$arguments = array("tids" => $tids, "format" => $format);
		$plugins->run_hooks("class_moderation_change_thread_subject", $arguments);

		return true;
	}

	/**
	 * Add thread expiry
	 *
	 * @param int $tid Thread ID
	 * @param int $deletetime Timestamp when the thread is deleted
	 * @return boolean
	 */
	function expire_thread($tid, $deletetime)
	{
		global $db, $plugins;

		$tid = (int)$tid;

		if(empty($tid))
		{
			return false;
		}

		$update_thread = array(
			"deletetime" => (int)$deletetime
		);
		$db->update_query("threads", $update_thread, "tid='{$tid}'");

		$arguments = array("tid" => $tid, "deletetime" => $deletetime);
		$plugins->run_hooks("class_moderation_expire_thread", $arguments);

		return true;
	}

	/**
	 * Toggle post visibility (approved/unapproved)
	 *
	 * @param array $pids Post IDs
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
			if($post['visible'] != 0)
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
	 * Toggle post visibility (deleted/restored)
	 *
	 * @param array $pids Post IDs
	 * @return boolean true
	 */
	function toggle_post_softdelete($pids)
	{
		global $db;

		// Make sure we only have valid values
		$pids = array_map('intval', $pids);

		$pid_list = implode(',', $pids);
		$query = $db->simple_select("posts", 'pid, visible', "pid IN ($pid_list)");
		while($post = $db->fetch_array($query))
		{
			if($post['visible'] != -1)
			{
				$delete[] = $post['pid'];
			}
			else
			{
				$restore[] = $post['pid'];
			}
		}
		if(is_array($delete))
		{
			$this->soft_delete_posts($delete);
		}
		if(is_array($restore))
		{
			$this->restore_posts($restore);
		}
		return true;
	}

	/**
	 * Toggle thread visibility (approved/unapproved)
	 *
	 * @param array $tids Thread IDs
	 * @param int $fid Forum ID
	 * @return boolean true
	 */
	function toggle_thread_visibility($tids, $fid)
	{
		global $db;

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);
		$fid = (int)$fid;

		$tid_list = implode(',', $tids);
		$query = $db->simple_select("threads", 'tid, visible', "tid IN ($tid_list)");
		while($thread = $db->fetch_array($query))
		{
			if($thread['visible'] != 0)
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
	 * Toggle thread visibility (deleted/restored)
	 *
	 * @param array $tids Thread IDs
	 * @return boolean true
	 */
	function toggle_thread_softdelete($tids)
	{
		global $db;

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$tid_list = implode(',', $tids);
		$query = $db->simple_select("threads", 'tid, visible', "tid IN ($tid_list)");
		while($thread = $db->fetch_array($query))
		{
			if($thread['visible'] != -1)
			{
				$delete[] = $thread['tid'];
			}
			else
			{
				$restore[] = $thread['tid'];
			}
		}
		if(is_array($delete))
		{
			$this->soft_delete_threads($delete);
		}
		if(is_array($restore))
		{
			$this->restore_threads($restore);
		}
		return true;
	}

	/**
	 * Toggle threads open/closed
	 *
	 * @param array $tids Thread IDs
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
	 * Toggle threads stick/unstick
	 *
	 * @param array $tids Thread IDs
	 * @return boolean true
	 */
	function toggle_thread_importance($tids)
	{
		global $db;

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$stick = array();
		$unstick = array();

		$tid_list = implode(',', $tids);
		$query = $db->simple_select("threads", 'tid, sticky', "tid IN ($tid_list)");
		while($thread = $db->fetch_array($query))
		{
			if($thread['sticky'] == 0)
			{
				$stick[] = $thread['tid'];
			}
			elseif($thread['sticky'] == 1)
			{
				$unstick[] = $thread['tid'];
			}
		}
		if(!empty($stick))
		{
			$this->stick_threads($stick);
		}
		if(!empty($unstick))
		{
			$this->unstick_threads($unstick);
		}
		return true;
	}

	/**
	 * Remove thread subscriptions (from one or multiple threads in the same forum)
	 *
	 * @param int|array $tids Thread ID, or an array of thread IDs from the same forum.
	 * @param boolean $all True (default) to delete all subscriptions, false to only delete subscriptions from users with no permission to read the thread
	 * @param int $fid (Only applies if $all is false) The forum ID of the thread
	 * @return boolean
	 */
	function remove_thread_subscriptions($tids, $all = true, $fid = 0)
	{
		global $db, $plugins;

		// Format thread IDs
		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		if(empty($tids))
		{
			return false;
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);
		$fid = (int)$fid;

		$tids_csv = implode(',', $tids);

		// Delete only subscriptions from users who no longer have permission to read the thread.
		if(!$all)
		{
			// Get groups that cannot view the forum or its threads
			$forum_parentlist = get_parent_list($fid);
			$query = $db->simple_select("forumpermissions", "gid", "fid IN ({$forum_parentlist}) AND (canview=0 OR canviewthreads=0)");
			$groups = array();
			$additional_groups = '';
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
	 * @param int|array $tids Thread ID, or an array of thread IDs from the same forum.
	 * @param int $prefix Prefix ID to apply to the threads
	 * @return bool
	 */
	function apply_thread_prefix($tids, $prefix = 0)
	{
		global $db, $plugins;

		// Format thread IDs
		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		if(empty($tids))
		{
			return false;
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);
		$tids_csv = implode(',', $tids);

		$update_thread = array('prefix' => (int)$prefix);
		$db->update_query('threads', $update_thread, "tid IN ({$tids_csv})");

		$arguments = array('tids' => $tids, 'prefix' => $prefix);

		$plugins->run_hooks('class_moderation_apply_thread_prefix', $arguments);

		return true;
	}

	/**
	 * Soft delete multiple posts
	 *
	 * @param array $pids PIDs
	 * @return boolean
	 */
	function soft_delete_posts($pids)
	{
		global $db, $cache, $plugins;

		if(empty($pids))
		{
			return false;
		}

		// Make sure we only have valid values
		$pids = array_map('intval', $pids);

		$pid_list = implode(',', $pids);
		$pids = $threads_to_update = array();

		// Make invisible
		$update = array(
			"visible" => -1,
		);

		// We have three cases we deal with in these code segments:
		// 1) We're deleting specific approved posts
		// 1.1) if the thread is approved
		// 1.2) if the thread is unapproved
		// 1.3) if the thread is deleted
		// 2) We're deleting the firstpost of the thread, therefore deleting the thread itself
		// 3) We're doing both 1 and 2
		$query = $db->query("
			SELECT p.tid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.pid IN ($pid_list) AND p.visible IN (0,1) AND t.firstpost = p.pid AND t.visible IN (0,1)
		");
		while($post = $db->fetch_array($query))
		{
			// This is the first post in the thread so we're deleting the whole thread.
			$threads_to_update[] = $post['tid'];
		}

		if(!empty($threads_to_update))
		{
			$this->soft_delete_threads($threads_to_update);
		}

		$thread_counters = $forum_counters = $user_counters = array();

		$query = $db->query("
			SELECT p.pid, p.tid, p.visible, f.fid, f.usepostcounts, p.uid, t.visible AS threadvisible
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
			WHERE p.pid IN ($pid_list) AND p.visible IN (0,1) AND t.firstpost != p.pid
		");
		while($post = $db->fetch_array($query))
		{
			$pids[] = $post['pid'];

			if(!isset($thread_counters[$post['tid']]))
			{
				$thread_counters[$post['tid']] = array(
					'replies' => 0,
					'unapprovedposts' => 0,
					'deletedposts' => 0
				);
			}

			++$thread_counters[$post['tid']]['deletedposts'];
			if($post['visible'] == 1)
			{
				++$thread_counters[$post['tid']]['replies'];
			}
			else
			{
				++$thread_counters[$post['tid']]['unapprovedposts'];
			}

			if(!isset($forum_counters[$post['fid']]))
			{
				$forum_counters[$post['fid']] = array(
					'num_posts' => 0,
					'num_unapproved_posts' => 0,
					'num_deleted_posts' => 0
				);
			}

			// If the thread of this post is deleted then we've already taken into account this counter as implied.
			// Updating it again would cause it to double count
			if($post['threadvisible'] == 1)
			{
				++$forum_counters[$post['fid']]['num_deleted_posts'];
				if($post['visible'] == 1)
				{
					++$forum_counters[$post['fid']]['num_posts'];
				}
				else
				{
					++$forum_counters[$post['fid']]['num_unapproved_posts'];
				}
			}

			// If post counts enabled in this forum and the thread is approved, subtract 1
			if($post['usepostcounts'] != 0 && $post['threadvisible'] == 1 && $post['visible'] == 1)
			{
				if(!isset($user_counters[$post['uid']]))
				{
					$user_counters[$post['uid']] = 0;
				}
				--$user_counters[$post['uid']];
			}
		}

		if(empty($pids) && empty($threads_to_update))
		{
			return false;
		}

		if(!empty($pids))
		{
			$where = "pid IN (".implode(',', $pids).")";
			$db->update_query("posts", $update, $where);
			mark_reports($pids, "posts");
		}

		$plugins->run_hooks("class_moderation_soft_delete_posts", $pids);

		if(is_array($thread_counters))
		{
			foreach($thread_counters as $tid => $counters)
			{
				$counters_update = array(
					"unapprovedposts" => "-".$counters['unapprovedposts'],
					"replies" => "-".$counters['replies'],
					"deletedposts" => "+".$counters['deletedposts']
				);

				update_thread_counters($tid, $counters_update);
				update_last_post($tid);
			}
		}

		if(is_array($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				$updated_forum_stats = array(
					'posts' => "-{$counters['num_posts']}",
					'unapprovedposts' => "-{$counters['num_unapproved_posts']}",
					'deletedposts' => "+{$counters['num_deleted_posts']}"
				);
				update_forum_counters($fid, $updated_forum_stats);
				update_forum_lastpost($fid);
			}
		}

		if(!empty($user_counters))
		{
			foreach($user_counters as $uid => $counter)
			{
				update_user_counters($uid, array('postnum' => "{$counter}"));
			}
		}

		return true;
	}

	/**
	 * Restore multiple posts
	 *
	 * @param array $pids PIDs
	 * @return boolean
	 */
	function restore_posts($pids)
	{
		global $db, $cache, $plugins;

		$num_posts = 0;

		if(empty($pids))
		{
			return false;
		}

		// Make sure we only have valid values
		$pids = array_map('intval', $pids);

		$pid_list = implode(',', $pids);
		$pids = $threads_to_update = array();

		// Make visible
		$update = array(
			"visible" => 1,
		);

		// We have three cases we deal with in these code segments:
		// 1) We're approving specific restored posts
		// 1.1) if the thread is deleted
		// 1.2) if the thread is restored
		// 2) We're restoring the firstpost of the thread, therefore restoring the thread itself
		// 3) We're doing both 1 and 2
		$query = $db->query("
			SELECT p.tid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.pid IN ($pid_list) AND p.visible = '-1' AND t.firstpost = p.pid AND t.visible = -1
		");
		while($post = $db->fetch_array($query))
		{
			// This is the first post in the thread so we're approving the whole thread.
			$threads_to_update[] = $post['tid'];
		}

		if(!empty($threads_to_update))
		{
			$this->restore_threads($threads_to_update);
		}

		$thread_counters = $forum_counters = $user_counters = array();

		$query = $db->query("
			SELECT p.pid, p.tid, f.fid, f.usepostcounts, p.uid, t.visible AS threadvisible
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
			WHERE p.pid IN ($pid_list) AND p.visible = '-1' AND t.firstpost != p.pid
		");
		while($post = $db->fetch_array($query))
		{
			$pids[] = $post['pid'];

			if(!isset($thread_counters[$post['tid']]))
			{
				$thread_counters[$post['tid']] = array(
					'replies' => 0
				);
			}

			++$thread_counters[$post['tid']]['replies'];

			// If the thread of this post is deleted then we've already taken into account this counter as implied.
			// Updating it again would cause it to double count
			if($post['threadvisible'] == 1)
			{
				if(!isset($forum_counters[$post['fid']]))
				{
					$forum_counters[$post['fid']] = array(
						'num_posts' => 0
					);
				}
				++$forum_counters[$post['fid']]['num_posts'];
			}

			// If post counts enabled in this forum and the thread is approved, add 1
			if($post['usepostcounts'] != 0 && $post['threadvisible'] == 1)
			{
				if(!isset($user_counters[$post['uid']]))
				{
					$user_counters[$post['uid']] = 0;
				}
				++$user_counters[$post['uid']];

			}
		}

		if(empty($pids) && empty($threads_to_update))
		{
			return false;
		}

		if(!empty($pids))
		{
			$where = "pid IN (".implode(',', $pids).")";
			$db->update_query("posts", $update, $where);
		}

		$plugins->run_hooks("class_moderation_restore_posts", $pids);

		if(is_array($thread_counters))
		{
			foreach($thread_counters as $tid => $counters)
			{
				$counters_update = array(
					"deletedposts" => "-".$counters['replies'],
					"replies" => "+".$counters['replies']
				);
				update_thread_counters($tid, $counters_update);
				update_last_post($tid);
			}
		}

		if(is_array($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				$updated_forum_stats = array(
					'posts' => "+{$counters['num_posts']}",
					'deletedposts' => "-{$counters['num_posts']}"
				);
				update_forum_counters($fid, $updated_forum_stats);
				update_forum_lastpost($fid);
			}
		}

		if(!empty($user_counters))
		{
			foreach($user_counters as $uid => $counter)
			{
				update_user_counters($uid, array('postnum' => "+{$counter}"));
			}
		}

		return true;
	}

	/**
	 * Restore one or more threads
	 *
	 * @param array|int $tids Thread ID(s)
	 * @return boolean true
	 */
	function restore_threads($tids)
	{
		global $db, $cache, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		if(empty($tids))
		{
			return false;
		}

		// Make sure we only have valid values
		$tids = array_map('intval', $tids);

		$tid_list = $forum_counters = $user_counters = $posts_to_restore = array();

		$tids_list = implode(",", $tids);
		$query = $db->simple_select("threads", "*", "tid IN ($tids_list)");

		while($thread = $db->fetch_array($query))
		{
			if($thread['visible'] != -1)
			{
				continue;
			}
			$tid_list[] = $thread['tid'];

			$forum = get_forum($thread['fid']);

			if(!isset($forum_counters[$forum['fid']]))
			{
				$forum_counters[$forum['fid']] = array(
					'num_posts' => 0,
					'num_threads' => 0,
					'num_deleted_posts' => 0,
					'num_unapproved_posts' => 0
				);
			}

			if(!isset($user_counters[$thread['uid']]))
			{
				$user_counters[$thread['uid']] = array(
					'num_posts' => 0,
					'num_threads' => 0
				);
			}

			++$forum_counters[$forum['fid']]['num_threads'];
			$forum_counters[$forum['fid']]['num_posts'] += $thread['replies']+1; // Remove implied visible from count
			$forum_counters[$forum['fid']]['num_deleted_posts'] += $thread['replies']+$thread['unapprovedposts']+1;
			$forum_counters[$forum['fid']]['num_unapproved_posts'] += $thread['unapprovedposts'];

			if($forum['usepostcounts'] != 0)
			{
				// On approving thread restore user post counts
				$query2 = $db->simple_select("posts", "COUNT(pid) as posts, uid", "tid='{$thread['tid']}' AND (visible='1' OR pid='{$thread['firstpost']}') AND uid > 0 GROUP BY uid");
				while($counter = $db->fetch_array($query2))
				{
					if(!isset($user_counters[$counter['uid']]))
					{
						$user_counters[$counter['uid']] = array(
							'num_posts' => 0,
							'num_threads' => 0
						);
					}
					$user_counters[$counter['uid']]['num_posts'] += $counter['posts'];
				}
			}

			if($forum['usethreadcounts'] != 0 && substr($thread['closed'], 0, 6) != 'moved|')
			{
				++$user_counters[$thread['uid']]['num_threads'];
			}

			$posts_to_restore[] = $thread['firstpost'];
		}

		if(!empty($tid_list))
		{
			$tid_moved_list = "";
			$comma = "";
			foreach($tid_list as $tid)
			{
				$tid_moved_list .= "{$comma}'moved|{$tid}'";
				$comma = ",";
			}
			$tid_list = implode(',', $tid_list);
			$update = array(
				"visible" => 1
			);
			$db->update_query("threads", $update, "tid IN ($tid_list)");
			// Restore redirects, too
			$redirect_tids = array();
			$query = $db->simple_select('threads', 'tid', "closed IN ({$tid_moved_list})");
			while($redirect_tid = $db->fetch_field($query, 'tid'))
			{
				$redirect_tids[] = $redirect_tid;
			}
			if(!empty($redirect_tids))
			{
				$this->restore_threads($redirect_tids);
			}
			if(!empty($posts_to_restore))
			{
				$db->update_query("posts", $update, "pid IN (".implode(',', $posts_to_restore).")");
			}

			$plugins->run_hooks("class_moderation_restore_threads", $tids);

			if(is_array($forum_counters))
			{
				foreach($forum_counters as $fid => $counters)
				{
					// Update stats
					$update_array = array(
						"threads" => "+{$counters['num_threads']}",
						"posts" => "+{$counters['num_posts']}",
						"unapprovedposts" => "+{$counters['num_unapproved_posts']}",
						"deletedposts" => "-{$counters['num_deleted_posts']}",
						"deletedthreads" => "-{$counters['num_threads']}"
					);
					update_forum_counters($fid, $update_array);
					update_forum_lastpost($fid);
				}
			}

			if(!empty($user_counters))
			{
				foreach($user_counters as $uid => $counters)
				{
					$update_array = array(
						"postnum" => "+{$counters['num_posts']}",
						"threadnum" => "+{$counters['num_threads']}",
					);
					update_user_counters($uid, $update_array);
				}
			}
		}
		return true;
	}

	/**
	 * Soft delete one or more threads
	 *
	 * @param array|int Thread ID(s)
	 * @return boolean
	 */
	function soft_delete_threads($tids)
	{
		global $db, $cache, $plugins;

		if(!is_array($tids))
		{
			$tids = array($tids);
		}

		if(empty($tids))
		{
			return false;
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

		$forum_counters = $user_counters = $posts_to_delete = array();

		$tids_list = implode(",", $tids);
		$query = $db->simple_select("threads", "*", "tid IN ($tids_list)");

		while($thread = $db->fetch_array($query))
		{
			$forum = get_forum($thread['fid']);

			if($thread['visible'] == 1 || $thread['visible'] == 0)
			{
				if(!isset($forum_counters[$forum['fid']]))
				{
					$forum_counters[$forum['fid']] = array(
						'num_posts' => 0,
						'num_threads' => 0,
						'num_deleted_threads' => 0,
						'num_deleted_posts' => 0,
						'unapproved_threads' => 0,
						'unapproved_posts' => 0
					);
				}

				if(!isset($user_counters[$thread['uid']]))
				{
					$user_counters[$thread['uid']] = array(
						'num_posts' => 0,
						'num_threads' => 0
					);
				}

				++$forum_counters[$forum['fid']]['num_deleted_threads'];
				$forum_counters[$forum['fid']]['num_deleted_posts'] += $thread['replies']+$thread['unapprovedposts']+1;

				if($thread['visible'] == 1)
				{
					++$forum_counters[$forum['fid']]['num_threads'];
					$forum_counters[$forum['fid']]['num_posts'] += $thread['replies']+1; // Add implied invisible to count
					$forum_counters[$forum['fid']]['unapproved_posts'] += $thread['unapprovedposts'];
				}
				else
				{
					++$forum_counters[$forum['fid']]['unapproved_threads'];
					$forum_counters[$forum['fid']]['unapproved_posts'] += $thread['replies']+$thread['deletedposts']+$thread['unapprovedposts']+1; // Add implied invisible to count
					$forum_counters[$forum['fid']]['num_deleted_posts'] += $thread['deletedposts'];
				}

				// On unapproving thread update user post counts
				if($thread['visible'] == 1 && $forum['usepostcounts'] != 0)
				{
					$query2 = $db->simple_select("posts", "COUNT(pid) AS posts, uid", "tid='{$thread['tid']}' AND (visible='1' OR pid='{$thread['firstpost']}') AND uid > 0 GROUP BY uid");
					while($counter = $db->fetch_array($query2))
					{
						if(!isset($user_counters[$counter['uid']]))
						{
							$user_counters[$counter['uid']] = array(
								'num_posts' => 0,
								'num_threads' => 0
							);
						}
						$user_counters[$counter['uid']]['num_posts'] += $counter['posts'];
					}
				}

				if($thread['visible'] == 1 && $forum['usethreadcounts'] != 0 && substr($thread['closed'], 0, 6) != 'moved|')
				{
					++$user_counters[$thread['uid']]['num_threads'];
				}
			}
			$posts_to_delete[] = $thread['firstpost'];
		}

		$update = array(
			"visible" => -1
		);
		$db->update_query("threads", $update, "tid IN ($tid_list)");
		// Soft delete redirects, too
		$redirect_tids = array();
		$query = $db->simple_select('threads', 'tid', "closed IN ({$tid_moved_list})");

		mark_reports($tids, "threads");

		while($redirect_tid = $db->fetch_field($query, 'tid'))
		{
			$redirect_tids[] = $redirect_tid;
		}
		if(!empty($redirect_tids))
		{
			$this->soft_delete_threads($redirect_tids);
		}
		if(!empty($posts_to_delete))
		{
			$db->update_query("posts", $update, "pid IN (".implode(',', $posts_to_delete).")");
		}

		$plugins->run_hooks("class_moderation_soft_delete_threads", $tids);

		if(is_array($forum_counters))
		{
			foreach($forum_counters as $fid => $counters)
			{
				// Update stats
				$update_array = array(
					"threads" => "-{$counters['num_threads']}",
					"unapprovedthreads" => "-{$counters['unapproved_threads']}",
					"posts" => "-{$counters['num_posts']}",
					"unapprovedposts" => "-{$counters['unapproved_posts']}",
					"deletedposts" => "+{$counters['num_deleted_posts']}",
					"deletedthreads" => "+{$counters['num_deleted_threads']}"
				);
				update_forum_counters($fid, $update_array);
				update_forum_lastpost($fid);
			}
		}

		if(!empty($user_counters))
		{
			foreach($user_counters as $uid => $counters)
			{
				$update_array = array(
					"postnum" => "-{$counters['num_posts']}",
					"threadnum" => "-{$counters['num_threads']}",
				);
				update_user_counters($uid, $update_array);
			}
		}

		return true;
	}
}
