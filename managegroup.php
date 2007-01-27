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

define("IN_MYBB", 1);

require_once "./global.php";

// Load language files
$lang->load("managegroup");

$gid = $mybb->input['gid'] = intval($mybb->input['gid']);
$usergroup = $groupscache[$mybb->input['gid']];
if(!$usergroup['gid'])
{
	error($lang->invalid_group);
}
$lang->nav_group_management = sprintf($lang->nav_group_management, $usergroup['title']);
add_breadcrumb($lang->nav_group_memberships, "usercp.php?action=usergroups");
add_breadcrumb($lang->nav_group_management, "managegroup.php?gid=$gid");

if($mybb->input['action'] == "joinrequests")
{
	add_breadcrumb($lang->nav_join_requests);
}

// Check that this user is actually a leader of this group
$query = $db->simple_select(TABLE_PREFIX."groupleaders", "*", "uid='{$mybb->user['uid']}' AND gid='{$gid}'");
$groupleader = $db->fetch_array($query);
if(!$groupleader['uid'])
{
	error($lang->not_leader_of_this_group);
}

if($mybb->input['action'] == "do_add" && $mybb->request_method == "post")
{
	if($groupleader['canmanagemembers'] == "no")
	{
		error_no_permission();
	}
	$query = $db->simple_select(TABLE_PREFIX."users", "uid, additionalgroups, usergroup", "username = '".$db->escape_string($mybb->input['username'])."'", array("limit" => 1));
	$user = $db->fetch_array($query);
	if($user['uid'])
	{
		$additionalgroups = explode(',', $user['additionalgroups']);
		if ($user['usergroup'] != $gid && !in_array($gid, $additionalgroups))
		{
			join_usergroup($user['uid'], $gid);
			redirect("managegroup.php?gid=".$gid, $lang->user_added);
		}
		else 
		{
			error($lang->error_alreadyingroup);
		}
	}
	else
	{
		error($lang->error_invalidusername);
	}
}
elseif($mybb->input['action'] == "do_joinrequests" && $mybb->request_method == "post")
{
	if($groupleader['canmanagerequests'] == "no")
	{
		error_no_permission();
	}

	$plugins->run_hooks("managegroup_do_joinrequests_start");

	if(is_array($mybb->input['request']))
	{
		foreach($mybb->input['request'] as $uid => $what)
		{
			if($what == "accept")
			{
				join_usergroup($uid, $gid);
				$uidin[] = intval($uid);
			}
			elseif($what == "decline")
			{
				$uidin[] = intval($uid);
			}
		}
	}
	if(is_array($uidin))
	{
		$uids = implode(",", $uidin);
		$db->delete_query(TABLE_PREFIX."joinrequests", "uid IN ({$uids}) AND gid='$gid'");
	}

	$plugins->run_hooks("managegroup_do_joinrequests_end");

	redirect("usercp.php?action=usergroups", $lang->join_requests_moderated);
}
elseif($mybb->input['action'] == "joinrequests")
{
	$users = "";
	$plugins->run_hooks("managegroup_joinrequests_start");

	$query = $db->query("
		SELECT j.*, u.uid, u.username, u.postnum, u.regdate
		FROM ".TABLE_PREFIX."joinrequests j
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=j.uid)
		WHERE j.gid='".$mybb->input['gid']."'
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$user['reason'] = htmlspecialchars_uni($user['reason']);
		$altbg = alt_trow();
		$regdate = my_date($mybb->settings['dateformat'], $user['regdate']);
		eval("\$users .= \"".$templates->get("managegroup_joinrequests_request")."\";");
	}
	if(!$users)
	{
		error($lang->no_requests);
	}
	$lang->join_requests = sprintf($lang->join_requests_title,htmlspecialchars_uni($usergroup['title']));

	$plugins->run_hooks("managegroup_joinrequests_end");

	eval("\$joinrequests = \"".$templates->get("managegroup_joinrequests")."\";");
	output_page($joinrequests);
}
elseif($mybb->input['action'] == "do_manageusers" && $mybb->request_method == "post")
{
	if($groupleader['canmanagemembers'] == "no")
	{
		error_no_permission();
	}

	$plugins->run_hooks("managegroup_do_manageusers_start");

	if(is_array($mybb->input['removeuser']))
	{
		foreach($mybb->input['removeuser'] as $uid)
		{
			leave_usergroup($uid, $mybb->input['gid']);
		}
	}

	$plugins->run_hooks("managegroup_do_manageusers_end");

	redirect("usercp.php?action=usergroups", $lang->users_removed);
}
else
{
	$plugins->run_hooks("managegroup_start");

	$lang->members_of = sprintf($lang->members_of, $usergroup['title']);
	$lang->add_member = sprintf($lang->add_member, $usergroup['title']);
	if($usergroup['type'] == 4)
	{
		$query = $db->simple_select(TABLE_PREFIX."joinrequests", "COUNT(*) AS req", "gid='".$mybb->input['gid']."'");
		$numrequests = $db->fetch_array($query);
		if($numrequests['req'])
		{
			$lang->num_requests_pending = sprintf($lang->num_requests_pending, $numrequests['req']);
			eval("\$joinrequests = \"".$templates->get("managegroup_requestnote")."\";");
		}
		$usergrouptype = $lang->group_public_moderated;
	}
	elseif($usergroup['type'] == 3)
	{
		$usergrouptype = $lang->group_public_not_moderated;
	}
	elseif($usergroup['type'] == 2)
	{
		$usergrouptype = $lang->group_private;
	}
	else
	{
		$usergrouptype = $lang->group_default;
	}
		

	$query = $db->query("
		SELECT *
		FROM ".TABLE_PREFIX."users
		WHERE CONCAT(',',additionalgroups,',') LIKE '%,".$mybb->input['gid'].",%' OR usergroup='".$mybb->input['gid']."'
		ORDER BY username ASC
	");
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
	$users = "";
	while($user = $db->fetch_array($query))
	{
		$altbg = alt_trow();
		$regdate = my_date($mybb->settings['dateformat'], $user['regdate']);
		$post = $user;
		if($mybb->settings['enablepms'] == "yes" && $post['receivepms'] != "no" && $mybb->usergroup['cansendpms'] == "yes" && strpos(",".$post['ignorelist'].",", ",".$mybb->user['uid'].",") === false)
		{
			eval("\$sendpm = \"".$templates->get("postbit_pm")."\";");
		}
		if($user['hideemail'] != "yes")
		{
			eval("\$email = \"".$templates->get("postbit_email")."\";");
		}
		else
		{
			$email = '';
		}
		$query1 = $db->simple_select(TABLE_PREFIX."groupleaders", "uid", "uid='{$user['uid']}' AND gid='{$gid}'");
		$isleader = $db->fetch_array($query1);
		$user['username'] = format_name($user['username'], $user['usergroup']);
		if($isleader['uid'])
		{
			$leader = $lang->leader;
		}
		else
		{
			$leader = '';
		}

		// Checkbox for user management - only if current user is allowed
		$checkbox = '';
		if($groupleader['canmanagemembers'] == "yes")
		{
			eval("\$checkbox = \"".$templates->get("managegroup_user_checkbox")."\";");
		}

		eval("\$users .= \"".$templates->get("managegroup_user")."\";");
	}

	$add_user = '';
	$remove_users = '';
	if($groupleader['canmanagemembers'] == "yes")
	{
		eval("\$add_user = \"".$templates->get("managegroup_adduser")."\";");
		eval("\$remove_users = \"".$templates->get("managegroup_removeusers")."\";");
	}

	$plugins->run_hooks("managegroup_end");

	eval("\$manageusers = \"".$templates->get("managegroup")."\";");
	output_page($manageusers);
}
?>