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
define('THIS_SCRIPT', 'usercp2.php');
define("ALLOWABLE_PAGE", "removesubscription,removesubscriptions");

$templatelist = 'usercp_nav_messenger,usercp_nav_changename,usercp_nav_profile,usercp_nav_misc,usercp_nav,usercp_addsubscription_thread,usercp_nav_messenger_tracking,usercp_nav_editsignature,usercp_nav_attachments,usercp_nav_messenger_compose,usercp_nav_messenger_folder';

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_user.php";

if($mybb->user['uid'] == 0)
{
	error_no_permission();
}

// Verify incoming POST request
verify_post_check($mybb->get_input('my_post_key'));

$lang->load("usercp");

usercp_menu();

$server_http_referer = htmlentities($_SERVER['HTTP_REFERER']);

$plugins->run_hooks("usercp2_start");

if($mybb->get_input('action') == "do_addsubscription" && $mybb->get_input('type') != "forum")
{
	$thread = get_thread($mybb->get_input('tid'));
	if(!$thread)
	{
		error($lang->error_invalidthread);
	}

	// Is the currently logged in user a moderator of this forum?
	if(is_moderator($thread['fid']))
	{
		$ismod = true;
	}
	else
	{
		$ismod = false;
	}

	// Make sure we are looking at a real thread here.
	if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
	{
		error($lang->error_invalidthread);
	}

	$forumpermissions = forum_permissions($thread['fid']);
	if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
	{
		error_no_permission();
	}

	$plugins->run_hooks("usercp2_do_addsubscription");

	add_subscribed_thread($thread['tid'], $mybb->get_input('notification', MyBB::INPUT_INT));

	if($mybb->get_input('referrer'))
	{
		$url = htmlspecialchars_uni($mybb->get_input('referrer'));
	}
	else
	{
		$url = get_thread_link($thread['tid']);
	}
	redirect($url, $lang->redirect_subscriptionadded);
}
elseif($mybb->get_input('action') == "addsubscription")
{
	if($mybb->get_input('type') == "forum")
	{
		$forum = get_forum($mybb->get_input('fid', MyBB::INPUT_INT));
		if(!$forum)
		{
			error($lang->error_invalidforum);
		}
		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
		{
			error_no_permission();
		}

		$plugins->run_hooks("usercp2_addsubscription_forum");

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
		$thread  = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
		if(!$thread)
		{
			error($lang->error_invalidthread);
		}

		// Is the currently logged in user a moderator of this forum?
		if(is_moderator($thread['fid']))
		{
			$ismod = true;
		}
		else
		{
			$ismod = false;
		}

		// Make sure we are looking at a real thread here.
		if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
		{
			error($lang->error_invalidthread);
		}

		add_breadcrumb($lang->nav_subthreads, "usercp.php?action=subscriptions");
		add_breadcrumb($lang->nav_addsubscription);

		$forumpermissions = forum_permissions($thread['fid']);
		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
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

		$notification_none_checked = $notification_email_checked = $notification_pm_checked = '';
		if($mybb->user['subscriptionmethod'] == 1 || $mybb->user['subscriptionmethod'] == 0)
		{
			$notification_none_checked = "checked=\"checked\"";
		}
		else if($mybb->user['subscriptionmethod'] == 2)
		{
			$notification_email_checked = "checked=\"checked\"";
		}
		else if($mybb->user['subscriptionmethod'] == 3)
		{
			$notification_pm_checked = "checked=\"checked\"";
		}

		$plugins->run_hooks("usercp2_addsubscription_thread");

		eval("\$add_subscription = \"".$templates->get("usercp_addsubscription_thread")."\";");
		output_page($add_subscription);
		exit;
	}
}
elseif($mybb->get_input('action') == "removesubscription")
{
	if($mybb->get_input('type') == "forum")
	{
		$forum = get_forum($mybb->get_input('fid', MyBB::INPUT_INT));
		if(!$forum)
		{
			error($lang->error_invalidforum);
		}

		$plugins->run_hooks("usercp2_removesubscription_forum");

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
		$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
		if(!$thread)
		{
			error($lang->error_invalidthread);
		}

		// Is the currently logged in user a moderator of this forum?
		if(is_moderator($thread['fid']))
		{
			$ismod = true;
		}
		else
		{
			$ismod = false;
		}

		// Make sure we are looking at a real thread here.
		if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
		{
			error($lang->error_invalidthread);
		}

		$plugins->run_hooks("usercp2_removesubscription_thread");

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
elseif($mybb->get_input('action') == "removesubscriptions")
{
	if($mybb->get_input('type') == "forum")
	{
		$plugins->run_hooks("usercp2_removesubscriptions_forum");

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
		$plugins->run_hooks("usercp2_removesubscriptions_thread");

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

