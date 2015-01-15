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

$plugins->run_hooks("report_start");

$report = array();
$verified = false;
$report_type = 'post';
$error = $report_type_db = '';

if(!empty($mybb->input['type']))
{
	$report_type = htmlspecialchars_uni($mybb->get_input('type'));
}

$report_title = $lang->report_content;
$report_string = "report_reason_{$report_type}";

if(isset($lang->$report_string))
{
	$report_title = $lang->$report_string;
}

$id = 0;
if($report_type == 'post')
{
	if($mybb->usergroup['canview'] == 0)
	{
		error_no_permission();
	}

	// Do we have a valid post?
	$post = get_post($mybb->get_input('pid', MyBB::INPUT_INT));

	if(!$post)
	{
		$error = $lang->error_invalid_report;
	}
	else
	{
		$id = $post['pid'];
		$id2 = $post['tid'];
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
		$id3 = $forum['fid'];
		check_forum_password($forum['parentlist']);
	}
}
else if($report_type == 'profile')
{
	$user = get_user($mybb->get_input('pid', MyBB::INPUT_INT));

	if(!isset($user['uid']))
	{
		$error = $lang->error_invalid_report;
	}
	else
	{
		$id2 = $id3 = 0; // We don't use these on the profile
		$id = $user['uid']; // id is the profile user
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
	$query = $db->simple_select("reputation", "*", "rid = '".$mybb->get_input('pid', MyBB::INPUT_INT)."'");

	if(!$db->num_rows($query))
	{
		$error = $lang->error_invalid_report;
	}
	else
	{
		$verified = true;
		$reputation = $db->fetch_array($query);

		$id = $reputation['rid']; // id is the reputation id
		$id2 = $reputation['adduid']; // id2 is the user who gave the comment
		$id3 = $reputation['uid']; // id3 is the user who received the comment

		$report_type_db = "type = 'reputation'";
	}
}

$plugins->run_hooks("report_type");

// Check for an existing report
if(!empty($report_type_db))
{
	$query = $db->simple_select("reportedcontent", "*", "reportstatus != '1' AND id = '{$id}' AND {$report_type_db}");

	if($db->num_rows($query))
	{
		// Existing report
		$report = $db->fetch_array($query);
		$report['reporters'] = my_unserialize($report['reporters']);

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

	$plugins->run_hooks("report_do_report_start");

	// Is this an existing report or a new offender?
	if(!empty($report))
	{
		// Existing report, add vote
		$report['reporters'][] = $mybb->user['uid'];
		update_report($report);

		$plugins->run_hooks("report_do_report_end");

		eval("\$report_thanks = \"".$templates->get("report_thanks")."\";");
		echo $report_thanks;
		exit;
	}
	else
	{
		// Bad user!
		$new_report = array(
			'id' => $id,
			'id2' => $id2,
			'id3' => $id3,
			'uid' => $mybb->user['uid']
		);

		// Figure out the reason
		$reason = trim($mybb->get_input('reason'));

		if($reason == 'other')
		{
			// Replace the reason with the user comment
			$reason = trim($mybb->get_input('comment'));
		}
		else
		{
			$report_reason_string = "report_reason_{$reason}";
			$reason = "\n".$lang->$report_reason_string;
		}

		if(my_strlen($reason) < 3)
		{
			$error = $lang->error_report_length;
		}

		if(empty($error))
		{
			$new_report['reason'] = $reason;
			add_report($new_report, $report_type);

			$plugins->run_hooks("report_do_report_end");

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

	$plugins->run_hooks("report_end");

	eval("\$report = \"".$templates->get("report", 1, 0)."\";");
	echo $report;
	exit;
}
