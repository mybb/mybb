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

$templatelist = "announcement";
require "./global.php";
require MYBB_ROOT."inc/functions_post.php";

// Load global language phrases
$lang->load("announcements");

$aid = intval($mybb->input['aid']);

$plugins->run_hooks("announcements_start");

// Get announcement fid
$query = $db->select_query(array(
	'select' => "fid",
	'from' => TABLE_PREFIX."announcements",
	'where' => "aid='$aid'"
));
$announcement = $db->fetch_array($query);

if(!$announcement)
{
	error($lang->error_invalidannouncement);
}

// Get forum info
$fid = $announcement['fid'];
if($fid > 0)
{
	$forum = get_forum($fid);

	if(!$forum)
	{
		error($lang->error_invalidforum);
	}

	// Make navigation
	build_forum_breadcrumb($forum['fid']);
}
add_breadcrumb($lang->nav_announcements);

// Permissions
$forumpermissions = forum_permissions($forum['fid']);
$parentlist = $forum['parentlist'];

if($forumpermissions['canview'] == "no" || $forumpermissions['canviewthreads'] == "no")
{
	error_no_permission();
}

// Get announcement info
$time = time();
// You made those nice skiny queries all fat and shit
$query = $db->select_query(array(
	'select' => "a.*",
	'from' => TABLE_PREFIX."announcements a",
	'where' => "a.startdate<={$time} AND a.enddate>={$time} AND a.aid={$aid}",
	'joins' => array(
		0 => array(
			'type' => "left",
			'select' => "u.*",
			'from' => TABLE_PREFIX."users u",
			'where' => "u.uid=a.uid"),
		1 => array(
			'type' => "left",
			'select' => "f.*",
			'from' => TABLE_PREFIX."userfields f",
			'where' => "f.ufid=u.uid"),
		2 => array(
			'type' => "left",
			'select' => "g.title AS grouptitle, g.usertitle AS groupusertitle, g.stars AS groupstars, g.starimage AS groupstarimage, g.image AS groupimage, g.namestyle, g.usereputationsystem"
			'from' => TABLE_PREFIX."usergroups g",
			'where' => "g.gid=u.usergroup")
	)
));
$announcementarray = $db->fetch_array($query);

$announcementarray['dateline'] = $announcementarray['startdate'];
$announcementarray['userusername'] = $announcementarray['username'];
$announcement = build_postbit($announcementarray, 3);
$lang->forum_announcement = sprintf($lang->forum_announcement, $announcementarray['subject']);

$plugins->run_hooks("announcements_end");

eval("\$forumannouncement = \"".$templates->get("announcement")."\";");
output_page($forumannouncement);
?>