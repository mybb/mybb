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
define('THIS_SCRIPT', 'announcements.php');

$templatelist = "announcement,postbit_groupimage,postbit_reputation,postbit_avatar,postbit_online,postbit_offline,postbit_away,postbit_find,postbit_pm,postbit_email,postbit_author_user";
$templatelist .= ",forumdisplay_password_wrongpass,forumdisplay_password,postbit_author_guest,postbit_userstar,announcement_quickdelete,postbit,postbit_classic,postbit_www,announcement_edit";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";

// Load global language phrases
$lang->load("announcements");

$aid = $mybb->get_input('aid', MyBB::INPUT_INT);

// Get announcement fid
$query = $db->simple_select("announcements", "fid", "aid='$aid'");
$announcement = $db->fetch_array($query);

$plugins->run_hooks("announcements_start");

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

	// Permissions
	$forumpermissions = forum_permissions($forum['fid']);

	if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
	{
		error_no_permission();
	}

	// Check if this forum is password protected and we have a valid password
	check_forum_password($forum['fid']);
}
add_breadcrumb($lang->nav_announcements);

$archive_url = build_archive_link("announcement", $aid);

// Get announcement info
$time = TIME_NOW;

$query = $db->query("
	SELECT u.*, u.username AS userusername, a.*, f.*
	FROM ".TABLE_PREFIX."announcements a
	LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
	LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
	WHERE a.startdate<='$time' AND (a.enddate>='$time' OR a.enddate='0') AND a.aid='$aid'
");

$announcementarray = $db->fetch_array($query);

if(!$announcementarray)
{
	error($lang->error_invalidannouncement);
}

// Gather usergroup data from the cache
// Field => Array Key
$data_key = array(
	'title' => 'grouptitle',
	'usertitle' => 'groupusertitle',
	'stars' => 'groupstars',
	'starimage' => 'groupstarimage',
	'image' => 'groupimage',
	'namestyle' => 'namestyle',
	'usereputationsystem' => 'usereputationsystem'
);

foreach($data_key as $field => $key)
{
	$announcementarray[$key] = $groupscache[$announcementarray['usergroup']][$field];
}

$announcementarray['dateline'] = $announcementarray['startdate'];
$announcementarray['userusername'] = $announcementarray['username'];
$announcement = build_postbit($announcementarray, 3);
$announcementarray['subject'] = $parser->parse_badwords($announcementarray['subject']);
$lang->forum_announcement = $lang->sprintf($lang->forum_announcement, htmlspecialchars_uni($announcementarray['subject']));

if($announcementarray['startdate'] > $mybb->user['lastvisit'])
{
	$setcookie = true;
	if(isset($mybb->cookies['mybb']['announcements']) && is_scalar($mybb->cookies['mybb']['announcements']))
	{
		$cookie = my_unserialize(stripslashes($mybb->cookies['mybb']['announcements']), false);

		if(isset($cookie[$announcementarray['aid']]))
		{
			$setcookie = false;
		}
	}

	if($setcookie)
	{
		my_set_array_cookie('announcements', $announcementarray['aid'], $announcementarray['startdate'], -1);
	}
}

$plugins->run_hooks("announcements_end");

eval("\$forumannouncement = \"".$templates->get("announcement")."\";");
output_page($forumannouncement);
