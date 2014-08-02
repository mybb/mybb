<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

function task_versioncheck($task)
{
	global $cache, $lang, $mybb;

	$current_version = rawurlencode($mybb->version_code);

	$updated_cache = array(
		'last_check' => TIME_NOW
	);

	// Check for the latest version
	require_once MYBB_ROOT.'inc/class_xml.php';
	$contents = fetch_remote_file("http://www.mybb.com/version_check.php");

	if(!$contents)
	{
		add_task_log($task, $lang->task_versioncheck_ran_errors);
		return false;
	}

	$pos = strpos($contents, "<");
	if($pos > 1)
	{
		$contents = substr($contents, $pos);
	}

	$pos = strpos(strrev($contents), ">");
	if($pos > 1)
	{
		$contents = substr($contents, 0, (-1) * ($pos-1));
	}

	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	$latest_code = $tree['mybb']['version_code']['value'];
	$latest_version = "<strong>".$tree['mybb']['latest_version']['value']."</strong> (".$latest_code.")";
	if($latest_code > $mybb->version_code)
	{
		$latest_version = "<span style=\"color: #C00;\">".$latest_version."</span>";
		$version_warn = 1;
		$updated_cache['latest_version'] = $latest_version;
		$updated_cache['latest_version_code'] = $latest_code;
	}
	else
	{
		$latest_version = "<span style=\"color: green;\">".$latest_version."</span>";
	}

	// Check for the latest news
	require_once MYBB_ROOT."inc/class_feedparser.php";

	$feed_parser = new FeedParser();
	$feed_parser->parse_feed("http://feeds.feedburner.com/MyBBDevelopmentBlog");

	$updated_cache['news'] = array();

	if($feed_parser->error == '')
	{
		foreach($feed_parser->items as $item)
		{
			if(isset($updated_cache['news'][2]))
			{
				break;
			}

			$updated_cache['news'][] = array(
				'title' => $item['title'],
				'description' => preg_replace('#<img(.*)/>#', '', $item['description']),
				'link' => $item['link'],
				'author' => $item['author'],
				'dateline' => $item['date_timestamp']
			);
		}
	}

	$cache->update("update_check", $updated_cache);
	add_task_log($task, $lang->task_versioncheck_ran);
}
?>