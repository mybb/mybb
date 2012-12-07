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

// Load global language phrases
$lang->load("report");

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
	if($mybb->usergroup['canview'] == 0)
	{
		error_no_permission();
	}

	// Set the type of report
	$report_type = $lang->report_post;
	$report_type_thanks = $lang->success_post_reported;

	$report = array();
	$error = $go_back = '';
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
			$reporters = unserialize($report['reporters']);

			if($mybb->user['uid'] == $report['uid'] || is_array($reporters) && in_array($mybb->user['uid'], $reporters))
			{
				$error = $lang->error_report_voted;
			}
		}
	}

	if($error)
	{
		eval("\$report_error = \"".$templates->get("report_error")."\";");
		output_page($report_error);
		exit;
	}

	$reportedposts = $cache->read("reportedposts");	

	$tid = $post['tid'];
	$thread = get_thread($tid);

	if($mybb->input['action'] == "do_report" && $mybb->request_method == "post")
	{
		// Save Report
		verify_post_check($mybb->input['my_post_key']);

		// Are we adding a vote to an existing report?
		if(isset($report['pid']))
		{
			$reporters[] = $mybb->user['uid'];

			$update_array = array(
				'type' => 'post',
				'reports' => ++$report['reports'],
				'lastreport' => TIME_NOW,
				'reporters' => $db->escape_string(serialize($reporters))
			);

			$db->update_query("reportedposts", $update_array, "rid = '{$report['rid']}'");
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
					'reportstatus' => 0,
					'reason' => $db->escape_string($reason."\n".$comment),
					'type' => 'post',
					'reports' => 1,
					'dateline' => TIME_NOW,
					'lastreport' => TIME_NOW,
					'reporters' => $db->escape_string(serialize(array($mybb->user['uid'])))
				);

				if($mybb->settings['reportmethod'] == "email" || $mybb->settings['reportmethod'] == "pms")
				{
					require_once MYBB_ROOT.'inc/functions_modcp.php';
					send_report($new_report);
				}
				else
				{
					$db->insert_query("reportedposts", $new_report);
					$cache->update_reportedposts();
				}
			}
		}

		$plugins->run_hooks("report_do_report_end");

		eval("\$report = \"".$templates->get("report_thanks")."\";");
		output_page($report);
		exit;
	}

	// Report a Post
	$plugins->run_hooks("report_start");

	if(isset($report['pid']))
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
}
?>