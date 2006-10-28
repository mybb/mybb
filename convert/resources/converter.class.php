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
 
class Converter
{
	/**
	 * Class constructor
	 */
    function Converter()
    {
    	// do nothing
    }
    
	
	/**
	 * Insert user into database
	 */
	function insert_user($user)
	{
		global $db;
	
		foreach($user as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}
		
		$query = $db->insert_query("users", $insertarray);
		$uid = $db->insert_id();
		
		return $uid;
	}
	
	/**
	 * Insert thread into database
	 */
	function insert_thread($thread)
	{
		global $db;
	
		foreach($thread as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}
		
		$query = $db->insert_query("threads", $insertarray);
		$tid = $db->insert_id();
		
		return $tid;
	}
	
	/**
	 * Get an array of imported users
	 * @return array
	 */
	function get_import_users()
	{
		global $db;
	
		$query = $db->simple_select("users", "uid, importuid");
		while($user = $db->fetch_array($query))
		{
			$users[$user['importuid']] = $user['uid'];
		}
		return $users;
	}
	
	/**
	 * Get an array of imported forums
	 * @return array
	 */
	function get_import_forums()
	{
		global $db;
	
		$query = $db->simple_select("forums", "fid, importfid");
		while($forum = $db->fetch_array($query))
		{
			$forums[$forum['importfid']] = $forum['fid'];
		}
		return $forums;
	}
	
	/**
	 * Get an array of imported threads
	 * @return array
	 */
	function get_import_threads()
	{
		global $db;
		
		$query = $db->simple_select("threads", "tid, importtid");
		while($thread = $db->fetch_array($query))
		{
			$threads[$thread['importtid']] = $thread['tid'];
		}
		return $threads;
	}
	
	/**
	 * Get an array of imported posts
	 * @return array
	 */
	function get_import_posts()
	{
		global $db;
		
		$query = $db->simple_select("posts", "pid, importpid");
		while($post = $db->fetch_array($query))
		{
			$posts[$post['importpid']] = $post['pid'];
		}
		return $posts;
	}
	
	/**
	 * Get an array of imported attachments
	 * @return array
	 */
	function get_import_attachments()
	{
		global $db;
		
		$query = $db->simple_select("attachments", "aid, importaid");
		while($attachment = $db->fetch_array($query))
		{
			$attachments[$attachment['importaid']] = $attachment['aid'];
		}
		return $attachments;
	}
	
	/**
	 * Get an array of imported usergroups
	 * @return array
	 */
	function get_import_usergroups()
	{
		global $db;
		
		$query = $db->query("usergroups", "gid, importgid");
		while($usergroup = $db->fetch_array($query))
		{
			$usergroups[$usergroup['importgid']] = $usergroup['gid'];
		}
		return $usergroups;
	}
}
?>