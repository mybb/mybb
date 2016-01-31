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
	$lang->join_requests = $lang->sprintf($lang->join_requests_title, htmlspecialchars_uni($usergroup['title']));

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

	if(is_array($mybb->get_input('removeuser', MyBB::INPUT_ARRAY)))
	{
		foreach($mybb->get_input('removeuser', MyBB::INPUT_ARRAY) as $uid)
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

	$lang->members_of = $lang->sprintf($lang->members_of, htmlspecialchars_uni($usergroup['title']));
	$lang->add_member = $lang->sprintf($lang->add_member, htmlspecialchars_uni($usergroup['title']));
	$lang->invite_member = $lang->sprintf($lang->invite_member, htmlspecialchars_uni($usergroup['title']));
	$joinrequests = '';
	if($usergroup['type'] == 5)
	{
		$usergrouptype = $lang->group_public_invite;
	}
	elseif($usergroup['type'] == 4)
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

	$leaders_array = array();

	if($db->num_rows($query))
	{
		$loop = 1;
		$leaders = '';
		$leader_count = $db->num_rows($query);
		while($leader = $db->fetch_array($query))
		{
			$leader_name = format_name(htmlspecialchars_uni($leader['username']), $leader['usergroup'], $leader['displaygroup']);
			$leader_profile_link = build_profile_link($leader_name, $leader['uid']);

			$leaders_array[] = $leader['uid'];

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

	$perpage = (int)$mybb->settings['membersperpage'];
	if($perpage < 1)
	{
		$perpage = 20;
	}

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
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

		$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
		$user['profilelink'] = build_profile_link($user['username'], $user['uid']);
		if(in_array($user['uid'], $leaders_array))
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

	if($usergroup['type'] == 5 && $groupleader['caninvitemembers'] == 1)
	{
		eval("\$invite_user = \"".$templates->get("managegroup_inviteuser")."\";");
	}

	$plugins->run_hooks("managegroup_end");

	eval("\$manageusers = \"".$templates->get("managegroup")."\";");
	output_page($manageusers);
}
