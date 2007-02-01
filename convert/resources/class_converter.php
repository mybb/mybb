<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
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
	 * Cache for the new Usernames
	 */
	var $import_usernames;
	
	/**
	 * Cache for the new Settinggroups
	 */
	var $import_settinggroups;
	
	/**
	 * Cache for the new Events
	 */
	var $import_events;
	
	/**
	 * Cache for the new Attachments
	 */
	var $import_attachments;
	
	/**
	 * Cache for the new Attachment Types
	 */
	var $import_attachtypes;
	
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
		require_once MYBB_ROOT."inc/db_{$config['dbtype']}.php";
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
		
		$db->insert_query("users", $insertarray);
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
		
		$db->insert_query("threads", $insertarray);
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
		
		$db->insert_query("forums", $insertarray);
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
		
		$db->insert_query("posts", $insertarray);
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
		
		$db->insert_query("moderators", $insertarray);
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
		
		$db->insert_query("usergroups", $insertarray);
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
		
		$db->insert_query("usertitles", $insertarray);
		$tid = $db->insert_id();
		
		return $tid;
	}
	
	/**
	 * Insert privatemessages into database
	 */
	function insert_privatemessage($title)
	{
		global $db;
	
		foreach($title as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}
		
		$db->insert_query("privatemessages", $insertarray);
		$pmid = $db->insert_id();
		
		return $pmid;
	}
	
	/**
	 * Insert forumpermissions into database
	 */
	function insert_forumpermission($title)
	{
		global $db;
	
		foreach($title as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}
		
		$query = $db->insert_query("forumpermissions", $insertarray);
		$fpid = $db->insert_id();
		
		return $fpid;
	}
	
	/**
	 * Insert poll into database
	 */
	function insert_poll($title)
	{
		global $db;
	
		foreach($title as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}
		
		$db->insert_query("polls", $insertarray);
		$pollid = $db->insert_id();
		
		return $pollid;
	}
	
	/**
	 * Insert poll vote into database
	 */
	function insert_pollvote($title)
	{
		global $db;
	
		foreach($title as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}
		
		$db->insert_query("pollvotes", $insertarray);
		$pollvoteid = $db->insert_id();
		
		return $pollvoteid;
	}

	/**
	 * Insert setting into database
	 */
	function insert_setting($setting)
	{
		global $db;
	
		foreach($setting as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}
		
		$db->insert_query("settings", $insertarray);
		$sid = $db->insert_id();
		
		return $sid;
	}

	/**
	 * Insert settinggroup into database
	 */
	function insert_settinggroup($settinggroup)
	{
		global $db;

		foreach($settinggroup as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}

		$db->insert_query("settinggroups", $insertarray);
		$gid = $db->insert_id();
		
		return $gid;
	}
	
	/**
	 * Insert attachment into database
	 */
	function insert_attachment($attachment)
	{
		global $db;

		foreach($attachment as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}

		$db->insert_query("attachments", $insertarray);
		$aid = $db->insert_id();
		
		return $aid;
	}
	
	/**
	 * Insert an event into database
	 */
	function insert_event($event)
	{
		global $db;

		foreach($event as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}

		$db->insert_query("events", $insertarray);
		$eid = $db->insert_id();
		
		return $eid;
	}
	
	/**
	 * Insert attachment type into database
	 */
	function insert_attachtype($attachtype)
	{
		global $db;

		foreach($attachtype as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}

		$db->insert_query("attachtypes", $insertarray);
		$atid = $db->insert_id();
		
		return $atid;
	}
	
	/**
	 * Insert a icon into database
	 */
	function insert_icon($icon)
	{
		global $db;

		foreach($icon as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}

		$db->insert_query("icons", $insertarray);
		$cid = $db->insert_id();
		
		return $cid;
	}
	
	/**
	 * Insert a smilie into database
	 */
	function insert_smilie($smilie)
	{
		global $db;

		foreach($smilie as $key => $value)
		{
			$insertarray[$key] = $db->escape_string($value);
		}

		$db->insert_query("smilies", $insertarray);
		$sid = $db->insert_id();
		
		return $sid;
	}

	/**
	 * Update setting in the database
	 */
	function update_setting($name, $value)
	{
		global $db;

		$modify = array(
			'value' => $db->escape_string($value)
		);
		$db->update_query("settings", $modify, "name='{$name}'");
	}
	
	/**
	 * Get an array of imported users
	 *
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
	 * Get an array of imported usernames
	 *
	 * @return array
	 */
	function get_import_usernames()
	{
		global $db;
	
		$query = $db->simple_select("users", "username, import_uid");
		while($user = $db->fetch_array($query))
		{
			$users[$user['import_uid']] = $user['username'];
		}
		$this->import_usernames = $users;
		return $users;
	}
	
	/**
	 * Get an array of imported polls
	 *
	 * @return array
	 */
	function get_import_polls()
	{
		global $db;
	
		$query = $db->simple_select("polls", "pid, import_pid");
		while($poll = $db->fetch_array($query))
		{
			$polls[$poll['import_pid']] = $poll['pid'];
		}
		$this->import_pids = $polls;
		return $polls;
	}
	
	/**
	 * Get an array of imported poll votes
	 *
	 * @return array
	 */
	function get_import_pollvotes()
	{
		global $db;
	
		$query = $db->simple_select("pollvotes", "vid, import_vid");
		while($pollvote = $db->fetch_array($query))
		{
			$pollvotes[$pollvote['import_vid']] = $pollvote['vid'];
		}
		$this->import_pollvotes = $pollvotes;
		return $pollvotes;
	}
	
	/**
	 * Get the MyBB PID of an old PID.
	 *
	 * @param int Poll ID used before import
	 * @return int Poll ID in MyBB or 0 if the old PID cannot be found
	 */
	function get_import_pid($old_pid)
	{
		if(!is_array($this->import_pids))
		{
			$pid_array = $this->get_import_polls();
		}
		else
		{
			$pid_array = $this->import_pids;
		}
		
		if(!isset($pid_array[$old_pid]) || $old_pid == 0)
		{
			return 0;
		}
		return $pid_array[$old_pid];
	}
	
	/**
	 * Get the MyBB VID of an old VID.
	 *
	 * @param int Vote ID used before import
	 * @return int Vote ID in MyBB or 0 if the old VID cannot be found
	 */
	function get_import_vid($old_vid)
	{
		if(!is_array($this->import_vids))
		{
			$vid_array = $this->get_import_pollvotes();
		}
		else
		{
			$vid_array = $this->import_vids;
		}
		
		if(!isset($vid_array[$old_vid]) || $old_vid == 0)
		{
			return 0;
		}
		return $vid_array[$old_vid];
	}
	
	/**
	 * Get the MyBB UID of an old UID.
	 *
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
	 * Get the MyBB Username of an old UID.
	 *
	 * @param int User ID used before import
	 * @return int User ID in MyBB or 0 if the old UID cannot be found
	 */
	function get_import_username($old_uid)
	{
		if(!is_array($this->import_usernames))
		{
			$username_array = $this->get_import_usernames();
		}
		else
		{
			$username_array = $this->import_usernames;
		}
		
		if(!isset($username_array[$old_uid]) || $old_uid == 0)
		{
			return 'Guest';
		}
		return $username_array[$old_uid];
	}
	
	/**
	 * Get an array of imported forums
	 *
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
	 *
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
	 *
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
		$this->import_tids = $threads;
		return $threads;
	}

	/**
	 * Get the MyBB TID of an old TID.
	 *
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
	 *
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
	 *
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
	 *
	 * @return array
	 */
	function get_import_usergroups()
	{
		global $db;
		
		$query = $db->simple_select("usergroups", "gid, import_gid");
		while($usergroup = $db->fetch_array($query))
		{
			$usergroups[$usergroup['import_gid']] = $usergroup['gid'];
		}
		$this->import_gids = $usergroups;
		return $usergroups;
	}
	
	/**
	 * Get an array of imported events
	 *
	 * @return array
	 */
	function get_import_events()
	{
		global $db;
		
		$query = $db->simple_select("events", "eid, import_eid");
		while($event = $db->fetch_array($query))
		{
			$events[$event['import_eid']] = $event['eid'];
		}
		$this->import_eids = $events;
		return $events;
	}
	
	/**
	 * Get the MyBB usergroup ID of an old GID.
	 *
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
	
	/**
	 * Get an array of imported settinggroups
	 *
	 * @return array
	 */
	function get_import_settinggroups()
	{
		global $db;

		$query = $db->simple_select("settinggroups", "gid, import_gid");
		while($settinggroup = $db->fetch_array($query))
		{
			$settinggroups[$settinggroup['import_gid']] = $settinggroup['gid'];
		}
		$this->import_settinggroups = $settinggroups;
		return $settinggroups;
	}
	
	/**
	 * Get the MyBB settinggroups ID of an old GID.
	 *
	 * @param int Group ID used before import
	 * @return int Group ID in MyBB
	 */
	function get_import_settinggroup($old_gid)
	{
		if(!is_array($this->import_settinggroups))
		{
			$gid_array = $this->get_import_settinggroups();
		}
		else
		{
			$gid_array = $this->import_settinggroups;
		}
		return $gid_array[$old_gid];
	}
	
	/**
	 * Get the MyBB attachments ID of an old AID.
	 *
	 * @param int Attachment ID used before import
	 * @return int Attachment ID in MyBB
	 */
	function get_import_aid($old_aid)
	{
		if(!is_array($this->import_attachments))
		{
			$aid_array = $this->get_import_attachments();
		}
		else
		{
			$aid_array = $this->import_attachments;
		}
		return $aid_array[$old_aid];
	}
	
	/**
	 * Get the MyBB event ID of an old EID.
	 *
	 * @param int Event ID used before import
	 * @return int Event ID in MyBB
	 */
	function get_import_eid($old_eid)
	{
		if(!is_array($this->import_events))
		{
			$eid_array = $this->get_import_events();
		}
		else
		{
			$eid_array = $this->import_events;
		}
		return $eid_array[$old_eid];
	}

	/**
	 * Get an array of imported attachment types
	 *
	 * @return array
	 */
	function get_import_attachtypes()
	{
		global $db;
		
		$query = $db->simple_select("attachtypes", "atid, import_atid");
		while($type = $db->fetch_array($query))
		{
			$attachtypes[$type['import_atid']] = $type['atid'];
		}
		$this->import_attachtypes = $attachtypes;
		return $attachtypes;
	}
	
	/**
	 * Get the MyBB attachment type ID of an old attachment type id.
	 *
	 * @param int Attachment Type ID used before import
	 * @return int Attachment Type ID in MyBB
	 */
	function get_import_atid($old_atid)
	{
		if(!is_array($this->import_attachtypes))
		{
			$atid_array = $this->get_import_attachtypes();
		}
		else
		{
			$atid_array = $this->import_attachtypes;
		}
		return $atid_array[$old_atid];
	}
	
	/**
	 * Get an array of imported icons
	 *
	 * @return array
	 */
	function get_import_icons()
	{
		global $db;
		
		$query = $db->simple_select("icons", "iid, import_iid");
		while($icon = $db->fetch_array($query))
		{
			$icons[$icon['import_iid']] = $icon['iid'];
		}
		$this->import_icons = $icons;
		return $icons;
	}
	
	/**
	 * Get the MyBB icon ID of an old icon id.
	 *
	 * @param int Icon ID used before import
	 * @return int Icon ID in MyBB
	 */
	function get_import_iid($old_iid)
	{
		if(!is_array($this->import_icons))
		{
			$iid_array = $this->get_import_icons();
		}
		else
		{
			$iid_array = $this->import_icons;
		}
		return $iid_array[$old_iid];
	}
}
?>