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

$templatelist = "stats,stats_thread";
require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("stats");

add_breadcrumb($lang->nav_stats);

$stats = $cache->read("stats");

if($stats['numthreads'] < 1 || $stats['numposts'] < 1)
{
	error($lang->not_enough_info_stats);
}

$plugins->run_hooks("stats_start");

$repliesperthread = mynumberformat(round((($stats['numposts'] - $stats['numthreads']) / $stats['numthreads']), 2));
$postspermember = mynumberformat(round(($stats['numposts'] / $stats['numusers']), 2));

// Get number of days since board start (might need improvement)
$query = $db->simple_select(TABLE_PREFIX."users", "regdate", "", array('order_by' => 'regdate', 'limit' => 1));
$result = $db->fetch_array($query);
$days = (time() - $result['regdate']) / 86400;

// Get "per day" things
$postsperday = mynumberformat(round(($stats['numposts'] / $days), 2));
$threadsperday = mynumberformat(round(($stats['numthreads'] / $days), 2));
$membersperday = mynumberformat(round(($stats['numusers'] / $days), 2));

// Get forum permissions
$unviewableforums = get_unviewable_forums();
$fidnot = '1=1';
if($unviewableforums)
{
	$fidnot = "fid NOT IN ($unviewableforums)";
}

// Most replied-to threads
$query = $db->simple_select(TABLE_PREFIX."threads", "tid, subject, replies", $fidnot, array('order_by' => 'replies', 'order_dir' => 'DESC', 'limit_start' => 0, 'limit' => $mybb->settings['statslimit']));
while($thread = $db->fetch_array($query))
{
	$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
	$numberbit = mynumberformat($thread['replies']);
	$numbertype = $lang->replies;
	eval("\$mostreplies .= \"".$templates->get("stats_thread")."\";");
}

// Most viewed threads
$query = $db->simple_select(TABLE_PREFIX."threads", "tid, subject, views", $fidnot, array('order_by' => 'views', 'order_dir' => 'DESC', 'limit_start' => 0, 'limit' => $mybb->settings['statslimit']));
while($thread = $db->fetch_array($query))
{
	$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
	$numberbit = mynumberformat($thread['views']);
	$numbertype = $lang->views;
	eval("\$mostviews .= \"".$templates->get("stats_thread")."\";");
}

// Top forum
if(!empty($fidnot))
{
	$fidnot .= " AND";
}
$query = $db->simple_select(TABLE_PREFIX."forums", "fid, name, threads, posts", "$fidnot type='f'", array('order_by' => 'posts', 'order_dir' => 'DESC', 'limit' => 1));
$forum = $db->fetch_array($query);
if(!$forum['posts'])
{
	$topforum = $lang->none;
	$topforumposts = $lang->no;
	$topforumthreads = $lang->no;
}
else
{
	$topforum = "<a href=\"forumdisplay.php?fid=$forum[fid]\">$forum[name]</a>";
	$topforumposts = $forum['posts'];
	$topforumthreads = $forum['threads'];
}

// Today's top poster
$timesearch = time() - 86400;
$query = $db->query("
	SELECT u.uid, u.username, COUNT(*) AS poststoday
	FROM ".TABLE_PREFIX."posts p
	LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid)
	WHERE p.dateline > $timesearch
	GROUP BY p.uid ORDER BY poststoday DESC
	LIMIT 1
");
$user = $db->fetch_array($query);
if(!$user['poststoday'])
{
	$topposter = $lang->nobody;
	$topposterposts = $lang->no_posts;
}
else
{
	if(!$user['uid'])
	{
		$topposter = $lang->guest;
	}
	else
	{
		$topposter = build_profile_link($user['username'], $user['uid']);
	}
	$topposterposts = $user['poststoday'];
}

// What percent of members have posted?
$query = $db->simple_select(TABLE_PREFIX."users", "COUNT(*) AS count", "postnum > 0");
$posters = $db->fetch_field($query, "count");
$havepostedpercent = mynumberformat(round((($posters / $stats['numusers']) * 100), 2)) . "%";

$lang->todays_top_poster = sprintf($lang->todays_top_poster, $topposter, mynumberformat($topposterposts));
$lang->popular_forum = sprintf($lang->popular_forum, $topforum, mynumberformat($topforumposts), mynumberformat($topforumthreads));

$stats['numposts'] = mynumberformat($stats['numposts']);
$stats['numthreads'] = mynumberformat($stats['numthreads']);
$stats['numusers'] = mynumberformat($stats['numusers']);

eval("\$stats = \"".$templates->get("stats")."\";");
$plugins->run_hooks("stats_end");
output_page($stats);
?>