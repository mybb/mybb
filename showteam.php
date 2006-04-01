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

//SQL Explanation
//SELECT everything from usergroups AND users (usergroup must contain users)
//See if there are any leaders for that group
//See if they are moderators and if there are, join with forums to get their details
//WHERE usergroup is visible
//AND The group is moderators (6) and the user is a mod of a forum (fid IS NOT NULL)
//OR the user is apart of that group through display or usergroup settings
$sql = "
SELECT
	g.gid, g.type, g.title, g.usertitle, u.uid, u.username, u.hideemail, u.receivepms, u.displaygroup, u.usergroup, u.ignorelist, l.lid, m.fid ,f.name
FROM
(
	(mybb_usergroups g, mybb_users u)
	LEFT JOIN
		mybb_groupleaders l
		ON l.gid = g.gid AND u.uid = l.uid
	LEFT JOIN
		(
			mybb_moderators m
			LEFT JOIN mybb_forums f
				ON f.fid= m.fid
		)
		ON m.uid = u.uid
)
WHERE g.showforumteam = 'yes'
AND
(
	(g.gid = 6 AND f.fid IS NOT NULL)
	OR
	((u.displaygroup = g.gid) OR (u.displaygroup = 0 AND g.gid IN (u.usergroup)))
)
ORDER BY g.disporder ASC, u.username ASC, f.disporder ASC
";

$users = array();
$mods = array();
$usergroup = array();
$groupusers = array();
$query = $db->query($sql);

//For each entry
while($details = $db->fetch_array($query))
{
	//Setup usergroup list
	$usergroup[$details['gid']] = $details['title'];
	if(!isset($groupusers[$details['gid']]))
	{
		$groupusers[$details['gid']] = array();
	}
	//Setup usergroup list with user ids
	$groupusers[$details['gid']][$details['uid']] = TRUE;
	//User information
	$users[$details['uid']] = array(
		'uid' => $details['uid'],
		'username' => $details['username'],
		'hideemail' => $details['hideemail'],
		'receivepms' => $details['receivepms'],
		'displaygroup' => $details['displaygroup'],
		'usergroup' => $details['usergroup'],
		'lid' => $details['lid'],
		'ignorelist' => $details['ignorelist'],
	);
	//If the user has an fid in the row, they are a moderator
	if(!empty($details['fid']))
	{
		if(!isset($mods[$details['uid']]))
		{
			$mods[$details['uid']] = array();
		}
		$mods[$details['uid']][$details['fid']] = array(
			'fid' => $details['fid'],
			'name' => $details['name'],
		);
	}
}

$usergroups = '';
//For each usergroup
foreach($usergroup as $gid=>$usergrouptitle)
{
	$groupleaderrows = '';
	$usergrouprows = '';
	$modrows = '';
	$forumslist = '';
	if(is_array($groupusers[$gid]))
	{
		//For every user in that group
		foreach($groupusers[$gid] as $uid=>$bool)
		{
			$bgcolor = 'trow1';
			$user = $users[$uid];
			$post['uid'] = $uid;

			$user_permissions = user_permissions($uid);

			$user['username'] = formatname($user['username'], $user['usergroup'], $user['displaygroup']);
			if($user['hideemail'] != 'yes')
			{
				eval("\$emailcode = \"".$templates->get("postbit_email")."\";");
			}
			else
			{
				$emailcode = '';
			}
			if($user['receivepms'] != 'no' && $mybb->settings['enablepms'] != 'no' && $user_permissions['canusepms'] != 'no' && strpos(",".$user['ignorelist'].",", ",".$mybb->user['uid'].",") === false)
			{
				eval("\$pmcode = \"".$templates->get("postbit_pm")."\";");
			}
			else
			{
				$pmcode = '';
			}

			//If the current group is a moderator group, do some special formatting
			if($gid == 6)
			{
				$forumslist = '';
				if(is_array($mods[$uid]))
				{
					foreach($mods[$uid] as $forum)
					{
						eval("\$forumslist .= \"".$templates->get("showteam_moderators_forum")."\";");
					}
					eval("\$modrows .= \"".$templates->get("showteam_moderators_mod")."\";");
				}
			}
			else
			{
				//Separate team leaders, if settings allow
				if($mybb->settings['showteamleaders'] == 'yes' && !empty($user['lid']))
				{
					eval("\$groupleaderrows .= \"".$templates->get("showteam_usergroup_user")."\";");
				}
				else
				{
					eval("\$usergrouprows .= \"".$templates->get("showteam_usergroup_user")."\";");
				}
			}

			//Sort out background colour for next round
			if($bgcolor == "trow1")
			{
				$bgcolor = "trow2";
			}
			else
			{
				$bgcolor = "trow1";
			}
			//Use this so we can determine which template to use after the foreach user loop
			$last_gid = $gid;
		}
		//Format the group leaders
		if(!empty($groupleaderrows))
		{
			$groupmemberrows = $usergrouprows;
			$usergrouprows = '';
			// There are leaders, so add them on first
			eval("\$usergrouprows .= \"".$templates->get("showteam_leader_header")."\";");
			$usergrouprows .= $groupleaderrows;
			// If there are group members, add them on
			if(!empty($groupmemberrows))
			{
				eval("\$usergrouprows .= \"".$templates->get("showteam_member_header")."\";");
				$usergrouprows .= $groupmemberrows;
			}
		}
		//Moderators need a different template
		if($last_gid == 6)
		{
			eval("\$usergroups .= \"".$templates->get("showteam_moderators")."\";");
		}
		else
		{
			eval("\$usergroups .= \"".$templates->get("showteam_usergroup")."\";");
		}
	}
}


if(empty($usergroups))
{
	error($lang->error_noteamstoshow);
}

eval("\$showteam = \"".$templates->get("showteam")."\";");
$plugins->run_hooks("showteam_end");
outputpage($showteam);
?>