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

$templatelist = "announcement";
require "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";

// Load global language phrases
$lang->load("announcements");

$aid = intval($mybb->input['aid']);

$plugins->run_hooks("announcements_start");

// Get announcement fid
$query = $db->select_query(array(
	'select' => "fid",
	'table' => TABLE_PREFIX."announcements",
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

$archive_url = $mybb->settings['bburl']."/archive/index.php/announcement-{$aid}.html";

// Permissions
$forumpermissions = forum_permissions($forum['fid']);
$parentlist = $forum['parentlist'];

if($forumpermissions['canview'] == "no" || $forumpermissions['canviewthreads'] == "no")
{
	error_no_permission();
}

// Get announcement info
$time = time();

$query = $db->query("
	SELECT u.*, u.username AS userusername, a.*, f.*, g.title AS grouptitle, g.usertitle AS groupusertitle, g.stars AS groupstars, g.starimage AS groupstarimage, g.image AS groupimage, g.namestyle, g.usereputationsystem
	FROM ".TABLE_PREFIX."announcements a
	LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
	LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
	LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup)
	WHERE a.startdate<='$time' AND (a.enddate>='$time' OR a.enddate='0') AND a.aid='$aid'
");
$announcementarray = $db->fetch_array($query);
if(!$announcementarray)
{
	error($lang->error_invalidannouncement);
}
$announcementarray['dateline'] = $announcementarray['startdate'];
$announcementarray['userusername'] = $announcementarray['username'];
$announcement = build_postbit($announcementarray, 3);
$lang->forum_announcement = sprintf($lang->forum_announcement, $announcementarray['subject']);

$plugins->run_hooks("announcements_end");

eval("\$forumannouncement = \"".$templates->get("announcement")."\";");
output_page($forumannouncement);
?>