<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id:$
 */

/*
EXAMPLE USE:

$post = get from POST data
$thread = get from DB using POST data id

$postHandler = new postHandler();
$postHandler->set_post_data($post);
if($postHandler->validate_post())
{
	$postHandler->insert_post();
}

*/

/**
 * Post handling class, provides common structure to handle post data.
 *
 */
class PostDataHandler extends DataHandler
{
	/**
	 * Array of post data.
	 *
	 * @var array
	 */
	var $post;
	
	/**
	 * Array of data of the thread the post is in.
	 *
	 * @var array
	 */
	var $thread;
	
	/**
	 * Set the post data of the post we are looking at.
	 *
	 * @param array The array of post data.
	 */
	function set_post_data($post)
	{
		$this->post = $post;
	}
	
	/**
	 * Set the thread data of the thread the post is in.
	 *
	 * @param unknown_type $thread
	 */
	function set_thread_data($thread)
	{
		$this->thread = $thread;
	}
	
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
		$this->post = $db->fetch_array($query);
		
		return $this->post;
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
			if($mybb->user['uid'] != 0 && $time-$mybb->user['lastpost'] <= $mybb->settings['postfloodsecs'] && ismod($this->post['fid']) != "yes")
			{
				$this->set_error("post_flooding");
			}
		}

		// Message of correct length?
		if(strlen(trim($this->post['message'])) == 0)
		{
			$this->set_error("no_message");
		}
		elseif(strlen($this->post['message']) > $mybb->settings['messagelength'] && $mybb->settings['messagelength'] > 0 && ismod($this->post['fid']) != "yes")
		{
			$this->set_error("message_too_long");
		}

		// If there is no post to reply to, let's reply to the first one.
		if(!$this->post['replyto'])
		{
			$options = array(
				"limit_start" => 0,
				"limit" => 1,
				"order_by" => "dateline",
				"order_dir" => "asc"
			);
			$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid='".$this->thread['tid']."'", $options);
			$replyto = $db->fetch_array($query);
			$this->post['replyto'] = $replyto['pid'];
		}

		// Perhaps we don't have a post icon?
		if(!$this->post['icon'])
		{
			$this->post['icon'] = "0";
		}

		// Just making sure the options are correct.
		if($this->post['postoptions']['signature'] != "yes")
		{
			$this->post['postoptions']['signature'] = "no";
		}
		if($this->post['postoptions']['emailnotify'] != "yes")
		{
			$this->post['postoptions']['emailnotify'] = "no";
		}
		if($this->post['postoptions']['disablesmilies'] != "yes")
		{
			$this->post['postoptions']['disablesmilies'] = "no";
		}

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
	 * Insert a post into the database.
	 *
	 * @param array The post data array.
	 * @return array Array of new post details, pid and visibility.
	 */
	function insert_post()
	{
		global $db;

		if($this->get_validated !== true)
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(!empty($this->get_errors()))
		{
			die("The post is not valid.");
		}

		if($this->post['savedraft']) // Save this post as a draft
		{
			$visible = -2;
		}
		else // This post is being made now
		{
			// Automatic subscription to the thread
			if($this->post['postoptions']['emailnotify'] != "no" && $this->post['uid'] > 0)
			{
				$query = $db->simple_select(
					TABLE_PREFIX."favorites",
					"uid",
					"type='s' AND tid='".$this->post['tid']."' AND uid='".$this->post['uid']."'"
				);
				$subcheck = $db->fetch_array($query);
				if(!$subcheck['uid'])
				{
					$db->query("
						INSERT INTO ".TABLE_PREFIX."favorites (uid,tid,type)
						VALUES ('".$this->post['uid']."','".$this->post['tid']."','s')
					");
				}
			}

			// Perform any selected moderation tools
			if(ismod($this->post['fid']) == "yes" && $this->post['modoptions'])
			{
				$modoptions = $this->post['modoptions'];
				$modlogdata['fid'] = $this->thread['fid'];
				$modlogdata['tid'] = $this->thread['tid'];

				// Close the thread
				if($modoptions['closethread'] == "yes" && $this->thread['closed'] != "yes")
				{
					$newclosed = "closed='yes'";
					logmod($modlogdata, "Thread closed");
				}

				// Open the thread
				if($modoptions['closethread'] != "yes" && $this->thread['closed'] == "yes")
				{
					$newclosed = "closed='no'";
					logmod($modlogdata, "Thread opened");
				}

				// Stick the thread
				if($modoptions['stickthread'] == "yes" && $this->thread['sticky'] != 1)
				{
					$newstick = "sticky='1'";
					logmod($modlogdata, "Thread stuck");
				}

				// Unstick the thread
				if($modoptions['stickthread'] != "yes" && $this->thread['sticky'])
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
						WHERE tid='".$this->thread['tid']."'
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
		if($this->post['pid'])
		{
			// Update a post that is a draft
			$updatedpost = array(
				"subject" => addslashes($this->post['subject']),
				"icon" => intval($this->post['icon']),
				"uid" => intval($this->post['uid']),
				"username" => addslashes($this->post['username']),
				"dateline" => time(),
				"message" => addslashes($this->post['message']),
				"ipaddress" => addslashes($this->post['ip']),
				"includesig" => $this->post['postoptions']['signature'],
				"smilieoff" => $this->post['postoptions']['disablesmilies'],
				"visible" => $visible
				);
			$db->update_query(TABLE_PREFIX."posts", $updatedpost, "pid='".$this->post['pid']."'");
			$pid = $this->post['pid'];
		}
		else
		{
			// Insert the post
			$newreply = array(
				"tid" => intval($this->post['tid']),
				"replyto" => intval($this->post['replyto']),
				"fid" => intval($this->post['fid']),
				"subject" => addslashes($this->post['subject']),
				"icon" => intval($this->post['icon']),
				"uid" => intval($this->post['uid']),
				"username" => addslashes($this->post['username']),
				"dateline" => time(),
				"message" => addslashes($this->post['message']),
				"ipaddress" => addslashes($this->post['ip']),
				"includesig" => $this->post['postoptions']['signature'],
				"smilieoff" => $this->post['postoptions']['disablesmilies'],
				"visible" => $visible
				);

			$plugins->run_hooks("datahandler_post_insert");

			$db->insert_query(TABLE_PREFIX."posts", $newreply);
			$pid = $db->insert_id();
		}

		// Assign any uploaded attachments with the specific posthash to the newly created post
		if($this->post['posthash'])
		{
			$db->query("
				UPDATE ".TABLE_PREFIX."attachments
				SET pid='".$this->post['pid']."'
				WHERE posthash='".addslashes($this->post['posthash'])."'
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
		
		if($this->get_validated !== true)
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(!empty($this->get_errors()))
		{
			die("The post is not valid.");
		}
		
		$db->update_query(TABLE_PREFIX."posts", $this->post, "pid = ".$pid);
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