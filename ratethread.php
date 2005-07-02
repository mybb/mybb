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

$templatelist = "";
require "./global.php";

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
$thread = $db->fetch_array($query);
if(!$thread['tid'])
{
	error($lang->error_invalidthread);
}

$forumpermissions = forum_permissions($thread['fid']);
if($forumpermissions['canview'] == "no" || $forumpermissions['canratethreads'] == "no")
{
	nopermission();
}

// Password protected forums ......... yhummmmy!
$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$thread[fid]'");
$forum = $db->fetch_array($query);
checkpwforum($forum['fid'], $forum['password']);

if($forum['allowtratings'] == "no")
{
	nopermission();
}
if($rating < 1 || $rating > 5)
{
	error($lang->error_invalidrating);
}

if($mybb->user['uid'] != "0")
{
	$whereclause = "uid='".$mybb->user[uid]."'";
}
else
{
	$whereclause = "ipaddress='$ipaddress'";
}
$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threadratings WHERE $whereclause AND tid='$tid'");
$ratecheck = $db->fetch_array($query);

if($ratecheck['rid'])
{
	error($lang->error_alreadyratedthread);
}
else
{
	if($mybbthreadrate[$tid])
	{
		error($lang->error_alreadyratedthread);
	}
	$db->query("UPDATE ".TABLE_PREFIX."threads SET numratings=numratings+1, totalratings=totalratings+'$rating' WHERE tid='$tid'");
	if($mybb->user['uid'] != "0")
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."threadratings (rid,tid,uid,rating,ipaddress) VALUES (NULL,'$tid','".$mybb->user[uid]."','$rating','$ipaddress')");
	}
	else
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."threadratings (rid,tid,rating,ipaddress) VALUES (NULL,'$tid','$rating','$ipaddress')");
		$time = time();
		mysetcookie("mybbratethread[$tid]", "$rating");
	}
}
redirect("showthread.php?tid=$tid", $lang->redirect_threadrated);
?>
