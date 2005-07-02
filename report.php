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

$templatelist = "report,email_reportpost,emailsubject_reportpost,report_thanks";
require "./global.php";

// Load global language phrases
$lang->load("report");

if($mybb->usergroup['canview'] == "no" || !$mybb->user['uid'])
{
	nopermission();
}

if($action != "do_report")
{
	$action = "report";
}

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE pid='$pid'");
$post = $db->fetch_array($query);

if(!$post['pid'])
{
	error($lang->error_invalidpost);
}

// Password protected forums ......... yhummmmy!
$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$post[fid]'");
$forum = $db->fetch_array($query);
checkpwforum($forum['fid'], $forum['password']);

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='$post[tid]'");
$thread = $db->fetch_array($query);

if($action == "report")
{
	eval("\$report = \"".$templates->get("report")."\";");
	outputpage($report);
}
elseif($action == "do_report")
{
	$reason = trim($reason);
	if(!$reason)
	{
		eval("\$report = \"".$templates->get("report_noreason")."\";");
		outputpage($report);
		exit;
	}
	if($mybb->settings['reportmethod'] == "email" || $mybb->settings['reportmethod'] == "pm")
	{

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$thread[fid]'");
		$forum = $db->fetch_array($query);
	
		$query = $db->query("SELECT DISTINCT u.username, u.email, u.receivepms, u.uid FROM ".TABLE_PREFIX."moderators m, ".TABLE_PREFIX."users u WHERE u.uid=m.uid AND m.fid IN ($forum[parentlist])");
		$nummods = $db->num_rows($query);
		if(!$nummods)
		{
			unset($query);
			$query = $db->query("SELECT u.username, u.email, u.receivepms, u.uid FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup) WHERE (g.cancp='yes' OR g.issupermod='yes')");
		}
		while($mod = $db->fetch_array($query))
		{
			$emailsubject = sprintf($lang->emailsubject_reportpost, $mybb->settings['bbname']);
			$emailmessage = sprintf($lang->email_reportpost, $mod['username'], $mybb->user['username'], $mybb->settings['bbname'], $post['subject'], $mybb->settings['bburl'], $thread['tid'], $pid, $thread['subject'], $reason);
			
			if($mybb->settings['reportmethod'] == "pms" && $mod['receivepms'] != "no")
			{
				$now = time();
				$emailmessage = addslashes($emailmessage);
				$db->query("INSERT INTO ".TABLE_PREFIX."privatemessages(pmid,uid,toid,fromid,folder,subject,message,dateline,status,readtime) VALUES(NULL,'$mod[uid]','$mod[uid]','-2','1','$emailsubject','$emailmessage','$now','0','0');");
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
		// Reported posts in the db!
		$reason = addslashes(htmlspecialchars($reason));
		$now = time();
		$db->query("INSERT INTO ".TABLE_PREFIX."reportedposts (rid,pid,tid,fid,uid,dateline,reportstatus,reason) VALUES (NULL,'$pid','$thread[tid]','$thread[fid]','".$mybb->user[uid]."','$now','0','$reason')");
		$cache->updatereportedposts();
	}
	eval("\$report = \"".$templates->get("report_thanks")."\";");
	outputpage($report);
}
?>
