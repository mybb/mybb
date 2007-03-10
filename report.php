<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/eula.html
 *
 * $Id$
 */

define("IN_MYBB", 1);

$templatelist = "report,email_reportpost,emailsubject_reportpost,report_thanks";
require_once "./global.php";

// Load global language phrases
$lang->load("report");

if($mybb->usergroup['canview'] == "no" || !$mybb->user['uid'])
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
check_forum_password($forum['fid'], $forum['password']);

$thread = get_thread($post['tid']);

if($mybb->input['action'] == "report")
{
	$plugins->run_hooks("report_start");
	$pid = $mybb->input['pid'];
	eval("\$report = \"".$templates->get("report")."\";");
	$plugins->run_hooks("report_end");
	output_page($report);
}
elseif($mybb->input['action'] == "do_report" && $mybb->request_method == "post")
{
	$plugins->run_hooks("report_do_report_start");
	if(!trim($mybb->input['reason']))
	{
		eval("\$report = \"".$templates->get("report_noreason")."\";");
		output_page($report);
		exit;
	}
	if($mybb->settings['reportmethod'] == "email" || $mybb->settings['reportmethod'] == "pm")
	{
		$query = $db->query("
			SELECT DISTINCT u.username, u.email, u.receivepms, u.uid
			FROM ".TABLE_PREFIX."moderators m, ".TABLE_PREFIX."users u
			WHERE u.uid=m.uid AND m.fid IN (".$forum['parentlist'].")
		");
		$nummods = $db->num_rows($query);
		if(!$nummods)
		{
			unset($query);
			$query = $db->query("
				SELECT u.username, u.email, u.receivepms, u.uid
				FROM ".TABLE_PREFIX."users u
				LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup)
				WHERE (g.cancp='yes' OR g.issupermod='yes')
			");
		}
		while($mod = $db->fetch_array($query))
		{
			$emailsubject = sprintf($lang->emailsubject_reportpost, $mybb->settings['bbname']);
			$emailmessage = sprintf($lang->email_reportpost, $mod['username'], $mybb->user['username'], $mybb->settings['bbname'], $post['subject'], $mybb->settings['bburl'], $thread['tid'], $post['pid'], $thread['subject'], $mybb->input['reason']);
			
			if($mybb->settings['reportmethod'] == "pms" && $mod['receivepms'] != "no" && $mybb->settings['enablepms'] != "no")
			{
				$reportpm = array(
					"uid" => $mod['uid'],
					"toid" => $mod['uid'],
					"fromid" => -2,
					"folder" => 1,
					"subject" => $db->escape_string($emailsubject),
					"message" => $db->escape_string($emailmessage),
					"dateline" => time(),
					"status" => 0,
					"readtime" => 0
					);
				$db->insert_query(TABLE_PREFIX."privatemessages", $reportpm);
				$db->update_query(TABLE_PREFIX."users", array('pmpopup' => 'new'), "uid='{$mod['uid']}'");
			}
			else
			{
				my_mail($mod['email'], $emailsubject, $emailmessage);
			}
		}
	}
	else
	{
		$reportedpost = array(
			"pid" => intval($mybb->input['pid']),
			"tid" => $thread['tid'],
			"fid" => $thread['fid'],
			"uid" => $mybb->user['uid'],
			"dateline" => time(),
			"reportstatus" => 0,
			"reason" => $db->escape_string(htmlspecialchars_uni($mybb->input['reason']))
			);
		$db->insert_query(TABLE_PREFIX."reportedposts", $reportedpost);
		$cache->updatereportedposts();
	}
	eval("\$report = \"".$templates->get("report_thanks")."\";");
	$plugins->run_hooks("report_do_report_end");
	output_page($report);
}
?>
