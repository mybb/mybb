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
	 * Cache for the new UIDs
	 */
	var $import_uids;
	
	/**
	 * Cache for the new FIDs
	 */
	var $import_fids;
	
	/**
	 * Cache for the new TIDs
	 */
	var $import_tids;

	/**
	 * Cache for the new GIDs
	 */
	var $import_gids;
	
	/**
	 * Class constructor
	 */
    function Converter()
    {
    	return 'MyBB'; 
    }
    
    /**
	 * Make a database connection
	 * @param array Database configuration
	 */
	function connect($config)
	{
		require_once MYBB_ROOT."/inc/db_{$config['dbtype']}.php";
		$this->olddb = new databaseEngine;
		
		// Connect to Database
		$this->olddb->connect($config['hostname'], $config['username'], $config['password']);
		$this->olddb->select_db($config['database']);
		$this->olddb->set_table_prefix($config['table_prefix']);
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
	 * Insert forum into database
	 */
	function insert_forum($forum)
	{
		global $db;
	
		foreach($forum as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}
		
		$query = $db->insert_query("forums", $insertarray);
		$fid = $db->insert_id();
		
		return $fid;
	}

	/**
	 * Insert post into database
	 */
	function insert_post($post)
	{
		global $db;
	
		foreach($post as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}
		
		$query = $db->insert_query("posts", $insertarray);
		$pid = $db->insert_id();
		
		return $pid;
	}
	/**
	 * Insert moderator into database
	 */
	function insert_moderator($mod)
	{
		global $db;
	
		foreach($mod as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}
		
		$query = $db->insert_query("moderators", $insertarray);
		$mid = $db->insert_id();
		
		return $mid;
	}
	/**
	 * Insert usergroup into database
	 */
	function insert_usergroup($group)
	{
		global $db;
	
		foreach($group as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}
		
		$query = $db->insert_query("usergroups", $insertarray);
		$gid = $db->insert_id();
		
		return $gid;
	}
	/**
	 * Insert user titles into database
	 */
	function insert_usertitle($title)
	{
		global $db;
	
		foreach($title as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}
		
		$query = $db->insert_query("usertitles", $insertarray);
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
	
		$query = $db->simple_select("users", "uid, import_uid");
		while($user = $db->fetch_array($query))
		{
			$users[$user['import_uid']] = $user['uid'];
		}
		$this->import_uids = $users;
		return $users;
	}
	
	/**
	 * Get the MyBB UID of an old UID.
	 * @param int User ID used before import
	 * @return int User ID in MyBB or 0 if the old UID cannot be found
	 */
	function get_import_uid($old_uid)
	{
		if(!is_array($this->import_uids))
		{
			$uid_array = $this->get_import_users();
		}
		else
		{
			$uid_array = $this->import_uids;
		}
		if(!isset($uid_array[$old_uid]) || $old_uid == 0)
		{
			return 0;
		}
		return $uid_array[$old_uid];
	}
	
	/**
	 * Get an array of imported forums
	 * @return array
	 */
	function get_import_forums()
	{
		global $db;
	
		$query = $db->simple_select("forums", "fid, import_fid");
		while($forum = $db->fetch_array($query))
		{
			$forums[$forum['import_fid']] = $forum['fid'];
		}
		$this->import_fids = $forums;
		return $forums;
	}
	
	/**
	 * Get the MyBB FID of an old FID.
	 * @param int Forum ID used before import
	 * @return int Forum ID in MyBB
	 */
	function get_import_fid($old_fid)
	{
		if(!is_array($this->import_fids))
		{
			$fid_array = $this->get_import_forums();
		}
		else
		{
			$fid_array = $this->import_fids;
		}
		return $fid_array[$old_fid];
	}
	
	/**
	 * Get an array of imported threads
	 * @return array
	 */
	function get_import_threads()
	{
		global $db;
		
		$query = $db->simple_select("threads", "tid, import_tid");
		while($thread = $db->fetch_array($query))
		{
			$threads[$thread['import_tid']] = $thread['tid'];
		}
		return $threads;
	}
	
	/**
	 * Get the MyBB TID of an old TID.
	 * @param int Thread ID used before import
	 * @return int Thread ID in MyBB
	 */
	function get_import_tid($old_tid)
	{
		if(!is_array($this->import_tids))
		{
			$tid_array = $this->get_import_threads();
		}
		else
		{
			$tid_array = $this->import_tids;
		}
		return $tid_array[$old_tid];
	}
	
	/**
	 * Get an array of imported posts
	 * @return array
	 */
	function get_import_posts()
	{
		global $db;
		
		$query = $db->simple_select("posts", "pid, import_pid");
		while($post = $db->fetch_array($query))
		{
			$posts[$post['import_pid']] = $post['pid'];
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
		
		$query = $db->simple_select("attachments", "aid, import_aid");
		while($attachment = $db->fetch_array($query))
		{
			$attachments[$attachment['import_aid']] = $attachment['aid'];
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
		
		$query = $db->query("usergroups", "gid, import_gid");
		while($usergroup = $db->fetch_array($query))
		{
			$usergroups[$usergroup['import_gid']] = $usergroup['gid'];
		}
		$this->import_gids = $usergroups;
		return $usergroups;
	}
	
	/**
	 * Get the MyBB usergroup ID of an old GID.
	 * @param int Group ID used before import
	 * @return int Group ID in MyBB
	 */
	function get_import_gid($old_gid)
	{
		if(!is_array($this->import_gids))
		{
			$gid_array = $this->get_import_usergroups();
		}
		else
		{
			$gid_array = $this->import_gids;
		}
		return $gid_array[$old_gid];
	}
}
?>