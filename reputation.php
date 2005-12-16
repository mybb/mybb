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

$templatesused = "";
require "./global.php";

// Load global language phrases
$lang->load("reputation");

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE pid='".intval($mybb->input['pid'])."'");
$post = $db->fetch_array($query);

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".$post[uid]."'");
$user = $db->fetch_array($query);

$usergroup = user_permissions($post['uid']);

if(!$post['pid'])
{
	error($lang->error_invalidpost);
}
$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='".$post[fid]."' AND active!='no'");
$forum = $db->fetch_array($query);

$permissions = forum_permissions($forum['fid']);

if($permissions['canview'] != "yes")
{
	nopermission();
}
$query = $db->query("SELECT g.usereputationsystem FROM ".TABLE_PREFIX."users u, ".TABLE_PREFIX."usergroups g WHERE u.uid='".$post[uid]."' AND g.gid=u.usergroup");
$usergroup = $db->fetch_array($query);

if($usergroup['usereputationsystem'] != "yes" || $mybb->usergroup['cangivereputations'] != "yes")
{
	error($lang->error_reputationdisabled);
}

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='".$post[fid]."'");
$foruminfo = $db->fetch_array($query);
checkpwforum($fid, $foruminfo['password']);

if($mybb->input['action'] == "do_add")
{
	$plugins->run_hooks("reputation_do_add_start");
	if($post['uid'] == $mybb->user['uid'])
	{ // let the user view their reputation
		eval("\$reputationbit = \"".$templates->get("reputation_yourpost")."\";");
		eval("\$reputation = \"".$templates->get("reputation")."\";");
		outputpage($reputation);
	}
	else
	{ // user is trying to give a reputation
		if($mybb->usergroup['maxreputationsday'] != 0)
		{
			$timesearch = time() - (60 * 60 * 24);
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."reputation WHERE adduid='".$mybb->user[uid]."' AND dateline>'$timesearch'");
			$numtoday = $db->num_rows($query);
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."reputation WHERE pid='".$post['pid']."' AND adduid='".$mybb->user[uid]."'");
		$reputation = $db->fetch_array($query);
		if($reputation['uid'])
		{
			eval("\$reputationbit = \"".$templates->get("reputation_samepost")."\";");
		}
		elseif($numtoday >= $mybb->usergroup['maxreputationsday'] && $mybb->usergroup['maxreputationsday'] != 0)
		{
			eval("\$reputationbit = \"".$templates->get("reputation_maxperday")."\";");
		}
		else
		{
			// work out new reputation
			$rep = $mybb->usergroup['reputationpower'];
			if($mybb->input['add'] == "neg")
			{
				$rep = "-".$rep;
			}
			$reputation = array(
				"uid" => $post['uid'],
				"pid" => $post['pid'],
				"adduid" => $mybb->user['uid'],
				"reputation" => $rep,
				"dateline" => time(),
				"comments" => addslashes($mybb->input['comments'])
				);
			$plugins->run_hooks("reputation_do_add_process");
			$db->insert_query(TABLE_PREFIX."reputation", $reputation);
			$db->query("UPDATE ".TABLE_PREFIX."users SET reputation=reputation+'$rep' WHERE uid='$post[uid]'");
			$reputationbit = "<script type=\"text/javascript\">window.close();</script>";
			$plugins->run_hooks("reputation_do_add_end");
		}
		eval("\$reputation = \"".$templates->get("reputation")."\";");
		outputpage($reputation);
	}	
}
else
{
	$plugins->run_hooks("reputation_start");

	$lang->add_reputation = sprintf($lang->add_reputation, $user['username']);
	if($post['uid'] == $mybb->user['uid'])
	{ // let the user view their reputation
		eval("\$reputationbit = \"".$templates->get("reputation_yourpost")."\";");
		eval("\$reputation = \"".$templates->get("reputation")."\";");
		outputpage($reputation);
	}
	else
	{ // user is trying to give a reputation
		if($mybb->usergroup['maxreputationsday'] != 0)
		{
			$timesearch = time() - (60 * 60 * 24);
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."reputation WHERE adduid='".$mybb->user[uid]."' AND dateline>'$timesearch'");
			$numtoday = $db->num_rows($query);
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."reputation WHERE pid='".$mybb->input['pid']."' AND adduid='".$mybb->user[uid]."'");
		$reputation = $db->fetch_array($query);
		if($reputation['uid'])
		{
			eval("\$reputationbit = \"".$templates->get("reputation_samepost")."\";");
		}
		elseif($numtoday >= $mybb->usergroup['maxreputationsday'] && $mybb->usergroup['maxreputationsday'] != 0)
		{
			eval("\$reputationbit = \"".$templates->get("reputation_maxperday")."\";");
		}
		else
		{
			if($mybb->input['type'] == "n")
			{
				$negcheck = "checked";
			}
			else
			{
				$poscheck = "checked";
			}
			$pid = $mybb->input['pid'];
			eval("\$reputationbit = \"".$templates->get("reputation_add")."\";");
		}
		$lang->add_reputation = sprintf($lang->add_reputation, $user['username']);
		eval("\$reputation = \"".$templates->get("reputation")."\";");
		$plugins->run_hooks("reputation_end");
		outputpage($reputation);
	}
}