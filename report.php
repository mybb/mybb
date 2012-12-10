<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: report.php 5297 2010-12-28 22:01:14Z Tomm $
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'report.php');

$templatelist = "report,email_reportpost,emailsubject_reportpost,report_thanks";
require_once "./global.php";
require_once MYBB_ROOT.'inc/functions_modcp.php';

// Load global language phrases
$lang->load("report");
$reportedposts = $cache->read("reportedposts");

if(!$mybb->user['uid'])
{
	error_no_permission();
}

$type = 'post';
if($mybb->input['type'])
{
	$type = $mybb->input['type'];
}

if($type == 'post')
{
	// These sections process the ability to report/actual reporting of content
	// depending on which type of report the user is reporting
	if($mybb->usergroup['canview'] == 0)
	{
		error_no_permission();
	}

	// Set the type of report
	$report_type = $lang->report_post;

	$report = array();
	$error = $go_back = '';

	// Check to make sure we can process this
	$post = get_post($mybb->input['pid']);

	if(!$post['pid'])
	{
		// Invalid post
		$error = $lang->error_invalid_report;
	}
	else
	{
		// Post OK - check for valid forum
		$pid = $post['pid'];
		$forum = get_forum($post['fid']);

		if(!$forum)
		{
			$error = $lang->error_invalid_report;
		}

		// Password protected forums ......... yhummmmy!
		$fid = $forum['fid'];
		check_forum_password($forum['parentlist']);

		// Check for existing report
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

	if($error)
	{
		eval("\$report_error = \"".$templates->get("report_error")."\";");
		output_page($report_error);
		exit;
	}

	if($mybb->input['action'] == "do_report" && $mybb->request_method == "post")
	{
		// Save Report
		verify_post_check($mybb->input['my_post_key']);

		// Are we adding a vote to an existing report?
		if(isset($report['rid']))
		{
			$report['reporters'][] = $mybb->user['uid'];
			update_report($report);
		}
		else
		{
			// This is a new report, check for reasons
			if(!$mybb->input['reason'] && !trim($mybb->input['comment']))
			{
				// No reason or no comment = no report
				$go_back = $lang->go_back;
				$error = $lang->error_no_reason;

				eval("\$report = \"".$templates->get("report_error")."\";");
				output_page($report);
				exit;
			}
			else
			{
				$reason = trim($mybb->input['reason']);
				$comment = trim($mybb->input['comment']);

				if(!$reason)
				{
					$reason = 'other';
				}

				$new_report = array(
					'pid' => $post['pid'],
					'tid' => $post['tid'],
					'fid' => $post['fid'],
					'uid' => $mybb->user['uid'],
					'reason' => $reason."\n".$comment,
				);

				add_report($new_report, 'post');
			}
		}

		$plugins->run_hooks("report_do_report_end");

		eval("\$report = \"".$templates->get("report_thanks")."\";");
		output_page($report);
		exit;
	}
}
elseif($type == 'profile')
{
	$report_type = 'Report Profile';

	$report = array();
	$error = $go_back = '';

	if(!(int)$mybb->input['pid'])
	{
		$error = $lang->error_invalid_report;
	}
	else
	{
		$user = get_user($mybb->input['pid']);

		if(!$user['uid'])
		{
			$error = $lang->error_invalid_report;
		}
		else
		{
			// Check to see if this user can be reported
			$pid = $user['uid'];
			$permissions = user_permissions($user['uid']);

			if(isset($permissions['canbereported']) && $permissions['canbereported'] == 0)
			{
				$error = $lang->error_invalid_report;
			}

			// Have we already reported this user?
			$query = $db->simple_select("reportedposts", "*", "reportstatus != '1' AND pid = '{$user['uid']}' AND type = 'profile'");
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

	if($error)
	{
		eval("\$report_error = \"".$templates->get("report_error")."\";");
		output_page($report_error);
		exit;
	}

	if($mybb->input['action'] == 'do_report' && $mybb->request_method == 'post')
	{
		verify_post_check($mybb->input['my_post_key']);

		if(isset($report['rid']))
		{
			// Existing report, add vote
			$report['reporters'][] = $mybb->user['uid'];
			update_report($report);
		}
		else
		{
			if(!$mybb->input['reason'] && !trim($mybb->input['comment']))
			{
				$go_back = $lang->go_back;
				$error = $lang->error_no_reason;

				eval("\$report = \"".$templates->get("report_error")."\";");
				output_page($report);
				exit;
			}
			else
			{
				$reason = trim($mybb->input['reason']);
				$comment = trim($mybb->input['comment']);

				if(!$reason)
				{
					$reason = 'other';
				}

				$new_report = array(
					'pid' => $user['uid'],
					'tid' => 0,
					'fid' => 0,
					'uid' => $mybb->user['uid'],
					'reason' => $reason."\n".$comment,
				);

				add_report($new_report, 'profile');
			}
		}

		$plugins->run_hooks("report_do_report_end");

		eval("\$report = \"".$templates->get("report_thanks")."\";");
		output_page($report);
		exit;
	}
}

if(isset($report['rid']))
{
	// Show duplicate message
	eval("\$report_reasons = \"".$templates->get("report_duplicate")."\";");
}
else
{
	// Generate reason box
	$reasons = '';
	$options = $reportedposts['reasons'];

	if($options)
	{
		foreach($options as $key => $option)
		{
			$reason = $option;
			$lang_string = "report_reason_{$key}";

			if(isset($lang->$lang_string))
			{
				$reason = $lang->$lang_string;
			}

			$reasons .= "<option value=\"{$key}\">{$reason}</option>\n";
		}
	}

	$reasons .= "<option value=\"other\">{$lang->report_reason_other}</option>\n";
	eval("\$report_reasons = \"".$templates->get("report_reasons")."\";");
}

$plugins->run_hooks("report_end");

eval("\$report = \"".$templates->get("report")."\";");
output_page($report);
?>