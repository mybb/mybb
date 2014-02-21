<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'managegroup.php');

$templatelist = "managegroup_leaders_bit,managegroup_leaders,postbit_pm,postbit_email,managegroup_user_checkbox,managegroup_user,managegroup_adduser,managegroup_removeusers,managegroup,managegroup_joinrequests_request,managegroup_joinrequests";
$templatelist .= ",managegroup_requestnote,managegroup_no_users,multipage_prevpage,multipage_start,multipage_page_current,multipage_page,multipage_end,multipage_nextpage,multipage";

require_once "./global.php";

// Load language files
$lang->load("managegroup");

$gid = $mybb->get_input('gid', 1);
if(!isset($groupscache[$gid]))
{
	error($lang->invalid_group);
}
$usergroup = $groupscache[$gid];
$lang->nav_group_management = $lang->sprintf($lang->nav_group_management, $usergroup['title']);
add_breadcrumb($lang->nav_group_memberships, "usercp.php?action=usergroups");
add_breadcrumb($lang->nav_group_management, "managegroup.php?gid=$gid");

$mybb->input['action'] = $mybb->get_input('action');

if($mybb->input['action'] == "joinrequests")
{
	add_breadcrumb($lang->nav_join_requests);
}

// Check that this user is actually a leader of this group
$query = $db->simple_select("groupleaders", "*", "uid='{$mybb->user['uid']}' AND gid='{$gid}'");
$groupleader = $db->fetch_array($query);
if(!$groupleader['uid'] && $mybb->user['cancp'] != 1)
{
	error($lang->not_leader_of_this_group);
}

if($mybb->input['action'] == "do_add" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($groupleader['canmanagemembers'] == 0)
	{
		error_no_permission();
	}
	$query = $db->simple_select("users", "uid, additionalgroups, usergroup", "username = '".$db->escape_string($mybb->get_input('username'))."'", array("limit" => 1));
	$user = $db->fetch_array($query);
	if($user['uid'])
	{
		$additionalgroups = explode(',', $user['additionalgroups']);
		if($user['usergroup'] != $gid && !in_array($gid, $additionalgroups))
		{
			join_usergroup($user['uid'], $gid);
			$db->delete_query("joinrequests", "uid='{$user['uid']}' AND gid='{$gid}'");
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
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($groupleader['canmanagerequests'] == 0)
	{
		error_no_permission();
	}

	$plugins->run_hooks("managegroup_do_joinrequests_start");

	$uidin = null;
	if(is_array($mybb->get_input('request', 2)))
	{
		$uidin = array();
		foreach($mybb->get_input('request', 2) as $uid => $what)
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
	if(is_array($uidin) && !empty($uidin))
	{
		$uids = implode(",", $uidin);
		$db->delete_query("joinrequests", "uid IN ({$uids}) AND gid='{$gid}'");
	}

	$plugins->run_hooks("managegroup_do_joinrequests_end");

	redirect("managegroup.php?gid={$gid}", $lang->join_requests_moderated);
}
elseif($mybb->input['action'] == "joinrequests")
{
	$users = "";
	$plugins->run_hooks("managegroup_joinrequests_start");

	$query = $db->query("
		SELECT j.*, u.uid, u.username, u.postnum, u.regdate
		FROM ".TABLE_PREFIX."joinrequests j
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=j.uid)
		WHERE j.gid='{$gid}' AND j.uid != 0
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$user['reason'] = htmlspecialchars_uni($user['reason']);
		$altbg = alt_trow();
		$regdate = my_date($mybb->settings['dateformat'], $user['regdate']);
		$user['profilelink'] = build_profile_link($user['username'], $user['uid']);
		eval("\$users .= \"".$templates->get("managegroup_joinrequests_request")."\";");
	}
	if(!$users)
	{
		error($lang->no_requests);
	}
	$lang->join_requests = $lang->sprintf($lang->join_requests_title,htmlspecialchars_uni($usergroup['title']));

	$plugins->run_hooks("managegroup_joinrequests_end");

	eval("\$joinrequests = \"".$templates->get("managegroup_joinrequests")."\";");
	output_page($joinrequests);
}
elseif($mybb->input['action'] == "do_manageusers" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($groupleader['canmanagemembers'] == 0)
	{
		error_no_permission();
	}

	$plugins->run_hooks("managegroup_do_manageusers_start");

	if(is_array($mybb->get_input('removeuser', 2)))
	{
		foreach($mybb->get_input('removeuser', 2) as $uid)
		{
			leave_usergroup($uid, $gid);
		}
	}
	else
	{
		error($lang->no_users_selected);
	}

	$plugins->run_hooks("managegroup_do_manageusers_end");

	redirect("managegroup.php?gid={$gid}", $lang->users_removed);
}
else
{
	$plugins->run_hooks("managegroup_start");

	$lang->members_of = $lang->sprintf($lang->members_of, $usergroup['title']);
	$lang->add_member = $lang->sprintf($lang->add_member, $usergroup['title']);
	$joinrequests = '';
	if($usergroup['type'] == 4)
	{
		$query = $db->simple_select("joinrequests", "COUNT(*) AS req", "gid='{$gid}'");
		$numrequests = $db->fetch_array($query);
		if($numrequests['req'])
		{
			$lang->num_requests_pending = $lang->sprintf($lang->num_requests_pending, $numrequests['req']);
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

	$group_leaders = '';

	// Display group leaders (if there is any)
	$query = $db->query("
		SELECT g.*, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."groupleaders g
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=g.uid)
		WHERE g.gid = '{$gid}'
	");
	if($db->num_rows($query))
	{
		$loop = 1;
		$leaders = '';
		$leader_count = $db->num_rows($query);
		while($leader = $db->fetch_array($query))
		{
			$leader_name = format_name(htmlspecialchars_uni($leader['username']), $leader['usergroup'], $leader['displaygroup']);
			$leader_profile_link = build_profile_link($leader_name, $leader['uid']);

			// Get commas...
			if($loop != $leader_count)
			{
				$comma = $lang->comma;
			}
			else
			{
				$comma = '';
			}

			++$loop;
			eval("\$leaders .= \"".$templates->get("managegroup_leaders_bit")."\";");
		}

		eval("\$group_leaders = \"".$templates->get("managegroup_leaders")."\";");
	}

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$query = $db->simple_select("users", "*", "','||additionalgroups||',' LIKE '%,{$gid},%' OR usergroup='{$gid}'", array('order_by' => 'username'));
			break;
		default:
			$query = $db->simple_select("users", "*", "CONCAT(',',additionalgroups,',') LIKE '%,{$gid},%' OR usergroup='{$gid}'", array('order_by' => 'username'));
	}

	$numusers = $db->num_rows($query);
	/*if(!$numusers && !$numrequests)
	{
		error($lang->group_no_members);
	}*/
	$perpage = $mybb->settings['membersperpage'];
	$page = $mybb->get_input('page', 1);
	if($page && $page > 0)
	{
		$start = ($page-1) *$perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$multipage = multipage($numusers, $perpage, $page, "managegroup.php?gid=".$gid);
	$users = "";
	while($user = $db->fetch_array($query))
	{
		$altbg = alt_trow();
		$regdate = my_date('relative', $user['regdate']);
		$post = $user;
		$sendpm = $email = '';
		if($mybb->settings['enablepms'] == 1 && $post['receivepms'] != 0 && $mybb->usergroup['cansendpms'] == 1 && my_strpos(",".$post['ignorelist'].",", ",".$mybb->user['uid'].",") === false)
		{
			eval("\$sendpm = \"".$templates->get("postbit_pm")."\";");
		}

		if($user['hideemail'] != 1)
		{
			eval("\$email = \"".$templates->get("postbit_email")."\";");
		}
		else
		{
			$email = '';
		}
		$query1 = $db->simple_select("groupleaders", "uid", "uid='{$user['uid']}' AND gid='{$gid}'");
		$isleader = $db->fetch_array($query1);
		$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
		$user['profilelink'] = build_profile_link($user['username'], $user['uid']);
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
		if($groupleader['canmanagemembers'] == 1)
		{
			eval("\$checkbox = \"".$templates->get("managegroup_user_checkbox")."\";");
		}

		eval("\$users .= \"".$templates->get("managegroup_user")."\";");
	}

	if(!$users)
	{
		eval("\$users = \"".$templates->get("managegroup_no_users")."\";");
	}

	$add_user = '';
	$remove_users = '';
	if($groupleader['canmanagemembers'] == 1)
	{
		eval("\$add_user = \"".$templates->get("managegroup_adduser")."\";");
		eval("\$remove_users = \"".$templates->get("managegroup_removeusers")."\";");
	}

	$plugins->run_hooks("managegroup_end");

	eval("\$manageusers = \"".$templates->get("managegroup")."\";");
	output_page($manageusers);
}
?>