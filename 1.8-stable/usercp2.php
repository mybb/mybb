<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: usercp2.php 5297 2010-12-28 22:01:14Z Tomm $
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'usercp2.php');
define("ALLOWABLE_PAGE", "removesubscription,removesubscriptions");

$templatelist = 'usercp_nav_messenger,usercp_nav_changename,usercp_nav_profile,usercp_nav_misc,usercp_nav';

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_user.php";

if($mybb->user['uid'] == 0)
{
	error_no_permission();
}

// Verify incoming POST request
verify_post_check($mybb->input['my_post_key']);

$lang->load("usercp");

usercp_menu();

$server_http_referer = htmlentities($_SERVER['HTTP_REFERER']);

if($mybb->input['action'] == "do_addsubscription")
{
	if($mybb->input['type'] != "forum")
	{
		$thread = get_thread($mybb->input['tid']);
		if(!$thread['tid'])
		{
			error($lang->error_invalidthread);
		}
		$forumpermissions = forum_permissions($thread['fid']);
		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
		{
			error_no_permission();
		}
		add_subscribed_thread($thread['tid'], $mybb->input['notification']);
		if($mybb->input['referrer'])
		{
			$url = htmlspecialchars_uni(addslashes($mybb->input['referrer']));
		}
		else
		{
			$url = get_thread_link($thread['tid']);
		}
		redirect($url, $lang->redirect_subscriptionadded);
	}
}

if($mybb->input['action'] == "addsubscription")
{
	if($mybb->input['type'] == "forum")
	{
		$forum = get_forum($mybb->input['fid']);
		if(!$forum['fid'])
		{
			error($lang->error_invalidforum);
		}
		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
		{
			error_no_permission();
		}
		add_subscribed_forum($forum['fid']);
		if($server_http_referer)
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "index.php";
		}
		redirect($url, $lang->redirect_forumsubscriptionadded);
	}
	else
	{
		$thread  = get_thread($mybb->input['tid']);
		if(!$thread['tid'])
		{
			error($lang->error_invalidthread);
		}
		add_breadcrumb($lang->nav_subthreads, "usercp.php?action=subscriptions");
		add_breadcrumb($lang->nav_addsubscription);

		$forumpermissions = forum_permissions($thread['fid']);
		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
		{
			error_no_permission();
		}
		$referrer = '';
		if($server_http_referer)
		{
			$referrer = $server_http_referer;
		}

		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
		$thread['subject'] = $parser->parse_badwords($thread['subject']);
		$thread['subject'] = htmlspecialchars_uni($thread['subject']);
		$lang->subscribe_to_thread = $lang->sprintf($lang->subscribe_to_thread, $thread['subject']);

		if($mybb->user['subscriptionmethod'] == 1 || $mybb->user['subscriptionmethod'] == 0)
		{
			$notification_none_checked = "checked=\"checked\"";
		}
		else if($mybb->user['subscriptionmethod'] == 2)
		{
			$notification_instant_checked = "checked=\"checked\"";
		}
		eval("\$add_subscription = \"".$templates->get("usercp_addsubscription_thread")."\";");
		output_page($add_subscription);
	}
}
elseif($mybb->input['action'] == "removesubscription")
{
	if($mybb->input['type'] == "forum")
	{
		$forum = get_forum($mybb->input['fid']);
		if(!$forum['fid'])
		{
			error($lang->error_invalidforum);
		}
		remove_subscribed_forum($forum['fid']);
		if($server_http_referer)
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=forumsubscriptions";
		}
		redirect($url, $lang->redirect_forumsubscriptionremoved);
	}
	else
	{
		$thread = get_thread($mybb->input['tid']);
		if(!$thread['tid'])
		{
			error($lang->error_invalidthread);
		}
		remove_subscribed_thread($thread['tid']);
		if($server_http_referer)
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=subscriptions";
		}
		redirect($url, $lang->redirect_subscriptionremoved);
	}
}
elseif($mybb->input['action'] == "removesubscriptions")
{	
	if($mybb->input['type'] == "forum")
	{
		$db->delete_query("forumsubscriptions", "uid='".$mybb->user['uid']."'");
		if($server_http_referer)
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=forumsubscriptions";
		}
		redirect($url, $lang->redirect_forumsubscriptionsremoved);
	}
	else
	{
		$db->delete_query("threadsubscriptions", "uid='".$mybb->user['uid']."'");
		if($server_http_referer)
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=subscriptions";
		}
		redirect($url, $lang->redirect_subscriptionsremoved);
	}
}
else
{
	error($lang->error_invalidaction);
}
?>