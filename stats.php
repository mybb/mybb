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

if($mybb->settings['statsenabled'] != 1)
{
	error($lang->stats_disabled);
}

$plugins->run_hooks("stats_start");

$repliesperthread = my_number_format(round((($stats['numposts'] - $stats['numthreads']) / $stats['numthreads']), 2));
$postspermember = my_number_format(round(($stats['numposts'] / $stats['numusers']), 2));
$threadspermember = my_number_format(round(($stats['numthreads'] / $stats['numusers']), 2));

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
$inactiveforums = get_inactive_forums();
$unviewablefids = $inactivefids = array();
$fidnot = '';

if($unviewableforums)
{
	$fidnot .= "AND fid NOT IN ($unviewableforums)";
	$unviewablefids = explode(',', $unviewableforums);
}
if($inactiveforums)
{
	$fidnot .= "AND fid NOT IN ($inactiveforums)";
	$inactivefids = explode(',', $inactiveforums);
}

$unviewableforumsarray = array_merge($unviewablefids, $inactivefids);

// Most replied-to threads
$most_replied = $cache->read("most_replied_threads");

if(!$most_replied)
{
	$cache->update_most_replied_threads();
	$most_replied = $cache->read("most_replied_threads", true);
}

$mostreplies = '';
if(!empty($most_replied))
{
	foreach($most_replied as $key => $thread)
	{
		if(!in_array($thread['fid'], $unviewableforumsarray))
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

$mostviews = '';
if(!empty($most_viewed))
{
	foreach($most_viewed as $key => $thread)
	{
		if(!in_array($thread['fid'], $unviewableforumsarray))
		{
			$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
			$numberbit = my_number_format($thread['views']);
			$numbertype = $lang->views;
			$thread['threadlink'] = get_thread_link($thread['tid']);
			eval("\$mostviews .= \"".$templates->get("stats_thread")."\";");
		}
	}
}

$statistics = $cache->read('statistics');
$mybb->settings['statscachetime'] = (int)$mybb->settings['statscachetime'];
if($mybb->settings['statscachetime'] < 1)
{
	$mybb->settings['statscachetime'] = 0;
}
$interval = (int)$mybb->settings['statscachetime']*60860;

if(!$statistics || TIME_NOW-$interval > $statistics['time'] || $mybb->settings['statscachetime'] == 0)
{
	$cache->update_statistics();
	$statistics = $cache->read('statistics');
}

// Top forum
$query = $db->simple_select('forums', 'fid, name, threads, posts', "type='f'$fidnot", array('order_by' => 'posts', 'order_dir' => 'DESC', 'limit' => 1));
$forum = $db->fetch_array($query);

if(empty($forum['fid']))
{
	$topforum = $lang->none;
	$topforumposts = $lang->no;
	$topforumthreads = $lang->no;
}
else
{
	$forum['name'] = htmlspecialchars_uni(strip_tags($forum['name']));
	$topforum = '<a href="'.get_forum_link($forum['fid'])."\">{$forum['name']}</a>";
	$topforumposts = $forum['posts'];
	$topforumthreads = $forum['threads'];
}

// Top referrer defined for the templates even if we don't use it
$top_referrer = '';
if($mybb->settings['statstopreferrer'] == 1 && isset($statistics['top_referrer']['uid']))
{
	// Only show this if we have anything more the 0 referrals
	if($statistics['top_referrer']['referrals'] > 0)
	{
		$toprefuser = build_profile_link($statistics['top_referrer']['username'], $statistics['top_referrer']['uid']);
		$top_referrer = $lang->sprintf($lang->top_referrer, $toprefuser, my_number_format($statistics['top_referrer']['referrals']));
	}
}

// Today's top poster
if(!isset($statistics['top_poster']['uid']))
{
	$topposter = $lang->nobody;
	$topposterposts = $lang->no_posts;
}
else
{
	if(!$statistics['top_poster']['uid'])
	{
		$topposter = $lang->guest;
	}
	else
	{
		$topposter = build_profile_link($statistics['top_poster']['username'], $statistics['top_poster']['uid']);
	}

	$topposterposts = $statistics['top_poster']['poststoday'];
}

// What percent of members have posted?
$posters = $statistics['posters'];
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
