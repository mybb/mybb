<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/* This file does all the misc operations of usercp.php */
require "./global.php";
require "./inc/functions_user.php";

if($mybb->user['uid'] == 0)
{
	nopermission();
}

if($mybb->input['action'] == "addfavorite")
{
	$query = $db->query("SELECT tid,fid FROM ".TABLE_PREFIX."threads WHERE tid='".intval($mybb->input['tid'])."'");
	$thread = $db->fetch_array($query);
	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
	$forumpermissions = forum_permissions($thread['fid']);
	if($forumpermissions['canview'] == "no" || $forumpermissions['canviewthreads'] == "no")
	{
		nopermission();
	}
	add_favorite_thread($thread['tid']);
	if($_SERVER['HTTP_REFERER'])
	{
		$url = addslashes($_SERVER['HTTP_REFERER']);
	}
	else
	{
		$url = "showthread.php?tid=".$thread['tid'];
	}
	redirect($url, $lang->redirect_favoriteadded);
}
elseif($mybb->input['action'] == "removefavorite")
{
	$query = $db->query("SELECT tid,fid FROM ".TABLE_PREFIX."threads WHERE tid='".intval($mybb->input['tid'])."'");
	$thread = $db->fetch_array($query);
	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
	remove_favorite_thread($thread['tid']);
	if($_SERVER['HTTP_REFERER'])
	{
		$url = addslashes($_SERVER['HTTP_REFERER']);
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
		$query = $db->query("SELECT fid FROM ".TABLE_PREFIX."forums WHERE fid='".intval($mybb->input['fid'])."'");
		$forum = $db->fetch_array($query);
		if(!$forum['fid'])
		{
			error($lang->error_invalidforum);
		}
		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] == "no" || $forumpermissions['canviewthreads'] == "no")
		{
			nopermission();
		}
		add_subscribed_forum($forum['fid']);
		if($_SERVER['HTTP_REFERER'])
		{
			$url = addslashes($_SERVER['HTTP_REFERER']);
		}
		else
		{
			$url = "index.php";
		}
		redirect($url, $lang->redirect_forumsubscriptionadded);
	}
	else
	{
		$query = $db->query("SELECT tid, fid FROM ".TABLE_PREFIX."threads WHERE tid='".intval($mybb->input['tid'])."'");
		$thread = $db->fetch_array($query);
		if(!$thread['tid'])
		{
			error($lang->error_invalidthread);
		}
		$forumpermissions = forum_permissions($thread['fid']);
		if($forumpermissions['canview'] == "no" || $forumpermissions['canviewthreads'] == "no")
		{
			nopermission();
		}
		add_subscribed_thread($thread['tid']);
		if($_SERVER['HTTP_REFERER'])
		{
			$url = addslashes($_SERVER['HTTP_REFERER']);
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
		$query = $db->query("SELECT fid FROM ".TABLE_PREFIX."forumsubscriptions WHERE fid='".intval($mybb->input['fid'])."' AND uid='".$mybb->user['uid']."'");
		$forum = $db->fetch_array($query);
		if(!$forum['fid'])
		{
			error($lang->error_invalidforum);
		}
		remove_subscribed_forum($forum['fid']);
		if($_SERVER['HTTP_REFERER'])
		{
			$url = addslashes($_SERVER['HTTP_REFERER']);
		}
		else
		{
			$url = "usercp.php?action=forumsubscriptions";
		}
		redirect($url, $lang->redirect_forumsubscriptionremoved);
	}
	else
	{
		$query = $db->query("SELECT tid FROM ".TABLE_PREFIX."favorites WHERE tid='".intval($mybb->input['tid'])."' AND type='s' AND uid='".$mybb->user['uid']."'");
		$thread = $db->fetch_array($query);
		if(!$thread['tid'])
		{
			error($lang->error_invalidthread);
		}
		remove_subscribed_thread($thread['tid']);
		if($_SERVER['HTTP_REFERER'])
		{
			$url = addslashes($_SERVER['HTTP_REFERER']);
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
		$db->query("DELETE FROM ".TABLE_PREFIX."forumsubscriptions WHERE uid='".$mybb->user[uid]."'");
		if($_SERVER['HTTP_REFERER'])
		{
			$url = addslashes($_SERVER['HTTP_REFERER']);
		}
		else
		{
			$url = "usercp.php?action=forumsubscriptions";
		}
		redirect($url, $lang->redirect_forumsubscriptionsremoved);
	}
	else
	{
		$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE type='s' AND uid='".$mybb->user[uid]."'");
		if($_SERVER['HTTP_REFERER'])
		{
			$url = addslashes($_SERVER['HTTP_REFERER']);
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
	$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE type='f' AND uid='".$mybb->user[uid]."'");
	if($_SERVER['HTTP_REFERER'])
	{
		$url = addslashes($_SERVER['HTTP_REFERER']);
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