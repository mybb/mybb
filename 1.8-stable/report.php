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

if($mybb->usergroup['canview'] == 0 || !$mybb->user['uid'])
{
	error_no_permission();
}

if($mybb->input['action'] != "do_report")
{
	$mybb->input['action'] = "report";
}

$post = get_post($mybb->input['pid']);

if(!$post['pid'])
{
	$error = $lang->error_invalidpost;
	eval("\$report_error = \"".$templates->get("report_error")."\";");
	output_page($report_error);
	exit;
}


$forum = get_forum($post['fid']);
if(!$forum)
{
	$error = $lang->error_invalidforum;
	eval("\$report_error = \"".$templates->get("report_error")."\";");
	output_page($report_error);
	exit;
}

// Password protected forums ......... yhummmmy!
check_forum_password($forum['parentlist']);

$thread = get_thread($post['tid']);

if($mybb->input['action'] == "report")
{
	$plugins->run_hooks("report_start");
	$pid = $mybb->input['pid'];
	
	$plugins->run_hooks("report_end");
	
	eval("\$report = \"".$templates->get("report")."\";");
	output_page($report);
}
elseif($mybb->input['action'] == "do_report" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("report_do_report_start");
	if(!trim($mybb->input['reason']))
	{
		eval("\$report = \"".$templates->get("report_noreason")."\";");
		output_page($report);
		exit;
	}

	if($mybb->settings['reportmethod'] == "email" || $mybb->settings['reportmethod'] == "pms")
	{
		$query = $db->query("
			SELECT DISTINCT u.username, u.email, u.receivepms, u.uid
			FROM ".TABLE_PREFIX."moderators m
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=m.id)
			WHERE m.fid IN (".$forum['parentlist'].") AND m.isgroup = '0'
		");
		$nummods = $db->num_rows($query);
		if(!$nummods)
		{
			unset($query);
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					$query = $db->query("
						SELECT u.username, u.email, u.receivepms, u.uid
						FROM ".TABLE_PREFIX."users u
						LEFT JOIN ".TABLE_PREFIX."usergroups g ON (((','|| u.additionalgroups|| ',' LIKE '%,'|| g.gid|| ',%') OR u.usergroup = g.gid))
						WHERE (g.cancp=1 OR g.issupermod=1)
					");
					break;
				default:
					$query = $db->query("
						SELECT u.username, u.email, u.receivepms, u.uid
						FROM ".TABLE_PREFIX."users u
						LEFT JOIN ".TABLE_PREFIX."usergroups g ON (((CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')) OR u.usergroup = g.gid))
						WHERE (g.cancp=1 OR g.issupermod=1)
					");
			}
		}
		
		while($mod = $db->fetch_array($query))
		{
			$emailsubject = $lang->sprintf($lang->emailsubject_reportpost, $mybb->settings['bbname']);
			$emailmessage = $lang->sprintf($lang->email_reportpost, $mybb->user['username'], $mybb->settings['bbname'], $post['subject'], $mybb->settings['bburl'], str_replace('&amp;', '&', get_post_link($post['pid'], $thread['tid'])."#pid".$post['pid']), $thread['subject'], $mybb->input['reason']);
			
			if($mybb->settings['reportmethod'] == "pms" && $mod['receivepms'] != 0 && $mybb->settings['enablepms'] != 0)
			{
				$pm_recipients[] = $mod['uid'];
			}
			else
			{
				my_mail($mod['email'], $emailsubject, $emailmessage);
			}
		}

		if(count($pm_recipients) > 0)
		{
			$emailsubject = $lang->sprintf($lang->emailsubject_reportpost, $mybb->settings['bbname']);
			$emailmessage = $lang->sprintf($lang->email_reportpost, $mybb->user['username'], $mybb->settings['bbname'], $post['subject'], $mybb->settings['bburl'], str_replace('&amp;', '&', get_post_link($post['pid'], $thread['tid'])."#pid".$post['pid']), $thread['subject'], $mybb->input['reason']);

			require_once MYBB_ROOT."inc/datahandlers/pm.php";
			$pmhandler = new PMDataHandler();

			$pm = array(
				"subject" => $emailsubject,
				"message" => $emailmessage,
				"icon" => 0,
				"fromid" => $mybb->user['uid'],
				"toid" => $pm_recipients
			);

			$pmhandler->admin_override = true;
			$pmhandler->set_data($pm);

			// Now let the pm handler do all the hard work.
			if(!$pmhandler->validate_pm())
			{
				// Force it to valid to just get it out of here
				$pmhandler->is_validated = true;
				$pmhandler->errors = array();
			}
			$pminfo = $pmhandler->insert_pm();
		}
	}
	else
	{
		$reportedpost = array(
			"pid" => intval($mybb->input['pid']),
			"tid" => $thread['tid'],
			"fid" => $thread['fid'],
			"uid" => $mybb->user['uid'],
			"dateline" => TIME_NOW,
			"reportstatus" => 0,
			"reason" => $db->escape_string(htmlspecialchars_uni($mybb->input['reason']))
		);
		$db->insert_query("reportedposts", $reportedpost);
		$cache->update_reportedposts();
	}
	
	$plugins->run_hooks("report_do_report_end");
	
	eval("\$report = \"".$templates->get("report_thanks")."\";");
	output_page($report);
}
?>