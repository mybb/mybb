<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->run_hooks("admin_home_index_begin");

$sub_tabs['dashboard'] = array(
	'title' => $lang->dashboard,
	'link' => "index.php",
	'description' => $lang->dashboard_description
);

$sub_tabs['version_check'] = array(
	'title' => $lang->version_check,
	'link' => "index.php?module=home&amp;action=version_check",
	'description' => $lang->version_check_description
);

if($mybb->input['action'] == "version_check")
{
	$plugins->run_hooks("admin_home_version_check_start");

	$current_version = rawurlencode($mybb->version_code);

	$updated_cache = array(
		"last_check" => TIME_NOW
	);

	require_once MYBB_ROOT."inc/class_xml.php";
	$contents = fetch_remote_file("https://mybb.com/version_check.php");

	if(!$contents)
	{
		flash_message($lang->error_communication, 'error');
		admin_redirect('index.php');
	}

	$plugins->run_hooks("admin_home_version_check");

	$page->add_breadcrumb_item($lang->version_check, "index.php?module=home-version_check");
	$page->output_header($lang->version_check);
	$page->output_nav_tabs($sub_tabs, 'version_check');

	// We do this because there is some weird symbols that show up in the xml file for unknown reasons
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

	$latest_code = (int)$tree['mybb']['version_code']['value'];
	$latest_version = "<strong>".htmlspecialchars_uni($tree['mybb']['latest_version']['value'])."</strong> (".$latest_code.")";
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

	if($version_warn)
	{
		$page->output_error("<p><em>{$lang->error_out_of_date}</em> {$lang->update_forum}</p>");
	}
	else
	{
		$page->output_success("<p><em>{$lang->success_up_to_date}</em></p>");
	}

	$table = new Table;
	$table->construct_header($lang->your_version);
	$table->construct_header($lang->latest_version);

	$table->construct_cell("<strong>".$mybb->version."</strong> (".$mybb->version_code.")");
	$table->construct_cell($latest_version);
	$table->construct_row();

	$table->output($lang->version_check);

	require_once MYBB_ROOT."inc/class_feedparser.php";

	$feed_parser = new FeedParser();
	$feed_parser->parse_feed("http://feeds.feedburner.com/MyBBDevelopmentBlog");

	$updated_cache['news'] = array();

	require_once MYBB_ROOT . '/inc/class_parser.php';
	$post_parser = new postParser();

	if($feed_parser->error == '')
	{
		foreach($feed_parser->items as $item)
		{
			if(!isset($updated_cache['news'][2]))
			{
				$description = $item['description'];
				$content = $item['content'];

				$description = $post_parser->parse_message($description, array(
						'allow_html' => true,
					)
				);

				$content = $post_parser->parse_message($content, array(
						'allow_html' => true,
					)
				);

				$description = preg_replace('#<img(.*)/>#', '', $description);
				$content = preg_replace('#<img(.*)/>#', '', $content);

				$updated_cache['news'][] = array(
					'title' => htmlspecialchars_uni($item['title']),
					'description' => $description,
					'link' => htmlspecialchars_uni($item['link']),
					'author' => htmlspecialchars_uni($item['author']),
					'dateline' => $item['date_timestamp'],
				);
			}

			$stamp = '';
			if($item['date_timestamp'])
			{
				$stamp = my_date('relative', $item['date_timestamp']);
			}

			$link = htmlspecialchars_uni($item['link']);

			$table->construct_cell("<span style=\"font-size: 16px;\"><strong>".htmlspecialchars_uni($item['title'])."</strong></span><br /><br />{$content}<strong><span style=\"float: right;\">{$stamp}</span><br /><br /><a href=\"{$link}\" target=\"_blank\" rel=\"noopener\">&raquo; {$lang->read_more}</a></strong>");
			$table->construct_row();
		}
	}
	else
	{
		$table->construct_cell("{$lang->error_fetch_news} <!-- error code: {$feed_parser->error} -->");
		$table->construct_row();
	}

	$cache->update("update_check", $updated_cache);

	$table->output($lang->latest_mybb_announcements);
	$page->output_footer();
}
elseif(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_home_index_start");

	if($mybb->request_method == "post" && isset($mybb->input['adminnotes']))
	{
		// Update Admin Notes cache
		$update_cache = array(
			"adminmessage" => $mybb->input['adminnotes']
		);

		$cache->update("adminnotes", $update_cache);

		$plugins->run_hooks("admin_home_index_start_begin");

		flash_message($lang->success_notes_updated, 'success');
		admin_redirect("index.php");
	}

	$page->add_breadcrumb_item($lang->dashboard);
	$page->output_header($lang->dashboard);

	$sub_tabs['dashboard'] = array(
		'title' => $lang->dashboard,
		'link' => "index.php",
		'description' => $lang->dashboard_description
	);

	$page->output_nav_tabs($sub_tabs, 'dashboard');

	// Load stats cache
	$stats = $cache->read("stats");

	$serverload = get_server_load();

	// Get the number of users
	$query = $db->simple_select("users", "COUNT(uid) AS numusers");
	$users = my_number_format($db->fetch_field($query, "numusers"));

	// Get the number of users awaiting validation
	$awaitingusers = $cache->read('awaitingactivation');

	if(!empty($awaitingusers['users']))
	{
		$awaitingusers = (int)$awaitingusers['users'];
	}
	else
	{
		$awaitingusers = 0;
	}

	if($awaitingusers < 1)
	{
		$awaitingusers = 0;
	}
	else
	{
		$awaitingusers = my_number_format($awaitingusers);
	}

	// Get the number of new users for today
	$timecut = TIME_NOW - 86400;
	$query = $db->simple_select("users", "COUNT(uid) AS newusers", "regdate > '$timecut'");
	$newusers = my_number_format($db->fetch_field($query, "newusers"));

	// Get the number of active users today
	$query = $db->simple_select("users", "COUNT(uid) AS activeusers", "lastvisit > '$timecut'");
	$activeusers = my_number_format($db->fetch_field($query, "activeusers"));

	// Get the number of threads
	$threads = my_number_format($stats['numthreads']);

	// Get the number of unapproved threads
	$unapproved_threads = my_number_format($stats['numunapprovedthreads']);

	// Get the number of new threads for today
	$query = $db->simple_select("threads", "COUNT(*) AS newthreads", "dateline > '$timecut' AND visible='1' AND closed NOT LIKE 'moved|%'");
	$newthreads = my_number_format($db->fetch_field($query, "newthreads"));

	// Get the number of posts
	$posts = my_number_format($stats['numposts']);

	// Get the number of unapproved posts
	if($stats['numunapprovedposts'] < 0)
	{
		$stats['numunapprovedposts'] = 0;
	}

	$unapproved_posts = my_number_format($stats['numunapprovedposts']);

	// Get the number of new posts for today
	$query = $db->simple_select("posts", "COUNT(*) AS newposts", "dateline > '$timecut' AND visible='1'");
	$newposts = my_number_format($db->fetch_field($query, "newposts"));

	// Get the number of reported post
	$query = $db->simple_select("reportedcontent", "COUNT(*) AS reported_posts", "type = 'post' OR type = ''");
	$reported_posts = my_number_format($db->fetch_field($query, "reported_posts"));

	// If report medium is MCP...
	if($mybb->settings['reportmethod'] == "db")
	{
		// Get the number of reported posts that haven't been marked as read yet
		$query = $db->simple_select("reportedcontent", "COUNT(*) AS new_reported_posts", "reportstatus='0' AND (type = 'post' OR type = '')");
		$new_reported_posts = my_number_format($db->fetch_field($query, "new_reported_posts"));
	}

	// Get the number and total file size of attachments
	$query = $db->simple_select("attachments", "COUNT(*) AS numattachs, SUM(filesize) as spaceused", "visible='1' AND pid > '0'");
	$attachs = $db->fetch_array($query);
	$attachs['spaceused'] = get_friendly_size($attachs['spaceused']);
	$approved_attachs = my_number_format($attachs['numattachs']);

	// Get the number of unapproved attachments
	$query = $db->simple_select("attachments", "COUNT(*) AS numattachs", "visible='0' AND pid > '0'");
	$unapproved_attachs = my_number_format($db->fetch_field($query, "numattachs"));

	// Fetch the last time an update check was run
	$update_check = $cache->read("update_check");

	// If last update check was greater than two weeks ago (14 days) show an alert
	if(isset($update_check['last_check']) && $update_check['last_check'] <= TIME_NOW-60*60*24*14)
	{
		$lang->last_update_check_two_weeks = $lang->sprintf($lang->last_update_check_two_weeks, "index.php?module=home&amp;action=version_check");
		$page->output_error("<p>{$lang->last_update_check_two_weeks}</p>");
	}

	// If the update check contains information about a newer version, show an alert
	if(isset($update_check['latest_version_code']) && $update_check['latest_version_code'] > $mybb->version_code)
	{
		$lang->new_version_available = $lang->sprintf($lang->new_version_available, "MyBB {$mybb->version}", "<a href=\"https://mybb.com/download\" target=\"_blank\" rel=\"noopener\">MyBB {$update_check['latest_version']}</a>");
		$page->output_error("<p><em>{$lang->new_version_available}</em></p>");
	}

	$plugins->run_hooks("admin_home_index_output_message");

	$adminmessage = $cache->read("adminnotes");

	$table = new Table;
	$table->construct_header($lang->mybb_server_stats, array("colspan" => 2));
	$table->construct_header($lang->forum_stats, array("colspan" => 2));

	$table->construct_cell("<strong>{$lang->mybb_version}</strong>", array('width' => '25%'));
	$table->construct_cell($mybb->version, array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->threads}</strong>", array('width' => '25%'));
	$table->construct_cell("<strong>{$threads}</strong> {$lang->threads}<br /><strong>{$newthreads}</strong> {$lang->new_today}<br /><a href=\"index.php?module=forum-moderation_queue&amp;type=threads\"><strong>{$unapproved_threads}</strong> {$lang->unapproved}</a>", array('width' => '25%'));
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->php_version}</strong>", array('width' => '25%'));
	$table->construct_cell(PHP_VERSION, array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->posts}</strong>", array('width' => '25%'));
	if($mybb->settings['reportmethod'] == "db")
	{
		$table->construct_cell("<strong>{$posts}</strong> {$lang->posts}<br /><strong>{$newposts}</strong> {$lang->new_today}<br /><a href=\"index.php?module=forum-moderation_queue&amp;type=posts\"><strong>{$unapproved_posts}</strong> {$lang->unapproved}</a><br /><strong>{$reported_posts}</strong> {$lang->reported_posts}<br /><strong>{$new_reported_posts}</strong> {$lang->unread_reports}", array('width' => '25%'));
	}
	else
	{
		$table->construct_cell("<strong>{$posts}</strong> {$lang->posts}<br /><strong>{$newposts}</strong> {$lang->new_today}<br /><a href=\"index.php?module=forum-moderation_queue&amp;type=posts\"><strong>{$unapproved_posts}</strong> {$lang->unapproved}</a><br /><strong>{$reported_posts}</strong> {$lang->reported_posts}", array('width' => '25%'));
	}
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->sql_engine}</strong>", array('width' => '25%'));
	$table->construct_cell($db->short_title." ".$db->get_version(), array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->users}</strong>", array('width' => '25%'));
	$table->construct_cell("<a href=\"index.php?module=user-users\"><strong>{$users}</strong> {$lang->registered_users}</a><br /><strong>{$activeusers}</strong> {$lang->active_users}<br /><strong>{$newusers}</strong> {$lang->registrations_today}<br /><a href=\"index.php?module=user-awaiting_activation\"><strong>{$awaitingusers}</strong> {$lang->awaiting_activation}</a>", array('width' => '25%'));
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->server_load}</strong>", array('width' => '25%'));
	$table->construct_cell($serverload, array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->attachments}</strong>", array('width' => '25%'));
	$table->construct_cell("<strong>{$approved_attachs}</strong> {$lang->attachments}<br /><a href=\"index.php?module=forum-moderation_queue&amp;type=attachments\"><strong>{$unapproved_attachs}</strong> {$lang->unapproved}</a><br /><strong>{$attachs['spaceused']}</strong> {$lang->used}", array('width' => '25%'));
	$table->construct_row();

	$table->output($lang->dashboard);

	echo '
	<div class="float_right" style="width: 48%;">';

	$table = new Table;
	$table->construct_header($lang->admin_notes_public);

	$form = new Form("index.php", "post");
	$table->construct_cell($form->generate_text_area("adminnotes", $adminmessage['adminmessage'], array('style' => 'width: 99%; height: 200px;')));
	$table->construct_row();

	$table->output($lang->admin_notes);

	$buttons[] = $form->generate_submit_button($lang->save_notes);
	$form->output_submit_wrapper($buttons);
	$form->end();

	echo '</div>
	<div class="float_left" style="width: 48%;">';

	// Latest news widget
	$table = new Table;
	$table->construct_header($lang->news_description);

	if(!empty($update_check['news']) && is_array($update_check['news']))
	{
		foreach($update_check['news'] as $news_item)
		{
			$posted = my_date('relative', $news_item['dateline']);
			$table->construct_cell("<strong><a href=\"{$news_item['link']}\" target=\"_blank\" rel=\"noopener\">{$news_item['title']}</a></strong><br /><span class=\"smalltext\">{$posted}</span>");
			$table->construct_row();

			$table->construct_cell($news_item['description']);
			$table->construct_row();
		}
	}
	else
	{
		$table->construct_cell($lang->no_announcements);
		$table->construct_row();
	}

	$table->output($lang->latest_mybb_announcements);
	echo '</div>';

	$page->output_footer();
}
