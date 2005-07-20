<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */
 define("KILL_GLOBALS", 1);

$templatelist = "report,email_reportpost,emailsubject_reportpost,report_thanks";
require "./global.php";

// Load global language phrases
$lang->load("report");

if($mybb->usergroup['canview'] == "no" || !$mybb->user['uid'])
{
	nopermission();
}

if($mybb->input['action'] != "do_report")
{
	$mybb->input['action'] = "report";
}

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE pid='".intval($mybb->input['pid'])."'");
$post = $db->fetch_array($query);

if(!$post['pid'])
{
	error($lang->error_invalidpost);
}

// Password protected forums ......... yhummmmy!
$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='".$post[fid]."'");
$forum = $db->fetch_array($query);
checkpwforum($forum['fid'], $forum['password']);

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='".$post[tid]."'");
$thread = $db->fetch_array($query);

if($mybb->input['action'] == "report")
{
	$plugins->run_hooks("report_start");
	$pid = $mybb->input['pid'];
	eval("\$report = \"".$templates->get("report")."\";");
	$plugins->run_hooks("report_end");
	outputpage($report);
}
elseif($mybb->input['action'] == "do_report")
{
	$plugins->run_hooks("report_do_report_start");
	if(!trim($mybb->input['reason']))
	{
		eval("\$report = \"".$templates->get("report_noreason")."\";");
		outputpage($report);
		exit;
	}
	if($mybb->settings['reportmethod'] == "email" || $mybb->settings['reportmethod'] == "pm")
	{

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='".$thread[fid]."'");
		$forum = $db->fetch_array($query);
	
		$query = $db->query("SELECT DISTINCT u.username, u.email, u.receivepms, u.uid FROM ".TABLE_PREFIX."moderators m, ".TABLE_PREFIX."users u WHERE u.uid=m.uid AND m.fid IN (".$forum[parentlist].")");
		$nummods = $db->num_rows($query);
		if(!$nummods)
		{
			unset($query);
			$query = $db->query("SELECT u.username, u.email, u.receivepms, u.uid FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup) WHERE (g.cancp='yes' OR g.issupermod='yes')");
		}
		while($mod = $db->fetch_array($query))
		{
			$emailsubject = sprintf($lang->emailsubject_reportpost, $mybb->settings['bbname']);
			$emailmessage = sprintf($lang->email_reportpost, $mod['username'], $mybb->user['username'], $mybb->settings['bbname'], $post['subject'], $mybb->settings['bburl'], $thread['tid'], $pid, $thread['subject'], $mybb->input['reason']);
			
			if($mybb->settings['reportmethod'] == "pms" && $mod['receivepms'] != "no")
			{
				$reportpm = array(
					"pmid" => "NULL",
					"uid" => $mod['uid'],
					"toid" => $mod['uid'],
					"fromid" => -2,
					"folder" => 1,
					"subject" => addslashes($emailsubject),
					"message" => addslashes($emailmessage),
					"dateline" => time(),
					"status" => 0,
					"readtime" => 0
					);
				$db->insert_query(TABLE_PREFIX."privatemessages", $reportpm);
				$db->query("UPDATE ".TABLE_PREFIX."users SET pmpopup='new' WHERE uid='$mod[uid]'");
			}
			else
			{
				mymail($mod['email'], $emailsubject, $emailmessage);
			}
		}
	}
	else
	{
		$reportedpost = array(
			"rid" => "NULL",
			"pid" => intval($mybb->input['pid']),
			"tid" => $thread['tid'],
			"fid" => $thread['fid'],
			"uid" => $mybb->user['uid'],
			"dateline" => time(),
			"reportstatus" => 0,
			"reason" => addslashes(htmlspecialchars_uni($mybb->input['reason']))
			);
		$db->insert_query(TABLE_PREFIX."reportedposts", $reportedpost);
		$cache->updatereportedposts();
	}
	eval("\$report = \"".$templates->get("report_thanks")."\";");
	$plugins->run_hooks("report_do_report_end");
	outputpage($report);
}
?>
