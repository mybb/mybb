<?php
/**
 * MyBB 1.6
 * Copyright 2013 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

require_once MYBB_ROOT.'inc/class_feedparser.php';

function task_newsupdate($task)
{
	global $cache;

	$feedParser = new FeedParser();
	$feedParser->parse_feed('http://blog.mybb.com/feed/');

	if ($feed_parser->error == '') {
	    $latestNews = array();
	    foreach ($feedParser->items as $newsItem) {
	        $latestNews[] = array(
	            'title'     => (string) $newsItem['title'],
	            'link'      => (string) $newsItem['link'],
	            'published' => (int) $newsItem['date_timestamp'],
	        );
	    }
	} else {
		add_task_log($task, 'Error parsing news feed.');
		return false;
	}

	$cache->update('latest_news', $latestNews);

	add_task_log($task, 'Latest news updated.');
}
