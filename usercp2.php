<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

define("IN_MYBB", 1);

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_user.php";

if($mybb->user['uid'] == 0)
{
	error_no_permission();
}

$lang->load("usercp");

$server_http_referer = htmlentities($_SERVER['HTTP_REFERER']);

if($mybb->input['action'] == "addfavorite")
{
	$thread  = get_thread($mybb->input['tid']);
	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
	$forumpermissions = forum_permissions($thread['fid']);
	if($forumpermissions['canview'] == "no" || $forumpermissions['canviewthreads'] == "no")
	{
		error_no_permission();
	}
	add_favorite_thread($thread['tid']);
	if($server_http_referer)
	{
		$url = $server_http_referer;
	}
	else
	{
		$url = "showthread.php?tid=".$thread['tid'];
	}
	redirect($url, $lang->redirect_favoriteadded);
}
elseif($mybb->input['action'] == "removefavorite")
{
	$thread  = get_thread($mybb->input['tid']);
	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
	remove_favorite_thread($thread['tid']);
	if($server_http_referer)
	{
		$url = $server_http_referer;
	}
	else
	{
		$url = "usercp.php?action=favorites";
	}
	redirect($url, $lang->redirect_favoriteremoved);
}
elseif($mybb->input['action'] == "addsubscription")
{
	if($mybb->input['type'] == "forum")
	{
		$forum = get_forum($mybb->input['fid']);
		if(!$forum['fid'])
		{
			error($lang->error_invalidforum);
		}
		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] == "no" || $forumpermissions['canviewthreads'] == "no")
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
		$forumpermissions = forum_permissions($thread['fid']);
		if($forumpermissions['canview'] == "no" || $forumpermissions['canviewthreads'] == "no")
		{
			error_no_permission();
		}
		add_subscribed_thread($thread['tid']);
		if($server_http_referer)
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "showthread.php?tid=".$thread['tid'];
		}
		redirect($url, $lang->redirect_subscriptionadded);
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
		$thread  = get_thread($mybb->input['tid']);
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
		$db->delete_query("favorites", "type='s' AND uid='".$mybb->user['uid']."'");
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
elseif($mybb->input['action'] == "removefavorites")
{
	$db->delete_query("favorites", "type='f' AND uid='".$mybb->user['uid']."'");
	if($server_http_referer)
	{
		$url = $server_http_referer;
	}
	else
	{
		$url = "usercp.php?action=favorites";
	}
	redirect($url, $lang->redirect_favoritesremoved);
}
else
{
	error($lang->error_invalidaction);
}
?>