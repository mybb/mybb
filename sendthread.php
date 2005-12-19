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

$templatelist = "sendthread,sendthread_guest,email_sendtofriend";
require "./global.php";
require "./inc/functions_post.php";

// Load global language phrases
$lang->load("sendthread");

$tid = intval($mybb->input['tid']);

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
$thread = $db->fetch_array($query);
$thread['subject'] = htmlspecialchars_uni(stripslashes(dobadwords($thread['subject'])));
if(!$thread['tid'])
{
	error($lang->error_invalidthread);
}
$fid = $thread['fid'];


// Make navigation
makeforumnav($fid);
addnav($thread['subject'], "showthread.php?tid=$tid");
addnav($lang->nav_sendthread);

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$thread[fid]'");
$forum = $db->fetch_array($query);

$forumpermissions = forum_permissions($forum['fid']);

if($forum['type'] != "f")
{
	error($lang->error_invalidforum);
}
if($forumpermissions['canview'] != "yes")
{
	nopermission();
}

// Password protected forums
checkpwforum($forum['fid'], $forum['password']);

if($mybb->usergroup['cansendemail'] == "no")
{
	nopermission();
}

if($mybb->input['action'] == "do_sendtofriend" && $mybb->request_method == "post")
{
	$plugins->run_hooks("sendthread_do_sendtofriend_start");
	if(!preg_match("/^(.+)@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/si", $mybb->input['sendto']))
	{
		error($lang->error_invalidemail);
	}
	elseif(!$mybb->input['subject'] || !$mybb->input['message'])
	{
		error($lang->error_incompletefields);
	}
	elseif(!strstr($mybb->input['message'], "$settings[bburl]/showthread.php?tid=$tid"))
	{
		error($lang->error_nothreadurl);
	}
	if($mybb->user['uid'] == 0)
	{
		if(!preg_match("/^(.+)@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/si", $mybb->input['fromemail']))
		{
			error($lang->error_invalidemail);
		}
		elseif(!$mybb->input['fromname'])
		{
			error($lang->error_incompletefields);
		}
		$from = $mybb->input['fromname'] . " <" . $mybb->input['fromemail'] . ">";
	}
	else
	{
		$from = $mybb->user['username'] . " <" . $mybb->user['email'] . ">";
	}
	mymail($mybb->input['sendto'], $mybb->input['subject'], $mybb->input['message'], $from);
	$plugins->run_hooks("sendthread_do_sendtofriend_end");
	redirect("showthread.php?tid=$tid", $lang->redirect_emailsent);
}
else
{
	$plugins->run_hooks("sendthread_start");
	if($mybb->user['uid'] == 0)
	{
		eval("\$guestfields = \"".$templates->get("sendthread_guest")."\";");
	}
	$message = sprintf($lang->email_sendtofriend, $mybb->settings['bbname'], $mybb->settings['bburl'], $tid);
	eval("\$sendtofriend = \"".$templates->get("sendthread")."\";");
	$plugins->run_hooks("sendthread_end");
	outputpage($sendtofriend);
}
?>