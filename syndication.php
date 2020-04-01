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
define("IGNORE_CLEAN_VARS", "fid");
define("NO_ONLINE", 1);
define('THIS_SCRIPT', 'syndication.php');

$templatelist = "postbit_attachments_attachment";

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
if($mybb->get_input('portal') && $mybb->settings['portal'] != 0)
{
	$thread_limit = $mybb->settings['portal_numannouncements'];
}
else
{
	$thread_limit = $mybb->get_input('limit', MyBB::INPUT_INT);
}

if($thread_limit > 50)
{
	$thread_limit = 50;
}
else if(!$thread_limit || $thread_limit < 0)
{
	$thread_limit = 15;
}

// Syndicate a specific forum or all viewable?
if($mybb->get_input('portal') && $mybb->settings['portal'] != 0)
{
	if($mybb->settings['portal_announcementsfid'] != '-1')
	{
		$forumlist = explode(',', $mybb->settings['portal_announcementsfid']);
	}
}
elseif($mybb->get_input('fid'))
{
	$forumlist = explode(',', $mybb->get_input('fid'));
}

// Get the forums the user is not allowed to see.
$unviewableforums = get_unviewable_forums(true);
$inactiveforums = get_inactive_forums();

$unviewable = '';

$plugins->run_hooks('syndication_start');

// If there are any, add SQL to exclude them.
if($unviewableforums)
{
	$unviewable .= " AND fid NOT IN($unviewableforums)";
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
		$forum_ids .= ",'".(int)$fid."'";
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
$query = $db->simple_select("forums", "name, fid, allowhtml, allowmycode, allowsmilies, allowimgcode, allowvideocode", "1=1 ".$forumlist);
$comma = " - ";
while($forum = $db->fetch_array($query))
{
	if(!$mybb->get_input('portal') || $mybb->settings['portal'] == 0)
	{
		$title .= $comma.$forum['name'];
		$comma = $lang->comma;
	}
	$forumcache[$forum['fid']] = $forum;
}

if($mybb->get_input('portal') && $mybb->settings['portal'] != 0)
{
		$title .= $comma.$lang->portal;
}

// If syndicating all forums then cut the title back to "All Forums"
if(isset($all_forums))
{
	if($mybb->get_input('portal') && $mybb->settings['portal'] != 0)
	{
		$title = $mybb->settings['bbname']." - ".$lang->portal;
	}
	else
	{
		$title = $mybb->settings['bbname']." - ".$lang->all_forums;
	}
}

// Set the feed type.
$feedgenerator->set_feed_format($mybb->get_input('type'));

// Set the channel header.
$channel = array(
	"title" => $title,
	"link" => $mybb->settings['bburl']."/",
	"date" => TIME_NOW,
	"description" => $mybb->settings['bbname']." - ".$mybb->settings['bburl']
);
$feedgenerator->set_channel($channel);

$permsql = "";
$onlyusfids = array();

// Check group permissions if we can't view threads not started by us
$group_permissions = forum_permissions();
foreach($group_permissions as $fid => $forum_permissions)
{
	if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
	{
		$onlyusfids[] = $fid;
	}
}
if(!empty($onlyusfids))
{
	$permsql .= "AND ((fid IN(".implode(',', $onlyusfids).") AND uid='{$mybb->user['uid']}') OR fid NOT IN(".implode(',', $onlyusfids)."))";
}

// Get the threads to syndicate.
$query = $db->simple_select("threads", "subject, tid, dateline, firstpost", "visible='1' AND closed NOT LIKE 'moved|%' {$permsql} {$forumlist}", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => $thread_limit));
// Loop through all the threads.
while($thread = $db->fetch_array($query))
{
	$items[$thread['tid']] = array(
		"title" => $parser->parse_badwords($thread['subject']),
		"link" => $channel['link'].get_thread_link($thread['tid']),
		"date" => $thread['dateline'],
	);

	$firstposts[] = $thread['firstpost'];
}

$plugins->run_hooks('syndication_get_posts');

if(!empty($firstposts))
{
	$firstpostlist = "pid IN(".$db->escape_string(implode(',', $firstposts)).")";

	if($mybb->settings['enableattachments'] == 1)
	{
		$attachments = array();
		$query = $db->simple_select("attachments", "*", $firstpostlist);
		while($attachment = $db->fetch_array($query))
		{
			if(!isset($attachments[$attachment['pid']]))
			{
				$attachments[$attachment['pid']] = array();
			}
			$attachments[$attachment['pid']][] = $attachment;
		}
	}

	$query = $db->simple_select("posts", "message, edittime, tid, uid, username, fid, pid", $firstpostlist, array('order_by' => 'dateline', 'order_dir' => 'desc'));
	while($post = $db->fetch_array($query))
	{
		$parser_options = array(
			"allow_html" => $forumcache[$post['fid']]['allowhtml'],
			"allow_mycode" => $forumcache[$post['fid']]['allowmycode'],
			"allow_smilies" => $forumcache[$post['fid']]['allowsmilies'],
			"allow_imgcode" => $forumcache[$post['fid']]['allowimgcode'],
			"allow_videocode" => $forumcache[$post['fid']]['allowvideocode'],
			"filter_badwords" => 1,
			"filter_cdata" => 1
		);

		$parsed_message = $parser->parse_message($post['message'], $parser_options);

		if($mybb->settings['enableattachments'] == 1 && isset($attachments[$post['pid']]) && is_array($attachments[$post['pid']]))
		{
			foreach($attachments[$post['pid']] as $attachment)
			{
				$ext = get_extension($attachment['filename']);
				$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
				$attachment['filesize'] = get_friendly_size($attachment['filesize']);
				$attachment['icon'] = get_attachment_icon($ext);
				eval("\$attbit = \"".$templates->get("postbit_attachments_attachment")."\";");
				if(stripos($parsed_message, "[attachment=".$attachment['aid']."]") !== false)
				{
					$parsed_message = preg_replace("#\[attachment=".$attachment['aid']."]#si", $attbit, $parsed_message);
				}
				else
				{
					$parsed_message .= "<br />".$attbit;
				}
			}
		}

		$items[$post['tid']]['description'] = $parsed_message;
		$items[$post['tid']]['updated'] = $post['edittime'];
		$items[$post['tid']]['author'] = array("uid" => $post['uid'], "name" => $post['username']);
		$feedgenerator->add_item($items[$post['tid']]);
	}
}

// Then output the feed.
$feedgenerator->output_feed();