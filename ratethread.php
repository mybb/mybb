<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

define("IN_MYBB", 1);

$templatelist = '';
require_once "./global.php";

$lang->load("ratethread");

$query = $db->simple_select(TABLE_PREFIX."threads", "*", "tid='".intval($mybb->input['tid'])."'");
$thread = $db->fetch_array($query);
if(!$thread['tid'])
{
	error($lang->error_invalidthread);
}

$forumpermissions = forum_permissions($thread['fid']);
if($forumpermissions['canview'] == "no" || $forumpermissions['canratethreads'] == "no" || $mybb->usergroup['canratethreads'] == "no")
{
	error_no_permission();
}

// Get forum info
$fid = $thread['fid'];
$forum = get_forum($fid);
if(!$forum)
{
	error($lang->error_invalidforum);
}
// Password protected forums ......... yhummmmy!
check_forum_password($forum['fid'], $forum['password']);

if($forum['allowtratings'] == "no")
{
	error_no_permission();
}
$mybb->input['rating'] = intval($mybb->input['rating']);
if($mybb->input['rating'] < 1 || $mybb->input['rating'] > 5)
{
	error($lang->error_invalidrating);
}
$plugins->run_hooks("ratethread_start");

if($mybb->user['uid'] != "0")
{
	$whereclause = "uid='".$mybb->user['uid']."'";
}
else
{
	$whereclause = "ipaddress='".$db->escape_string($ipaddress)."'";
}
$query = $db->simple_select(TABLE_PREFIX."threadratings", "*", "$whereclause AND tid='".intval($mybb->input['tid'])."'");
$ratecheck = $db->fetch_array($query);

if($ratecheck['rid'])
{
	error($lang->error_alreadyratedthread);
}
else
{
	if($_COOKIE['mybbthreadrate'][$mybb->input['tid']])
	{
		error($lang->error_alreadyratedthread);
	}
	$plugins->run_hooks("ratethread_process");

	$db->query("
		UPDATE ".TABLE_PREFIX."threads
		SET numratings=numratings+1, totalratings=totalratings+'".$mybb->input['rating']."'
		WHERE tid='".intval($mybb->input['tid'])."'
	");
	if($mybb->user['uid'] != "0")
	{
		$updatearray = array(
			'tid' => intval($mybb->input['tid']),
			'uid' => $mybb->user['uid'],
			'rating' => $mybb->input['rating'],
			'ipaddress' => $db->escape_string($ipaddress)
		);
		$db->insert_query(TABLE_PREFIX."threadratings", $updatearray);
	}
	else
	{
		$updatearray = array(
			'tid' => intval($mybb->input['tid']),
			'rating' => $mybb->input['rating'],
			'ipaddress' => $db->escape_string($ipaddress)
		);
		$db->insert_query(TABLE_PREFIX."threadratings", $updatearray);
		$time = time();
		mysetcookie("mybbratethread[".$mybb->input['tid']."]", $mybb->input['rating']);
	}
}
$plugins->run_hooks("ratethread_end");
redirect("showthread.php?tid=".$mybb->input['tid'], $lang->redirect_threadrated);
?>