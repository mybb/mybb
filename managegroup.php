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

 define("KILL_GLOBALS", 1);

require "./global.php";

// Load language files
$lang->load("managegroup");

$gid = intval($mybb->input['gid']);

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='$gid' AND type <> 1");
$usergroup = $db->fetch_array($query);
if(!$usergroup['gid'])
{
	error($lang->invalid_group);
}
$lang->nav_group_management = sprintf($lang->nav_group_management, $usergroup['title']);
addnav($lang->nav_group_memberships, "usercp.php?action=usergroups");
addnav($lang->nav_group_management, "managegroup.php?gid=$gid");

if($mybb->input['action'] == "joinrequests")
{
	addnav($lang->nav_join_requests);
}

// Check that this user is actually a leader of this group
$query = $db->query("SELECT * FROM ".TABLE_PREFIX."groupleaders WHERE uid='".$mybb->user['uid']."' AND gid='$gid'");
$groupleader = $db->fetch_array($query);
if(!$groupleader['uid'])
{
	error($lang->not_leader_of_this_group);
}

if($mybb->input['action'] == "do_add")
{
	$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users u WHERE username = '".addslashes($mybb->input['username'])."' LIMIT 1");
	$user = $db->fetch_array($query);
	if($user['uid'])
	{
		join_usergroup($user['uid'], $gid);
		redirect("managegroup.php?gid=".$gid, $lang->user_added);
	}
	else
	{
		error($lang->error_invalidusername);
	}
}
elseif($mybb->input['action'] == "do_joinrequests")
{
	if(is_array($mybb->input['request']))
	{
		foreach($mybb->input['request'] as $uid => $what)
		{
			if($what == "accept")
			{
				join_usergroup($uid, $gid);
				$uidin[] = $uid;
			}
			elseif($what == "decline")
			{
				$uidin[] = $uid;
			}
		}
	}
	if(is_array($uidin))
	{
		$uids = implode(",", $uidin);
		$db->query("DELETE FROM ".TABLE_PREFIX."joinrequests WHERE uid IN($uids)");
	}
	redirect("usercp.php?action=usergroups", $lang->join_requests_moderated);
}
elseif($mybb->input['action'] == "joinrequests")
{
	$query = $db->query("SELECT j.*, u.uid, u.username, u.postnum, u.regdate FROM ".TABLE_PREFIX."joinrequests j LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=j.uid) WHERE j.gid='".$mybb->input['gid']."' ORDER BY u.username ASC");
	while($user = $db->fetch_array($query))
	{
		$user['reason'] = htmlspecialchars_uni($user['reason']);
		$altbg = alt_trow();
		$regdate = mydate($mybb->settings['dateformat'], $user['regdate']);
		eval("\$users .= \"".$templates->get("managegroup_joinrequests_request")."\";");
	}
	if(!$users)
	{
		error($lang->no_requests);
	}
	$lang->join_requests = sprintf($lang->join_requests_title,htmlspecialchars_uni($usergroup['title']));
	eval("\$joinrequests = \"".$templates->get("managegroup_joinrequests")."\";");
	outputpage($joinrequests);
}
elseif($mybb->input['action'] == "do_manageusers")
{
	if(is_array($mybb->input['removeuser']))
	{
		foreach($mybb->input['removeuser'] as $uid)
		{
			leave_usergroup($uid, $mybb->input['gid']);
		}
	}
	redirect("usercp.php?action=usergroups", $lang->users_removed);
}
else
{
	$lang->members_of = sprintf($lang->members_of, $usergroup['title']);
	$lang->add_member = sprintf($lang->add_member, $usergroup['title']);
	if($usergroup['type'] == 4)
	{
		$query = $db->query("SELECT COUNT(*) AS req FROM ".TABLE_PREFIX."joinrequests WHERE gid='".$mybb->input['gid']."'");
		$numrequests = $db->fetch_array($query);
		if($numrequests['req'])
		{
			$lang->num_requests_pending = sprintf($lang->num_requests_pending, $numrequests['req']);
			eval("\$joinrequests = \"".$templates->get("managegroup_requestnote")."\";");
		}
	}

	$uquery = "SELECT * FROM ".TABLE_PREFIX."users WHERE CONCAT(',',additionalgroups,',') LIKE '%,".$mybb->input['gid'].",%' ORDER BY username ASC";
	$query = $db->query($uquery);
	$numusers = $db->num_rows($query);
	/*if(!$numusers && !$numrequests)
	{
		error($lang->group_no_members);
	}*/
	$perpage = $mybb->settings['membersperpage'];
	if($page)
	{
		$start = ($page-1) *$perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$multipage = multipage($numusers, $perpage, $page, "managegroup.php?gid=".$mybb->input['gid']);
	$uquery .= " LIMIT $start, $perpage";

	while($user = $db->fetch_array($query))
	{
		$altbg = alt_trow();
		$regdate = mydate($mybb->settings['dateformat'], $user['regdate']);
		$post = $user;
		eval("\$sendpm = \"".$templates->get("postbit_pm")."\";");
		if($user['hideemail'] != "yes")
		{
			eval("\$email = \"".$templates->get("postbit_email")."\";");
		}
		else
		{
			$email = "";
		}
		$query1 = $db->query("SELECT uid FROM ".TABLE_PREFIX."groupleaders WHERE uid='$user[uid]' AND gid='$gid'");
		$isleader = $db->fetch_array($query1);
		$user['username'] = formatname($user['username'], $user['usergroup']);
		if($isleader['uid'])
		{
			$leader = $lang->leader;
		}
		else
		{
			$leader = '';
		}
		eval("\$users .= \"".$templates->get("managegroup_user")."\";");
	}
	eval("\$manageusers = \"".$templates->get("managegroup")."\";");
	outputpage($manageusers);
}
?>