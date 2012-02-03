<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: stats.php 5297 2010-12-28 22:01:14Z Tomm $
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'stats.php');

$templatelist = "stats,stats_thread";
require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("stats");

add_breadcrumb($lang->nav_stats);

$stats = $cache->read("stats");

if($stats['numthreads'] < 1 || $stats['numusers'] < 1)
{
	error($lang->not_enough_info_stats);
}

$plugins->run_hooks("stats_start");

$repliesperthread = my_number_format(round((($stats['numposts'] - $stats['numthreads']) / $stats['numthreads']), 2));
$postspermember = my_number_format(round(($stats['numposts'] / $stats['numusers']), 2));

// Get number of days since board start (might need improvement)
$query = $db->simple_select("users", "regdate", "", array('order_by' => 'regdate', 'limit' => 1));
$result = $db->fetch_array($query);
$days = (TIME_NOW - $result['regdate']) / 86400;
if($days < 1)
{
	$days = 1;
}
// Get "per day" things
$postsperday = my_number_format(round(($stats['numposts'] / $days), 2));
$threadsperday = my_number_format(round(($stats['numthreads'] / $days), 2));
$membersperday = my_number_format(round(($stats['numusers'] / $days), 2));

// Get forum permissions
$unviewableforums = get_unviewable_forums(true);
$fidnot = '1=1';
$unviewableforumsarray = array();
if($unviewableforums)
{
	$fidnot = "fid NOT IN ($unviewableforums)";
	$unviewableforumsarray = explode(',', $unviewableforums);
}

// Most replied-to threads
$most_replied = $cache->read("most_replied_threads");

if(!$most_replied)
{
	$cache->update_most_replied_threads();
	$most_replied = $cache->read("most_replied_threads", true);
}

if(!empty($most_replied))
{
	foreach($most_replied as $key => $thread)
	{
		if(!in_array("'{$thread['fid']}'", $unviewableforumsarray))
		{
			$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
			$numberbit = my_number_format($thread['replies']);
			$numbertype = $lang->replies;
			$thread['threadlink'] = get_thread_link($thread['tid']);
			eval("\$mostreplies .= \"".$templates->get("stats_thread")."\";");
		}
	}
}

// Most viewed threads
$most_viewed = $cache->read("most_viewed_threads");

if(!$most_viewed)
{
	$cache->update_most_viewed_threads();
	$most_viewed = $cache->read("most_viewed_threads", true);
}

if(!empty($most_viewed))
{
	foreach($most_viewed as $key => $thread)
	{
		if(!in_array("'{$thread['fid']}'", $unviewableforumsarray))
		{
			$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
			$numberbit = my_number_format($thread['views']);
			$numbertype = $lang->views;
			$thread['threadlink'] = get_thread_link($thread['tid']);
			eval("\$mostviews .= \"".$templates->get("stats_thread")."\";");
		}
	}
}

// Top forum
if(!empty($fidnot))
{
	$fidnot .= " AND";
}
$query = $db->simple_select("forums", "fid, name, threads, posts", "$fidnot type='f'", array('order_by' => 'posts', 'order_dir' => 'DESC', 'limit' => 1));
$forum = $db->fetch_array($query);
if(!$forum['posts'])
{
	$topforum = $lang->none;
	$topforumposts = $lang->no;
	$topforumthreads = $lang->no;
}
else
{
	$topforum = "<a href=\"".get_forum_link($forum['fid'])."\">{$forum['name']}</a>";
	$topforumposts = $forum['posts'];
	$topforumthreads = $forum['threads'];
}

// Today's top poster
$timesearch = TIME_NOW - 86400;
switch($db->type)
{
	case "pgsql":
		$query = $db->query("
			SELECT u.uid, u.username, COUNT(*) AS poststoday
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid)
			WHERE p.dateline > $timesearch
			GROUP BY ".$db->build_fields_string("users", "u.")." ORDER BY poststoday DESC
			LIMIT 1
		");
		break;
	default:
		$query = $db->query("
			SELECT u.uid, u.username, COUNT(*) AS poststoday
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid)
			WHERE p.dateline > $timesearch
			GROUP BY p.uid ORDER BY poststoday DESC
			LIMIT 1
		");
}
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
$query = $db->simple_select("users", "COUNT(*) AS count", "postnum > 0");
$posters = $db->fetch_field($query, "count");
$havepostedpercent = my_number_format(round((($posters / $stats['numusers']) * 100), 2)) . "%";

$lang->todays_top_poster = $lang->sprintf($lang->todays_top_poster, $topposter, my_number_format($topposterposts));
$lang->popular_forum = $lang->sprintf($lang->popular_forum, $topforum, my_number_format($topforumposts), my_number_format($topforumthreads));

$stats['numposts'] = my_number_format($stats['numposts']);
$stats['numthreads'] = my_number_format($stats['numthreads']);
$stats['numusers'] = my_number_format($stats['numusers']);
$stats['newest_user'] = build_profile_link($stats['lastusername'], $stats['lastuid']);

$plugins->run_hooks("stats_end");

eval("\$stats = \"".$templates->get("stats")."\";");
output_page($stats);
?>