<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: userpruning.php 5297 2010-12-28 22:01:14Z Tomm $
 */

function task_userpruning($task)
{
	global $db, $lang, $mybb, $cache;
	
	if($mybb->settings['enablepruning'] != 1)
	{
		return;
	}
	
	// Are we pruning by posts?
	if($mybb->settings['enableprunebyposts'] == 1)
	{
		$in_usergroups = array();
		$users = array();
		
		$usergroups = $cache->read("usergroups");
		foreach($usergroups as $gid => $usergroup)
		{
			// Exclude admin, moderators, super moderators, banned
			if($usergroup['canmodcp'] == 1 || $usergroup['cancp'] == 1 || $usergroup['issupermod'] == 1 || $usergroup['isbannedgroup'] == 1)
			{
				continue;
			}
			$in_usergroups[] = $gid;
		}
		
		// If we're not pruning unactivated users, then remove them from the criteria
		if($mybb->settings['pruneunactived'] == 0)
		{
			$key = array_search('5', $in_usergroups);
			unset($in_usergroups[$key]);
		}
		
		$regdate = TIME_NOW-(intval($mybb->settings['dayspruneregistered'])*24*60*60);
		$query = $db->simple_select("users", "uid", "regdate <= ".intval($regdate)." AND postnum <= ".intval($mybb->settings['prunepostcount'])." AND usergroup IN(".$db->escape_string(implode(',', $in_usergroups)).")");
		while($user = $db->fetch_array($query))
		{
			$users[$user['uid']] = $user['uid'];
		}
	}
	
	// Are we pruning unactivated users?
	if($mybb->settings['pruneunactived'] == 1)
	{
		$regdate = TIME_NOW-(intval($mybb->settings['dayspruneunactivated'])*24*60*60);
		$query = $db->simple_select("users", "uid", "regdate <= ".intval($regdate)." AND usergroup='5'");
		while($user = $db->fetch_array($query))
		{
			$users[$user['uid']] = $user['uid'];
		}
	}
	
	if(!empty($users))
	{
		$uid_list = $db->escape_string(implode(',', $users));
		
		// Delete the user
		$db->delete_query("userfields", "ufid IN({$uid_list})");
		$db->delete_query("privatemessages", "uid IN({$uid_list})");
		$db->delete_query("events", "uid IN({$uid_list})");
		$db->delete_query("moderators", "id IN({$uid_list}) AND isgroup='0'");
		$db->delete_query("forumsubscriptions", "uid IN({$uid_list})");
		$db->delete_query("threadsubscriptions", "uid IN({$uid_list})");
		$db->delete_query("sessions", "uid IN({$uid_list})");
		$db->delete_query("banned", "uid IN({$uid_list})");
		$db->delete_query("threadratings", "uid IN({$uid_list})");
		$db->delete_query("joinrequests", "uid IN({$uid_list})");
		$db->delete_query("awaitingactivation", "uid IN({$uid_list})");
		$query = $db->delete_query("users", "uid IN({$uid_list})");
		$num_deleted = $db->affected_rows($query);

		// Remove any of the user(s) uploaded avatars
		$query = $db->simple_select("users", "avatar", "uid IN ({$uid_list}) AND avatartype = 'upload'");
		if($db->num_rows($query))
		{
			while($avatar = $db->fetch_field($query, "avatar"))
			{
				$avatar = substr($avatar, 2, -20);
				@unlink(MYBB_ROOT.$avatar);
			}
		}

		// Are we removing the posts/threads of a user?
		if($mybb->settings['prunethreads'] == 1)
		{
			require_once MYBB_ROOT."inc/class_moderation.php";
			$moderation = new Moderation();

			// Threads
			$query = $db->simple_select("threads", "tid", "uid IN({$uid_list})");
			while($thread = $db->fetch_array($query))
			{
				$moderation->delete_thread($thread['tid']);
			}

			// Posts
			$query = $db->simple_select("posts", "pid", "uid IN({$uid_list})");
			while($post = $db->fetch_array($query))
			{
				$moderation->delete_post($post['pid']);
			}
		}
		else
		{
			// We're just updating the UID
			$db->update_query("posts", array('uid' => 0), "uid IN({$uid_list})");
		}

		// Update forum stats
		update_stats(array('numusers' => '-'.intval($num_deleted)));
		
		$cache->update_moderators();
		$cache->update_banned();
	}
	
	add_task_log($task, $lang->task_userpruning_ran);
}
?>