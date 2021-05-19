<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * Used to execute a custom moderation tool
 *
 */

class CustomModeration extends Moderation
{
	/**
	 * Get info on a tool
	 *
	 * @param int $tool_id Tool ID
	 * @return array|bool Returns tool data (tid, type, name, description) in an array, otherwise boolean false.
	 */
	function tool_info($tool_id)
	{
		global $db;

		// Get tool info
		$query = $db->simple_select("modtools", "*", 'tid='.(int)$tool_id);
		$tool = $db->fetch_array($query);
		if(!$tool['tid'])
		{
			return false;
		}
		else
		{
			return $tool;
		}
	}

	/**
	 * Execute Custom Moderation Tool
	 *
	 * @param int $tool_id Tool ID
	 * @param int|array Thread ID(s)
	 * @param int|array Post ID(s)
	 * @return string 'forum' or 'default' indicating where to redirect
	 */
	function execute($tool_id, $tids=0, $pids=0)
	{
		global $db;

		// Get tool info
		$query = $db->simple_select("modtools", '*', 'tid='.(int)$tool_id);
		$tool = $db->fetch_array($query);
		if(!$tool['tid'])
		{
			return false;
		}

		// Format single tid and pid
		if(!is_array($tids))
		{
			$tids = array($tids);
		}
		if(!is_array($pids))
		{
			$pids = array($pids);
		}

		// Unserialize custom moderation
		$post_options = my_unserialize($tool['postoptions']);
		$thread_options = my_unserialize($tool['threadoptions']);

		// If the tool type is a post tool, then execute the post moderation
		$deleted_thread = 0;
		if($tool['type'] == 'p')
		{
			$deleted_thread = $this->execute_post_moderation($tids, $post_options, $pids);
		}
		// Always execute thead moderation
		$this->execute_thread_moderation($thread_options, $tids);

		// If the thread is deleted, indicate to the calling script to redirect to the forum, and not the nonexistant thread
		if($thread_options['deletethread'] == 1 || $deleted_thread === 1)
		{
			return 'forum';
		}
		return 'default';
	}

	/**
	 * Execute Inline Post Moderation
	 *
	 * @param array|int $tid Thread IDs (in order of dateline ascending). Only the first one will be used
	 * @param array $post_options Moderation information
	 * @param array $pids Post IDs
	 *
	 * @return boolean true
	 */
	function execute_post_moderation($tid, $post_options=array(), $pids=array())
	{
		global $db, $mybb, $lang, $plugins;

		if(is_array($tid))
		{
			$tid = (int)$tid[0]; // There's only 1 thread when doing inline post moderation
			// The thread chosen is the first thread in the array of tids.
			// It is recommended that this be the tid of the oldest post
		}

		// Get the information about thread
		$thread = get_thread($tid);
		$author = get_user($thread['uid']);

		$args = array(
			'post_options' => &$post_options,
			'pids' => &$pids,
			'thread' => &$thread,
		);

		$plugins->run_hooks("class_custommoderation_execute_post_moderation_start", $args);

		// If deleting posts, only do that
		if($post_options['deleteposts'] == 1)
		{
			foreach($pids as $pid)
			{
				$this->delete_post($pid);
			}

			$delete_tids = array();
			$imploded_pids = implode(",", array_map("intval", $pids));
			$query = $db->simple_select("threads", "tid", "firstpost IN ({$imploded_pids})");
			while($threadid = $db->fetch_field($query, "tid"))
			{
				$delete_tids[] = $threadid;
			}
			if(!empty($delete_tids))
			{
				foreach($delete_tids as $delete_tid)
				{
					$this->delete_thread($delete_tid);
				}
				// return true here so the code in execute() above knows to redirect to the forum
				return true;
			}
		}
		else
		{
			if($post_options['mergeposts'] == 1) // Merge posts
			{
				$this->merge_posts($pids);
			}

			if($post_options['approveposts'] == 'approve') // Approve posts
			{
				$this->approve_posts($pids);
			}
			elseif($post_options['approveposts'] == 'unapprove') // Unapprove posts
			{
				$this->unapprove_posts($pids);
			}
			elseif($post_options['approveposts'] == 'toggle') // Toggle post visibility
			{
				$this->toggle_post_visibility($pids);
			}

			if($post_options['softdeleteposts'] == 'softdelete') // Soft delete posts
			{
				$this->soft_delete_posts($pids);
			}
			elseif($post_options['softdeleteposts'] == 'restore') // Restore posts
			{
				$this->restore_posts($pids);
			}
			elseif($post_options['softdeleteposts'] == 'toggle') // Toggle post visibility
			{
				$this->toggle_post_softdelete($pids);
			}

			if($post_options['splitposts'] > 0 || $post_options['splitposts'] == -2) // Split posts
			{
				$query = $db->simple_select("posts", "COUNT(*) AS totalposts", "tid='{$tid}'");
				$count = $db->fetch_array($query);

				if($count['totalposts'] == 1)
				{
					error($lang->error_cantsplitonepost);
				}

				if($count['totalposts'] == count($pids))
				{
					error($lang->error_cantsplitall);
				}

				if($post_options['splitposts'] == -2)
				{
					$post_options['splitposts'] = $thread['fid'];
				}
				if(empty($post_options['splitpostsnewsubject']))
				{
					// Enter in a subject if a predefined one does not exist.
					$post_options['splitpostsnewsubject'] = "{$lang->split_thread_subject} {$thread['subject']}";
				}

				$find = array('{username}', '{author}', '{subject}');
				$replace = array($mybb->user['username'], $author['username'], $thread['subject']);

				$new_subject = str_ireplace($find, $replace, $post_options['splitpostsnewsubject']);

				$args = array(
					'post_options' => &$post_options,
					'pids' => &$pids,
					'thread' => &$thread,
					'new_subject' => &$new_subject,
				);

				$plugins->run_hooks("class_custommoderation_splitposts", $args);

				$new_thread_subject = $new_subject;
				$new_tid = $this->split_posts($pids, $tid, $post_options['splitposts'], $new_thread_subject);

				if($post_options['splitpostsclose'] == 'close') // Close new thread
				{
					$this->close_threads($new_tid);
				}
				if($post_options['splitpostsstick'] == 'stick') // Stick new thread
				{
					$this->stick_threads($new_tid);
				}
				if($post_options['splitpostsunapprove'] == 'unapprove') // Unapprove new thread
				{
					$this->unapprove_threads($new_tid, $thread['fid']);
				}
				if($post_options['splitthreadprefix'] != '0')
				{
					$this->apply_thread_prefix($new_tid, $post_options['splitthreadprefix']); // Add thread prefix to new thread
				}
				if(!empty($post_options['splitpostsaddreply'])) // Add reply to new thread
				{
					require_once MYBB_ROOT."inc/datahandlers/post.php";
					$posthandler = new PostDataHandler("insert");

					$find = array('{username}', '{author}', '{subject}');
					$replace = array($mybb->user['username'], $author['username'], $new_thread_subject);

					if(empty($post_options['splitpostsreplysubject']))
					{
						$new_subject = 'RE: '.$new_thread_subject;
					}
					else
					{
						$new_subject = str_ireplace($find, $replace, $post_options['splitpostsreplysubject']);
					}

					$new_message = str_ireplace($find, $replace, $post_options['splitpostsaddreply']);

					$args = array(
						'post_options' => &$post_options,
						'pids' => &$pids,
						'thread' => &$thread,
						'new_subject' => &$new_subject,
						'new_message' => &$new_message,
					);

					$plugins->run_hooks("class_custommoderation_splitpostsaddreply", $args);

					// Set the post data that came from the input to the $post array.
					$post = array(
						"tid" => $new_tid,
						"fid" => $post_options['splitposts'],
						"subject" => $new_subject,
						"uid" => $mybb->user['uid'],
						"username" => $mybb->user['username'],
						"message" => $new_message,
						"ipaddress" => my_inet_pton(get_ip()),
					);
					// Set up the post options from the input.
					$post['options'] = array(
						"signature" => 1,
						"emailnotify" => 0,
						"disablesmilies" => 0
					);

					$posthandler->set_data($post);

					if($posthandler->validate_post($post))
					{
						$posthandler->insert_post($post);
					}
				}
			}
		}

		$args = array(
			'post_options' => &$post_options,
			'pids' => &$pids,
			'thread' => &$thread,
		);

		$plugins->run_hooks("class_custommoderation_execute_post_moderation_end", $args);

		return true;
	}

	/**
	 * Execute Normal and Inline Thread Moderation
	 *
	 * @param array $thread_options Moderation information
	 * @param array Thread IDs. Only the first one will be used, but it needs to be an array
	 * @return boolean true
	 */
	function execute_thread_moderation($thread_options=array(), $tids=array())
	{
		global $db, $mybb, $plugins;

		$tid = (int)$tids[0]; // Take the first thread to get thread data from
		$query = $db->simple_select("threads", 'fid', "tid='$tid'");
		$thread = $db->fetch_array($query);

		$args = array(
			'thread_options' => &$thread_options,
			'tids' => &$tids,
			'thread' => &$thread,
		);

		$plugins->run_hooks("class_custommoderation_execute_thread_moderation_start", $args);

		// If deleting threads, only do that
		if($thread_options['deletethread'] == 1)
		{
			foreach($tids as $tid)
			{
				$this->delete_thread($tid);
			}
		}
		else
		{
			if($thread_options['mergethreads'] == 1 && count($tids) > 1) // Merge Threads (ugly temp code until find better fix)
			{
				$tid_list = implode(',', $tids);
				$options = array('order_by' => 'dateline', 'order_dir' => 'DESC');
				$query = $db->simple_select("threads", 'tid, subject', "tid IN ($tid_list)", $options); // Select threads from newest to oldest
				$last_tid = 0;
				while($tid = $db->fetch_array($query))
				{
					if($last_tid != 0)
					{
						$this->merge_threads($last_tid, $tid['tid'], $tid['subject']); // And keep merging them until we get down to one thread.
					}
					$last_tid = $tid['tid'];
				}
			}
			if($thread_options['deletepoll'] == 1) // Delete poll
			{
				foreach($tids as $tid)
				{
					$this->delete_poll($tid);
				}
			}
			if($thread_options['removeredirects'] == 1) // Remove redirects
			{
				foreach($tids as $tid)
				{
					$this->remove_redirects($tid);
				}
			}

			if($thread_options['removesubscriptions'] == 1) // Remove thread subscriptions
			{
				$this->remove_thread_subscriptions($tids, true);
			}

			if($thread_options['approvethread'] == 'approve') // Approve thread
			{
				$this->approve_threads($tids, $thread['fid']);
			}
			elseif($thread_options['approvethread'] == 'unapprove') // Unapprove thread
			{
				$this->unapprove_threads($tids, $thread['fid']);
			}
			elseif($thread_options['approvethread'] == 'toggle') // Toggle thread visibility
			{
				$this->toggle_thread_visibility($tids, $thread['fid']);
			}

			if($thread_options['softdeletethread'] == 'softdelete') // Soft delete thread
			{
				$this->soft_delete_threads($tids);
			}
			elseif($thread_options['softdeletethread'] == 'restore') // Restore thread
			{
				$this->restore_threads($tids);
			}
			elseif($thread_options['softdeletethread'] == 'toggle') // Toggle thread visibility
			{
				$this->toggle_thread_softdelete($tids);
			}

			if($thread_options['openthread'] == 'open') // Open thread
			{
				$this->open_threads($tids);
			}
			elseif($thread_options['openthread'] == 'close') // Close thread
			{
				$this->close_threads($tids);
			}
			elseif($thread_options['openthread'] == 'toggle') // Toggle thread visibility
			{
				$this->toggle_thread_status($tids);
			}

			if($thread_options['stickthread'] == 'stick') // Stick thread
			{
				$this->stick_threads($tids);
			}
			elseif($thread_options['stickthread'] == 'unstick') // Unstick thread
			{
				$this->unstick_threads($tids);
			}
			elseif($thread_options['stickthread'] == 'toggle') // Toggle thread importance
			{
				$this->toggle_thread_importance($tids);
			}

			if($thread_options['threadprefix'] != '-1')
			{
				$this->apply_thread_prefix($tids, $thread_options['threadprefix']); // Update thread prefix
			}

			if(my_strtolower(trim($thread_options['newsubject'])) != '{subject}') // Update thread subjects
			{
				$this->change_thread_subject($tids, $thread_options['newsubject']);
			}
			if(!empty($thread_options['addreply'])) // Add reply to thread
			{
				$tid_list = implode(',', $tids);
				$query = $db->query("
					SELECT u.uid, u.username, t.fid, t.subject, t.tid, t.firstpost, t.closed FROM ".TABLE_PREFIX."threads t
					LEFT JOIN ".TABLE_PREFIX."users u ON t.uid=u.uid
					WHERE tid IN ($tid_list) AND closed NOT LIKE 'moved|%'
				");
				require_once MYBB_ROOT."inc/datahandlers/post.php";

				// Loop threads adding a reply to each one
				while($thread = $db->fetch_array($query))
				{
					$posthandler = new PostDataHandler("insert");

					$find = array('{username}', '{author}', '{subject}');
					$replace = array($mybb->user['username'], $thread['username'], $thread['subject']);

					if(empty($thread_options['replysubject']))
					{
						$new_subject = 'RE: '.$thread['subject'];
					}
					else
					{
						$new_subject = str_ireplace($find, $replace, $thread_options['replysubject']);
					}

					$new_message = str_ireplace($find, $replace, $thread_options['addreply']);

					$args = array(
						'thread_options' => &$thread_options,
						'tids' => &$tids,
						'thread' => &$thread,
						'new_subject' => &$new_subject,
						'new_message' => &$new_message,
					);

					$plugins->run_hooks("class_custommoderation_addreply", $args);

					// Set the post data that came from the input to the $post array.
					$post = array(
						"tid" => $thread['tid'],
						"replyto" => $thread['firstpost'],
						"fid" => $thread['fid'],
						"subject" => $new_subject,
						"uid" => $mybb->user['uid'],
						"username" => $mybb->user['username'],
						"message" => $new_message,
						"ipaddress" => my_inet_pton(get_ip()),
					);

					// Set up the post options from the input.
					$post['options'] = array(
						"signature" => 1,
						"emailnotify" => 0,
						"disablesmilies" => 0
					);

					if($thread['closed'] == 1)
					{
						// Keep this thread closed
						$post['modoptions']['closethread'] = 1;
					}

					$posthandler->set_data($post);
					if($posthandler->validate_post($post))
					{
						$posthandler->insert_post($post);
					}
				}
			}
			if($thread_options['movethread'] > 0 && $thread_options['movethread'] != $thread['fid']) // Move thread
			{
				if($thread_options['movethreadredirect'] == 1) // Move Thread with redirect
				{
					$time = TIME_NOW + ($thread_options['movethreadredirectexpire'] * 86400);
					foreach($tids as $tid)
					{
						$this->move_thread($tid, $thread_options['movethread'], 'redirect', $time);
					}
				}
				else // Normal move
				{
					$this->move_threads($tids, $thread_options['movethread']);
				}
			}
			if($thread_options['copythread'] > 0 || $thread_options['copythread'] == -2) // Copy thread
			{
				if($thread_options['copythread'] == -2)
				{
					$thread_options['copythread'] = $thread['fid'];
				}
				foreach($tids as $tid)
				{
					$new_tid = $this->move_thread($tid, $thread_options['copythread'], 'copy');
				}
			}
			if(!empty($thread_options['recountrebuild']))
			{
				require_once MYBB_ROOT.'/inc/functions_rebuild.php';

				foreach($tids as $tid)
				{
					rebuild_thread_counters($tid);
				}
			}
		}
		
		// Do we have a PM subject and PM message?
		if(isset($thread_options['pm_subject']) && $thread_options['pm_subject'] != '' && isset($thread_options['pm_message']) && $thread_options['pm_message'] != '')
		{
			$tid_list = implode(',', $tids);
			
			// For each thread, we send a PM to the author
			$query = $db->query("
				SELECT u.uid, u.username, t.subject FROM ".TABLE_PREFIX."threads t
				LEFT JOIN ".TABLE_PREFIX."users u ON t.uid=u.uid
				WHERE tid IN ($tid_list)
			");
			while($thread = $db->fetch_array($query))
			{
				$find = array('{username}', '{author}', '{subject}');
				$replace = array($mybb->user['username'], $thread['username'], $thread['subject']);

				$pm_subject = str_ireplace($find, $replace, $thread_options['pm_subject']);
				$pm_message = str_ireplace($find, $replace, $thread_options['pm_message']);

				$args = array(
					'thread_options' => &$thread_options,
					'tids' => &$tids,
					'thread' => &$thread,
					'pm_subject' => &$pm_subject,
					'pm_message' => &$pm_message,
				);

				$plugins->run_hooks("class_custommoderation_pm", $args);

				// Let's send our PM
				$pm = array(
					'subject' => $pm_subject,
					'message' => $pm_message,
					'touid' => $thread['uid']
				);
				send_pm($pm, $mybb->user['uid'], 1);
			}
		}

		$args = array(
			'thread_options' => &$thread_options,
			'tids' => &$tids,
			'thread' => &$thread,
		);

		$plugins->run_hooks("class_custommoderation_execute_thread_moderation_end", $args);
		
		return true;
	}
}
