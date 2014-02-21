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
define('THIS_SCRIPT', 'report.php');

$templatelist = "report,report_thanks,report_error,report_reasons,report_error_nomodal,forumdisplay_password_wrongpass,forumdisplay_password";
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
$error = $report_type_db = '';

if(!empty($mybb->input['type']))
{
	$report_type = $mybb->get_input('type');
}

$report_title = $lang->report_content;
$report_string = "report_reason_{$report_type}";

if(isset($lang->$report_string))
{
	$report_title = $lang->$report_string;
}

$pid = 0;
if($report_type == 'post')
{
	if($mybb->usergroup['canview'] == 0)
	{
		error_no_permission();
	}

	// Do we have a valid post?
	$post = get_post($mybb->get_input('pid', 1));

	if(!$post)
	{
		$error = $lang->error_invalid_report;
	}
	else
	{
		$pid = $post['pid'];
		$tid = $post['tid'];
		$report_type_db = "(type = 'post' OR type = '')";

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
	}
}
else if($report_type == 'profile')
{
	$user = get_user($mybb->get_input('pid', 1));

	if(!isset($user['uid']))
	{
		$error = $lang->error_invalid_report;
	}
	else
	{
		$tid = $fid = 0; // We don't use these on the profile
		$pid = $user['uid']; // pid is now the profile user
		$permissions = user_permissions($user['uid']);

		if(empty($permissions['canbereported']))
		{
			$error = $lang->error_invalid_report;
		}
		else
		{
			$verified = true;
			$report_type_db = "type = 'profile'";
		}
	}
}
else if($report_type == 'reputation')
{
	// Any member can report a reputation comment but let's make sure it exists first
	$query = $db->simple_select("reputation", "*", "rid = '".$mybb->get_input('pid', 1)."'");

	if(!$db->num_rows($query))
	{
		$error = $lang->error_invalid_report;
	}
	else
	{
		$verified = true;
		$reputation = $db->fetch_array($query);

		$pid = $reputation['rid']; // pid is the reputation id
		$tid = $reputation['adduid']; // tid is now the user who gave the comment
		$fid = $reputation['uid']; // fid is now the user who received the comment

		$report_type_db = "type = 'reputation'";
	}
}

// Plugin hook?

// Check for an existing report
if(!empty($report_type_db))
{
	$query = $db->simple_select("reportedposts", "*", "reportstatus != '1' AND pid = '{$pid}' AND {$report_type_db}");

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

$mybb->input['action'] = $mybb->get_input('action');

if(empty($error) && $verified == true && $mybb->input['action'] == "do_report" && $mybb->request_method == "post")
{
	verify_post_check($mybb->get_input('my_post_key'));

	// Is this an existing report or a new offender?
	if(!empty($report))
	{
		// Existing report, add vote
		$report['reporters'][] = $mybb->user['uid'];
		update_report($report);

		eval("\$report_thanks = \"".$templates->get("report_thanks")."\";");
		echo $report_thanks;
		exit;
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
		$reason = trim($mybb->get_input('reason'));

		if($reason == 'other')
		{
			// Replace the reason with the user comment
			$reason = trim($mybb->get_input('comment'));
		}

		if(my_strlen($reason) < 3)
		{
			$error = $lang->error_report_length;
		}

		if(empty($error))
		{
			$new_report['reason'] = $reason;
			add_report($new_report, $report_type);

			eval("\$report_thanks = \"".$templates->get("report_thanks")."\";");
			echo $report_thanks;
			exit;
		}
	}
}

if(!empty($error) || $verified == false)
{
	$mybb->input['action'] = '';

	if($verified == false && empty($error))
	{
		$error = $lang->error_invalid_report;
	}
}

if(!$mybb->input['action'])
{
	if(!empty($error))
	{
		if($mybb->input['no_modal'])
		{
			eval("\$report_reasons = \"".$templates->get("report_error_nomodal")."\";");
		}
		else
		{
			eval("\$report_reasons = \"".$templates->get("report_error")."\";");
		}
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

	if($mybb->input['no_modal'])
	{
		echo $report_reasons;
		exit;
	}

	eval("\$report = \"".$templates->get("report", 1, 0)."\";");
	echo $report;
	exit;
}
?>