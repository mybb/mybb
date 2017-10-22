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

$page->add_breadcrumb_item($lang->recount_rebuild, "index.php?module=tools-recount_rebuild");

$plugins->run_hooks("admin_tools_recount_rebuild");

/**
 * Rebuild forum counters
 */
function acp_rebuild_forum_counters()
{
	global $db, $mybb, $lang;

	$query = $db->simple_select("forums", "COUNT(*) as num_forums");
	$num_forums = $db->fetch_field($query, 'num_forums');

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$per_page = $mybb->get_input('forumcounters', MyBB::INPUT_INT);
	if($per_page <= 0)
	{
		$per_page = 50;
	}
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select("forums", "fid", '', array('order_by' => 'fid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($forum = $db->fetch_array($query))
	{
		$update['parentlist'] = make_parent_list($forum['fid']);
		$db->update_query("forums", $update, "fid='{$forum['fid']}'");
		rebuild_forum_counters($forum['fid']);
	}

	check_proceed($num_forums, $end, ++$page, $per_page, "forumcounters", "do_rebuildforumcounters", $lang->success_rebuilt_forum_counters);
}

/**
 * Rebuild thread counters
 */
function acp_rebuild_thread_counters()
{
	global $db, $mybb, $lang;

	$query = $db->simple_select("threads", "COUNT(*) as num_threads");
	$num_threads = $db->fetch_field($query, 'num_threads');

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$per_page = $mybb->get_input('threadcounters', MyBB::INPUT_INT);
	if($per_page <= 0)
	{
		$per_page = 500;
	}
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select("threads", "tid", '', array('order_by' => 'tid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($thread = $db->fetch_array($query))
	{
		rebuild_thread_counters($thread['tid']);
	}

	check_proceed($num_threads, $end, ++$page, $per_page, "threadcounters", "do_rebuildthreadcounters", $lang->success_rebuilt_thread_counters);
}

/**
 * Rebuild poll counters
 */
function acp_rebuild_poll_counters()
{
	global $db, $mybb, $lang;

	$query = $db->simple_select("polls", "COUNT(*) as num_polls");
	$num_polls = $db->fetch_field($query, 'num_polls');

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$per_page = $mybb->get_input('pollcounters', MyBB::INPUT_INT);
	if($per_page <= 0)
	{
		$per_page = 500;
	}
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select("polls", "pid", '', array('order_by' => 'pid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($poll = $db->fetch_array($query))
	{
		rebuild_poll_counters($poll['pid']);
	}

	check_proceed($num_polls, $end, ++$page, $per_page, "pollcounters", "do_rebuildpollcounters", $lang->success_rebuilt_poll_counters);
}

/**
 * Recount user posts
 */
function acp_recount_user_posts()
{
	global $db, $mybb, $lang;

	$query = $db->simple_select("users", "COUNT(uid) as num_users");
	$num_users = $db->fetch_field($query, 'num_users');

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$per_page = $mybb->get_input('userposts', MyBB::INPUT_INT);
	if($per_page <= 0)
	{
		$per_page = 500;
	}
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select("forums", "fid", "usepostcounts = 0");
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
		$fids = " AND p.fid NOT IN($fids)";
	}
	else
	{
		$fids = "";
	}

	$query = $db->simple_select("users", "uid", '', array('order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($user = $db->fetch_array($query))
	{
		$query2 = $db->query("
			SELECT COUNT(p.pid) AS post_count
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.uid='{$user['uid']}' AND t.visible > 0 AND p.visible > 0{$fids}
		");
		$num_posts = $db->fetch_field($query2, "post_count");

		$db->update_query("users", array("postnum" => (int)$num_posts), "uid='{$user['uid']}'");
	}

	check_proceed($num_users, $end, ++$page, $per_page, "userposts", "do_recountuserposts", $lang->success_rebuilt_user_post_counters);
}

/**
 * Recount user threads
 */
function acp_recount_user_threads()
{
	global $db, $mybb, $lang;

	$query = $db->simple_select("users", "COUNT(uid) as num_users");
	$num_users = $db->fetch_field($query, 'num_users');

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$per_page = $mybb->get_input('userthreads', MyBB::INPUT_INT);
	if($per_page <= 0)
	{
		$per_page = 500;
	}
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select("forums", "fid", "usethreadcounts = 0");
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
		$fids = " AND t.fid NOT IN($fids)";
	}
	else
	{
		$fids = "";
	}

	$query = $db->simple_select("users", "uid", '', array('order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($user = $db->fetch_array($query))
	{
		$query2 = $db->query("
			SELECT COUNT(t.tid) AS thread_count
			FROM ".TABLE_PREFIX."threads t
			WHERE t.uid='{$user['uid']}' AND t.visible > 0 AND t.closed NOT LIKE 'moved|%'{$fids}
		");
		$num_threads = $db->fetch_field($query2, "thread_count");

		$db->update_query("users", array("threadnum" => (int)$num_threads), "uid='{$user['uid']}'");
	}

	check_proceed($num_users, $end, ++$page, $per_page, "userthreads", "do_recountuserthreads", $lang->success_rebuilt_user_thread_counters);
}

/**
 * Recount reputation values
 */
function acp_recount_reputation()
{
	global $db, $mybb, $lang;

	$query = $db->simple_select("users", "COUNT(uid) as num_users");
	$num_users = $db->fetch_field($query, 'num_users');

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$per_page = $mybb->get_input('reputation', MyBB::INPUT_INT);
	if($per_page <= 0)
	{
		$per_page = 500;
	}
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select("users", "uid", '', array('order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($user = $db->fetch_array($query))
	{
		$query2 = $db->query("
			SELECT SUM(reputation) as total_rep
			FROM ".TABLE_PREFIX."reputation
			WHERE uid='{$user['uid']}'
		");
		$total_rep = $db->fetch_field($query2, "total_rep");

		$db->update_query("users", array("reputation" => (int)$total_rep), "uid='{$user['uid']}'");
	}

	check_proceed($num_users, $end, ++$page, $per_page, "reputation", "do_recountreputation", $lang->success_rebuilt_reputation);
}

/**
 * Recount warnings for users
 */
function acp_recount_warning()
{
	global $db, $mybb, $lang;

	$query = $db->simple_select("users", "COUNT(uid) as num_users");
	$num_users = $db->fetch_field($query, 'num_users');

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$per_page = $mybb->get_input('warning', MyBB::INPUT_INT);
	if($per_page <= 0)
	{
		$per_page = 500;
	}
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select("users", "uid", '', array('order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($user = $db->fetch_array($query))
	{
		$query2 = $db->query("
			SELECT SUM(points) as warn_lev
			FROM ".TABLE_PREFIX."warnings
			WHERE uid='{$user['uid']}' AND expired='0'
		");
		$warn_lev = $db->fetch_field($query2, "warn_lev");

		$db->update_query("users", array("warningpoints" => (int)$warn_lev), "uid='{$user['uid']}'");
	}

	check_proceed($num_users, $end, ++$page, $per_page, "warning", "do_recountwarning", $lang->success_rebuilt_warning);
}

/**
 * Recount private messages (total and unread) for users
 */
function acp_recount_private_messages()
{
	global $db, $mybb, $lang;

	$query = $db->simple_select("users", "COUNT(uid) as num_users");
	$num_users = $db->fetch_field($query, 'num_users');

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$per_page = $mybb->get_input('privatemessages', MyBB::INPUT_INT);
	if($per_page <= 0)
	{
		$per_page = 500;
	}
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	require_once MYBB_ROOT."inc/functions_user.php";

	$query = $db->simple_select("users", "uid", '', array('order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($user = $db->fetch_array($query))
	{
		update_pm_count($user['uid']);
	}

	check_proceed($num_users, $end, ++$page, $per_page, "privatemessages", "do_recountprivatemessages", $lang->success_rebuilt_private_messages);
}

/**
 * Recount referrals for users
 */
function acp_recount_referrals()
{
	global $db, $mybb, $lang;

	$query = $db->simple_select("users", "COUNT(uid) as num_users");
	$num_users = $db->fetch_field($query, 'num_users');

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$per_page = $mybb->get_input('referral', MyBB::INPUT_INT);
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select("users", "uid", '', array('order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($user = $db->fetch_array($query))
	{
		$query2 = $db->query("
			SELECT COUNT(uid) as num_referrers
			FROM ".TABLE_PREFIX."users
			WHERE referrer='{$user['uid']}'
		");
		$num_referrers = $db->fetch_field($query2, "num_referrers");

		$db->update_query("users", array("referrals" => (int)$num_referrers), "uid='{$user['uid']}'");
	}

	check_proceed($num_users, $end, ++$page, $per_page, "referral", "do_recountreferral", $lang->success_rebuilt_referral);
}

/**
 * Recount thread ratings
 */
function acp_recount_thread_ratings()
{
	global $db, $mybb, $lang;

	$query = $db->simple_select("threads", "COUNT(*) as num_threads");
	$num_threads = $db->fetch_field($query, 'num_threads');

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$per_page = $mybb->get_input('threadrating', MyBB::INPUT_INT);
	if($per_page <= 0)
	{
		$per_page = 500;
	}
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select("threads", "tid", '', array('order_by' => 'tid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($thread = $db->fetch_array($query))
	{
		$query2 = $db->query("
			SELECT COUNT(tid) as num_ratings, SUM(rating) as total_rating
			FROM ".TABLE_PREFIX."threadratings
			WHERE tid='{$thread['tid']}'
		");
		$recount = $db->fetch_array($query2);

		$db->update_query("threads", array("numratings" => (int)$recount['num_ratings'], "totalratings" => (int)$recount['total_rating']), "tid='{$thread['tid']}'");
	}

	check_proceed($num_threads, $end, ++$page, $per_page, "threadrating", "do_recountthreadrating", $lang->success_rebuilt_thread_ratings);
}

/**
 * Rebuild thumbnails for attachments
 */
function acp_rebuild_attachment_thumbnails()
{
	global $db, $mybb, $lang;

	$query = $db->simple_select("attachments", "COUNT(aid) as num_attachments");
	$num_attachments = $db->fetch_field($query, 'num_attachments');

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$per_page = $mybb->get_input('attachmentthumbs', MyBB::INPUT_INT);
	if($per_page <= 0)
	{
		$per_page = 20;
	}
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$uploadspath = $mybb->settings['uploadspath'];
	if(my_substr($uploadspath, 0, 1) == '.')
	{
		$uploadspath = MYBB_ROOT . $mybb->settings['uploadspath'];
	}

	require_once MYBB_ROOT."inc/functions_image.php";

	$query = $db->simple_select("attachments", "*", '', array('order_by' => 'aid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($attachment = $db->fetch_array($query))
	{
		$ext = my_strtolower(my_substr(strrchr($attachment['filename'], "."), 1));
		if($ext == "gif" || $ext == "png" || $ext == "jpg" || $ext == "jpeg" || $ext == "jpe")
		{
			$thumbname = str_replace(".attach", "_thumb.$ext", $attachment['attachname']);
			$thumbnail = generate_thumbnail($uploadspath."/".$attachment['attachname'], $uploadspath, $thumbname, $mybb->settings['attachthumbh'], $mybb->settings['attachthumbw']);
			if($thumbnail['code'] == 4)
			{
				$thumbnail['filename'] = "SMALL";
			}
			$db->update_query("attachments", array("thumbnail" => $thumbnail['filename']), "aid='{$attachment['aid']}'");
		}
	}

	check_proceed($num_attachments, $end, ++$page, $per_page, "attachmentthumbs", "do_rebuildattachmentthumbs", $lang->success_rebuilt_attachment_thumbnails);
}

/**
 * @param int $current
 * @param int $finish
 * @param int $next_page
 * @param int $per_page
 * @param string $name
 * @param string $name2
 * @param string $message
 */
function check_proceed($current, $finish, $next_page, $per_page, $name, $name2, $message)
{
	global $page, $lang;

	if($finish >= $current)
	{
		flash_message($message, 'success');
		admin_redirect("index.php?module=tools-recount_rebuild");
	}
	else
	{
		$page->output_header();

		$form = new Form("index.php?module=tools-recount_rebuild", 'post');

		echo $form->generate_hidden_field("page", $next_page);
		echo $form->generate_hidden_field($name, $per_page);
		echo $form->generate_hidden_field($name2, $lang->go);
		echo "<div class=\"confirm_action\">\n";
		echo "<p>{$lang->confirm_proceed_rebuild}</p>\n";
		echo "<br />\n";
		echo "<script type=\"text/javascript\">$(function() { var button = $(\"#proceed_button\"); if(button.length > 0) { button.val(\"{$lang->automatically_redirecting}\"); button.attr(\"disabled\", true); button.css(\"color\", \"#aaa\"); button.css(\"borderColor\", \"#aaa\"); document.forms[0].submit(); }})</script>";
		echo "<p class=\"buttons\">\n";
		echo $form->generate_submit_button($lang->proceed, array('class' => 'button_yes', 'id' => 'proceed_button'));
		echo "</p>\n";
		echo "</div>\n";

		$form->end();

		$page->output_footer();
		exit;
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_tools_recount_rebuild_start");

	if($mybb->request_method == "post")
	{
		require_once MYBB_ROOT."inc/functions_rebuild.php";

		if(!isset($mybb->input['page']) || $mybb->get_input('page', MyBB::INPUT_INT) < 1)
		{
			$mybb->input['page'] = 1;
		}

		if(isset($mybb->input['do_rebuildforumcounters']))
		{
			$plugins->run_hooks("admin_tools_recount_rebuild_forum_counters");

			if($mybb->input['page'] == 1)
			{
				// Log admin action
				log_admin_action("forum");
			}
			if(!$mybb->get_input('forumcounters', MyBB::INPUT_INT))
			{
				$mybb->input['forumcounters'] = 50;
			}

			acp_rebuild_forum_counters();
		}
		elseif(isset($mybb->input['do_rebuildthreadcounters']))
		{
			$plugins->run_hooks("admin_tools_recount_rebuild_thread_counters");

			if($mybb->input['page'] == 1)
			{
				// Log admin action
				log_admin_action("thread");
			}
			if(!$mybb->get_input('threadcounters', MyBB::INPUT_INT))
			{
				$mybb->input['threadcounters'] = 500;
			}

			acp_rebuild_thread_counters();
		}
		elseif(isset($mybb->input['do_recountuserposts']))
		{
			$plugins->run_hooks("admin_tools_recount_rebuild_user_posts");

			if($mybb->input['page'] == 1)
			{
				// Log admin action
				log_admin_action("userposts");
			}
			if(!$mybb->get_input('userposts', MyBB::INPUT_INT))
			{
				$mybb->input['userposts'] = 500;
			}

			acp_recount_user_posts();
		}
		elseif(isset($mybb->input['do_recountuserthreads']))
		{
			$plugins->run_hooks("admin_tools_recount_rebuild_user_threads");

			if($mybb->input['page'] == 1)
			{
				// Log admin action
				log_admin_action("userthreads");
			}
			if(!$mybb->get_input('userthreads', MyBB::INPUT_INT))
			{
				$mybb->input['userthreads'] = 500;
			}

			acp_recount_user_threads();
		}
		elseif(isset($mybb->input['do_rebuildattachmentthumbs']))
		{
			$plugins->run_hooks("admin_tools_recount_rebuild_attachment_thumbs");

			if($mybb->input['page'] == 1)
			{
				// Log admin action
				log_admin_action("attachmentthumbs");
			}

			if(!$mybb->get_input('attachmentthumbs', MyBB::INPUT_INT))
			{
				$mybb->input['attachmentthumbs'] = 500;
			}

			acp_rebuild_attachment_thumbnails();
		}
		elseif(isset($mybb->input['do_recountreputation']))
		{
			$plugins->run_hooks("admin_tools_recount_recount_reputation");

			if($mybb->input['page'] == 1)
			{
				// Log admin action
				log_admin_action("reputation");
			}

			if(!$mybb->get_input('reputation', MyBB::INPUT_INT))
			{
				$mybb->input['reputation'] = 500;
			}

			acp_recount_reputation();
		}
		elseif(isset($mybb->input['do_recountwarning']))
		{
			$plugins->run_hooks("admin_tools_recount_recount_warning");

			if($mybb->input['page'] == 1)
			{
				// Log admin action
				log_admin_action("warning");
			}

			if(!$mybb->get_input('warning', MyBB::INPUT_INT))
			{
				$mybb->input['warning'] = 500;
			}

			acp_recount_warning();
		}
		elseif(isset($mybb->input['do_recountprivatemessages']))
		{
			$plugins->run_hooks("admin_tools_recount_recount_private_messages");

			if($mybb->input['page'] == 1)
			{
				// Log admin action
				log_admin_action("privatemessages");
			}

			if(!$mybb->get_input('privatemessages', MyBB::INPUT_INT))
			{
				$mybb->input['privatemessages'] = 500;
			}

			acp_recount_private_messages();
		}
		elseif(isset($mybb->input['do_recountreferral']))
		{
			$plugins->run_hooks("admin_tools_recount_recount_referral");

			if($mybb->input['page'] == 1)
			{
				// Log admin action
				log_admin_action("referral");
			}

			if(!$mybb->get_input('referral', MyBB::INPUT_INT))
			{
				$mybb->input['referral'] = 500;
			}

			acp_recount_referrals();
		}
		elseif(isset($mybb->input['do_recountthreadrating']))
		{
			$plugins->run_hooks("admin_tools_recount_recount_thread_ratings");

			if($mybb->input['page'] == 1)
			{
				// Log admin action
				log_admin_action("threadrating");
			}

			if(!$mybb->get_input('threadrating', MyBB::INPUT_INT))
			{
				$mybb->input['threadrating'] = 500;
			}

			acp_recount_thread_ratings();
		}
		elseif(isset($mybb->input['do_rebuildpollcounters']))
		{
			$plugins->run_hooks("admin_tools_recount_rebuild_poll_counters");

			if($mybb->input['page'] == 1)
			{
				// Log admin action
				log_admin_action("poll");
			}

			if(!$mybb->get_input('pollcounters', MyBB::INPUT_INT))
			{
				$mybb->input['pollcounters'] = 500;
			}

			acp_rebuild_poll_counters();
		}
		else
		{
			$plugins->run_hooks("admin_tools_recount_rebuild_stats");

			$cache->update_stats();

			// Log admin action
			log_admin_action("stats");

			flash_message($lang->success_rebuilt_forum_stats, 'success');
			admin_redirect("index.php?module=tools-recount_rebuild");
		}
	}

	$page->output_header($lang->recount_rebuild);

	$sub_tabs['recount_rebuild'] = array(
		'title' => $lang->recount_rebuild,
		'link' => "index.php?module=tools-recount_rebuild",
		'description' => $lang->recount_rebuild_desc
	);

	$page->output_nav_tabs($sub_tabs, 'recount_rebuild');

	$form = new Form("index.php?module=tools-recount_rebuild", "post");

	$form_container = new FormContainer($lang->recount_rebuild);
	$form_container->output_row_header($lang->name);
	$form_container->output_row_header($lang->data_per_page, array('width' => 50));
	$form_container->output_row_header("&nbsp;");

	$form_container->output_cell("<label>{$lang->rebuild_forum_counters}</label><div class=\"description\">{$lang->rebuild_forum_counters_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field("forumcounters", 50, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_rebuildforumcounters")));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->rebuild_thread_counters}</label><div class=\"description\">{$lang->rebuild_thread_counters_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field("threadcounters", 500, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_rebuildthreadcounters")));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->rebuild_poll_counters}</label><div class=\"description\">{$lang->rebuild_poll_counters_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field("pollcounters", 500, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_rebuildpollcounters")));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->recount_user_posts}</label><div class=\"description\">{$lang->recount_user_posts_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field("userposts", 500, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_recountuserposts")));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->recount_user_threads}</label><div class=\"description\">{$lang->recount_user_threads_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field("userthreads", 500, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_recountuserthreads")));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->rebuild_attachment_thumbs}</label><div class=\"description\">{$lang->rebuild_attachment_thumbs_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field("attachmentthumbs", 20, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_rebuildattachmentthumbs")));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->recount_stats}</label><div class=\"description\">{$lang->recount_stats_desc}</div>");
	$form_container->output_cell($lang->na);
	$form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_recountstats")));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->recount_reputation}</label><div class=\"description\">{$lang->recount_reputation_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field("reputation", 500, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_recountreputation")));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->recount_warning}</label><div class=\"description\">{$lang->recount_warning_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field("warning", 500, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_recountwarning")));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->recount_private_messages}</label><div class=\"description\">{$lang->recount_private_messages_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field("privatemessages", 500, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_recountprivatemessages")));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->recount_referrals}</label><div class=\"description\">{$lang->recount_referrals_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field("referral", 500, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_recountreferral")));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->recount_thread_ratings}</label><div class=\"description\">{$lang->recount_thread_ratings_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field("threadrating", 500, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array("name" => "do_recountthreadrating")));
	$form_container->construct_row();

	$plugins->run_hooks("admin_tools_recount_rebuild_output_list");

	$form_container->end();

	$form->end();

	$page->output_footer();
}

