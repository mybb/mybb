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

$stats['replies_perthread'] = my_number_format(round((($stats['numposts'] - $stats['numthreads']) / $stats['numthreads']),
	2));
$stats['posts_permember'] = my_number_format(round(($stats['numposts'] / $stats['numusers']), 2));
$stats['threads_permember'] = my_number_format(round(($stats['numthreads'] / $stats['numusers']), 2));

// Get number of days since board start (might need improvement)
$query = $db->simple_select("users", "regdate", "", array('order_by' => 'regdate', 'limit' => 1));
$result = $db->fetch_array($query);
$days = (TIME_NOW - $result['regdate']) / 86400;
if($days < 1)
{
	$days = 1;
}
// Get "per day" things
$stats['posts_perday'] = my_number_format(round(($stats['numposts'] / $days), 2));
$stats['threads_perday'] = my_number_format(round(($stats['numthreads'] / $days), 2));
$stats['members_perday'] = my_number_format(round(($stats['numusers'] / $days), 2));

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

// Check group permissions if we can't view threads not started by us
$group_permissions = forum_permissions();
$onlyusfids = array();
foreach($group_permissions as $gpfid => $forum_permissions)
{
	if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
	{
		$onlyusfids[] = $gpfid;
	}
}

// Most replied-to threads
$most_replied = $cache->read("most_replied_threads");

if(!$most_replied)
{
	$cache->update_most_replied_threads();
	$most_replied = $cache->read("most_replied_threads", true);
}

$most_replied_to_threads = [];
if(!empty($most_replied))
{
	foreach($most_replied as $key => $thread)
	{
		if(
			!in_array($thread['fid'], $unviewableforumsarray) &&
			(!in_array($thread['fid'], $onlyusfids) || ($mybb->user['uid'] && $thread['uid'] == $mybb->user['uid']))
		)
		{
			$most_replied_to_threads[] = [
				'subject' => htmlspecialchars_uni($parser->parse_badwords($thread['subject'])),
				'replies' => my_number_format($thread['replies']),
				'link' => get_thread_link($thread['tid']),
			];
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

$most_viewed_threads = [];
if(!empty($most_viewed))
{
	foreach($most_viewed as $key => $thread)
	{
		if(
			!in_array($thread['fid'], $unviewableforumsarray) &&
			(!in_array($thread['fid'], $onlyusfids) || ($mybb->user['uid'] && $thread['uid'] == $mybb->user['uid']))
		)
		{
			$most_viewed_threads[] = [
				'subject' => htmlspecialchars_uni($parser->parse_badwords($thread['subject'])),
				'views' => my_number_format($thread['views']),
				'link' => get_thread_link($thread['tid']),
			];
		}
	}
}

$statistics = $cache->read('statistics');
$mybb->settings['statscachetime'] = (int)$mybb->settings['statscachetime'];

if($mybb->settings['statscachetime'] < 1)
{
	$mybb->settings['statscachetime'] = 0;
}

$interval = $mybb->settings['statscachetime'] * 3600;

if(!$statistics || $interval == 0 || TIME_NOW - $interval > $statistics['time'])
{
	$cache->update_statistics();
	$statistics = $cache->read('statistics');
}

// Top forum
$query = $db->simple_select('forums', 'fid, name, threads, posts', "type='f'$fidnot",
	array('order_by' => 'posts', 'order_dir' => 'DESC', 'limit' => 1));
$forum = $db->fetch_array($query);

// Not 100% sure, but this conditional is not necessary.
// We check at start whether is any thread, if not, we throw an error.
// So at this point, we always have at least one forum to show.
if($forum)
{
	$top_forum = [
		'name' => htmlspecialchars_uni(strip_tags($forum['name'])),
		'link' => get_forum_link($forum['fid']),
		'posts' => my_number_format($forum['posts']),
		'threads' => my_number_format($forum['threads']),
	];
}

if($mybb->settings['statstopreferrer'] == 1 && isset($statistics['top_referrer']['uid']))
{
	// Only show this if we have anything more the 0 referrals
	if($statistics['top_referrer']['referrals'] > 0)
	{
		$stats['top_referrer_user'] = build_profile_link(
			htmlspecialchars_uni($statistics['top_referrer']['username']),
			$statistics['top_referrer']['uid']
		);
		$stats['top_referrer_count'] = my_number_format($statistics['top_referrer']['referrals']);
	}
	else
	{
		$stats['top_referrer_user'] = false;
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
		$topposter = build_profile_link(htmlspecialchars_uni($statistics['top_poster']['username']),
			$statistics['top_poster']['uid']);
	}

	$topposterposts = $statistics['top_poster']['poststoday'];
}

// What percent of members have posted?
$posters = $statistics['posters'];
$stats['have_posted_percent'] = my_number_format(round((($posters / $stats['numusers']) * 100), 2))."%";

$stats['top_poster'] = $topposter;
$stats['top_poster_posts'] = my_number_format($topposterposts);

$stats['numposts'] = my_number_format($stats['numposts']);
$stats['numthreads'] = my_number_format($stats['numthreads']);
$stats['numusers'] = my_number_format($stats['numusers']);
$stats['newest_user'] = build_profile_link($stats['lastusername'], $stats['lastuid']);

$plugins->run_hooks("stats_end");

output_page(\MyBB\template('stats/stats.twig', [
	'stats' => $stats,
	'most_replied_to_threads' => $most_replied_to_threads,
	'most_viewed_threads' => $most_viewed_threads,
	'top_forum' => $top_forum,
]));
