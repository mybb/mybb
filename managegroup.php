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
$templatelist .= ",managegroup_requestnote,managegroup_no_users,multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";

require_once "./global.php";

// Load language files
$lang->load("managegroup");

$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
if(!isset($groupscache[$gid]))
{
	error($lang->invalid_group);
}
$usergroup = $groupscache[$gid];
$lang->nav_group_management = $lang->sprintf($lang->nav_group_management, htmlspecialchars_uni($usergroup['title']));
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

if(!$groupleader['uid'] && $mybb->usergroup['cancp'] != 1)
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

	$plugins->run_hooks("managegroup_do_add_start");

	$options = array(
		'fields' => array('additionalgroups', 'usergroup')
	);

	$user = get_user_by_username($mybb->get_input('username'), $options);

	if($user['uid'])
	{
		$additionalgroups = explode(',', $user['additionalgroups']);
		if($user['usergroup'] != $gid && !in_array($gid, $additionalgroups))
		{
			join_usergroup($user['uid'], $gid);
			$db->delete_query("joinrequests", "uid='{$user['uid']}' AND gid='{$gid}'");
			$plugins->run_hooks("managegroup_do_add_end");
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

elseif($mybb->input['action'] == "do_invite" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($groupleader['caninvitemembers'] == 0)
	{
		error_no_permission();
	}

	$plugins->run_hooks("managegroup_do_invite_start");

	$options = array(
		'fields' => array('additionalgroups', 'usergroup', 'language')
	);

	$user = get_user_by_username($mybb->get_input('inviteusername'), $options);

	if($user['uid'])
	{
		$additionalgroups = explode(',', $user['additionalgroups']);
		if($user['usergroup'] != $gid && !in_array($gid, $additionalgroups))
		{
			$query = $db->simple_select("joinrequests", "rid", "uid = '".(int)$user['uid']."' AND gid = '".(int)$gid."'", array("limit" => 1));
			$pendinginvite = $db->fetch_array($query);
			if($pendinginvite['rid'])
			{
				error($lang->error_alreadyinvited);
			}
			else
			{
				$usergroups_cache = $cache->read('usergroups');
				$usergroup = $usergroups_cache[$gid];

				$joinrequest = array(
					"uid" => $user['uid'],
					"gid" => $usergroup['gid'],
					"dateline" => TIME_NOW,
					"invite" => 1
				);
				$db->insert_query("joinrequests", $joinrequest);

				$lang_var = 'invite_pm_message';
				if($mybb->settings['deleteinvites'] != 0)
				{
					$lang_var .= '_expires';
				}

				$pm = array(
					'subject' => array('invite_pm_subject', $usergroup['title']),
					'message' => array($lang_var, $usergroup['title'], $mybb->settings['bburl'], $mybb->settings['deleteinvites']),
					'touid' => $user['uid'],
					'language' => $user['language'],
					'language_file' => 'managegroup'
				);

				send_pm($pm, $mybb->user['uid'], true);

				$plugins->run_hooks("managegroup_do_invite_end");

				redirect("managegroup.php?gid=".$gid, $lang->user_invited);
			}
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
	if(is_array($mybb->get_input('request', MyBB::INPUT_ARRAY)))
	{
		$uidin = array();
		foreach($mybb->get_input('request', MyBB::INPUT_ARRAY) as $uid => $what)
		{
			if($what == "accept")
			{
				join_usergroup($uid, $gid);
				$uidin[] = (int)$uid;
			}
			elseif($what == "decline")
			{
				$uidin[] = (int)$uid;
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
	$users = [];
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
		$altbg = alt_trow();
		$regdate = my_date($mybb->settings['dateformat'], $user['regdate']);
		$user['profilelink'] = build_profile_link($user['username'], $user['uid']);

		$users[] = $user;
	}

	if(!$users)
	{
		error($lang->no_requests);
	}

	$plugins->run_hooks("managegroup_joinrequests_end");

	output_page(\MyBB\template('managegroup/joinrequests.twig', [
		'usergroup' => $usergroup,
		'users' => $users,
	]));
}

elseif($mybb->input['action'] == "do_manageusers" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($groupleader['canmanagemembers'] == 0)
	{
		error_no_permission();
	}

	$users = $mybb->get_input('removeuser', MyBB::INPUT_ARRAY);

	$plugins->run_hooks("managegroup_do_manageusers_start");

	if(!empty($users))
	{
		foreach($users as $uid)
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

	$usergroup['pendingrequests'] = false;

	if($usergroup['type'] == 5)
	{
		$usergroup['usergrouptype'] = $lang->group_public_invite;
	}
	else if($usergroup['type'] == 4)
	{
		$query = $db->simple_select("joinrequests", "COUNT(*) AS req", "gid='{$gid}'");
		$numrequests = $db->fetch_array($query);

		if($numrequests['req'])
		{
			$usergroup['pendingrequests'] = true;
			$usergroup['num_requests'] = $numrequests['req'];
		}
		$usergroup['usergrouptype'] = $lang->group_public_moderated;
	}
	else if($usergroup['type'] == 3)
	{
		$usergroup['usergrouptype'] = $lang->group_public_not_moderated;
	}
	else if($usergroup['type'] == 2)
	{
		$usergroup['usergrouptype'] = $lang->group_private;
	}
	else
	{
		$usergroup['usergrouptype'] = $lang->group_default;
	}

	$usergroup['leaders'] = false;

	// Display group leaders (if there is any)
	$query = $db->query("
        SELECT g.*, u.username, u.usergroup, u.displaygroup
        FROM ".TABLE_PREFIX."groupleaders g
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=g.uid)
        WHERE g.gid = '{$gid}'
    ");

	$leaders_array = [];

	if($db->num_rows($query))
	{
		$leaders = [];
		while($leader = $db->fetch_array($query))
		{
			$leader_name = format_name($leader['username'], $leader['usergroup'], $leader['displaygroup']);
			$leader['profilelink'] = build_profile_link($leader_name, $leader['uid']);

			$leaders_array[] = $leader['uid'];

			$leaders[] = $leader;
		}

		$usergroup['leaders'] = true;
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

	$perpage = (int)$mybb->settings['membersperpage'];
	if($perpage < 1)
	{
		$perpage = 20;
	}

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	if($page && $page > 0)
	{
		$start = ($page-1) * $perpage;
		$pages = ceil($numusers / $perpage);
		if($page > $pages)
		{
			$start = 0;
			$page = 1;
		}
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$multipage = multipage($numusers, $perpage, $page, "managegroup.php?gid=".$gid);

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$query = $db->simple_select("users", "*", "','||additionalgroups||',' LIKE '%,{$gid},%' OR usergroup='{$gid}'", array('order_by' => 'username', 'limit' => $perpage, 'limit_start' => $start));
			break;
		default:
			$query = $db->simple_select("users", "*", "CONCAT(',',additionalgroups,',') LIKE '%,{$gid},%' OR usergroup='{$gid}'", array('order_by' => 'username', 'limit' => $perpage, 'limit_start' => $start));
	}

	$users = [];
	while($user = $db->fetch_array($query))
	{
		$user['reg_date'] = my_date('relative', $user['regdate']);

		$user['showpm'] = false;
		$user['showemail'] = false;

		if($mybb->settings['enablepms'] == 1 && $user['receivepms'] != 0 && $mybb->usergroup['cansendpms'] == 1 && my_strpos(",".$user['ignorelist'].",", ",".$mybb->user['uid'].",") === false)
		{
			$user['showpm'] = true;
		}

		if($user['hideemail'] != 1 && (my_strpos(",".$user['ignorelist'].",", ",".$mybb->user['uid'].",") === false || $mybb->usergroup['cansendemailoverride'] != 0))
		{
			$user['showemail'] = true;
		}

		$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
		$user['profilelink'] = build_profile_link($user['username'], $user['uid']);

		if(in_array($user['uid'], $leaders_array))
		{
			$user['leader'] = $lang->leader;
		}
		else
		{
			$user['leader'] = '';
		}

		$user['disabled'] = '';
		if($user['usergroup'] == $gid)
		{
			$user['disabled'] = ' disabled="disabled"';
		}

		$users[] = $user;
	}

	$plugins->run_hooks("managegroup_end");

	output_page(\MyBB\template('managegroup/managegroup.twig', [
		'usergroup' => $usergroup,
		'groupleader' => $groupleader,
		'leaders' => $leaders,
		'multipage' => $multipage,
		'users' => $users,
	]));
}
