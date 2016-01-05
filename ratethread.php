<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'ratethread.php');

$templatelist = 'forumdisplay_password_wrongpass,forumdisplay_password';
require_once "./global.php";

// Verify incoming POST request
verify_post_check($mybb->get_input('my_post_key'));

$lang->load("ratethread");

$tid = $mybb->get_input('tid');
$thread = get_thread($tid);
if(!$thread)
{
	error($lang->error_invalidthread);
}

// Is the currently logged in user a moderator of this forum?
if(is_moderator($thread['fid']))
{
	$ismod = true;
}
else
{
	$ismod = false;
}

// Make sure we are looking at a real thread here.
if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
{
	error($lang->error_invalidthread);
}

if($thread['uid'] == $mybb->user['uid'])
{
	error($lang->error_cannotrateownthread);
}

$forumpermissions = forum_permissions($thread['fid']);
if($forumpermissions['canview'] == 0 || $forumpermissions['canratethreads'] == 0 || $mybb->usergroup['canratethreads'] == 0 || $mybb->settings['allowthreadratings'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0))
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

// Get forum info
$forum = get_forum($fid);
if(!$forum)
{
	error($lang->error_invalidforum);
}
else
{
	// Is our forum closed?
	if($forum['open'] == 0)
	{
		// Doesn't look like it is
		error($lang->error_closedinvalidforum);
	}
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

if($forum['allowtratings'] == 0)
{
	error_no_permission();
}
$mybb->input['rating'] = $mybb->get_input('rating', MyBB::INPUT_INT);
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
	$whereclause = "ipaddress=".$db->escape_binary($session->packedip);
}
$query = $db->simple_select("threadratings", "*", "{$whereclause} AND tid='{$tid}'");
$ratecheck = $db->fetch_array($query);

if($ratecheck['rid'] || isset($mybb->cookies['mybbratethread'][$tid]))
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
			'ipaddress' => $db->escape_binary($session->packedip)
		);
		$db->insert_query("threadratings", $insertarray);
	}
	else
	{
		$insertarray = array(
			'tid' => $tid,
			'rating' => $mybb->input['rating'],
			'ipaddress' => $db->escape_binary($session->packedip)
		);
		$db->insert_query("threadratings", $insertarray);
		$time = TIME_NOW;
		my_setcookie("mybbratethread[{$tid}]", $mybb->input['rating']);
	}
}
$plugins->run_hooks("ratethread_end");

if(!empty($mybb->input['ajax']))
{
	$json = array("success" => $lang->rating_added);
	$query = $db->simple_select("threads", "totalratings, numratings", "tid='$tid'", array('limit' => 1));
	$fetch = $db->fetch_array($query);
	$width = 0;
	if($fetch['numratings'] >= 0)
	{
		$averagerating = (float)round($fetch['totalratings']/$fetch['numratings'], 2);
		$width = (int)round($averagerating)*20;
		$fetch['numratings'] = (int)$fetch['numratings'];
		$ratingvotesav = $lang->sprintf($lang->rating_votes_average, $fetch['numratings'], $averagerating);
		$json = $json + array("average" => $ratingvotesav);
	}
	$json = $json + array("width" => $width);

	@header("Content-type: application/json; charset={$lang->settings['charset']}");
	echo json_encode($json);
	exit;
}

redirect(get_thread_link($thread['tid']), $lang->redirect_threadrated);
