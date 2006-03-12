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

$templatesused = '';
require "./global.php";

// Load global language phrases
$lang->load("reputation");

if($mybb->settings['enablereputation'] != "yes")
{
	error($lang->reputation_disabled);
}

// Get post info
$pid = intval($mybb->input['pid']);
$query = $db->simple_select(TABLE_PREFIX."posts", "*", "pid='".$pid."'");
$post = $db->fetch_array($query);
if(!$post['pid'])
{
	error($lang->error_invalidpost);
}

// Get author info
$posteruid = $post['uid'];
$query = $db->simple_select(TABLE_PREFIX."users", "*", "uid='".$posteruid."'");
$user = $db->fetch_array($query);

// Get forum info
$fid = $post['fid'];
/*$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='".$fid."' AND active!='no'");
$forum = $db->fetch_array($query);*/
cacheforums();
$forum = $forumcache[$fid];
// Check if forum and parents are active
$parents = explode(",", $forum['parentlist'].",$fid");
foreach($parents as $chkfid)
{
	if($forumcache[$chkfid]['active'] == "no")
	{
		error($lang->error_invalidforum);
	}
}

// Do the permissions thing
$usergroup = user_permissions($posteruid);
$permissions = forum_permissions($fid);

if($permissions['canview'] != "yes")
{
	nopermission();
}
/*$query = $db->query("SELECT g.usereputationsystem FROM ".TABLE_PREFIX."users u, ".TABLE_PREFIX."usergroups g WHERE u.uid='".$post['uid']."' AND g.gid=u.usergroup");
$usergroup = $db->fetch_array($query);*/

if($usergroup['usereputationsystem'] != "yes" || $mybb->usergroup['cangivereputations'] != "yes")
{
	error($lang->error_reputationdisabled);
}

checkpwforum($fid, $forum['password']);

if($mybb->input['action'] == "do_add")
{
	$plugins->run_hooks("reputation_do_add_start");
	if($posteruid == $mybb->user['uid'])
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
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."reputation WHERE adduid='".$mybb->user['uid']."' AND dateline>'$timesearch'");
			$numtoday = $db->num_rows($query);
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."reputation WHERE pid='".$pid."' AND adduid='".$mybb->user[uid]."'");
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
				"uid" => $authorpid,
				"pid" => $pid,
				"adduid" => $mybb->user['uid'],
				"reputation" => $rep,
				"dateline" => time(),
				"comments" => addslashes($mybb->input['comments'])
				);
			$plugins->run_hooks("reputation_do_add_process");
			$db->insert_query(TABLE_PREFIX."reputation", $reputation);
			$db->query("UPDATE ".TABLE_PREFIX."users SET reputation=reputation+'$rep' WHERE uid='$authorpid'");
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
	if($posteruid == $mybb->user['uid'])
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
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."reputation WHERE adduid='".$mybb->user['uid']."' AND dateline>'$timesearch'");
			$numtoday = $db->num_rows($query);
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."reputation WHERE pid='".$pid."' AND adduid='".$mybb->user['uid']."'");
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
				$negcheck = "checked=\"checked\"";
			}
			else
			{
				$poscheck = "checked=\"checked\"";
			}
			eval("\$reputationbit = \"".$templates->get("reputation_add")."\";");
		}
		$lang->add_reputation = sprintf($lang->add_reputation, $user['username']);
		eval("\$reputation = \"".$templates->get("reputation")."\";");
		$plugins->run_hooks("reputation_end");
		outputpage($reputation);
	}
}
?>