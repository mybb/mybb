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

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item("Version Check", "index.php?".SID."&amp;module=home/version_check");

if(!$mybb->input['action'])
{	
	$page->output_header("Version Check");
	
	$sub_tabs['version_check'] = array(
		'title' => "Version Check",
		'link' => "index.php?".SID."&amp;module=home/version_check",
		'description' => "Here you can check that you are currently running the latest copy of MyBB and see the latest announcements directly from MyBB."
	);
	
	$sub_tabs['download_mybb'] = array(
		'title' => "Download the Latest MyBB",
		'link' => "http://mybboard.net/downloads",
		'link_target' => '_blank'
	);
	
	$sub_tabs['check_plugins'] = array(
		'title' => "Check your Plugin Versions",
		'link' => "index.php?".SID."&amp;module=config/plugins&amp;action=check",
	);
	
	$page->output_nav_tabs($sub_tabs, 'version_check');	
	
	$current_version = rawurlencode($mybb->version_code);

	$updated_cache = array(
		"last_check" => time()
	);

	require_once MYBB_ROOT."inc/class_xml.php";
	$contents = @implode("", @file("http://mybboard.net/version_check.php"));
	if(!$contents)
	{
		$page->output_inline_error("There was a problem communicating with the version server. Please try again in a few minutes.");
		$page->output_footer();
		exit;
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
	
	$cache->update("update_check", $updated_cache);

	require_once MYBB_ROOT."inc/class_feedparser.php";
	$feed_parser = new FeedParser();
	$feed_parser->parse_feed("http://www.mybboard.net/latest_news.php");
	echo $feed_parser->error;
	
	$table = new Table;
	$table->construct_header("Your Version");
	$table->construct_header("Latest Version");
	
	$table->construct_cell("<strong>".$mybb->version."</strong> (".$mybb->version_code.")");
	$table->construct_cell($latest_version);
	$table->construct_row();
	
	$table->output("Version Check");
	
	if($version_warn)
	{
		$page->output_error("<p><em>Your copy of MyBB is out of date.</em> Please upgrade to the latest version of MyBB by visiting the <a href=\"http://mybboard.net\" target=\"_blank\">MyBB Website</a>.</p>");
	}
	else
	{
		$page->output_success("<p><em>Congratulations, you are running the latest version of MyBB.</em></p>");
	}
	
	if($feed_parser->error == '')
	{
		foreach($feed_parser->items as $item)
		{
			if($item['date_timestamp'])
			{
				$stamp = my_date($mybb->settings['dateformat'], $item['date_timestamp']).", ".my_date($mybb->settings['timeformat'], $item['date_timestamp']);
			}
			else
			{
				$stamp = '';
			}
			if($item['content'])
			{
				$content = $item['content'];
			}
			else
			{
				$content = $item['description'];
			}
			$table->construct_cell("<span style=\"font-size: 16px;\"><strong>".$item['title']."</strong></span>{$content}<strong><span style=\"float: right;\">{$stamp}</span><a href=\"{$item['link']}\" target=\"_blank\">&raquo; Read more</a></strong>");
			$table->construct_row();
		}
	}
	else
	{
		$table->construct_cell("MyBB was unable to successfully fetch the latest announcements from the MyBB website. <!-- error code: {$feed_parser->error} -->");
		$table->construct_row();
	}
	
	$table->output("Lastest MyBB Announcements");
	
	$page->output_footer();
}

?>