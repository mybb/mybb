<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
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
$unviewableforums = get_unviewable_forums();
$fidnot = '1=1';
if($unviewableforums)
{
	$fidnot = "fid NOT IN ($unviewableforums)";
}

// Most replied-to threads
$mostreplied = $stats['mostreplied'];

if(!$mostreplied || $mostreplied['lastupdated'] <= time()-60*60*24)
{
	$mostreplied = array();
	$query = $db->simple_select("threads", "tid, subject, replies", $fidnot, array('order_by' => 'replies', 'order_dir' => 'DESC', 'limit_start' => 0, 'limit' => $mybb->settings['statslimit']));
	while($thread = $db->fetch_array($query))
	{
		$mostreplied['threads'][] = $thread;
	}
	$mostreplied['lastupdated'] = time();
	$cache->update("most_replied_threads", $mostreplied);
	
	$mostreplied['lastupdated'] = time();
	$stats['mostviewed'] = $mostreplied;
	
	$update_stats = true;
	
	reset($mostreplied);
}

if(!empty($mostreplied))
{
	foreach($mostreplied['threads'] as $key => $thread)
	{
		$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
		$numberbit = my_number_format($thread['replies']);
		$numbertype = $lang->replies;
		eval("\$mostreplies .= \"".$templates->get("stats_thread")."\";");
	}
}


// Most viewed threads
$mostviewed = $stats['mostviewed'];

if(!$mostviewed || $mostviewed['lastupdated'] <= time()-60*60*24)
{
	$mostviewed = array();
	$query = $db->simple_select("threads", "tid, subject, views", $fidnot, array('order_by' => 'views', 'order_dir' => 'DESC', 'limit_start' => 0, 'limit' => $mybb->settings['statslimit']));
	while($thread2 = $db->fetch_array($query))
	{
		$mostviewed['threads'][] = $thread2;
	}
	$mostviewed['lastupdated'] = time();
	$stats['mostviewed'] = $mostviewed;
	
	$update_stats = true;
	
	reset($mostviewed);
}

if(!empty($mostviewed))
{
	foreach($mostviewed['threads'] as $key => $thread)
	{
		$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
		$numberbit = my_number_format($thread['views']);
		$numbertype = $lang->views;
		eval("\$mostviews .= \"".$templates->get("stats_thread")."\";");
	}
}

if($update_stats == true)
{
	$cache->update("stats", $stats);
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
	$topforum = "<a href=\"forumdisplay.php?fid={$forum['fid']}\">{$forum['name']}</a>";
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

$lang->todays_top_poster = sprintf($lang->todays_top_poster, $topposter, my_number_format($topposterposts));
$lang->popular_forum = sprintf($lang->popular_forum, $topforum, my_number_format($topforumposts), my_number_format($topforumthreads));

$stats['numposts'] = my_number_format($stats['numposts']);
$stats['numthreads'] = my_number_format($stats['numthreads']);
$stats['numusers'] = my_number_format($stats['numusers']);
$stats['newest_user'] = build_profile_link($stats['lastusername'], $stats['lastuid']);

eval("\$stats = \"".$templates->get("stats")."\";");
$plugins->run_hooks("stats_end");
output_page($stats);
?>