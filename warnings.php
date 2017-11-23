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
define('THIS_SCRIPT', 'warnings.php');

$templatelist = "warnings,warnings_warn_post,warnings_active_header,warnings_expired_header,warnings_warning,warnings_warn_existing,warnings_warn_type,warnings_warn_custom,warnings_warn_pm,warnings_view";
$templatelist .= ",warnings_view_post,warnings_view_user,warnings_view_revoke,warnings_view_revoked,warnings_warn_type_result,warnings_postlink,codebuttons,warnings_warn,warnings_warn_pm_anonymous";
$templatelist .= ",multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,warnings_no_warnings";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_warnings.php";
require_once MYBB_ROOT."inc/functions_modcp.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

$lang->load("warnings");
$lang->load("datahandler_warnings");

if($mybb->settings['enablewarningsystem'] == 0)
{
	error($lang->error_warning_system_disabled);
}

// Expire old warnings
require_once MYBB_ROOT.'inc/datahandlers/warnings.php';
$warningshandler = new WarningsHandler('update');

$warningshandler->expire_warnings();

$mybb->input['action'] = $mybb->get_input('action');

$plugins->run_hooks("warnings_start");

// Actually warn a user
if($mybb->input['action'] == "do_warn" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canwarnusers'] != 1)
	{
		error_no_permission();
	}

	$user = get_user($mybb->get_input('uid', MyBB::INPUT_INT));

	if(!$user['uid'])
	{
		error($lang->error_invalid_user);
	}

	$group_permissions = user_permissions($user['uid']);

	if($group_permissions['canreceivewarnings'] != 1)
	{
		error($lang->error_cant_warn_group);
	}

	if(!modcp_can_manage_user($user['uid']))
	{
		error($lang->error_cant_warn_user);
	}

	$plugins->run_hooks("warnings_do_warn_start");

	$warning = array(
		'uid' => $mybb->get_input('uid', MyBB::INPUT_INT),
		'notes' => $mybb->get_input('notes'),
		'type' => $mybb->get_input('type'),
		'custom_reason' => $mybb->get_input('custom_reason'),
		'custom_points' => $mybb->get_input('custom_points', MyBB::INPUT_INT),
		'expires' => $mybb->get_input('expires', MyBB::INPUT_INT),
		'expires_period' => $mybb->get_input('expires_period')
	);

	// Is this warning being given for a post?
	if($mybb->get_input('pid', MyBB::INPUT_INT))
	{
		$warning['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);

		$post = get_post($warning['pid']);

		$forum_permissions = forum_permissions($post['fid']);

		if($forum_permissions['canview'] != 1)
		{
			error_no_permission();
		}
	}

	$warningshandler->set_data($warning);

	if($warningshandler->validate_warning())
	{
		$warninginfo = $warningshandler->insert_warning();

		// Are we notifying the user?
		if($mybb->get_input('send_pm', MyBB::INPUT_INT) == 1 && $group_permissions['canusepms'] != 0 && $mybb->settings['enablepms'] != 0)
		{

			$pm = array(
				'subject' => $mybb->get_input('pm_subject'),
				'message' => $mybb->get_input('pm_message'),
				'touid' => $user['uid']
			);

			$sender_uid = $mybb->user['uid'];
			if($mybb->settings['allowanonwarningpms'] == 1 && $mybb->get_input('pm_anonymous', MyBB::INPUT_INT))
			{
				$sender_uid = -1;
			}

			// Some kind of friendly error notification
			if(!send_pm($pm, $sender_uid, true))
			{
				$warningshandler->friendly_action .= $lang->redirect_warned_pmerror;
			}
		}

		$plugins->run_hooks("warnings_do_warn_end");

		$lang->redirect_warned = $lang->sprintf($lang->redirect_warned, htmlspecialchars_uni($user['username']), $warningshandler->new_warning_level, $warningshandler->friendly_action);

		if(!empty($post['pid']))
		{
			redirect(get_post_link($post['pid']), $lang->redirect_warned);
		}
		else
		{
			redirect(get_profile_link($user['uid']), $lang->redirect_warned);
		}
	}
	else
	{
		$warn_errors = $warningshandler->get_friendly_errors();
		$warn_errors = inline_error($warn_errors);
		$mybb->input['action'] = 'warn';
	}
}

// Warn a user
if($mybb->input['action'] == "warn")
{
	if($mybb->usergroup['canwarnusers'] != 1)
	{
		error_no_permission();
	}

	// Check we haven't exceeded the maximum number of warnings per day
	if($mybb->usergroup['maxwarningsday'] != 0)
	{
		$timecut = TIME_NOW-60*60*24;
		$query = $db->simple_select("warnings", "COUNT(wid) AS given_today", "issuedby='{$mybb->user['uid']}' AND dateline>'$timecut'");
		$given_today = $db->fetch_field($query, "given_today");
		if($given_today >= $mybb->usergroup['maxwarningsday'])
		{
			error($lang->sprintf($lang->warnings_reached_max_warnings_day, $mybb->usergroup['maxwarningsday']));
		}
	}

	$user = get_user($mybb->get_input('uid', MyBB::INPUT_INT));
	if(!$user)
	{
		error($lang->error_invalid_user);
	}

	if($user['uid'] == $mybb->user['uid'])
	{
		error($lang->warnings_error_cannot_warn_self);
	}

	if($user['warningpoints'] >= $mybb->settings['maxwarningpoints'])
	{
		error($lang->warnings_error_user_reached_max_warning);
	}

	$group_permissions = user_permissions($user['uid']);

	if($group_permissions['canreceivewarnings'] != 1)
	{
		error($lang->error_cant_warn_group);
	}

	if(!modcp_can_manage_user($user['uid']))
	{
		error($lang->error_cant_warn_user);
	}

	$post = $existing_warnings = '';

	// Giving a warning for a specific post
	if($mybb->get_input('pid', MyBB::INPUT_INT))
	{
		$post = get_post($mybb->get_input('pid', MyBB::INPUT_INT));

		if($post)
		{
			$thread = get_thread($post['tid']);
		}

		if(!$post || !$thread)
		{
			error($lang->warnings_error_invalid_post);
		}

		$forum_permissions = forum_permissions($thread['fid']);
		if($forum_permissions['canview'] != 1)
		{
			error_no_permission();
		}

		$post['subject'] = $parser->parse_badwords($post['subject']);
		$post['subject'] = htmlspecialchars_uni($post['subject']);
		$post_link = get_post_link($post['pid']);
		eval("\$post = \"".$templates->get("warnings_warn_post")."\";");

		// Fetch any existing warnings issued for this post
		$query = $db->query("
			SELECT w.*, t.title AS type_title, u.username
			FROM ".TABLE_PREFIX."warnings w
			LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (t.tid=w.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.issuedby)
			WHERE w.pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'
			ORDER BY w.expired ASC, w.dateline DESC
		");
		$first = true;
		$warnings = '';
		while($warning = $db->fetch_array($query))
		{
			if($warning['expired'] != $last_expired || $first)
			{
				if($warning['expired'] == 0)
				{
					eval("\$warnings .= \"".$templates->get("warnings_active_header")."\";");
				}
				else
				{
					eval("\$warnings .= \"".$templates->get("warnings_expired_header")."\";");
				}
			}
			$last_expired = $warning['expired'];
			$first = false;

			$post_link = "";
			$warning['username'] = htmlspecialchars_uni($warning['username']);
			$issuedby = build_profile_link($warning['username'], $warning['issuedby']);
			$date_issued = my_date('relative', $warning['dateline']);
			if($warning['type_title'])
			{
				$warning_type = $warning['type_title'];
			}
			else
			{
				$warning_type = $warning['title'];
			}
			$warning_type = htmlspecialchars_uni($warning_type);
			if($warning['points'] > 0)
			{
				$warning['points'] = "+{$warning['points']}";
			}
			$points = $lang->sprintf($lang->warning_points, $warning['points']);
			if($warning['expired'] != 1)
			{
				if($warning['expires'] == 0)
				{
					$expires = $lang->never;
				}
				else
				{
					$expires = nice_time($warning['expires']-TIME_NOW);
				}
			}
			else
			{
				if($warning['daterevoked'])
				{
					$expires = $lang->warning_revoked;
				}
				else if($warning['expires'])
				{
					$expires = $lang->already_expired;
				}
			}
			$alt_bg = alt_trow();
			$plugins->run_hooks("warnings_warning");
			eval("\$warnings .= \"".$templates->get("warnings_warning")."\";");
		}
		if($warnings)
		{
			eval("\$existing_warnings = \"".$templates->get("warnings_warn_existing")."\";");
		}
	}

	$plugins->run_hooks("warnings_warn_start");

	$type_checked = array('custom' => '');
	$expires_period = array('hours' => '', 'days' => '', 'weeks' => '', 'months' => '', 'never' => '');
	$send_pm_checked = '';

	// Coming here from failed do_warn?
	$user['username'] = htmlspecialchars_uni($user['username']);
	if(!empty($warn_errors))
	{
		$notes = htmlspecialchars_uni($mybb->get_input('notes'));
		if($mybb->get_input('type', MyBB::INPUT_INT))
		{
			$type_checked[$mybb->get_input('type', MyBB::INPUT_INT)] = "checked=\"checked\"";
		}
		$pm_subject = htmlspecialchars_uni($mybb->get_input('pm_subject'));
		$message = htmlspecialchars_uni($mybb->get_input('pm_message'));
		if(!empty($mybb->input['send_pm']))
		{
			$send_pm_checked = "checked=\"checked\"";
		}
		$custom_reason = htmlspecialchars_uni($mybb->get_input('custom_reason'));
		$custom_points = $mybb->get_input('custom_points', MyBB::INPUT_INT);
		$expires = $mybb->get_input('expires', MyBB::INPUT_INT);
		if($mybb->get_input('expires_period', MyBB::INPUT_INT))
		{
			$expires_period[$mybb->get_input('expires_period', MyBB::INPUT_INT)] = "selected=\"selected\"";
		}
	}
	else
	{
		$notes = $custom_reason = $custom_points = $expires = '';
		$expires = 1;
		$custom_points = 2;
		$pm_subject = $lang->warning_pm_subject;
		$message = $lang->sprintf($lang->warning_pm_message, $user['username'], $mybb->settings['bbname']);
		$warn_errors = '';
	}

	$lang->nav_profile = $lang->sprintf($lang->nav_profile, $user['username']);
	add_breadcrumb($lang->nav_profile, get_profile_link($user['uid']));
	add_breadcrumb($lang->nav_add_warning);

	$user_link = build_profile_link($user['username'], $user['uid']);

	if($mybb->settings['maxwarningpoints'] < 1)
	{
		$mybb->settings['maxwarningpoints'] = 10;
	}

	if(!is_array($groupscache))
	{
		$groupscache = $cache->read("usergroups");
	}

	$current_level = round($user['warningpoints']/$mybb->settings['maxwarningpoints']*100);

	// Fetch warning levels
	$levels = array();
	$query = $db->simple_select("warninglevels", "*");
	while($level = $db->fetch_array($query))
	{
		$level['action'] = my_unserialize($level['action']);
		switch($level['action']['type'])
		{
			case 1:
				if($level['action']['length'] > 0)
				{
					$ban_length = fetch_friendly_expiration($level['action']['length']);
					$lang_str = "expiration_".$ban_length['period'];
					$period = $lang->sprintf($lang->result_period, $ban_length['time'], $lang->$lang_str);
				}
				else
				{
					$period = $lang->result_period_perm;
				}
				$group_name = $groupscache[$level['action']['usergroup']]['title'];
				$level['friendly_action'] = $lang->sprintf($lang->result_banned, $group_name, $period);
				break;
			case 2:
				if($level['action']['length'] > 0)
				{
					$period = fetch_friendly_expiration($level['action']['length']);
					$lang_str = "expiration_".$period['period'];
					$period = $lang->sprintf($lang->result_period, $period['time'], $lang->$lang_str);
				}
				else
				{
					$period = $lang->result_period_perm;
				}
				$level['friendly_action'] = $lang->sprintf($lang->result_suspended, $period);
				break;
			case 3:
				if($level['action']['length'] > 0)
				{
					$period = fetch_friendly_expiration($level['action']['length']);
					$lang_str = "expiration_".$period['period'];
					$period = $lang->sprintf($lang->result_period, $period['time'], $lang->$lang_str);
				}
				else
				{
					$period = $lang->result_period_perm;
				}
				$level['friendly_action'] = $lang->sprintf($lang->result_moderated, $period);
				break;
		}
		$levels[$level['percentage']] = $level;
	}
	krsort($levels);

	$types = '';

	// Fetch all current warning types
	$query = $db->simple_select("warningtypes", "*", "", array("order_by" => "title"));
	while($type = $db->fetch_array($query))
	{
		if(!isset($type_checked[$type['tid']]))
		{
			$type_checked[$type['tid']] = '';
		}
		$checked = $type_checked[$type['tid']];
		$type['title'] = htmlspecialchars_uni($type['title']);
		$new_warning_level = round(($user['warningpoints']+$type['points'])/$mybb->settings['maxwarningpoints']*100);
		if($new_warning_level > 100)
		{
			$new_warning_level = 100;
		}
		if($type['points'] > 0)
		{
			$type['points'] = "+{$type['points']}";
		}
		$points = $lang->sprintf($lang->warning_points, $type['points']);

		if(is_array($levels))
		{
			foreach($levels as $level)
			{
				if($new_warning_level >= $level['percentage'])
				{
					$new_level = $level;
					break;
				}
			}
		}
		$level_diff = $new_warning_level-$current_level;
		$result = '';
		if(!empty($new_level['friendly_action']))
		{
			eval("\$result = \"".$templates->get("warnings_warn_type_result")."\";");
		}
		eval("\$types .= \"".$templates->get("warnings_warn_type")."\";");
		unset($new_level);
		unset($result);
	}

	$custom_warning = '';

	if($mybb->settings['allowcustomwarnings'] != 0)
	{
		if(empty($types) && empty($warn_errors) || $mybb->get_input('type') == 'custom')
		{
			$type_checked['custom'] = "checked=\"checked\"";
		}

		eval("\$custom_warning = \"".$templates->get("warnings_warn_custom")."\";");
	}

	$pm_notify = '';

	if($group_permissions['canusepms']  != 0 && $mybb->user['receivepms'] != 0 && $mybb->settings['enablepms'] != 0)
	{
		$smilieinserter = $codebuttons = "";

		if($mybb->settings['bbcodeinserter'] != 0 && $mybb->settings['pmsallowmycode'] != 0 && $mybb->user['showcodebuttons'] != 0)
		{
			$codebuttons = build_mycode_inserter("message", $mybb->settings['pmsallowsmilies']);
			if($mybb->settings['pmsallowsmilies'] != 0)
			{
				$smilieinserter = build_clickable_smilies();
			}
		}

		$anonymous_pm = '';
		if($mybb->settings['allowanonwarningpms'] == 1)
		{
			$checked = '';
			if($mybb->get_input('pm_anonymous', MyBB::INPUT_INT))
			{
				$checked = ' checked="checked"';
			}

			eval('$anonymous_pm = "'.$templates->get('warnings_warn_pm_anonymous').'";');
		}

		eval("\$pm_notify = \"".$templates->get("warnings_warn_pm")."\";");
	}

	$plugins->run_hooks("warnings_warn_end");

	eval("\$warn = \"".$templates->get("warnings_warn")."\";");
	output_page($warn);
	exit;
}

// Revoke a warning
if($mybb->input['action'] == "do_revoke" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canwarnusers'] != 1)
	{
		error_no_permission();
	}

	$warning = $warningshandler->get($mybb->input['wid']);

	if(!$warning)
	{
		error($lang->error_invalid_warning);
	}
	else if($warning['daterevoked'])
	{
		error($lang->warning_already_revoked);
	}

	$user = get_user($warning['uid']);

	$group_permissions = user_permissions($user['uid']);
	if($group_permissions['canreceivewarnings'] != 1)
	{
		error($lang->error_cant_warn_group);
	}

	$plugins->run_hooks("warnings_do_revoke_start");

	if(!trim($mybb->get_input('reason')))
	{
		$warn_errors[] = $lang->no_revoke_reason;
		$warn_errors = inline_error($warn_errors);
		$mybb->input['action'] = "view";
	}
	else
	{
		$warning_data = array(
			'wid' => $warning['wid'],
			'reason' => $mybb->get_input('reason'),
			'expired' => $warning['expired'],
			'uid' => $warning['uid'],
			'points' => $warning['points']
		);

		$warningshandler->set_data($warning_data);

		$warningshandler->update_warning();

		redirect("warnings.php?action=view&wid={$warning['wid']}", $lang->redirect_warning_revoked);
	}
}

// Detailed view of a warning
if($mybb->input['action'] == "view")
{
	if($mybb->usergroup['canwarnusers'] != 1)
	{
		error_no_permission();
	}

	$query = $db->query("
		SELECT w.*, t.title AS type_title, u.username, p.subject AS post_subject
		FROM ".TABLE_PREFIX."warnings w
		LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (t.tid=w.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.issuedby)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=w.pid)
		WHERE w.wid='".$mybb->get_input('wid', MyBB::INPUT_INT)."'
	");
	$warning = $db->fetch_array($query);

	if(!$warning)
	{
		error($lang->error_invalid_warning);
	}

	$user = get_user((int)$warning['uid']);
	if(!$user)
	{
		$user['username'] = $lang->guest;
	}
	$user['username'] = htmlspecialchars_uni($user['username']);

	$group_permissions = user_permissions($user['uid']);
	if($group_permissions['canreceivewarnings'] != 1)
	{
		error($lang->error_cant_warn_group);
	}

	$plugins->run_hooks("warnings_view_start");

	$lang->nav_profile = $lang->sprintf($lang->nav_profile, $user['username']);
	if($user['uid'])
	{
		add_breadcrumb($lang->nav_profile, get_profile_link($user['uid']));
		add_breadcrumb($lang->nav_warning_log, "warnings.php?uid={$user['uid']}");
	}
	else
	{
		add_breadcrumb($lang->nav_profile);
		add_breadcrumb($lang->nav_warning_log);
	}
	add_breadcrumb($lang->nav_view_warning);

	$user_link = build_profile_link($user['username'], $user['uid']);

	$post_link = "";
	if($warning['post_subject'])
	{
		$warning['post_subject'] = $parser->parse_badwords($warning['post_subject']);
		$warning['post_subject'] = htmlspecialchars_uni($warning['post_subject']);
		$post_link = get_post_link($warning['pid'])."#pid{$warning['pid']}";
		eval("\$warning_info = \"".$templates->get("warnings_view_post")."\";");
	}
	else
	{
		eval("\$warning_info = \"".$templates->get("warnings_view_user")."\";");
	}

	$warning['username'] = htmlspecialchars_uni($warning['username']);
	$issuedby = build_profile_link($warning['username'], $warning['issuedby']);
	$notes = nl2br(htmlspecialchars_uni($warning['notes']));

	$date_issued = my_date('relative', $warning['dateline']);
	if($warning['type_title'])
	{
		$warning_type = $warning['type_title'];
	}
	else
	{
		$warning_type = $warning['title'];
	}
	$warning_type = htmlspecialchars_uni($warning_type);
	if($warning['points'] > 0)
	{
		$warning['points'] = "+{$warning['points']}";
	}

	$revoked_date = '';

	$points = $lang->sprintf($lang->warning_points, $warning['points']);
	if($warning['expired'] != 1)
	{
		if($warning['expires'] == 0)
		{
			$expires = $lang->never;
		}
		else
		{
			$expires = my_date('normal', $warning['expires']); // Purposely not using nice_time here as the moderator has clicked for more details so the actual day/time should be shown
		}
		$status = $lang->warning_active;
	}
	else
	{
		if($warning['daterevoked'])
		{
			$expires = $status = $lang->warning_revoked;
		}
		else if($warning['expires'])
		{
			$revoked_date = '('.my_date('normal', $warning['expires']).')';
			$expires = $status = $lang->already_expired;
		}
	}

	if(!$warning['daterevoked'])
	{
		if(!isset($warn_errors))
		{
			$warn_errors = '';
		}
		eval("\$revoke = \"".$templates->get("warnings_view_revoke")."\";");
	}
	else
	{
		$date_revoked = my_date('relative', $warning['daterevoked']);
		$revoked_user = get_user($warning['revokedby']);
		if(!$revoked_user['username'])
		{
			$revoked_user['username'] = $lang->guest;
		}
		$revoked_user['username'] = htmlspecialchars_uni($revoked_user['username']);
		$revoked_by = build_profile_link($revoked_user['username'], $revoked_user['uid']);
		$revoke_reason = nl2br(htmlspecialchars_uni($warning['revokereason']));
		eval("\$revoke = \"".$templates->get("warnings_view_revoked")."\";");
	}

	$plugins->run_hooks("warnings_view_end");

	eval("\$warning = \"".$templates->get("warnings_view")."\";");
	output_page($warning);
}

// Showing list of warnings for a particular user
if(!$mybb->input['action'])
{
	if($mybb->usergroup['canwarnusers'] != 1)
	{
		error_no_permission();
	}

	$user = get_user($mybb->get_input('uid', MyBB::INPUT_INT));
	if(!$user['uid'])
	{
		error($lang->error_invalid_user);
	}

	$group_permissions = user_permissions($user['uid']);
	if($group_permissions['canreceivewarnings'] != 1)
	{
		error($lang->error_cant_warn_group);
	}

	$user['username'] = htmlspecialchars_uni($user['username']);
	$lang->nav_profile = $lang->sprintf($lang->nav_profile, $user['username']);
	add_breadcrumb($lang->nav_profile, get_profile_link($user['uid']));
	add_breadcrumb($lang->nav_warning_log);

	if(!$mybb->settings['postsperpage'] || (int)$mybb->settings['postsperpage'] < 1)
	{
		$mybb->settings['postsperpage'] = 20;
	}

	// Figure out if we need to display multiple pages.
	$perpage = $mybb->settings['postsperpage'];
	$page = $mybb->get_input('page', MyBB::INPUT_INT);

	$query = $db->simple_select("warnings", "COUNT(wid) AS warning_count", "uid='{$user['uid']}'");
	$warning_count = $db->fetch_field($query, "warning_count");

	$pages = ceil($warning_count/$perpage);

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}
	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$multipage = multipage($warning_count, $perpage, $page, "warnings.php?uid={$user['uid']}");

	if($mybb->settings['maxwarningpoints'] < 1)
	{
		$mybb->settings['maxwarningpoints'] = 10;
	}

	$warning_level = round($user['warningpoints']/$mybb->settings['maxwarningpoints']*100);
	if($warning_level > 100)
	{
		$warning_level = 100;
	}

	if($user['warningpoints'] > $mybb->settings['maxwarningpoints'])
	{
		$user['warningpoints'] = $mybb->settings['maxwarningpoints'];
	}

	if($warning_level > 0)
	{
		$lang->current_warning_level = $lang->sprintf($lang->current_warning_level, $warning_level, $user['warningpoints'], $mybb->settings['maxwarningpoints']);
	}
	else
	{
		$lang->current_warning_level = "";
	}

	// Fetch the actual warnings
	$query = $db->query("
		SELECT w.*, t.title AS type_title, u.username, p.subject AS post_subject
		FROM ".TABLE_PREFIX."warnings w
		LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (t.tid=w.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.issuedby)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=w.pid)
		WHERE w.uid='{$user['uid']}'
		ORDER BY w.expired ASC, w.dateline DESC
		LIMIT {$start}, {$perpage}
	");
	$warnings = '';
	while($warning = $db->fetch_array($query))
	{
		if(!isset($last_expired) || $warning['expired'] != $last_expired)
		{
			if($warning['expired'] == 0)
			{
				eval("\$warnings .= \"".$templates->get("warnings_active_header")."\";");
			}
			else
			{
				eval("\$warnings .= \"".$templates->get("warnings_expired_header")."\";");
			}
		}
		$last_expired = $warning['expired'];

		$post_link = '';
		if($warning['post_subject'])
		{
			$warning['post_subject'] = $parser->parse_badwords($warning['post_subject']);
			$warning['post_subject'] = htmlspecialchars_uni($warning['post_subject']);
			$warning['post_link'] = get_post_link($warning['pid']);
			eval("\$post_link = \"".$templates->get("warnings_postlink")."\";");
		}

		$warning['username'] = htmlspecialchars_uni($warning['username']);
		$issuedby = build_profile_link($warning['username'], $warning['issuedby']);
		$date_issued = my_date('relative', $warning['dateline']);
		if($warning['type_title'])
		{
			$warning_type = $warning['type_title'];
		}
		else
		{
			$warning_type = $warning['title'];
		}
		$warning_type = htmlspecialchars_uni($warning_type);
		if($warning['points'] > 0)
		{
			$warning['points'] = "+{$warning['points']}";
		}
		$points = $lang->sprintf($lang->warning_points, $warning['points']);
		if($warning['expired'] != 1)
		{
			if($warning['expires'] == 0)
			{
				$expires = $lang->never;
			}
			else
			{
				$expires = nice_time($warning['expires']-TIME_NOW);
			}
		}
		else
		{
			if($warning['daterevoked'])
			{
				$expires = $lang->warning_revoked;
			}
			else if($warning['expires'])
			{
				$expires = $lang->already_expired;
			}
		}
		$alt_bg = alt_trow();
		$plugins->run_hooks("warnings_warning");
		eval("\$warnings .= \"".$templates->get("warnings_warning")."\";");
	}

	if(!$warnings)
	{
		eval("\$warnings = \"".$templates->get("warnings_no_warnings")."\";");
	}

	$plugins->run_hooks("warnings_end");

	eval("\$warnings = \"".$templates->get("warnings")."\";");
	output_page($warnings);
}

