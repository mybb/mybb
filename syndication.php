<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
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
$permissions = forum_permissions();
$inactiveforums = get_inactive_forums();

// If there are any, add SQL to exclude them.
if(is_array($permissions))
{
	foreach($permissions as $fid => $forum_permission)
	{
		if($forum_permission['canview'] == 0 || $forum_permission['canviewthreads'] == 0)
		{
			$unviewable[] = $fid;
		}
	}
	if(is_array($unviewable))
	{
		$unviewable = "AND f.fid NOT IN (".implode(",", $unviewable).")";
	}
}

if($inactiveforums)
{
	$unviewable .= " AND f.fid NOT IN($inactiveforums)";
}

// If there are no forums to syndicate, syndicate all viewable.
if(!empty($forumlist))
{
	$forum_ids = "'-1'";
	foreach($forumlist as $fid)
	{
		$forum_ids .= ",'".intval($fid)."'";
	}
	$forumlist = "AND f.fid IN ($forum_ids) $unviewable";
}
else
{
	$forumlist = $unviewable;
	$all_forums = 1;
}

// Find out which title to add to the feed.
$title = $mybb->settings['bbname'];
$query = $db->simple_select("forums f", "f.name, f.fid", "1=1 ".$forumlist);
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

// Get the threads to syndicate.
$query = $db->query("
	SELECT t.tid, t.dateline AS date, p.edittime AS updated, t.subject AS title, f.allowhtml, f.allowmycode, f.allowsmilies, f.allowimgcode,
	f.name AS forumname, p.message AS description, u.username AS author, p.smilieoff, f.fid
	FROM ".TABLE_PREFIX."threads t
	LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid)
	LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=t.firstpost)
	LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
	WHERE t.visible=1 AND t.closed NOT LIKE 'moved|%' ".$forumlist."
	ORDER BY t.dateline DESC
	LIMIT 0, ".$thread_limit
);

// Set the feed type.
$feedgenerator->set_feed_format($mybb->input['type']);

// Set the channel header.
$channel = array(
	"title" => $title,
	"link" => $mybb->settings['bburl']."/",
	"date" => TIME_NOW,
	"description" => $mybb->settings['bbname']." - ".$mybb->settings['bburl']
);
$feedgenerator->set_channel($channel);

// Loop through all the threads.
while($thread = $db->fetch_array($query))
{
	$thread['link'] = $channel['link'] . get_thread_link($thread['tid']);
	if($forumcache[$thread['fid']])
	{
		$feedgenerator->add_item($thread);
	}
}
$db->free_result($query);

// Then output the feed XML.
$feedgenerator->output_feed();
?>