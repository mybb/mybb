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
$templatelist = "showteam,showteam_row,showteam_row_mod";
require "./global.php";

// Load global language phrases
$lang->load("showteam");

addnav($lang->nav_showteam);

$plugins->run_hooks("showteam_start");

$teamquery = '';
$comma = '';

$options = array(
	'order_by' => 'u.title',
	'order_dir' => 'ASC',
);
//Get all visible usergroups that aren't moderators
$query = $db->simple_select(TABLE_PREFIX."usergroups u", "u.*", "u.showforumteam='yes'", $options);
while($usergroup = $db->fetch_array($query))
{
	$leaders[$usergroup['gid']] = array();
	$users_sort[$usergroup['gid']] = array();
	$teams[$usergroup['gid']] = $usergroup;
	$teamquery .= "$comma'$usergroup[gid]'";
	$comma = ",";
}
//If we have some groups to display then if statement
//If no groups then proceed to moderators
if(!empty($teamquery))
{

	//Get the users in those groups
	$options = array(
		'order_by' => 'u.username',
		'order_dir' => 'ASC',
	);
	$query = $db->simple_select(TABLE_PREFIX."users u", "u.*", "u.displaygroup IN ($teamquery) OR (u.displaygroup = 0 AND u.usergroup IN ($teamquery))", $options);
	$users = array();
	while($user = $db->fetch_array($query))
	{
		if($user['displaygroup'] == 0)
		{
			$users[$user['usergroup']][$user['uid']] = $user;
			$users_sort[$user['usergroup']][$user['uid']] = $user['username'];
		}
		else
		{
			$users[$user['displaygroup']][$user['uid']] = $user;
			$users_sort[$user['displaygroup']][$user['uid']] = $user['username'];
		}
	}
	//Get leaders of the groups if settings allow
	if($mybb->settings['showteamleaders'] == "yes")
	{
		$query = $db->query("SELECT l.lid, l.gid, u.* FROM (".TABLE_PREFIX."groupleaders l, ".TABLE_PREFIX."users u) WHERE u.uid=l.uid AND l.gid IN ($teamquery)");
		while($leader = $db->fetch_array($query))
		{
			$leaders[$leader['gid']][] = $leader['uid'];
			$users[$leader['gid']][$leader['uid']] = $leader;
			$users_sort[$leader['gid']][$leader['uid']] = $leader['username'];
		}
	}
	//If there are users then start to display them
	//Else carry on to moderators
	if(count($users) > 0)
	{
		$usergrouprows = '';
		$groupleaderrows = '';
		$usergroups = '';
		//For each team
		foreach($teams as $gid => $usergroup)
		{
			asort($users_sort[$gid]);
			//If there are users in this group
			if(is_array($users[$gid]))
			{
				$bgcolor = "trow1";
				//For each user in this group
				foreach($users_sort[$gid] as $uid => $username)
				{
					$user = $users[$gid][$uid];
					$post['uid'] = $user['uid'];
					$user['username'] = formatname($user['username'], $user['usergroup'], $user['displaygroup']);
					if($user['hideemail'] != "yes")
					{
						eval("\$emailcode = \"".$templates->get("postbit_email")."\";");
					}
					else
					{
						$emailcode = '';
					}
					if($user['receivepms'] != "no" && $mybb->settings['enablepms'] != "no")
					{
						eval("\$pmcode = \"".$templates->get("postbit_pm")."\";");
					}
					else
					{
						$pmcode = '';
					}

					//Separate team leaders, if settings allow
					if($mybb->settings['showteamleaders'] == "yes" && isset($user['lid']))
					{
						eval("\$groupleaderrows .= \"".$templates->get("showteam_usergroup_user")."\";");
					}
					else
					{
						eval("\$usergrouprows .= \"".$templates->get("showteam_usergroup_user")."\";");
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
				}//End for each user in group
				
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

				// Put the group together
				eval("\$usergroups .= \"".$templates->get("showteam_usergroup")."\";");
			}//End for each group
			$usergrouprows = $groupleaderrows = '';
		}//For each team
	}//End if users
}//End if displayable groups

unset($user);
//Get list of moderators if settings allow
if($mybb->settings['showteammods'] == "yes")
{
	$query = $db->query("
		SELECT m.fid, m.uid, u.username, u.usergroup, u.displaygroup, u.hideemail, u.receivepms, f.name
		FROM ".TABLE_PREFIX."moderators m
		LEFT JOIN ".TABLE_PREFIX."users u ON (m.uid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=m.fid)
		ORDER BY u.username, f.name
	");
	while($mod = $db->fetch_array($query))
	{
		$modsarray[$mod['uid']] = $mod;
		if($modforums[$mod['uid']])
		{
			$modforums[$mod['uid']] .= "<br>";
		}
		$modforums[$mod['uid']] .= "<a href=\"forumdisplay.php?fid=$mod[fid]\">$mod[name]</a>";
	}
	$modrows = '';
	
	//Display moderators if they exist	
	if(is_array($modsarray))
	{
		$bgcolor = "trow1";
		while(list($uid, $user) = each($modsarray))
		{
			$forumslist = $modforums[$uid];
			$uid = $user['uid'];
			$post['uid'] = $user['uid'];
			if($user['hideemail'] != "yes")
			{
				eval("\$emailcode = \"".$templates->get("postbit_email")."\";");
			}
			else
			{
				$emailcode = '';
			}
			if($user['receivepms'] != "no")
			{
				eval("\$pmcode = \"".$templates->get("postbit_pm")."\";");
			}
			else
			{
				$pmcode = '';
			}
			$username = formatname($user['username'], $user['usergroup'], $user['displaygroup']);
			$location = $user['location'];
			eval("\$modrows .= \"".$templates->get("showteam_moderators_mod")."\";");
			if($bgcolor == "trow1")
			{
				$bgcolor = "trow2";
			}
			else
			{
				$bgcolor = "trow1";
			}
		}
		eval("\$usergroups .= \"".$templates->get("showteam_moderators")."\";");
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