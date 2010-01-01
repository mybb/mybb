<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: ratethread.php 4304 2009-01-02 01:11:56Z chris $
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'ratethread.php');

$templatelist = '';
require_once "./global.php";

// Verify incoming POST request
verify_post_check($mybb->input['my_post_key']);

$lang->load("ratethread");

$tid = intval($mybb->input['tid']);
$query = $db->simple_select("threads", "*", "tid='{$tid}'");
$thread = $db->fetch_array($query);
if(!$thread['tid'])
{
	error($lang->error_invalidthread);
}

$forumpermissions = forum_permissions($thread['fid']);
if($forumpermissions['canview'] == 0 || $forumpermissions['canratethreads'] == 0 || $mybb->usergroup['canratethreads'] == 0)
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

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

if($forum['allowtratings'] == 0)
{
	error_no_permission();
}
$mybb->input['rating'] = intval($mybb->input['rating']);
if($mybb->input['rating'] < 1 || $mybb->input['rating'] > 5)
{
	error($lang->error_invalidrating);
}
$plugins->run_hooks("ratethread_start");

if($mybb->user['uid'] != 0)
{
	$whereclause = "uid='{$mybb->user['uid']}'";
}
else
{
	$whereclause = "ipaddress='".$db->escape_string($session->ipaddress)."'";
}
$query = $db->simple_select("threadratings", "*", "{$whereclause} AND tid='{$tid}'");
$ratecheck = $db->fetch_array($query);

if($ratecheck['rid'] || $mybb->cookies['mybbratethread'][$tid])
{
	error($lang->error_alreadyratedthread);
}
else
{
	$plugins->run_hooks("ratethread_process");

	$db->write_query("
		UPDATE ".TABLE_PREFIX."threads
		SET numratings=numratings+1, totalratings=totalratings+'{$mybb->input['rating']}'
		WHERE tid='{$tid}'
	");
	if($mybb->user['uid'] != 0)
	{
		$insertarray = array(
			'tid' => $tid,
			'uid' => $mybb->user['uid'],
			'rating' => $mybb->input['rating'],
			'ipaddress' => $db->escape_string($session->ipaddress)
		);
		$db->insert_query("threadratings", $insertarray);
	}
	else
	{
		$insertarray = array(
			'tid' => $tid,
			'rating' => $mybb->input['rating'],
			'ipaddress' => $db->escape_string($session->ipaddress)
		);
		$db->insert_query("threadratings", $insertarray);
		$time = TIME_NOW;
		my_setcookie("mybbratethread[{$tid}]", $mybb->input['rating']);
	}
}
$plugins->run_hooks("ratethread_end");

if($mybb->input['ajax'])
{
	echo "<success>{$lang->rating_added}</success>\n";
	$query = $db->simple_select("threads", "totalratings, numratings", "tid='$tid'", array('limit' => 1));
	$fetch = $db->fetch_array($query);
	$width = 0;
	if($fetch['numratings'] >= 0)
	{
		$averagerating = intval(round($fetch['totalratings']/$fetch['numratings'], 2));
		$width = $averagerating*20;
		$fetch['numratings'] = intval($fetch['numratings']);
		$ratingvotesav = $lang->sprintf($lang->rating_votes_average, $fetch['numratings'], $averagerating);
		echo "<average>{$ratingvotesav}</average>\n";
	}
	echo "<width>{$width}</width>";
	exit;
}

redirect(get_thread_link($thread['tid']), $lang->redirect_threadrated);
?>