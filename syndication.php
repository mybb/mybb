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
define("IGNORE_CLEAN_VARS", "fid");
define("NO_ONLINE", 1);

require_once "./global.php";

// Load global language phrases
$lang->load("syndication");

// Load syndication class.
require_once MYBB_ROOT."inc/class_feedgeneration.php";
$feedgenerator = new FeedGenerator();

// Load the post parser
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;
$parser_options = array(
	'allow_html' => "yes"
);

// Find out the thread limit.
$thread_limit = intval($mybb->input['limit']);
if($thread_limit > 50)
{
	$thread_limit = 50;
}
else if(!$thread_limit)
{
	$thread_limit = 20;
}

// Syndicate a specific forum or all viewable?
if(isset($mybb->input['fid']))
{
	$forumlist = $mybb->input['fid'];
	$forumlist = explode(',', $forumlist);
}
else
{
	$forumlist = "";
}

// Get the forums the user is not allowed to see.
$unviewable = get_unviewable_forums();
$inactiveforums = get_inactive_forums();

// If there are any, add SQL to exclude them.
if($unviewable)
{
	$unviewable = "AND fid NOT IN($unviewable)";
}
if($inactiveforums)
{
	$unviewable .= " AND fid NOT IN($inactiveforums)";
}

// If there are no forums to syndicate, syndicate all viewable.
if(!empty($forumlist))
{
	$forum_ids = "'-1'";
	foreach($forumlist as $fid)
	{
		$forum_ids .= ",'".intval($fid)."'";
	}
	$forumlist = "AND fid IN ($forum_ids) $unviewable";
}
else
{
	$forumlist = $unviewable;
	$all_forums = 1;
}

// Find out which title to add to the feed.
$title = $mybb->settings['bbname'];
$query = $db->simple_select(TABLE_PREFIX."forums", "name, fid", "1=1 ".$forumlist);
$comma = " - ";
while($forum = $db->fetch_array($query))
{
	$title .= $comma.$forum['name'];
	$forumcache[$forum['fid']] = $forum;
	$comma = ", ";
}

// If syndicating all forums then cut the title back to "All Forums"
if($all_forums)
{
	$title = $mybb->settings['bbname']." - ".$lang->all_forums;
}

// Set the feed type.
$feedgenerator->set_feed_format($mybb->input['type']);

// Set the channel header.
$channel = array(
	"title" => $title,
	"link" => $mybb->settings['bburl']."/",
	"date" => time(),
	"description" => $mybb->settings['bbname']." - ".$mybb->settings['bburl']
);
$feedgenerator->set_channel($channel);

// Get the threads to syndicate.
$query = $db->simple_select(TABLE_PREFIX."threads", "subject, tid, dateline, firstpost", "visible='1' AND closed NOT LIKE 'moved|%' ".$forumlist, array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => $thread_limit));
// Loop through all the threads.
while($thread = $db->fetch_array($query))
{
	$items[$thread['tid']] = array(
		"title" => $thread['subject'],
		"link" => $mybb->settings['bburl']."/showthread.php?tid=".$thread['tid'],		
		"date" => $thread['dateline'],
	);
	
	$firstposts[] = $thread['firstpost'];
}

if(!empty($firstposts))
{
	$firstpostlist = "pid IN(".implode(',', $firstposts).")";
	$query = $db->simple_select(TABLE_PREFIX."posts", "message, edittime, tid", $firstpostlist);	
	while($post = $db->fetch_array($query))
	{
		$items[$post['tid']]['description'] = $parser->strip_mycode($post['message'], $parser_options);
		$items[$post['tid']]['updated'] = $post['edittime'];
		$feedgenerator->add_item($items[$post['tid']]);
	}
}

// Then output the feed XML.
$feedgenerator->output_feed();
?>