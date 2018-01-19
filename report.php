<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'report.php');

require_once './global.php';
require_once MYBB_ROOT . 'inc/functions_modcp.php';

$lang->load('report');

if (!$mybb->user['uid']) {
	error_no_permission();
}

$plugins->run_hooks('report_start');

$report = [];
$verified = false;
$report_info['type'] = 'post';
$error = $report_type_db = '';

if (!empty($mybb->input['type'])) {
	$report_info['type'] = $mybb->get_input('type');
}

$report_info['title'] = $lang->report_content;
$report_string = "report_reason_{$report_info['type']}";

if (isset($lang->$report_string)) {
	$report_info['title'] = $lang->$report_string;
}

$report_info['id'] = 0;
if ($report_info['type'] == 'post') {
	if ($mybb->usergroup['canview'] == 0) {
		error_no_permission();
	}

	// Do we have a valid post?
	$post = get_post($mybb->get_input('pid', MyBB::INPUT_INT));

	if (!$post) {
		$error = $lang->error_invalid_report;
	} else {
		$report_info['id'] = $post['pid'];
		$id2 = $post['tid'];
		$report_type_db = "(type = 'post' OR type = '')";

		// Check for a valid forum
		$forum = get_forum($post['fid']);

		if (!isset($forum['fid'])) {
			$error = $lang->error_invalid_report;
		} else {
			$verified = true;
		}

		// Password protected forums ......... yhummmmy!
		$id3 = $forum['fid'];
		check_forum_password($forum['parentlist']);
	}
} else if ($report_info['type'] == 'profile') {
	$user = get_user($mybb->get_input('pid', MyBB::INPUT_INT));

	if (!isset($user['uid'])) {
		$error = $lang->error_invalid_report;
	} else {
		$id2 = $id3 = 0; // We don't use these on the profile
		$report_info['id'] = $user['uid']; // id is the profile user
		$permissions = user_permissions($user['uid']);

		if (empty($permissions['canbereported'])) {
			$error = $lang->error_invalid_report;
		} else {
			$verified = true;
			$report_type_db = "type = 'profile'";
		}
	}
} else if ($report_info['type'] == 'reputation') {
	// Any member can report a reputation comment but let's make sure it exists first
	$query = $db->simple_select('reputation', '*', "rid = '".$mybb->get_input('pid', MyBB::INPUT_INT)."'");

	if (!$db->num_rows($query)) {
		$error = $lang->error_invalid_report;
	} else {
		$verified = true;
		$reputation = $db->fetch_array($query);

		$report_info['id'] = $reputation['rid']; // id is the reputation id
		$id2 = $reputation['adduid']; // id2 is the user who gave the comment
		$id3 = $reputation['uid']; // id3 is the user who received the comment

		$report_type_db = "type = 'reputation'";
	}
}

$plugins->run_hooks('report_type');

// Check for an existing report
$report_info['isduplicate'] = false;
if (!empty($report_type_db)) {
	$query = $db->simple_select('reportedcontent', '*', "reportstatus != '1' AND id = '{$report_info['id']}' AND {$report_type_db}");

	if ($db->num_rows($query)) {
		$report_info['isduplicate'] = true;

		// Existing report
		$report = $db->fetch_array($query);
		$report['reporters'] = my_unserialize($report['reporters']);

		if ($mybb->user['uid'] == $report['uid'] ||
			is_array($report['reporters']) &&
			in_array($mybb->user['uid'], $report['reporters'])) {
			$error = $lang->success_report_voted;
		}
	}
}

$mybb->input['action'] = $mybb->get_input('action');

if (empty($error) &&
	$verified == true &&
	$mybb->input['action'] == 'do_report' &&
	$mybb->request_method == 'post') {
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks('report_do_report_start');

	// Is this an existing report or a new offender?
	if ($report_info['isduplicate']) {
		// Existing report, add vote
		$report['reporters'][] = $mybb->user['uid'];
		update_report($report);

		$plugins->run_hooks('report_do_report_end');

		echo \MyBB\template('report/report_thanks.twig', [
			'report' => $report_info,
		]);
		exit;
	} else {
		// Bad user!
		$new_report = array(
			'id' => $report_info['id'],
			'id2' => $id2,
			'id3' => $id3,
			'uid' => $mybb->user['uid']
		);

		// Figure out the reason
		$rid = $mybb->get_input('reason', MyBB::INPUT_INT);
		$query = $db->simple_select('reportreasons', '*', "rid='{$rid}'");

		if (!$db->num_rows($query)) {
			$error = $lang->error_invalid_report_reason;
			$verified = false;
		} else {
			$reason = $db->fetch_array($query);

			$new_report['reasonid'] = $reason['rid'];

			if ($reason['extra']) {
				$comment = trim($mybb->get_input('comment'));
				if (empty($comment) ||
					$comment == '') {
					$error = $lang->error_comment_required;
					$verified = false;
				} else {
					if (my_strlen($comment) < 3) {
						$error = $lang->error_report_length;
						$verified = false;
					} else {
						$new_report['reason'] = $comment;
					}
				}
			}
		}

		if (empty($error)) {
			add_report($new_report, $report_info['type']);

			$plugins->run_hooks('report_do_report_end');

			echo \MyBB\template('report/report_thanks.twig', [
				'report' => $report_info,
			]);
			exit;
		}
	}
}

if (!empty($error) ||
	$verified == false) {
	$mybb->input['action'] = '';

	if ($verified == false &&
		empty($error)) {
		$error = $lang->error_invalid_report;
	}
}

if ($mybb->input['action']) {
	exit;
}

$report_info['has_errors'] = false;
if (!empty($error)) {
	$report_info['has_errors'] = true;
	$report_info['error'] = $error;
} else {
	$report_info['reasons'] = $cache->read('reportreasons')[$report_info['type']];
	foreach ($report_info['reasons'] as $key => $reason) {
		$report_info['reasons'][$key]['title'] = $lang->parse($reason['title']);
	}
}

if ($mybb->input['no_modal']) {
	$template = 'report/report_reasons.twig';
	if ($report_info['has_errors']) {
		$template = 'report/report_error_nomodal.twig';
	}

	echo \MyBB\template($template, [
		'report' => $report_info,
	]);
	exit;
}

$plugins->run_hooks('report_end');

output_page(\MyBB\template('report/report.twig', [
	'report' => $report_info,
]));
exit;
