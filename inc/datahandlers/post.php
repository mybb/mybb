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

/**
 * Post handling class, provides common structure to handle post data.
 *
 */
class PostDataHandler extends DataHandler
{
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
			SELECT tid, replyto, fid, subject, icon, uid, username, dateline, message, ipaddress, includesig, smielieoff, edituid, edittime, visible
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
	 * @param array The array of post data.
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_post($post)
	{
		global $mybb;

		if($mybb->settings['postfloodcheck'] == "on")
		{
			if($mybb->user['uid'] != 0 && $time-$mybb->user['lastpost'] <= $mybb->settings['postfloodsecs'] && ismod($post['fid']) != "yes")
			{
				$this->set_error("post_flooding");
			}
		}

		$time = time();

		if(strlen(trim($post['message'])) == 0)
		{
			$this->set_error("no_message");
		}
		elseif(strlen($post['message']) > $mybb->settings['messagelength'] && $mybb->settings['messagelength'] > 0 && ismod($post['fid']) != "yes")
		{
			$this->set_error("message_too_long");
		}

		if(!$post['replyto'])
		{
			$query = $db->query("SELECT pid FROM ".TABLE_PREFIX."posts WHERE tid='$tid' ORDER BY dateline ASC LIMIT 0,1");
			$repto = $db->fetch_array($query);
			$post['replyto'] = $repto['pid'];
		}

		if(!$post['icon'])
		{
			$post['icon'] = "0";
		}

		if($post['postoptions']['signature'] != "yes")
		{
			$post['postoptions']['signature'] = "no";
		}
		if($post['postoptions']['emailnotify'] != "yes")
		{
			$post['postoptions']['emailnotify'] = "no";
		}
		if($post['postoptions']['disablesmilies'] != "yes")
		{
			$post['postoptions']['disablesmilies'] = "no";
		}

		/* We are done validating, return. */
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
	function insert_post($post)
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

		if($post['savedraft']) // Save this post as a draft
		{
			$visible = -2;
		}
		else // This post is being made now
		{
			// Automatic subscription to the thread
			if($post['postoptions']['emailnotify'] != "no" && $post['uid'] > 0)
			{
				$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."favorites WHERE type='s' AND tid='".$post['tid']."' AND uid='".$post['uid']."'");
				$subcheck = $db->fetch_array($query);
				if(!$subcheck['uid'])
				{
					$db->query("INSERT INTO ".TABLE_PREFIX."favorites (uid,tid,type) VALUES ('".$post['uid']."','".$post['tid']."','s')");
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
				if($newstick && $newclosed) { $sep = ","; }
				if($newstick || $newclosed)
				{
					$db->query("UPDATE ".TABLE_PREFIX."threads SET $newclosed$sep$newstick WHERE tid='$tid'");
				}
			}

			// Decide on the visibility of this post
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
			$updatedpost = array(
				"subject" => addslashes($post['subject']),
				"icon" => intval($post['icon']),
				"uid" => intval($post['uid']),
				"username" => addslashes($post['username']),
				"dateline" => time(),
				"message" => addslashes($post['message']),
				"ipaddress" => addslashes($post['ip']),
				"includesig" => $post['postoptions']['signature'],
				"smilieoff" => $$post['postoptions']['disablesmilies'],
				"visible" => $visible
				);
			$db->update_query(TABLE_PREFIX."posts", $updatedpost, "pid='".$post['pid']."'");
			$pid = $post['pid'];
		}
		else
		{
			// Insert the post
			$newreply = array(
				"tid" => intval($post['tid']),
				"replyto" => intval($post['replyto'],
				"fid" => intval($post['fid']),
				"subject" => addslashes($post['subject']),
				"icon" => intval($post['icon']),
				"uid" => intval($post['uid']),
				"username" => addslashes($post['username']),
				"dateline" => time(),
				"message" => addslashes($post['message']),
				"ipaddress" => addslashes($post['ip'],
				"includesig" => $post['postoptions']['signature'],
				"smilieoff" => $post['postoptions']['disablesmilies'],
				"visible" => $visible
				);

			$plugins->run_hooks("datahandler_post_insert");

			$db->insert_query(TABLE_PREFIX."posts", $newreply);
			$pid = $db->insert_id();
		}

		// Assign any uploaded attachments with the specific posthash to the newly created post
		if($post['posthash'])
		{
			$db->query("UPDATE ".TABLE_PREFIX."attachments SET pid='".$pid."' WHERE posthash='".addslashes($post['posthash'])."'");
		}

		return array(
			"pid" => $pid,
			"visible" => $visible
			);
	
	}
	
	/**
	 * Updates a post that is already in the database.
	 *
	 * @param array The post data array.
	 * @param int The post id of the post to update.
	 */
	function update_post($post, $pid)
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
		
		$db->update_query(TABLE_PREFIX."posts", $post, "pid = ".$pid);
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