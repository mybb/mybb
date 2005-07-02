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

if($mybb->user['uid'] == 0) {
	nopermission();
}

if($action == "addfavorite") {
	$query = $db->query("SELECT tid,fid FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
	$thread = $db->fetch_array($query);
	if(!$thread['tid']) {
		error($lang->error_invalidthread);
	}
	$forumpermissions = forum_permissions($thread['fid']);
	if($forumpermissions['canview'] == "no" || $forumpermissions['canviewthreads'] == "no") {
		nopermission();
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."favorites WHERE tid='$tid' AND type='f' AND uid='".$mybb->user[uid]."'");
	$favorite = $db->fetch_array($query);
	if(!$favorite['tid']) {
		$db->query("INSERT INTO ".TABLE_PREFIX."favorites (fid,uid,tid,type) VALUES (NULL,'".$mybb->user[uid]."','$tid','f')");
	}
	if($HTTP_REFERER) {
		$url = $HTTP_REFERER;
	} else {
		$url = "showthread.php?tid=$tid";
	}
	redirect($url, $lang->redirect_favoriteadded);
}
elseif($action == "removefavorite") {
	$query = $db->query("SELECT tid FROM ".TABLE_PREFIX."favorites WHERE tid='$tid' AND type='f' AND uid='".$mybb->user[uid]."'");
	$thread = $db->fetch_array($query);
	if(!$thread['tid']) {
		error($lang->error_invalidthread);
	}
	$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE tid='$tid' AND type='f' AND uid='".$mybb->user[uid]."'");
	if($HTTP_REFERER) {
		$url = $HTTP_REFERER;
	} else {
		$url = "usercp.php?action=favorites";
	}
	redirect($url, $lang->redirect_favoriteremoved);
}
if($action == "addsubscription") {
	if($type == "forum") {
		$query = $db->query("SELECT fid FROM ".TABLE_PREFIX."forums WHERE fid='$fid'");
		$forum = $db->fetch_array($query);
		if(!$forum['fid']) {
			error($lang->error_invalidforum);
		}
	$forumpermissions = forum_permissions($fid);
		if($forumpermissions['canview'] == "no" || $forumpermissions['canviewthreads'] == "no") {
			nopermission();
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forumsubscriptions WHERE fid='$fid' AND uid='".$mybb->user[uid]."'");
		$fsubscription = $db->fetch_array($query);
		if(!$fsubscription['fid']) {
			$db->query("INSERT INTO ".TABLE_PREFIX."forumsubscriptions (fsid,fid,uid) VALUES (NULL,'$fid','".$mybb->user[uid]."')");
		}
		if($HTTP_REFERER) {
			$url = $HTTP_REFERER;
		} else {
			$url = "index.php";
		}
		redirect($url, $lang->redirect_forumsubscriptionadded);
	} else {	
		$query = $db->query("SELECT tid,fid FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
		$thread = $db->fetch_array($query);
		if(!$thread['tid']) {
			error($lang->error_invalidthread);
		}
		$forumpermissions = forum_permissions($thread['fid']);
		if($forumpermissions['canview'] == "no" || $forumpermissions['canviewthreads'] == "no") {
			nopermission();
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."favorites WHERE tid='$tid' AND type='s' AND uid='".$mybb->user[uid]."'");
		$favorite = $db->fetch_array($query);
		if(!$favorite['tid']) {
			$db->query("INSERT INTO ".TABLE_PREFIX."favorites (fid,uid,tid,type) VALUES (NULL,'".$mybb->user[uid]."','$tid','s')");
		}
		if($HTTP_REFERER) {
			$url = $HTTP_REFERER;
		} else {
			$url = "showthread.php?tid=$tid";
		}
		redirect($url, $lang->redirect_subscriptionadded);
	}
}
elseif($action == "removesubscription") {
	if($type == "forum") {
		$query = $db->query("SELECT fid FROM ".TABLE_PREFIX."forumsubscriptions WHERE fid='$fid' AND uid='".$mybb->user[uid]."'");
		$forum = $db->fetch_array($query);
		if(!$forum['fid']) {
			error($lang->error_invalidforum);
		}
		$db->query("DELETE FROM ".TABLE_PREFIX."forumsubscriptions WHERE fid='$fid' AND uid='".$mybb->user[uid]."'");
		if($HTTP_REFERER) {
			$url = $HTTP_REFERER;
		} else {
			$url = "usercp.php?action=forumsubscriptions";
		}
		redirect($url, $lang->redirect_forumsubscriptionremoved);
	} else {
		$query = $db->query("SELECT tid FROM ".TABLE_PREFIX."favorites WHERE tid='$tid' AND type='s' AND uid='".$mybb->user[uid]."'");
		$thread = $db->fetch_array($query);
		if(!$thread['tid']) {
			error($lang->error_invalidthread);
		}
		$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE tid='$tid' AND type='s' AND uid='".$mybb->user[uid]."'");
		if($HTTP_REFERER) {
			$url = $HTTP_REFERER;
		} else {
			$url = "usercp.php?action=subscriptions";
		}
		redirect($url, $lang->redirect_subscriptionremoved);
	}
}
elseif($action == "removesubscriptions") {
	if($type == "forum") {
		$db->query("DELETE FROM ".TABLE_PREFIX."forumsubscriptions WHERE uid='".$mybb->user[uid]."'");
		if($HTTP_REFERER) {
			$url = $HTTP_REFERER;
		} else {
			$url = "usercp.php?action=forumsubscriptions";
		}
		redirect($url, $lang->redirect_forumsubscriptionsremoved);
	} else {
		$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE type='s' AND uid='".$mybb->user[uid]."'");
		if($HTTP_REFERER) {
			$url = $HTTP_REFERER;
		} else {
			$url = "usercp.php?action=subscriptions";
		}
		redirect($url, $lang->redirect_subscriptionsremoved);
	}
}
elseif($action == "removefavorites") {
	$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE type='f' AND uid='".$mybb->user[uid]."'");
	if($HTTP_REFERER) {
		$url = $HTTP_REFERER;
	} else {
		$url = "usercp.php?action=favorites";
	}
	redirect($url, $lang->redirect_favoritesremoved);
}
?>