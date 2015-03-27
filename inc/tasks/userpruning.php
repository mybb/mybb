<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

function task_userpruning($task)
{
	global $db, $lang, $mybb, $cache, $plugins;

	if($mybb->settings['enablepruning'] != 1)
	{
		return;
	}

	// Are we pruning by posts?
	if($mybb->settings['enableprunebyposts'] == 1)
	{
		$in_usergroups = array();
		$users = array();

		$usergroups = $cache->read('usergroups');
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

		$prunepostcount = (int)$mybb->settings['prunepostcount'];

		$regdate = TIME_NOW-((int)$mybb->settings['dayspruneregistered']*24*60*60);

		$usergroups = $db->escape_string(implode(',', $in_usergroups));

		$query = $db->simple_select('users', 'uid', "regdate<={$regdate} AND postnum<={$prunepostcount} AND usergroup IN({$usergroups})");
		while($uid = $db->fetch_field($query, 'uid'))
		{
			$users[$uid] = $uid;
		}

		if($users && $mybb->settings['prunepostcountall'])
		{
			$query = $db->simple_select('posts', 'uid, COUNT(pid) as posts', "uid IN ('".implode("','", $users)."') AND visible>0", array('group_by' => 'uid'));
			while($user = $db->fetch_array($query))
			{
				if($user['posts'] >= $prunepostcount)
				{
					unset($users[$user['uid']]);
				}
			}
		}
	}

	// Are we pruning unactivated users?
	if($mybb->settings['pruneunactived'] == 1)
	{
		$regdate = TIME_NOW-((int)$mybb->settings['dayspruneunactivated']*24*60*60);
		$query = $db->simple_select("users", "uid", "regdate<={$regdate} AND usergroup='5'");
		while($user = $db->fetch_array($query))
		{
			$users[$user['uid']] = $user['uid'];
		}
	}

	if(is_object($plugins))
	{
		$args = array(
			'task' => &$task,
			'in_usergroups' => &$in_usergroups,
			'users' => &$users,
		);
		$plugins->run_hooks('task_userpruning', $args);
	}

	if(!empty($users))
	{
		// Set up user handler.
		require_once MYBB_ROOT.'inc/datahandlers/user.php';
		$userhandler = new UserDataHandler('delete');

		// Delete the prunned users
		$userhandler->delete_user($users, $mybb->settings['prunethreads']);
	}

	add_task_log($task, $lang->task_userpruning_ran);
}
