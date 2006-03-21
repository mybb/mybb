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

/*
EXAMPLE USE:

$post = get from POST data
$thread = get from DB using POST data id

$postHandler = new postDataHandler();
if($postHandler->validate_post($post))
{
	$postHandler->insert_post($post);
}

*/

/**
 * Post handling class, provides common structure to handle post data.
 *
 */
class PostDataHandler extends DataHandler
{
	/**
	 * Verifies a post subject.
	 *
	 * @param string The post subject.
	 */
	function verify_subject()
	{
		$subject = &$this->data['subject'];
		// Check for correct subject content.
		if($post['action'] == "edit" && $post['pid'])
		{
			$options = array(
				"limit" => 1,
				"limit_start" => 0,
				"order_by" => "dateline",
				"order_dir" => "asc"
			);
			$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid=".$tid, $options);
			$firstcheck = $db->fetch_array($query);
			if($firstcheck['pid'] == $post['pid'])
			{
				$firstpost = 1;
			}
			else
			{
				$firstpost = 0;
			}

			// If this is the first post there needs to be a subject, else make it the default one.
			if(strlen(trim($subject)) == 0 && $firstpost)
			{
				$this->set_error("firstpost_no_subject");
				return false;
			}
			elseif(strlen(trim($subject)) == 0)
			{
				$subject = "[no subject]";
			}
		}
		else
		{
			if(strlen(trim($subject)) == 0)
			{
				$subject = "[no subject]";
			}
		}
		return true;
	}
	/**
	 * Verifies a post message.
	 *
	 * @param string The message content.
	 */
	function verify_message($message)
	{
		// Message of correct length?
		if(trim($message) == "")
		{
			$this->set_error("no_message");
			return false;
		}
		elseif(strlen($message) > $mybb->settings['messagelength'] && $mybb->settings['messagelength'] > 0 && ismod($post['fid']) != "yes")
		{
			$this->set_error("message_too_long");
			return false;
		}
		elseif(strlen($message) < $mybb->settings['minmessagelength'] && $mybb->settings['minmessagelength'] > 0 && ismod($post['fid']) != "yes")
		{
			$this->set_error("message_too_short");
			return false;
		}
	}

	/**
	 * Verifies the specified post options are correct.
	 *
	 * @return boolean True
	 */
	function get_options()
	{
		$options = &$this->data['options'];
		if($options['signature'] != "yes")
		{
			$options['signature'] = "no";
		}
		if($options['emailnotify'] != "yes")
		{
			$options['emailnotify'] = "no";
		}
		if($options['disablesmilies'] != "yes")
		{
			$options['disablesmilies'] = "no";
		}
		return true;
	}
	
	function verify_post_flooding()
	{
		global $mybb;
		
		$post = &$this->post;
		if($mybb->settings['postfloodcheck'] == "on" && $post['uid'] != 0 $this->admin_override == false)
		{
			$user = get_user($post['uid']);
			if(time()-$user['lastpost'] <= $mybb->settings['postfloodsecs'] && ismod($post['fid']) != "yes")
			{
				$this->set_error("post_flooding");
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Validate a post.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_post()
	{
		global $mybb, $db, $plugins;
		
		$post = &$this->data;
		$time = time();

		$this->verify_subject();
		
		$this->verify_message();
		
		$this->verify_post_flooding();
		

		

		// Check if this post contains more images than the forum allows
		if(!$mybb->input['savedraft'] && $mybb->settings['maxpostimages'] != 0 && $mybb->usergroup['cancp'] != "yes")
		{
			if($mybb->input['postoptions']['disablesmilies'] == "yes")
			{
				$allowsmilies = "no";
			}
			else
			{
				$allowsmilies = $forum['allowsmilies'];
			}
			$imagecheck = postify($mybb->input['message'], $forum['allowhtml'], $forum['allowmycode'], $allowsmilies, $forum['allowimgcode']);
			if(substr_count($imagecheck, "<img") > $mybb->settings['maxpostimages'])
			{
				eval("\$maximageserror = \"".$templates->get("error_maxpostimages")."\";");
				$mybb->input['action'] = "newreply";
			}
		}

		// If there is no post to reply to, let's reply to the first one.
		if(!$post['replyto'])
		{
			$options = array(
				"limit_start" => 0,
				"limit" => 1,
				"order_by" => "dateline",
				"order_dir" => "asc"
			);
			$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid='".$thread['tid']."'", $options);
			$replyto = $db->fetch_array($query);
			$post['replyto'] = $replyto['pid'];
		}

		// Perhaps we don't have a post icon?
		if(!$post['icon'])
		{
			$post['icon'] = "0";
		}

		// Clean the post options for this post.
		$post = $this->get_options($post);

		$plugins->run_hooks("datahandler_post_validate");

		// We are done validating, return.
		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}


	/**
	 * Insert a post into the database.
	 *
	 * @param array The post data array.
	 * @return array Array of new post details, pid and visibility.
	 */
	function insert_post($post)
	{
		global $db, $mybb, $plugins;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The post is not valid.");
		}

		if($post['savedraft']) // Save this post as a draft
		{
			$visible = -2;
		}
		else // This post is being made now
		{
			// Automatic subscription to the thread
			if($post['options']['emailnotify'] != "no" && $post['uid'] > 0)
			{
				$query = $db->simple_select(
					TABLE_PREFIX."favorites",
					"uid",
					"type='s' AND tid='".$post['tid']."' AND uid='".$post['uid']."'"
				);
				$subcheck = $db->fetch_array($query);
				if(!$subcheck['uid'])
				{
					$favoriteadd = array(
						"uid" => intval($post['uid']),
						"tid" => intval($post['tid']),
						"type" => "s"
					);
					$db->insert_query(TABLE_PREFIX."favorites", $favoriteadd);
				}
			}

			// Perform any selected moderation tools.
			if(ismod($post['fid']) == "yes" && $post['modoptions'])
			{
				$modoptions = $post['modoptions'];
				$modlogdata['fid'] = $thread['fid'];
				$modlogdata['tid'] = $thread['tid'];

				// Close the thread.
				if($modoptions['closethread'] == "yes" && $thread['closed'] != "yes")
				{
					$newclosed = "closed='yes'";
					logmod($modlogdata, "Thread closed");
				}

				// Open the thread.
				if($modoptions['closethread'] != "yes" && $thread['closed'] == "yes")
				{
					$newclosed = "closed='no'";
					logmod($modlogdata, "Thread opened");
				}

				// Stick the thread.
				if($modoptions['stickthread'] == "yes" && $thread['sticky'] != 1)
				{
					$newstick = "sticky='1'";
					logmod($modlogdata, "Thread stuck");
				}

				// Unstick the thread.
				if($modoptions['stickthread'] != "yes" && $thread['sticky'])
				{
					$newstick = "sticky='0'";
					logmod($modlogdata, "Thread unstuck");
				}

				// Execute moderation options.
				if($newstick && $newclosed)
				{
					$sep = ",";
				}
				if($newstick || $newclosed)
				{
					$db->query("
						UPDATE ".TABLE_PREFIX."threads
						SET $newclosed$sep$newstick
						WHERE tid='".$thread['tid']."'
					");
				}
			}

			// Decide on the visibility of this post.
			if($forum['modposts'] == "yes" && $mybb->usergroup['cancp'] != "yes")
			{
				$visible = 0;
			}
			else
			{
				$visible = 1;
			}
		}

		// Are we updating a post which is already a draft? Perhaps changing it into a visible post?
		if($post['savedraft'] == 1 && $post['pid'])
		{
			// Update a post that is a draft
			$updatedpost = array(
				"subject" => $db->escape_string($post['subject']),
				"icon" => intval($post['icon']),
				"uid" => intval($post['uid']),
				"username" => $db->escape_string($post['username']),
				"dateline" => time(),
				"message" => $db->escape_string($post['message']),
				"ipaddress" => $db->escape_string($post['ipaddress']),
				"includesig" => $post['options']['signature'],
				"smilieoff" => $post['options']['disablesmilies'],
				"visible" => $visible
				);
			$db->update_query(TABLE_PREFIX."posts", $updatedpost, "pid='".$post['pid']."'");
			$pid = $post['pid'];
		}
		else
		{
			// Insert the post.
			$newreply = array(
				"tid" => intval($post['tid']),
				"replyto" => intval($post['replyto']),
				"fid" => intval($post['fid']),
				"subject" => $db->escape_string($post['subject']),
				"icon" => intval($post['icon']),
				"uid" => intval($post['uid']),
				"username" => $db->escape_string($post['username']),
				"dateline" => time(),
				"message" => $db->escape_string($post['message']),
				"ipaddress" => $db->escape_string($post['ipaddress']),
				"includesig" => $post['options']['signature'],
				"smilieoff" => $post['options']['disablesmilies'],
				"visible" => $visible
				);

			$db->insert_query(TABLE_PREFIX."posts", $newreply);
			$pid = $db->insert_id();
		}

		// Assign any uploaded attachments with the specific posthash to the newly created post.
		if($post['posthash'])
		{
			$post['posthash'] = $db->escape_string($post['posthash']);
			$attachmentassign = array(
				"pid" => $post['pid']
			);
			$db->update_query(TABLE_PREFIX."attachments", $attachmentassign, "posthash='".$post['posthash']."'");
		}

		$plugins->run_hooks("datahandler_post_insert");

		// Return the post's pid and whether or not it is visible.
		return array(
			"pid" => $pid,
			"visible" => $visible
		);
	}

	/**
	 * Updates a post that is already in the database.
	 *
	 * @param int The post id of the post to update.
	 */
	function update_post($pid)
	{
		global $db, $mybb, $plugins;

		// Yes, validating is required.
		if($this->get_validated() != true)
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors() > 0))
		{
			die("The post is not valid.");
		}

		// Check if this is the first post in a thread.
		$options = array(
			"orderby" => "dateline",
			"order_dir" => "asc",
			"limit_start" => 0,
			"limit" => 1
		);
		$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid=".$pid, $options);
		$firstcheck = $db->fetch_array($query);
		if($firstcheck['pid'] == $pid)
		{
			$firstpost = 1;
		}
		else
		{
			$firstpost = 0;
		}

		// Check what icon we want on the thread.
		if(!$mybb->input['icon'] || $mybb->input['icon'] == -1)
		{
			$mybb->input['icon'] = "0";
		}

		// Update the thread details that might have been changed first.
		if($firstpost)
		{
			$updatethread = array(
				"subject" => $db->escape_string($mybb->input['subject']),
				"icon" => intval($mybb->input['icon']),
				);
			$db->update_query(TABLE_PREFIX."threads", $updatethread, "tid='$tid'");
		}

		// Prepare array for post updating.
		$updatepost = array(
			"subject" => $db->escape_string($post['subject']),
			"message" => $db->escape_string($post['message']),
			"icon" => intval($post['icon']),
			"smilieoff" => $post['options']['disablesmilies'],
			"includesig" => $post['options']['signature']
		);

		// If we need to show the edited by, let's do so.
		if(($mybb->settings['showeditedby'] == "yes" && ismod($fid, "caneditposts") != "yes") || ($mybb->settings['showeditedbyadmin'] == "yes" && ismod($fid, "caneditposts") == "yes"))
		{
			$updatepost['edituid'] = $mybb->user['uid'];
			$updatepost['edittime'] = time();
		}
		$db->update_query(TABLE_PREFIX."posts", $updatepost, "pid=$pid");

		$plugins->run_hooks("datahandler_post_update");
	}

	/**
	 * Delete a post from the database.
	 *
	 * @param int The post id of the post that is to be deleted.
	 * @param int The thread id of the thread the post is in.
	 * @param int The forum id of the forum the post is in.
	 */
	function delete_by_pid($pid, $tid, $fid)
	{
		global $db;

		// Is this the first post of a thread? If so, we'll need to delete the whole thread.
		$options = array(
			"orderby" => "dateline",
			"order_dir" => "asc",
			"limit_start" => 0,
			"limit" => 1
		);
		$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid=".$pid, $options);
		$firstcheck = $db->fetch_array($query);
		if($firstcheck['pid'] == $pid)
		{
			$firstpost = true;
		}
		else
		{
			$firstpost = false;
		}

		// Delete the whole thread or this post only?
		if($firstpost === true)
		{
			deletethread($tid);
			updateforumcount($fid);
		}
		else
		{
			deletepost($pid);
			updatethreadcount($tid);
			updateforumcount($fid);
		}

		$plugins->run_hooks("datahandler_thread_delete");
	}
}

?>