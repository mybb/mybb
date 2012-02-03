<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: index.php 5548 2011-08-08 17:30:31Z PirataNervo $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->run_hooks("admin_home_index_begin");

if(!$mybb->input['action'])
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
	$query = $db->simple_select("users", "COUNT(uid) AS awaitingusers", "usergroup='5'");
	$awaitingusers = my_number_format($db->fetch_field($query, "awaitingusers"));

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
		$status['numunapprovedposts'] = 0;
	}
	
	$unapproved_posts = my_number_format($stats['numunapprovedposts']);

	// Get the number of new posts for today
	$query = $db->simple_select("posts", "COUNT(*) AS newposts", "dateline > '$timecut' AND visible='1'");
	$newposts = my_number_format($db->fetch_field($query, "newposts"));

	// Get the number and total file size of attachments
	$query = $db->simple_select("attachments", "COUNT(*) AS numattachs, SUM(filesize) as spaceused", "visible='1' AND pid > '0'");
	$attachs = $db->fetch_array($query);
	$attachs['spaceused'] = get_friendly_size($attachs['spaceused']);

	// Get the number of unapproved attachments
	$query = $db->simple_select("attachments", "COUNT(*) AS numattachs", "visible='0' AND pid > '0'");
	$unapproved_attachs = my_number_format($db->fetch_field($query, "numattachs"));

	// Fetch the last time an update check was run
	$update_check = $cache->read("update_check");

	// If last update check was greater than two weeks ago (14 days) show an alert
	if($update_check['last_check'] <= TIME_NOW-60*60*24*14)
	{
		$lang->last_update_check_two_weeks = $lang->sprintf($lang->last_update_check_two_weeks, "index.php?module=home-version_check");
		$page->output_error("<p>{$lang->last_update_check_two_weeks}</p>");
	}

	// If the update check contains information about a newer version, show an alert
	if($update_check['latest_version_code'] > $mybb->version_code)
	{
		$lang->new_version_available = $lang->sprintf($lang->new_version_available, "MyBB {$mybb->version}", "<a href=\"http://mybb.com/downloads\" target=\"_blank\">MyBB {$update_check['latest_version']}</a>");
		$page->output_error("<p><em>{$lang->new_version_available}</em></p>");
	}
	
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
	$table->construct_cell("<strong>{$posts}</strong> {$lang->posts}<br /><strong>{$newposts}</strong> {$lang->new_today}<br /><a href=\"index.php?module=forum-moderation_queue&amp;type=posts\"><strong>{$unapproved_posts}</strong> {$lang->unapproved}</a>", array('width' => '25%'));
	$table->construct_row();
	
	$table->construct_cell("<strong>{$lang->sql_engine}</strong>", array('width' => '25%'));
	$table->construct_cell($db->short_title." ".$db->get_version(), array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->users}</strong>", array('width' => '25%'));
	$table->construct_cell("<a href=\"index.php?module=user-users\"><strong>{$users}</strong> {$lang->registered_users}</a><br /><strong>{$activeusers}</strong> {$lang->active_users}<br /><strong>{$newusers}</strong> {$lang->registrations_today}<br /><a href=\"index.php?module=user-users&amp;action=search&amp;results=1&amp;conditions=".urlencode(serialize(array('usergroup' => '5')))."&amp;from=home\"><strong>{$awaitingusers}</strong> {$lang->awaiting_activation}</a>", array('width' => '25%'));
	$table->construct_row();
	
	$table->construct_cell("<strong>{$lang->server_load}</strong>", array('width' => '25%'));
	$table->construct_cell($serverload, array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->attachments}</strong>", array('width' => '25%'));
	$table->construct_cell("<strong>{$attachs['numattachs']}</strong> {$lang->attachments}<br /><a href=\"index.php?module=forum-moderation_queue&amp;type=attachments\"><strong>{$unapproved_attachs}</strong> {$lang->unapproved}</a><br /><strong>{$attachs['spaceused']}</strong> {$lang->used}", array('width' => '25%'));
	$table->construct_row();
	
	$table->output($lang->dashboard);
	
	$table->construct_header($lang->admin_notes_public);
	
	$form = new Form("index.php", "post");
	$table->construct_cell($form->generate_text_area("adminnotes", $adminmessage['adminmessage'], array('style' => 'width: 99%; height: 200px;')));
	$table->construct_row();
	
	$table->output($lang->admin_notes);	
	
	$buttons[] = $form->generate_submit_button($lang->save_notes);
	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	$page->output_footer();
}
?>