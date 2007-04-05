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

$page->add_breadcrumb_item("Recount &amp; Rebuild", "index.php?".SID."&amp;module=tools/recount_rebuild");

function acp_rebuild_forum_counters()
{	
	global $db, $mybb;
	
	$query = $db->simple_select("forums", "COUNT(*) as num_forums");
	$num_forums = $db->fetch_field($query, 'num_forums');
	
	$page = intval($mybb->input['page']);
	$per_page = intval($mybb->input['forumcounters']);
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select("forums", "fid", '', array('order_by' => 'fid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($forum = $db->fetch_array($query))
	{
		$update['parentlist'] = make_parent_list($forum['fid']);
		$db->update_query("forums", $update, "fid='{$forum['fid']}'");
		rebuild_forum_counters($forum['fid']);
	}
	
	check_proceed($num_forums, $end, ++$page, $per_page, "forumcounters", "do_rebuildforumcounters", "The forum counters have successfully been rebuilt.");
}

function acp_rebuild_thread_counters()
{	
	global $db, $mybb;
	
	$query = $db->simple_select("threads", "COUNT(*) as num_threads");
	$num_threads = $db->fetch_field($query, 'num_threads');
	
	$page = intval($mybb->input['page']);
	$per_page = intval($mybb->input['threadcounters']);
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select("threads", "tid", '', array('order_by' => 'tid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($thread = $db->fetch_array($query))
	{
		rebuild_thread_counters($thread['tid']);
	}
	
	check_proceed($num_threads, $end, ++$page, $per_page, "threadcounters", "do_rebuildthreadcounters", "The thread counters have successfully been rebuilt.");
}

function acp_recount_user_posts()
{
	global $db, $mybb;
	
	$query = $db->simple_select("users", "COUNT(uid) as num_users");
	$num_users = $db->fetch_field($query, 'num_users');
	
	$page = intval($mybb->input['page']);
	$per_page = intval($mybb->input['userposts']);
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;
	
	$query = $db->simple_select("forums", "fid", "usepostcounts = 'no'");
	while($forum = $db->fetch_array($query))
	{
		$fids[] = $forum['fid'];
	}
	if(is_array($fids))
    {
        $fids = implode(',', $fids);
    }
	if($fids)
	{
		$fids = " AND FID NOT IN($fids)";
	}
	else
	{
		$fids = "";
	}
	
	$query = $db->simple_select("users", "uid", '', array('order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	
	while($user = $db->fetch_array($query))
	{
		$query2 = $db->simple_select("posts", "COUNT(pid) AS post_count", "uid='{$user['uid']}' AND visible > 0{$fids}");
		$num_posts = $db->fetch_field($query2, "post_count");
		$db->update_query("users", array("postnum" => intval($num_posts)), "uid='{$user['uid']}'");
	}
	
	check_proceed($num_users, $end, ++$page, $per_page, "userposts", "do_recountuserposts", "The user posts count have successfully been recounted.");
}

function acp_rebuild_attachment_thumbnails()
{
	global $db, $mybb;
	
	$query = $db->simple_select("attachments", "COUNT(aid) as num_attachments");
	$num_attachments = $db->fetch_field($query, 'num_attachments');
	
	$page = intval($mybb->input['page']);
	$per_page = intval($mybb->input['attachmentthumbs']);
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	require_once MYBB_ROOT."inc/functions_image.php";
	
	$query = $db->simple_select("attachments", "*", '', array('order_by' => 'aid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($attachment = $db->fetch_array($query))
	{
		$ext = my_strtolower(my_substr(strrchr($attachment['filename'], "."), 1));
		if($ext == "gif" || $ext == "png" || $ext == "jpg" || $ext == "jpeg" || $ext == "jpe")
		{
			$thumbname = str_replace(".attach", "_thumb.$ext", $attachment['attachname']);
			$thumbnail = generate_thumbnail(MYBB_ROOT."uploads/".$attachment['attachname'], MYBB_ROOT."uploads", $thumbname, $mybb->settings['attachthumbh'], $mybb->settings['attachthumbw']);
			if($thumbnail['code'] == 4)
			{
				$thumbnail['filename'] = "SMALL";
			}
			$db->update_query("attachments", array("thumbnail" => $thumbnail['filename']), "aid='{$attachment['aid']}'");
		}
	}
	
	check_proceed($num_users, $end, ++$page, $per_page, "attachmentthumbs", "do_rebuildattachmentthumbs", "The attachment thumbnails have successfully been rebuilt.");
}

function check_proceed($current, $finish, $next_page, $per_page, $name, $name2, $message)
{
	global $page;
	
	if($finish >= $current)
	{
		flash_message($message, 'success');
		admin_redirect("index.php?".SID."&module=tools/recount_rebuild");
	}
	else
	{
		$page->output_header();
		
		$form = new Form("index.php?".SID."&amp;module=tools/recount_rebuild", 'post');
		
		echo $form->generate_hidden_field("page", $next_page);
		echo $form->generate_hidden_field($name, $per_page);
		echo $form->generate_hidden_field($name2, "Go");
		echo "<div class=\"confirm_action\">\n";
		echo "<p>Click \"Proceed\" to continue the recount and rebuild process.</p>\n";
		echo "<br />\n";
		echo "<p class=\"buttons\">\n";
		echo $form->generate_submit_button("Proceed", array('class' => 'button_yes'));
		echo "</p>\n";
		echo "</div>\n";
		
		$form->end();
		
		$page->output_footer();
		exit;
	}
}

if(!$mybb->input['action'])
{
	if($mybb->request_method == "post")
	{
		require_once MYBB_ROOT."inc/functions_rebuild.php";
		
		if(!isset($mybb->input['page']) || intval($mybb->input['page']) < 1)
		{
			$mybb->input['page'] = 1;
		}
		
		if(isset($mybb->input['do_rebuildforumcounters']))
		{
			if(!intval($mybb->input['forumcounters']))
			{
				$mybb->input['forumcounters'] = 50;
			}
			
			acp_rebuild_forum_counters();
		}
		elseif(isset($mybb->input['do_rebuildthreadcounters']))
		{
			if(!intval($mybb->input['threadcounters']))
			{
				$mybb->input['threadcounters'] = 500;
			}
			
			acp_rebuild_thread_counters();
		}
		elseif(isset($mybb->input['do_recountuserposts']))
		{
			if(!intval($mybb->input['userposts']))
			{
				$mybb->input['userposts'] = 500;
			}
			
			acp_recount_user_posts();
		}
		elseif(isset($mybb->input['do_rebuildattachmentthumbs']))
		{
			if(!intval($mybb->input['attachmentthumbs']))
			{
				$mybb->input['attachmentthumbs'] = 500;
			}
			
			acp_rebuild_attachment_thumbnails();
		}
		else
		{
			$cache->update_stats();
			
			flash_message("The forum statistics have successfully been rebuilt.", 'success');
			admin_redirect("index.php?".SID."&module=tools/recount_rebuild");
		}
	}
	
	
	$page->output_header("Recount and Rebuild");
	
	$sub_tabs['recount_rebuild'] = array(
		'title' => "Recount &amp; Rebuild",
		'link' => "index.php?".SID."&amp;module=tools/recount_rebuild",
		'description' => 'Here you can recount &amp; rebuild data to fix any synchronization errors in your forum.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'recount_rebuild');

	$form = new Form("index.php?".SID."&amp;module=tools/recount_rebuild", "post");
	
	$form_container = new FormContainer("Recount and Rebuild");
	$form_container->output_row_header("Name");
	$form_container->output_row_header("Data Entries Per Page", array('width' => 50));
	$form_container->output_row_header("&nbsp;");
	
	$form_container->output_cell("<label>Recount Statistics</label><div class=\"description\">This will recount and update your forum statistics on the forum index and statistics pages.</div>");
	$form_container->output_cell("N/A");
	$form_container->output_cell($form->generate_submit_button("Go", array("name" => "do_recountstats")));
	$form_container->construct_row();
	
	$form_container->output_cell("<label>Rebuild Forum Counters</label><div class=\"description\">When this is run, the post/thread counters and last post of each forum will be updated to reflect the correct values.</div>");
	$form_container->output_cell($form->generate_text_box("forumcounters", 50, array('style' => 'width: 150px;')));
	$form_container->output_cell($form->generate_submit_button("Go", array("name" => "do_rebuildforumcounters")));
	$form_container->construct_row();
	
	$form_container->output_cell("<label>Rebuild Thread Counters</label><div class=\"description\">When this is run, the post/view counters and last post of each thread will be updated to reflect the correct values.</div>");
	$form_container->output_cell($form->generate_text_box("threadcounters", 500, array('style' => 'width: 150px;')));
	$form_container->output_cell($form->generate_submit_button("Go", array("name" => "do_rebuildthreadcounters")));
	$form_container->construct_row();
	
	$form_container->output_cell("<label>Recount User Post Counts</label><div class=\"description\">When this is run, the post count for each user will be updated to reflect its current live value based on the posts in the database, and forums that have post count disabled.</div>");
	$form_container->output_cell($form->generate_text_box("userposts", 500, array('style' => 'width: 150px;')));
	$form_container->output_cell($form->generate_submit_button("Go", array("name" => "do_recountuserposts")));
	$form_container->construct_row();
	
	$form_container->output_cell("<label>Rebuild Attachment Thumbnails</label><div class=\"description\">This will rebuild attachment thumbnails to ensure they're using the current width and height dimensions and will also rebuild missing thumbnails.</div>");
	$form_container->output_cell($form->generate_text_box("attachmentthumbs", 20, array('style' => 'width: 150px;')));
	$form_container->output_cell($form->generate_submit_button("Go", array("name" => "do_rebuildattachmentthumbs")));
	$form_container->construct_row();
	
	$form_container->end();

	$form->end();
		
	$page->output_footer();
}

?>