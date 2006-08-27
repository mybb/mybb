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

/**
 * Used to execute a custom moderation tool
 *
 */

class CustomModeration extends Moderation
{
	/**
	 * The thread IDs and forum IDs to be updated
	 */
	var $update_tids = array();
	var $update_fids = array();

	/**
	 * Get info on a tool
	 *
	 * @param int Tool ID
	 * @param mixed Thread IDs
	 * @param mixed Post IDs
	 * @return mixed Returns tool data (tid, type, name, description) in an array, otherwise boolean false.
	 */
	function tool_info($tool_id)
	{
		global $db;

		// Get tool info
		$query = $db->simple_select(TABLE_PREFIX."modtools", 'tid, type, name, description', 'tid="'.intval($tool_id).'"');
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
	 * @param int Tool ID
	 * @param mixed Thread ID(s)
	 * @param mixed Post IDs
	 * @return string 'forum' or 'default' indicating where to redirect
	 */
	function execute($tool_id, $tids=0, $pids=0)
	{
		global $db;

		// Get tool info
		$query = $db->simple_select(TABLE_PREFIX."modtools", '*', 'tid="'.intval($tool_id).'"');
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
		$post_options = unserialize($tool['postoptions']);
		$thread_options = unserialize($tool['threadoptions']);

		// If the tool type is a post tool, then execute the post moderation
		if($tool['type'] == 'p')
		{
			$this->execute_post_moderation($post_options, $pids, $tids);
		}
		// Always execute thead moderation
		$this->execute_thread_moderation($thread_options, $tids);

		// Update counts
		$this->update_counts();

		// If the thread is deleted, indicate to the calling script to redirect to the forum, and not the nonexistant thread
		if($thread_options['deletethread'] == 'yes')
		{
			return 'forum';
		}
		return 'default';
	}

	/**
	 * Execute Inline Post Moderation
	 *
	 * @param array Moderation information
	 * @param mixed Post IDs
	 * @param array Thread IDs
	 * @return boolean true
	 */
	function execute_post_moderation($post_options, $pids, $tid)
	{
		global $db, $mybb;

		if(is_array($tid))
		{
			$tid = intval($tid[0]); // There's only 1 thread when doing inline post moderation
		}

		$this->update_tids[$tid] = 1;

		// Get the information about thread
		$thread = get_thread($tid);
		$this->update_fids[$thread['fid']] = 1;

		// If deleting posts, only do that
		if($post_options['deleteposts'] == 'yes')
		{
			foreach($pids as $pid)
			{
				$this->delete_post($pid);
			}
		}
		else
		{
			if($post_options['mergeposts'] == 'yes') // Merge posts
			{
				$this->merge_posts($pids, $tid);
			}

			if($post_options['approveposts'] == 'approve') // Approve posts
			{
				$this->approve_posts($pids, $tid, $thread['fid']);
			}
			elseif($post_options['approveposts'] == 'unapprove') // Unapprove posts
			{
				$this->unapprove_posts($pids, $tid, $thread['fid']);
			}
			elseif($post_options['approveposts'] == 'toggle') // Toggle post visibility
			{
				$this->toggle_post_visibility($pids, $tid, $thread['fid']);
			}

			if($post_options['splitposts'] > 0 || $post_options['splitposts'] == -2) // Split posts
			{
				if($post_options['splitposts'] == -2)
				{
					$post_options['splitposts'] = $thread['fid'];
				}
				if(empty($post_options['splitpostsnewsubject']))
				{
					// Enter in a subject if a predefined one does not exist.
					$post_options['splitpostsnewsubject'] = '[split] '.$thread['subject'];
				}
				$new_subject = str_replace('{subject}', $thread['subject'], $post_options['splitpostsnewsubject']);
				$new_tid = $this->split_posts($pids, $tid, $post_options['splitposts'], $new_subject);
				$this->update_tids[$new_tid] = 1;
				$this->update_fids[$post_options['splitposts']] = 1;
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
				if(!empty($post_options['splitpostsaddreply'])) // Add reply to new thread
				{
					require MYBB_ROOT."inc/datahandlers/post.php";
					$posthandler = new PostDataHandler("insert");

					if(empty($post_options['splitpostsreplysubject']))
					{
						$post_options['splitpostsreplysubject'] = 'RE: '.$new_subject;
					}	

					// Set the post data that came from the input to the $post array.
					$post = array(
						"tid" => $new_tid,
						"fid" => $post_options['splitposts'],
						"subject" => $post_options['splitpostsreplysubject'],
						"uid" => $mybb->user['uid'],
						"username" => $mybb->user['username'],
						"message" => $post_options['splitpostsaddreply'],
						"ipaddress" => $db->escape_string(get_ip()),
					);
					// Set up the post options from the input.
					$post['options'] = array(
						"signature" => 'yes',
						"emailnotify" => 'no',
						"disablesmilies" => 'no'
					);

					$posthandler->set_data($post);

					if($posthandler->validate_post($post))
					{
						$posthandler->insert_post($post);
					}
				}
			}
		}
		return true;
	}

	/**
	 * Execute Normal and Inline Thread Moderation
	 *
	 * @param array Moderation information
	 * @param mixed Thread IDs
	 * @return boolean true
	 */
	function execute_thread_moderation($thread_options, $tids)
	{
		global $db, $mybb;

		$tid = intval($tids[0]); // Take the first thread to get thread data from
		$query = $db->simple_select(TABLE_PREFIX."threads", 'fid', "tid='$tid'");
		$thread = $db->fetch_array($query);

		$this->update_fids[$thread['fid']] = 1;

		// If deleting threads, only do that
		if($thread_options['deletethread'] == 'yes')
		{
			foreach($tids as $tid)
			{
				$this->delete_thread($tid);
			}
		}
		else
		{
			if($thread_options['mergethreads'] == 'yes' && count($tids) > 1) // Merge Threads (ugly temp code until find better fix)
			{
				$tid_list = implode(',', $tids);
				$options = array('order_by' => 'dateline', 'order_dir' => 'DESC');
				$query = $db->simple_select(TABLE_PREFIX."threads", 'tid, subject', "tid IN ($tid_list)", $options); // Select threads from newest to oldest
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
			if($thread_options['deletepoll'] == 'yes') // Delete poll
			{
				foreach($tids as $tid)
				{
					$this->delete_poll($tid);
				}
			}
			if($thread_options['removeredirects'] == 'yes') // Remove redirects
			{
				foreach($tids as $tid)
				{
					$this->remove_redirects($tid);
				}
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

			if($thread_options['movethread'] > 0 && $thread_options['movethread'] != $thread['fid']) // Move thread
			{
				if($thread_options['movethreadredirect'] == 'yes') // Move Thread with redirect
				{
					$time = time() + ($thread_options['movethreadredirectexpire'] * 86400);
					foreach($tids as $tid)
					{
						$this->move_thread($tid, $thread_options['movethread'], 'redirect', $time);
					}
				}
				else // Normal move
				{
					$this->move_threads($tids, $thread_options['movethread']);
				}
				$this->update_fids[$thread_options['movethread']] = 1;
			}
			if($thread_options['copythread'] > 0 || $thread_options['copythread'] == -2) // Copy thread
			{
				if($thread_options['copythread'] == -2)
				{
					$thread_options['copythread'] = $thread['fid'];
				}
				//var_dump($tids);
				foreach($tids as $tid)
				{
					$new_tid = $this->move_thread($tid, $thread_options['copythread'], 'copy');
					$this->update_tids[$new_tid] = 1;
				}
				$this->update_fids[$thread_options['copythread']] = 1;
			}
			if(trim($thread_options['newsubject']) != '{subject}') // Update thread subjects
			{
				$this->change_thread_subject($tids, $thread_options['newsubject']);
			}
			if(!empty($thread_options['addreply'])) // Add reply to thread
			{
				$tid_list = implode(',', $tids);
				$query = $db->simple_select(TABLE_PREFIX."threads", 'fid, subject, tid, firstpost', "tid IN ($tid_list)");
				require MYBB_ROOT."inc/datahandlers/post.php";
				// Loop threads adding a reply to each one
				while($thread = $db->fetch_array($query))
				{
					$posthandler = new PostDataHandler("insert");
			
					if(empty($thread_options['replysubject']))
					{
						$thread_options['replysubject'] = 'RE: '.$thread['subject'];
					}	
	
					// Set the post data that came from the input to the $post array.
					$post = array(
						"tid" => $thread['tid'],
						"replyto" => $thread['firstpost'],
						"fid" => $thread['fid'],
						"subject" => $thread_options['replysubject'],
						"uid" => $mybb->user['uid'],
						"username" => $mybb->user['username'],
						"message" => $thread_options['addreply'],
						"ipaddress" => $db->escape_string(get_ip()),
					);
					// Set up the post options from the input.
					$post['options'] = array(
						"signature" => 'yes',
						"emailnotify" => 'no',
						"disablesmilies" => 'no'
					);
	
					$posthandler->set_data($post);
					if($posthandler->validate_post($post))
					{
						$posthandler->insert_post($post);
					}
				}
			}
		}
		foreach($tids as $tid)
		{
			$this->update_tids[$tid] = 1;
		}
		return true;
	}

	/**
	 * Update Forum/Thread Counts
	 *
	 * @return boolean true
	 */
	function update_counts()
	{
		global $db;
		foreach($this->update_tids as $tid => $val)
		{
			update_thread_count($tid);
		}
		foreach($this->update_fids as $fid => $val)
		{
			update_forum_count($fid);
		}
		return true;
	}
}
?>