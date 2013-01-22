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

require_once MYBB_ROOT.'/inc/functions.php';

function task_newsupdate($task)
{
	global $cache;

	if (!extension_loaded('SimpleXML')) {
		add_task_log($task, 'The SimpleXML extension is not installed. Please contact your host.');
		return false;
	}

	$fetchedNews = fetch_remote_file('http://blog.mybb.com/feed/');

	if (!$fetchedNews) {
		add_task_log($task, 'Error communicating with the MyBB server.');
		return false;
	}

	try {
		$feed = new SimpleXMLElement($fetchedNews);
	} catch (Exception $e) {
		add_task_log($task, $e);
		return false;
	}

	$latestNews = array();
	foreach ($feed->channel->item as $newsItem) {
		$latestNews[] = array(
			'title'     => (string) $newsItem->title,
			'link'      => (string) $newsItem->link,
			'published' => (int) strtotime($newsItem->pubDate),
		);
	}

	$cache->update('latest_news', $latestNews);

	add_task_log($task, 'Latest news updated.');
}
