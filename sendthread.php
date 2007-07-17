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

$templatelist = "sendthread,sendthread_guest,email_sendtofriend";
require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("sendthread");

// Get thread info
$tid = intval($mybb->input['tid']);

$thread = get_thread($tid);
$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
if(!$thread['tid'])
{
	error($lang->error_invalidthread);
}
$fid = $thread['fid'];


// Make navigation
build_forum_breadcrumb($fid);
add_breadcrumb($thread['subject'], "showthread.php?tid=$tid");
add_breadcrumb($lang->nav_sendthread);

// Get forum info
$forum = get_forum($fid);

$forumpermissions = forum_permissions($forum['fid']);

if(!$forum || $forum['type'] != "f")
{
	error($lang->error_invalidforum);
}
if($forumpermissions['canview'] != "yes")
{
	error_no_permission();
}

// Password protected forums
check_forum_password($forum['parentlist'], $forum['password']);

if($mybb->usergroup['cansendemail'] == "no")
{
	error_no_permission();
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
	elseif(!strstr($mybb->input['message'], "{$mybb->settings['bburl']}/showthread.php?tid=$tid"))
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
	my_mail($mybb->input['sendto'], $mybb->input['subject'], $mybb->input['message'], $from);
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
	output_page($sendtofriend);
}
?>