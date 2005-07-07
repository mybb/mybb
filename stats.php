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

$templatelist = "stats,stats_thread";
require "./global.php";
require "./inc/functions_post.php";

// Load global language phrases
$lang->load("stats");

addnav($lang->nav_stats);

$stats = $cache->read("stats");

if($stats['numthreads'] < 1 || $stats['numposts'] < 1)
{
	error($lang->not_enough_info_stats);
}

$repliesperthread = round((($stats['numposts'] - $stats['numthreads']) / $stats['numthreads']), 2);
$postspermember = round(($stats['numposts'] / $stats['numusers']), 2);

// Get number of days since board start (might need improvement)
$query = $db->query("SELECT regdate FROM ".TABLE_PREFIX."users ORDER BY regdate LIMIT 1");
$result = $db->fetch_array($query);
$days = (time() - $result['regdate']) / 86400;

// Get "per day" things
$postsperday = round(($stats['numposts'] / $days), 2);
$threadsperday = round(($stats['numthreads'] / $days), 2);
$membersperday = round(($stats['numusers'] / $days), 2);

// Get forum permissions
$unviewableforums = getunviewableforums();
if($unviewableforums) {
	$fidnot = " AND fid NOT IN ($unviewableforums)";
}

// Most replied-to threads
$query = $db->query("SELECT tid, subject, replies FROM ".TABLE_PREFIX."threads WHERE 1=1 $fidnot ORDER BY replies DESC LIMIT 0, ".$mybb->settings[statslimit]);
while($thread = $db->fetch_array($query)) {
	$viewreply = "replies";
	$thread['subject'] = htmlspecialchars_uni(stripslashes(dobadwords($thread['subject'])));
	eval("\$mostreplies .= \"".$templates->get("stats_thread")."\";");
}

// Most viewed threads
$query = $db->query("SELECT tid, subject, views FROM ".TABLE_PREFIX."threads WHERE 1=1 $fidnot ORDER BY views DESC LIMIT 0, ".$mybb->settings[statslimit]);
while($thread = $db->fetch_array($query)) {
	$viewreply = "views";
	$thread['subject'] = htmlspecialchars_uni(stripslashes(dobadwords($thread['subject'])));
	eval("\$mostviews .= \"".$templates->get("stats_thread")."\";");
}

// Top forum
$query = $db->query("SELECT fid, name, threads, posts FROM ".TABLE_PREFIX."forums WHERE 1=1 $fidnot AND type='f' ORDER BY posts DESC LIMIT 1");
$forum = $db->fetch_array($query);
if(!$forum['posts']) {
	$topforum = $lang->none;
	$topforumposts = $lang->no;
	$topforumthreads = $lang->no;
} else {
	$forum['name'] = htmlspecialchars_uni(stripslashes($forum['name']));
	$topforum = "<a href=\"forumdisplay.php?fid=$forum[fid]\">$forum[name]</a>";
	$topforumposts = $forum['posts'];
	$topforumthreads = $forum['threads'];
}

// Today's top poster
$timesearch = time() - 86400;
$query = $db->query("SELECT u.uid, u.username, COUNT(*) AS poststoday FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid) WHERE p.dateline > $timesearch GROUP BY p.uid ORDER BY poststoday DESC LIMIT 1");
$user = $db->fetch_array($query);
if(!$user['poststoday']) {
	$topposter = $lang->nobody;
	$topposterposts = $lang->no_posts;
} else {
	$topposter = "<a href=\"member.php?action=profile&uid=$user[uid]\">$user[username]</a>";
	$topposterposts = $user['poststoday'];
}

// What percent of members have posted?
$query = $db->query("SELECT COUNT(*) FROM ".TABLE_PREFIX."users WHERE postnum > 0");
$posters = $db->result($query, 0);
$havepostedpercent = round((($posters / $stats['numusers']) * 100), 2) . "%";

$lang->todays_top_poster = sprintf($lang->todays_top_poster, $topposter, $topposterposts);
$lang->popular_forum = sprintf($lang->popular_forum, $topforum, $topforumposts, $topforumthreads);

eval("\$stats = \"".$templates->get("stats")."\";");
outputpage($stats);
?>