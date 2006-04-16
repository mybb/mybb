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
$templatelist = 'showteam,showteam_row,showteam_row_mod,postbit_email,postbit_pm';
$templatelist .= ',showteam_usergroup_user,showteam_usergroup,showteam_moderators_mod';
$templatelist .= ',showteam_moderators,showteam_leader_header,showteam_moderators_forum';
require "./global.php";

// Load global language phrases
$lang->load('showteam');

addnav($lang->nav_showteam);

$plugins->run_hooks('showteam_start');

$usergroups = array();
$moderators = array();
$users = array();

// Fetch the list of groups which are to be shown on the page
$query = $db->query("SELECT gid, title, usertitle FROM ".TABLE_PREFIX."usergroups WHERE showforumteam='yes' ORDER BY disporder");
while($usergroup = $db->fetch_array($query))
{
	$usergroups[$usergroup['gid']] = $usergroup;
}

if(empty($usergroups))
{
	error($lang->error_noteamstoshow);
}

// Fetch specific forum moderator details
$query = $db->query("SELECT m.*, f.name FROM ".TABLE_PREFIX."moderators m LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=m.uid) LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=m.fid) ORDER BY u.username");
while($moderator = $db->fetch_array($query))
{
	$moderators[$moderator['uid']][] = $moderator;
} 

// Now query the users of those specific groups
$groups_in = implode(",", array_keys($usergroups));
$users_in = implode(",", array_keys($moderators));
if(!$groups_in)
{
	$groups_in = 0;
}
if(!$users_in)
{
	$users_in = 0;
}
$forum_permissions = forum_permissions();

$query = $db->query("SELECT uid, username, displaygroup, usergroup, ignorelist, hideemail, receivepms FROM ".TABLE_PREFIX."users WHERE usergroup IN ($groups_in) OR uid IN ($users_in) ORDER BY username");
while($user = $db->fetch_array($query))
{
	// If this user is a moderator
	if($moderators[$user['uid']])
	{
		foreach($moderators[$user['uid']] as $forum)
		{
			if($forum_permissions[$forum['fid']]['canview'] == "yes")
			{
				eval("\$forumslist .= \"".$templates->get("showteam_moderators_forum")."\";");
			}
		}
		$user['forumlist'] = $forumlist;
		$forumlist = '';
		$usergroups[6]['user_list'][$user['uid']] = $user;
	}
	
	// Are they also in another group which is being shown on the list?
	if($usergroups[$user['usergroup']] && $user['usergroup'] != 6)
	{
		$usergroups[$user['usergroup']]['user_list'][$user['uid']] = $user;
	}
}

// Now we have all of our user details we can display them.
foreach($usergroups as $usergroup)
{
	// If we have no users - don't show this group
	if(!$usergroup['user_list'])
	{
		continue;
	}
	$bgcolor = '';
	foreach($usergroup['user_list'] as $user)
	{
		$user['username'] = formatname($user['username'], $user['usergroup'], $user['displaygroup']);
		if($user['hideemail'] != 'yes')
		{
			eval("\$emailcode = \"".$templates->get("postbit_email")."\";");
		}
		else
		{
			$emailcode = '';
		}
		if($user['receivepms'] != 'no' && $mybb->settings['enablepms'] != 'no' && strpos(",".$user['ignorelist'].",", ",".$mybb->user['uid'].",") === false)
		{
			eval("\$pmcode = \"".$templates->get("postbit_pm")."\";");
		}
		else
		{
			$pmcode = '';
		}
		
		if($bgcolor == 'trow1')
		{
			$bgcolor = 'trow2';
		}
		else
		{
			$bgcolor = 'trow1';
		}

		//If the current group is a moderator group
		if($usergroup['gid'] == 6)
		{
			$forumlist = $user['forumlist'];
			eval("\$modrows .= \"".$templates->get("showteam_moderators_mod")."\";");
		}
		else
		{
			eval("\$usergrouprows .= \"".$templates->get("showteam_usergroup_user")."\";");
		}	
	}
	
	if($usergroup['gid'] == 6)
	{
		eval("\$grouplist .= \"".$templates->get("showteam_moderators")."\";");
	}
	else
	{
		eval("\$grouplist .= \"".$templates->get("showteam_usergroup")."\";");
	}
	$usergrouprows = '';
}

eval("\$showteam = \"".$templates->get("showteam")."\";");
$plugins->run_hooks("showteam_end");
outputpage($showteam);
?>