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
				// Workaround for eliminating PHP warnings in PHP 8. Ref: https://github.com/mybb/mybb/issues/4630#issuecomment-1369144163
				$pm['sender']['uid'] = -1;
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
		$timecut = TIME_NOW - 60 * 60 * 24;
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
		$post['link'] = get_post_link($post['pid']);

		$warnings = [];

		// Fetch any existing warnings issued for this post
		$query = $db->query("
            SELECT w.*, t.title AS type_title, u.username
            FROM ".TABLE_PREFIX."warnings w
            LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (t.tid=w.tid)
            LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.issuedby)
            WHERE w.pid = '".$mybb->get_input('pid', MyBB::INPUT_INT)."'
            ORDER BY w.expired ASC, w.dateline DESC
        ");
		$last_expired = -1;

		while($warning = $db->fetch_array($query))
		{
			if($warning['expired'] != $last_expired)
			{
				if($warning['expired'] == 0)
				{
					$warning['active'] = 1;
				}
				else
				{
					$warning['active'] = 2;
				}
			}
			$last_expired = $warning['expired'];

			$warning['username'] = htmlspecialchars_uni($warning['username']);
			$warning['issuedby'] = build_profile_link($warning['username'], $warning['issuedby']);

			$warning['date_issued'] = my_date('relative', $warning['dateline']);

			if($warning['type_title'])
			{
				$warning['warning_type'] = $warning['type_title'];
			}
			else
			{
				$warning['warning_type'] = $warning['title'];
			}

			if($warning['points'] > 0)
			{
				$warning['points'] = "+{$warning['points']}";
			}

			if($warning['expired'] != 1)
			{
				if($warning['expires'] == 0)
				{
					$warning['expires'] = $lang->never;
				}
				else
				{
					$warning['expires'] = nice_time($warning['expires'] - TIME_NOW);
				}
			}
			else
			{
				if($warning['daterevoked'])
				{
					$warning['expires'] = $lang->warning_revoked;
				}
				else if($warning['expires'])
				{
					$warning['expires'] = $lang->already_expired;
				}
			}

			$plugins->run_hooks("warnings_warning");

			$warnings[] = $warning;
		}
	}

	$plugins->run_hooks("warnings_warn_start");

	$type_checked = array('custom' => '');

	// Coming here from failed do_warn?
	$user['username'] = htmlspecialchars_uni($user['username']);
	if(!empty($warn_errors))
	{
		$type_checked['notes'] = htmlspecialchars_uni($mybb->get_input('notes'));
		if($mybb->get_input('type', MyBB::INPUT_INT))
		{
			$type_checked[$mybb->get_input('type', MyBB::INPUT_INT)] = "checked=\"checked\"";
		}
		$type_checked['pm_subject'] = htmlspecialchars_uni($mybb->get_input('pm_subject'));
		$type_checked['message'] = htmlspecialchars_uni($mybb->get_input('pm_message'));
		if(!empty($mybb->input['send_pm']))
		{
			$type_checked['send_pm_checked'] = "checked=\"checked\"";
		}
		$type_checked['custom_reason'] = htmlspecialchars_uni($mybb->get_input('custom_reason'));
		$type_checked['custom_points'] = $mybb->get_input('custom_points', MyBB::INPUT_INT);
		$type_checked['expires'] = $mybb->get_input('expires', MyBB::INPUT_INT);
		if($mybb->get_input('expires_period'))
		{
			$type_checked[$mybb->get_input('expires_period')] = "selected=\"selected\"";
		}
	}
	else
	{
		$type_checked['notes'] = $type_checked['custom_reason'] = $type_checked['custom_points'] = $type_checked['expires'] = '';
		$type_checked['expires'] = 1;
		$type_checked['custom_points'] = 2;
		$type_checked['pm_subject'] = $lang->warning_pm_subject;
		$type_checked['message'] = $lang->sprintf($lang->warning_pm_message, $user['username'], $mybb->settings['bbname']);
		$warn_errors = '';
	}

	$lang->nav_profile = $lang->sprintf($lang->nav_profile, $user['username']);
	add_breadcrumb($lang->nav_profile, get_profile_link($user['uid']));
	add_breadcrumb($lang->nav_add_warning);

	$user['user_link'] = build_profile_link($user['username'], $user['uid']);

	if($mybb->settings['maxwarningpoints'] < 1)
	{
		$mybb->settings['maxwarningpoints'] = 10;
	}

	if(!is_array($groupscache))
	{
		$groupscache = $cache->read("usergroups");
	}

	$user['currentlevel'] = round($user['warningpoints'] / $mybb->settings['maxwarningpoints'] * 100);

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

	$types = [];

	// Fetch all current warning types
	$query = $db->simple_select("warningtypes", "*", "", array("order_by" => "title"));
	while($type = $db->fetch_array($query))
	{
		if(!isset($type_checked[$type['tid']]))
		{
			$type_checked[$type['tid']] = '';
		}

		$type['new_warning_level'] = round(($user['warningpoints'] + $type['points']) / $mybb->settings['maxwarningpoints'] * 100);

		if($type['new_warning_level'] > 100)
		{
			$type['new_warning_level'] = 100;
		}

		if($type['points'] > 0)
		{
			$type['points'] = "+{$type['points']}";
		}

		if(is_array($levels))
		{
			foreach($levels as $level)
			{
				if($type['new_warning_level'] >= $level['percentage'])
				{
					$type['action'] = $level;
					break;
				}
			}
		}

		$type['level_diff'] = $type['new_warning_level'] - $user['currentlevel'];

		$types[] = $type;
	}

	if($mybb->settings['allowcustomwarnings'] != 0)
	{
		if(empty($types) && empty($warn_errors) || $mybb->get_input('type') == 'custom')
		{
			$type_checked['custom'] = "checked=\"checked\"";
		}
	}

	$pm['haspermission'] = false;
	if($group_permissions['canusepms'] != 0 && $mybb->user['receivepms'] != 0 && $mybb->settings['enablepms'] != 0)
	{
		$pm['haspermission'] = true;
		$smilieinserter = '';

		if($mybb->settings['bbcodeinserter'] != 0 && $mybb->settings['pmsallowmycode'] != 0 && $mybb->user['showcodebuttons'] != 0)
		{
			$pm['codebuttons'] = build_mycode_inserter("message", $mybb->settings['pmsallowsmilies']);
			if($mybb->settings['pmsallowsmilies'] != 0)
			{
				$smilieinserter = build_clickable_smilies();
			}
		}

		if($mybb->get_input('pm_anonymous', MyBB::INPUT_INT))
		{
			$pm['anonymous_checked'] = ' checked="checked"';
		}
	}

	$plugins->run_hooks("warnings_warn_end");

	output_page(\MyBB\template('warnings/warn.twig', [
		'warnings' => $warnings,
		'post' => $post,
		'warn_errors' => $warn_errors,
		'types' => $types,
		'type_checked' => $type_checked,
		'user' => $user,
		'pm' => $pm,
	]));
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

	$user['user_link'] = build_profile_link($user['username'], $user['uid']);

	if($warning['post_subject'])
	{
		$warning['post_subject'] = $parser->parse_badwords($warning['post_subject']);
		$warning['post_link'] = get_post_link($warning['pid']);
	}

	$warning['issuedby'] = build_profile_link($warning['username'], $warning['issuedby']);

	$warning['date_issued'] = my_date('relative', $warning['dateline']);

	if($warning['type_title'])
	{
		$warning['warning_type'] = $warning['type_title'];
	}
	else
	{
		$warning['warning_type'] = $warning['title'];
	}

	if($warning['points'] > 0)
	{
		$warning['points'] = "+{$warning['points']}";
	}

	if($warning['expired'] != 1)
	{
		if($warning['expires'] == 0)
		{
			$warning['expires'] = $lang->never;
		}
		else
		{
			$warning['expires'] = my_date('normal', $warning['expires']); // Purposely not using nice_time here as the moderator has clicked for more details so the actual day/time should be shown
		}
		$warning['status'] = $lang->warning_active;
	}
	else
	{
		if($warning['daterevoked'])
		{
			$warning['expires'] = $warning['status'] = $lang->warning_revoked;
		}
		else if($warning['expires'])
		{
			$warning['revoked_date'] = '('.my_date('normal', $warning['expires']).')';
			$warning['expires'] = $warning['status'] = $lang->already_expired;
		}
	}

	if(!$warning['daterevoked'])
	{
		if(!isset($warn_errors))
		{
			$warn_errors = '';
		}
	}
	else
	{
		$warning['daterevoked'] = my_date('relative', $warning['daterevoked']);
		$revoked_user = get_user($warning['revokedby']);
		if(!$revoked_user['username'])
		{
			$revoked_user['username'] = $lang->guest;
		}

		$revoked_user['profile'] = build_profile_link($revoked_user['username'], $revoked_user['uid']);
	}

	$plugins->run_hooks("warnings_view_end");

	output_page(\MyBB\template('warnings/view.twig', [
		'user' => $user,
		'revoked_user' => $revoked_user,
		'warning' => $warning,
		'warn_errors' => $warn_errors,
	]));
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

	$pages = ceil($warning_count / $perpage);

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}
	if($page)
	{
		$start = ($page - 1) * $perpage;
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

	$warning_level = round($user['warningpoints'] / $mybb->settings['maxwarningpoints'] * 100);
	if($warning_level > 100)
	{
		$warning_level = 100;
	}

	if($user['warningpoints'] > $mybb->settings['maxwarningpoints'])
	{
		$user['warningpoints'] = $mybb->settings['maxwarningpoints'];
	}

	$warnings = [];

	// Fetch the actual warnings
	$query = $db->query("
        SELECT w.*, t.title AS type_title, u.username, p.subject AS post_subject
        FROM ".TABLE_PREFIX."warnings w
        LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (t.tid=w.tid)
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.issuedby)
        LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=w.pid)
        WHERE w.uid = '{$user['uid']}'
        ORDER BY w.expired ASC, w.dateline DESC
        LIMIT {$start}, {$perpage}
    ");

	while($warning = $db->fetch_array($query))
	{
		if(!isset($last_expired) || $warning['expired'] != $last_expired)
		{
			if($warning['expired'] == 0)
			{
				$warning['active'] = 1;
			}
			else
			{
				$warning['active'] = 2;
			}
		}
		$last_expired = $warning['expired'];

		if($warning['post_subject'])
		{
			$warning['post_subject'] = $parser->parse_badwords($warning['post_subject']);
			$warning['post_subject'] = htmlspecialchars_uni($warning['post_subject']);
			$warning['post_link'] = get_post_link($warning['pid']);
		}

		$warning['username'] = htmlspecialchars_uni($warning['username']);
		$warning['issuedby'] = build_profile_link($warning['username'], $warning['issuedby']);

		$warning['date_issued'] = my_date('relative', $warning['dateline']);

		if($warning['type_title'])
		{
			$warning['warning_type'] = $warning['type_title'];
		}
		else
		{
			$warning['warning_type'] = $warning['title'];
		}

		if($warning['points'] > 0)
		{
			$warning['points'] = "+{$warning['points']}";
		}

		if($warning['expired'] != 1)
		{
			if($warning['expires'] == 0)
			{
				$warning['expires'] = $lang->never;
			}
			else
			{
				$warning['expires'] = nice_time($warning['expires'] - TIME_NOW);
			}
		}
		else
		{
			if($warning['daterevoked'])
			{
				$warning['expires'] = $lang->warning_revoked;
			}
			else if($warning['expires'])
			{
				$warning['expires'] = $lang->already_expired;
			}
		}

		$plugins->run_hooks("warnings_warning");

		$warnings[] = $warning;
	}

	$plugins->run_hooks("warnings_end");

	output_page(\MyBB\template('warnings/warnings.twig', [
		'user' => $user,
		'warning_level' => $warning_level,
		'warnings' => $warnings
	]));
}
