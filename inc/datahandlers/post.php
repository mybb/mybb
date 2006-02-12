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

$postHandler = new postHandler();
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
	 * What we are doing: inserting, updating or deleting.
	 *
	 * @var string
	 */
	var $action;
	
	/**
	 * Get a post from the database by post id.
	 *
	 * @param int The id of the post to be retrieved.
	 * @return array An array of post data.
	 */
	function get_post_by_pid($pid)
	{
		global $db;
		
		$pid = intval($pid);		
		$query = $db->query("
			SELECT tid, replyto, fid, subject, icon, uid, username, dateline, message, ipaddress, includesig, smilieoff, edituid, edittime, visible
			FROM ".TABLE_PREFIX."posts
			WHERE pid = ".$pid."
			LIMIT 1
		");
		$post = $db->fetch_array($query);
		
		return $post;
	}

	/**
	 * Validate a post.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_post()
	{
		global $mybb, $db;

		$time = time();
		
		// Check is the user is being naughty.
		if($mybb->settings['postfloodcheck'] == "on")
		{
			if($mybb->user['uid'] != 0 && $time-$mybb->user['lastpost'] <= $mybb->settings['postfloodsecs'] && ismod($post['fid']) != "yes")
			{
				$this->set_error("post_flooding");
			}
		}

		// Message of correct length?
		if(strlen(trim($post['message'])) == 0)
		{
			$this->set_error("no_message");
		}
		elseif(strlen($post['message']) > $mybb->settings['messagelength'] && $mybb->settings['messagelength'] > 0 && ismod($post['fid']) != "yes")
		{
			$this->set_error("message_too_long");
		}

		// Check for correct subject content.
		if($this->action == "insert")
		{
			// If there is no subject, make it the default one.
			if(strlen(trim($mybb->input['subject'])) == 0)
			{
				$post['subject'] = "RE: " . $thread['subject'];
			}
		}
		elseif($this->action == "update")
		{
			// If this is the first post there needs to be a subject, else make it the default one.
			if(strlen(trim($mybb->input['subject'])) == 0 && $firstpost)
			{
				$this->set_error("no_subject");
			}
			elseif(strlen(trim($mybb->input['subject'])) == 0)
			{
				$post['subject'] = "RE: " . $thread['subject'];
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
		$post = $post->get_options($post);

		// We are done validating, return.
		$this->set_validated(true);
		if(empty($this->get_errors()))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Assigns post options to the post data array.
	 *
	 * @param array The post data array.
	 */
	function get_options(&$post)
	{
		// Just to be safe here.
		$post['options'] = $mybb->input['postoptions'];
		if($post['options']['signature'] != "yes")
		{
			$post['options']['signature'] = "no";
		}
		if($post['options']['emailnotify'] != "yes")
		{
			$post['options']['emailnotify'] = "no";
		}
		if($post['options']['disablesmilies'] != "yes")
		{
			$post['options']['disablesmilies'] = "no";
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
		global $db;

		// Make the validation method know what we are doing.
		$this->action = "insert";
		
		// Yes, validating is required.
		if($this->get_validated !== true)
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(!empty($this->get_errors()))
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
					$db->query("
						INSERT INTO ".TABLE_PREFIX."favorites (uid,tid,type)
						VALUES ('".$post['uid']."','".$post['tid']."','s')
					");
				}
			}

			// Perform any selected moderation tools
			if(ismod($post['fid']) == "yes" && $post['modoptions'])
			{
				$modoptions = $post['modoptions'];
				$modlogdata['fid'] = $thread['fid'];
				$modlogdata['tid'] = $thread['tid'];

				// Close the thread
				if($modoptions['closethread'] == "yes" && $thread['closed'] != "yes")
				{
					$newclosed = "closed='yes'";
					logmod($modlogdata, "Thread closed");
				}

				// Open the thread
				if($modoptions['closethread'] != "yes" && $thread['closed'] == "yes")
				{
					$newclosed = "closed='no'";
					logmod($modlogdata, "Thread opened");
				}

				// Stick the thread
				if($modoptions['stickthread'] == "yes" && $thread['sticky'] != 1)
				{
					$newstick = "sticky='1'";
					logmod($modlogdata, "Thread stuck");
				}

				// Unstick the thread
				if($modoptions['stickthread'] != "yes" && $thread['sticky'])
				{
					$newstick = "sticky='0'";
					logmod($modlogdata, "Thread unstuck");
				}
				
				// Execute moderation options
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
		if($post['pid'])
		{
			// Update a post that is a draft
			$updatedpost = array(
				"subject" => $db->escape_string($post['subject']),
				"icon" => intval($post['icon']),
				"uid" => intval($post['uid']),
				"username" => $db->escape_string($post['username']),
				"dateline" => time(),
				"message" => $db->escape_string($post['message']),
				"ipaddress" => $db->escape_string($post['ip']),
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
				"ipaddress" => $db->escape_string($post['ip']),
				"includesig" => $post['options']['signature'],
				"smilieoff" => $post['options']['disablesmilies'],
				"visible" => $visible
				);

			$plugins->run_hooks("datahandler_post_insert");

			$db->insert_query(TABLE_PREFIX."posts", $newreply);
			$pid = $db->insert_id();
		}

		// Assign any uploaded attachments with the specific posthash to the newly created post.
		if($post['posthash'])
		{
			$db->query("
				UPDATE ".TABLE_PREFIX."attachments
				SET pid='".$post['pid']."'
				WHERE posthash='".$db->escape_string($post['posthash'])."'
			");
		}

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
		global $db;
		
		// Make the validation method know what we are doing.
		$this->action = "update";
		
		// Yes, validating is required.
		if($this->get_validated !== true)
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(!empty($this->get_errors()))
		{
			die("The post is not valid.");
		}
		
		// Check if this is the first post in a thread.
		$query = $db->query("
			SELECT pid
			FROM ".TABLE_PREFIX."posts
			WHERE tid='$tid'
			ORDER BY dateline ASC
			LIMIT 0,1
		");
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
	}
	
	/**
	 * Delete a post from the database.
	 *
	 * @param int The post id of the post that is to be deleted.
	 * @param int The thread id of the thread the post is in.
	 * @param int The forum id of the forum the post is in.
	 */
	function delete_post($pid, $tid, $fid)
	{
		global $db;
		
		/* Is this the first post of a thread? If so, we'll need to delete the whole thread. */
		$query = $db->query("
			SELECT pid FROM ".TABLE_PREFIX."posts
			WHERE tid='$tid'
			ORDER BY dateline ASC
			LIMIT 0,1
		");
		$firstcheck = $db->fetch_array($query);
		if($firstcheck['pid'] == $pid)
		{
			$firstpost = true;
		}
		else
		{
			$firstpost = false;
		}
		
		/* Delete the whole thread or this post only? */
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
	}
}

?>