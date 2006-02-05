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
	 * Insert a post into the database.
	 *
	 * @param array The post data array.
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

		$db->insert_query(TABLE_PREFIX."posts", $post);
		
		/* Update post count for thread and forum the post is in. */
		updatethreadcount($post['tid']);
		updateforumcount($post['fid']);
		
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
	
	/**
	 * Validate a post.
	 *
	 * @param array The array of post data.
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_post($post)
	{
		if(!$post['subject'])
		{
			$this->set_error("no_post_subject");
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
}

?>