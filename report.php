<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'report.php');

$templatelist = "report,report_thanks,report_error,report_noreason,forumdisplay_password_wrongpass,forumdisplay_password";
require_once "./global.php";
require_once MYBB_ROOT.'inc/functions_modcp.php';

$lang->load("report");

if(!$mybb->user['uid'])
{
	error_no_permission();
}

$report = array();
$verified = false;
$report_type = 'post';
$error = $go_back = '';

if(!empty($mybb->input['type']))
{
	$report_type = $mybb->input['type'];
}

$report_title = $lang->report_content;
$report_string = "report_reason_{$report_type}";

if(isset($lang->$report_string))
{
	$report_title = $lang->$report_string;
}

if($report_type == 'post')
{
	if($mybb->usergroup['canview'] == 0)
	{
		error_no_permission();
	}

	// Do we have a valid post?
	$post = get_post($mybb->input['pid']);

	if(!isset($post['pid']))
	{
		$error = $lang->error_invalid_report;
	}
	else
	{
		$pid = $post['pid'];
		$tid = $post['tid'];

		// Check for a valid forum
		$forum = get_forum($post['fid']);

		if(!isset($forum['fid']))
		{
			$error = $lang->error_invalid_report;
		}
		else
		{
			$verified = true;
		}

		// Password protected forums ......... yhummmmy!
		$fid = $forum['fid'];
		check_forum_password($forum['parentlist']);

		// Check for an existing report
		$query = $db->simple_select("reportedposts", "*", "reportstatus != '1' AND pid = '{$pid}' AND (type = 'post' OR type = '')");

		if($db->num_rows($query))
		{
			// Existing report
			$report = $db->fetch_array($query);
			$report['reporters'] = unserialize($report['reporters']);

			if($mybb->user['uid'] == $report['uid'] || is_array($report['reporters']) && in_array($mybb->user['uid'], $report['reporters']))
			{
				$error = $lang->success_report_voted;
			}
		}
	}
}
else if($report_type == 'profile')
{
	$user = get_user($mybb->input['pid']);

	if(!isset($user['uid']))
	{
		$error = $lang->error_invalid_report;
	}
	else
	{
		$tid = $fid = 0;
		$pid = $user['uid'];
		$permissions = user_permissions($user['uid']);

		if(empty($permissions['canbereported']))
		{
			$error = $lang->error_invalid_report;
		}
		else
		{
			$verified = true;
		}

		$query = $db->simple_select("reportedposts", "*", "reportstatus != '1' AND pid = '{$user['uid']}' AND type = 'profile'");

		if($db->num_rows($query))
		{
			$report = $db->fetch_array($query);
			$report['reporters'] = unserialize($report['reporters']);

			if($mybb->user['uid'] == $report['uid'] || is_array($report['reporters']) && in_array($mybb->user['uid'], $report['reporters']))
			{
				$error = $lang->success_report_voted;
			}
		}
	}
}

if(empty($error) && $verified == true && $mybb->input['action'] == "do_report" && $mybb->request_method == "post")
{
	verify_post_check($mybb->input['my_post_key']);

	// Is this an existing report or a new offender?
	if(!empty($report))
	{
		// Existing report, add vote
		$report['reporters'][] = $mybb->user['uid'];
		update_report($report);

		eval("\$report_thanks = \"".$templates->get("report_thanks")."\";");
		output_page($report_thanks);
	}
	else
	{
		// Bad user!
		$new_report = array(
			'pid' => $pid,
			'tid' => $tid,
			'fid' => $fid,
			'uid' => $mybb->user['uid']
		);

		// Figure out the reason
		$reason = trim($mybb->input['reason']);

		if($reason == 'other')
		{
			// Replace the reason with the user comment
			$reason = trim($mybb->input['comment']);
		}

		if(my_strlen($reason) < 3)
		{
			$error = $lang->error_report_length;
			$go_back = $lang->go_back;
		}

		if(empty($error))
		{
			$new_report['reason'] = $reason;
			add_report($new_report, $report_type);

			eval("\$report_thanks = \"".$templates->get("report_thanks")."\";");
			output_page($report_thanks);
		}
	}
}

if(!empty($error) || $verified == false)
{
	unset($mybb->input['action']);
}

if(!$mybb->input['action'])
{
	if(!empty($error))
	{
		eval("\$report_reasons = \"".$templates->get("report_error")."\";");
	}
	else
	{
		if(!empty($report))
		{
			eval("\$report_reasons = \"".$templates->get("report_duplicate")."\";");
		}
		else
		{
			eval("\$report_reasons = \"".$templates->get("report_reasons")."\";");
		}
	}

	eval("\$report = \"".$templates->get("report")."\";");
	output_page($report);
}
?>