<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */

define("IN_MYBB", 1);
define("IN_PORTAL", 1);
define('THIS_SCRIPT', 'portal.php');

// set the path to your forums directory here (without trailing slash)
$forumdir = "./";

// end editing

$change_dir = "./";

if(!@chdir($forumdir) && !empty($forumdir))
{
	if(@is_dir($forumdir))
	{
		$change_dir = $forumdir;
	}
	else
	{
		die("\$forumdir is invalid!");
	}
}

$templatelist = "portal_welcome,portal_welcome_membertext,portal_stats,portal_search,portal_whosonline_memberbit,portal_whosonline,portal_latestthreads_thread,portal_latestthreads,portal_announcement_numcomments_no,portal_announcement,portal_announcement_numcomments,portal_pms,portal";
$templatelist .= ",portal_welcome_guesttext,postbit_attachments_thumbnails_thumbnail,postbit_attachments_images_image,postbit_attachments_attachment,postbit_attachments_thumbnails,postbit_attachments_images,postbit_attachments";

require_once $change_dir."/global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("portal");

// Fetch the current URL
$portal_url = get_current_location();

add_breadcrumb($lang->nav_portal, "portal.php");

$plugins->run_hooks("portal_start");


// get forums user cannot view
$unviewable = get_unviewable_forums(true);
if($unviewable)
{
	$unviewwhere = " AND fid NOT IN ($unviewable)";
	$tunviewwhere = " AND t.fid NOT IN ($unviewable)";
}

// get inactive forums
$inactive = get_inactive_forums(true);
if($inactive)
{
	$inactivewhere = " AND fid NOT IN ($inactive)";
	$tinactivewhere = " AND t.fid NOT IN ($inactive)";
}

// If user is known, welcome them
if($mybb->settings['portal_showwelcome'] != 0)
{
	if($mybb->user['uid'] != 0)
	{
		// Get number of new posts, threads, announcements
		$query = $db->simple_select("posts", "COUNT(pid) AS newposts", "visible=1 AND dateline>'".$mybb->user['lastvisit']."' {$unviewwhere}{$inactivewhere}");
		$newposts = $db->fetch_field($query, "newposts");
		if($newposts)
		{
			// If there aren't any new posts, there is no point in wasting two more queries
			$query = $db->simple_select("threads", "COUNT(tid) AS newthreads", "visible=1 AND dateline>'".$mybb->user['lastvisit']."' {$unviewwhere}{$inactivewhere}");
			$newthreads = $db->fetch_field($query, "newthreads");

			if(!empty($mybb->settings['portal_announcementsfid']))
			{
				$announcementsfids = explode(',', $mybb->settings['portal_announcementsfid']);
				if(is_array($announcementsfids))
				{
					foreach($announcementsfids as $fid)
					{
						$fid_array[] = intval($fid);
					}

					$announcementsfids = implode(',', $fid_array);
					$query = $db->simple_select("threads", "COUNT(tid) AS newann", "visible=1 AND dateline>'".$mybb->user['lastvisit']."' AND fid IN (".$announcementsfids.") {$unviewwhere}{$inactivewhere}");
					$newann = $db->fetch_field($query, "newann");
				}
			}

			if(!$newthreads)
			{
				$newthreads = 0;
			}

			if(!$newann)
			{
				$newann = 0;
			}
		}
		else
		{
			$newposts = 0;
			$newthreads = 0;
			$newann = 0;
		}

		// Make the text
		if($newann == 1)
		{
			$lang->new_announcements = $lang->new_announcement;
		}
		else
		{
			$lang->new_announcements = $lang->sprintf($lang->new_announcements, $newann);
		}
		if($newthreads == 1)
		{
			$lang->new_threads = $lang->new_thread;
		}
		else
		{
			$lang->new_threads = $lang->sprintf($lang->new_threads, $newthreads);
		}
		if($newposts == 1)
		{
			$lang->new_posts = $lang->new_post;
		}
		else
		{
			$lang->new_posts = $lang->sprintf($lang->new_posts, $newposts);
		}
		eval("\$welcometext = \"".$templates->get("portal_welcome_membertext")."\";");

	}
	else
	{
		$lang->guest_welcome_registration = $lang->sprintf($lang->guest_welcome_registration, $mybb->settings['bburl'] . '/member.php?action=register');
		$mybb->user['username'] = $lang->guest;
		switch($mybb->settings['username_method'])
		{
			case 0:
				$username = $lang->username;
				break;
			case 1:
				$username = $lang->username1;
				break;
			case 2:
				$username = $lang->username2;
				break;
			default:
				$username = $lang->username;
				break;
		}
		eval("\$welcometext = \"".$templates->get("portal_welcome_guesttext")."\";");
	}
	$lang->welcome = $lang->sprintf($lang->welcome, $mybb->user['username']);
	eval("\$welcome = \"".$templates->get("portal_welcome")."\";");
	if($mybb->user['uid'] == 0)
	{
		$mybb->user['username'] = "";
	}
}
// Private messages box
if($mybb->settings['portal_showpms'] != 0)
{
	if($mybb->user['uid'] != 0 && $mybb->user['receivepms'] != 0 && $mybb->usergroup['canusepms'] != 0 && $mybb->settings['enablepms'] != 0)
	{
		switch($db->type)
		{
			case "sqlite":
			case "pgsql":
				$query = $db->simple_select("privatemessages", "COUNT(*) AS pms_total", "uid='".$mybb->user['uid']."'");
				$messages['pms_total'] = $db->fetch_field($query, "pms_total");

				$query = $db->simple_select("privatemessages", "COUNT(*) AS pms_unread", "uid='".$mybb->user['uid']."' AND CASE WHEN status = '0' AND folder = '0' THEN TRUE ELSE FALSE END");
				$messages['pms_unread'] = $db->fetch_field($query, "pms_unread");
				break;
			default:
				$query = $db->simple_select("privatemessages", "COUNT(*) AS pms_total, SUM(IF(status='0' AND folder='1','1','0')) AS pms_unread", "uid='".$mybb->user['uid']."'");
				$messages = $db->fetch_array($query);
		}

		// the SUM() thing returns "" instead of 0
		if($messages['pms_unread'] == "")
		{
			$messages['pms_unread'] = 0;
		}
		$lang->pms_received_new = $lang->sprintf($lang->pms_received_new, $mybb->user['username'], $messages['pms_unread']);
		eval("\$pms = \"".$templates->get("portal_pms")."\";");
	}
}
// Get Forum Statistics
if($mybb->settings['portal_showstats'] != 0)
{
	$stats = $cache->read("stats");
	$stats['numthreads'] = my_number_format($stats['numthreads']);
	$stats['numposts'] = my_number_format($stats['numposts']);
	$stats['numusers'] = my_number_format($stats['numusers']);
	if(!$stats['lastusername'])
	{
		$newestmember = "<strong>" . $lang->no_one . "</strong>";
	}
	else
	{
		$newestmember = build_profile_link($stats['lastusername'], $stats['lastuid']);
	}
	eval("\$stats = \"".$templates->get("portal_stats")."\";");
}

// Search box
if($mybb->settings['portal_showsearch'] != 0)
{
	eval("\$search = \"".$templates->get("portal_search")."\";");
}

// Get the online users
if($mybb->settings['portal_showwol'] != 0 && $mybb->usergroup['canviewonline'] != 0)
{
	$timesearch = TIME_NOW - $mybb->settings['wolcutoff'];
	$comma = '';
	$guestcount = 0;
	$membercount = 0;
	$onlinemembers = '';
	$query = $db->query("
		SELECT s.sid, s.ip, s.uid, s.time, s.location, u.username, u.invisible, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."sessions s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.time>'$timesearch'
		ORDER BY u.username ASC, s.time DESC
	");
	while($user = $db->fetch_array($query))
	{

		// Create a key to test if this user is a search bot.
		$botkey = my_strtolower(str_replace("bot=", '', $user['sid']));

		if($user['uid'] == "0")
		{
			++$guestcount;
		}
		elseif(my_strpos($user['sid'], "bot=") !== false && $session->bots[$botkey])
		{
			// The user is a search bot.
			$onlinemembers .= $comma.format_name($session->bots[$botkey], $session->botgroup);
			$comma = $lang->comma;
			++$botcount;
		}
		else
		{
			if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
			{
				++$membercount;

				$doneusers[$user['uid']] = $user['time'];

				// If the user is logged in anonymously, update the count for that.
				if($user['invisible'] == 1)
				{
					++$anoncount;
				}

				if($user['invisible'] == 1)
				{
					$invisiblemark = "*";
				}
				else
				{
					$invisiblemark = '';
				}

				if(($user['invisible'] == 1 && ($mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $mybb->user['uid'])) || $user['invisible'] != 1)
				{
					$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
					$user['profilelink'] = get_profile_link($user['uid']);
					eval("\$onlinemembers .= \"".$templates->get("portal_whosonline_memberbit", 1, 0)."\";");
					$comma = $lang->comma;
				}
			}
		}
	}

	$onlinecount = $membercount + $guestcount + $botcount;

	// If we can see invisible users add them to the count
	if($mybb->usergroup['canviewwolinvis'] == 1)
	{
		$onlinecount += $anoncount;
	}

	// If we can't see invisible users but the user is an invisible user incriment the count by one
	if($mybb->usergroup['canviewwolinvis'] != 1 && $mybb->user['invisible'] == 1)
	{
		++$onlinecount;
	}

	// Most users online
	$mostonline = $cache->read("mostonline");
	if($onlinecount > $mostonline['numusers'])
	{
		$time = TIME_NOW;
		$mostonline['numusers'] = $onlinecount;
		$mostonline['time'] = $time;
		$cache->update("mostonline", $mostonline);
	}
	$recordcount = $mostonline['numusers'];
	$recorddate = my_date($mybb->settings['dateformat'], $mostonline['time']);
	$recordtime = my_date($mybb->settings['timeformat'], $mostonline['time']);

	if($onlinecount == 1)
	{
	  $lang->online_users = $lang->online_user;
	}
	else
	{
	  $lang->online_users = $lang->sprintf($lang->online_users, $onlinecount);
	}
	$lang->online_counts = $lang->sprintf($lang->online_counts, $membercount, $guestcount);
	eval("\$whosonline = \"".$templates->get("portal_whosonline")."\";");
}

// Latest forum discussions
if($mybb->settings['portal_showdiscussions'] != 0 && $mybb->settings['portal_showdiscussionsnum'])
{
	$altbg = alt_trow();
	$threadlist = '';
	$query = $db->query("
		SELECT t.*, u.username
		FROM ".TABLE_PREFIX."threads t
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
		WHERE 1=1 {$tunviewwhere}{$tinactivewhere} AND t.visible='1' AND t.closed NOT LIKE 'moved|%'
		ORDER BY t.lastpost DESC
		LIMIT 0, ".$mybb->settings['portal_showdiscussionsnum']
	);
	while($thread = $db->fetch_array($query))
	{
		$forumpermissions[$thread['fid']] = forum_permissions($thread['fid']);

		// Make sure we can view this thread
		if($forumpermissions[$thread['fid']]['canview'] == 0 || $forumpermissions[$thread['fid']]['canviewthreads'] == 0 || $forumpermissions[$thread['fid']]['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
		{
			continue;
		}

		$lastpostdate = my_date($mybb->settings['dateformat'], $thread['lastpost']);
		$lastposttime = my_date($mybb->settings['timeformat'], $thread['lastpost']);
		// Don't link to guest's profiles (they have no profile).
		if($thread['lastposteruid'] == 0)
		{
			$lastposterlink = $thread['lastposter'];
		}
		else
		{
			$lastposterlink = build_profile_link($thread['lastposter'], $thread['lastposteruid']);
		}
		if(my_strlen($thread['subject']) > 25)
		{
			$thread['subject'] = my_substr($thread['subject'], 0, 25) . "...";
		}
		$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
		$thread['threadlink'] = get_thread_link($thread['tid']);
		$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");
		eval("\$threadlist .= \"".$templates->get("portal_latestthreads_thread")."\";");
		$altbg = alt_trow();
	}
	if($threadlist)
	{
		// Show the table only if there are threads
		eval("\$latestthreads = \"".$templates->get("portal_latestthreads")."\";");
	}
}

$announcements = '';
if(!empty($mybb->settings['portal_announcementsfid']))
{
	// Get latest news announcements
	// First validate announcement fids:
	$announcementsfids = explode(',', $mybb->settings['portal_announcementsfid']);
	if(is_array($announcementsfids))
	{
		foreach($announcementsfids as $fid)
		{
			$fid_array[] = intval($fid);
		}
		$announcementsfids = implode(',', $fid_array);
	}
	// And get them!
	foreach($forum_cache as $fid => $f)
	{
		if(is_array($fid_array) && in_array($fid, $fid_array))
		{
			$forum[$fid] = $f;
		}
	}

	$numannouncements = intval($mybb->settings['portal_numannouncements']);
	if(!$numannouncements)
	{
		$numannouncements = 10; // Default back to 10
	}

	$pids = '';
	$tids = '';
	$comma = '';
	$posts = array();
	$query = $db->query("
		SELECT p.pid, p.message, p.tid, p.smilieoff
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		WHERE t.fid IN (".$announcementsfids."){$tunviewwhere} AND t.visible='1' AND t.closed NOT LIKE 'moved|%' AND t.firstpost=p.pid
		ORDER BY t.dateline DESC
		LIMIT 0, {$numannouncements}"
	);
	while($getid = $db->fetch_array($query))
	{
		$pids .= ",'{$getid['pid']}'";
		$tids .= ",'{$getid['tid']}'";
		$posts[$getid['tid']] = $getid;
	}
	if(!empty($posts))
	{
		$pids = "pid IN(0{$pids})";
		// Now lets fetch all of the attachments for these posts
		$query = $db->simple_select("attachments", "*", $pids);
		while($attachment = $db->fetch_array($query))
		{
			$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
		}

		if(is_array($forum))
		{
			foreach($forum as $fid => $forumrow)
			{
				$forumpermissions[$fid] = forum_permissions($fid);
			}
		}

		$icon_cache = $cache->read("posticons");

		$query = $db->query("
			SELECT t.*, t.username AS threadusername, u.username, u.avatar, u.avatardimensions
			FROM ".TABLE_PREFIX."threads t
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
			WHERE t.fid IN (".$announcementsfids.") AND t.tid IN (0{$tids}) AND t.visible='1' AND t.closed NOT LIKE 'moved|%'
			ORDER BY t.dateline DESC
			LIMIT 0, {$numannouncements}"
		);
		while($announcement = $db->fetch_array($query))
		{
			// Make sure we can view this announcement
			if($forumpermissions[$announcement['fid']]['canview'] == 0 || $forumpermissions[$announcement['fid']]['canviewthreads'] == 0 || $forumpermissions[$announcement['fid']]['canonlyviewownthreads'] == 1 && $announcement['uid'] != $mybb->user['uid'])
			{
				continue;
			}

			$announcement['message'] = $posts[$announcement['tid']]['message'];
			$announcement['pid'] = $posts[$announcement['tid']]['pid'];
			$announcement['smilieoff'] = $posts[$announcement['tid']]['smilieoff'];
			$announcement['threadlink'] = get_thread_link($announcement['tid']);

			if($announcement['uid'] == 0)
			{
				$profilelink = htmlspecialchars_uni($announcement['threadusername']);
			}
			else
			{
				$profilelink = build_profile_link($announcement['username'], $announcement['uid']);
			}

			if(!$announcement['username'])
			{
				$announcement['username'] = $announcement['threadusername'];
			}
			$announcement['subject'] = htmlspecialchars_uni($parser->parse_badwords($announcement['subject']));
			if($announcement['icon'] > 0 && $icon_cache[$announcement['icon']])
			{
				$icon = $icon_cache[$announcement['icon']];
				$icon['path'] = htmlspecialchars_uni($icon['path']);
				$icon['name'] = htmlspecialchars_uni($icon['name']);
				$icon = "<img src=\"{$icon['path']}\" alt=\"{$icon['name']}\" />";
			}
			else
			{
				$icon = "&nbsp;";
			}
			if($announcement['avatar'] != '')
			{
				$avatar_dimensions = explode("|", $announcement['avatardimensions']);
				if($avatar_dimensions[0] && $avatar_dimensions[1])
				{
					$avatar_width_height = "width=\"{$avatar_dimensions[0]}\" height=\"{$avatar_dimensions[1]}\"";
				}
				if (!stristr($announcement['avatar'], 'http://'))
				{
					$announcement['avatar'] = $mybb->settings['bburl'] . '/' . $announcement['avatar'];
				}
				$announcement['avatar'] = htmlspecialchars_uni($announcement['avatar']);
				$avatar = "<td class=\"trow1\" width=\"1\" align=\"center\" valign=\"top\"><img src=\"{$announcement['avatar']}\" alt=\"\" {$avatar_width_height} /></td>";
			}
			else
			{
				$avatar = '';
			}
			$anndate = my_date($mybb->settings['dateformat'], $announcement['dateline']);
			$anntime = my_date($mybb->settings['timeformat'], $announcement['dateline']);

			if($announcement['replies'])
			{
				eval("\$numcomments = \"".$templates->get("portal_announcement_numcomments")."\";");
			}
			else
			{
				eval("\$numcomments = \"".$templates->get("portal_announcement_numcomments_no")."\";");
				$lastcomment = '';
			}

			$plugins->run_hooks("portal_announcement");

			$parser_options = array(
				"allow_html" => $forum[$announcement['fid']]['allowhtml'],
				"allow_mycode" => $forum[$announcement['fid']]['allowmycode'],
				"allow_smilies" => $forum[$announcement['fid']]['allowsmilies'],
				"allow_imgcode" => $forum[$announcement['fid']]['allowimgcode'],
				"allow_videocode" => $forum[$announcement['fid']]['allowvideocode'],
				"filter_badwords" => 1
			);
			if($announcement['smilieoff'] == 1)
			{
				$parser_options['allow_smilies'] = 0;
			}

			$message = $parser->parse_message($announcement['message'], $parser_options);

			if(is_array($attachcache[$announcement['pid']]))
			{ // This post has 1 or more attachments
				$validationcount = 0;
				$id = $announcement['pid'];
				foreach($attachcache[$id] as $aid => $attachment)
				{
					if($attachment['visible'])
					{ // There is an attachment thats visible!
						$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
						$attachment['filesize'] = get_friendly_size($attachment['filesize']);
						$ext = get_extension($attachment['filename']);
						if($ext == "jpeg" || $ext == "gif" || $ext == "bmp" || $ext == "png" || $ext == "jpg")
						{
							$isimage = true;
						}
						else
						{
							$isimage = false;
						}
						$attachment['icon'] = get_attachment_icon($ext);
						// Support for [attachment=id] code
						if(stripos($message, "[attachment=".$attachment['aid']."]") !== false)
						{
							if($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != '')
							{ // We have a thumbnail to show (and its not the "SMALL" enough image
								eval("\$attbit = \"".$templates->get("postbit_attachments_thumbnails_thumbnail")."\";");
							}
							elseif($attachment['thumbnail'] == "SMALL" && $forumpermissions[$announcement['fid']]['candlattachments'] == 1)
							{
								// Image is small enough to show - no thumbnail
								eval("\$attbit = \"".$templates->get("postbit_attachments_images_image")."\";");
							}
							else
							{
								// Show standard link to attachment
								eval("\$attbit = \"".$templates->get("postbit_attachments_attachment")."\";");
							}
							$message = preg_replace("#\[attachment=".$attachment['aid']."]#si", $attbit, $message);
						}
						else
						{
							if($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != '')
							{ // We have a thumbnail to show
								eval("\$post['thumblist'] .= \"".$templates->get("postbit_attachments_thumbnails_thumbnail")."\";");
								if($tcount == 5)
								{
									$thumblist .= "<br />";
									$tcount = 0;
								}
								++$tcount;
							}
							elseif($attachment['thumbnail'] == "SMALL" && $forumpermissions[$announcement['fid']]['candlattachments'] == 1)
							{
								// Image is small enough to show - no thumbnail
								eval("\$post['imagelist'] .= \"".$templates->get("postbit_attachments_images_image")."\";");
							}
							else
							{
								eval("\$post['attachmentlist'] .= \"".$templates->get("postbit_attachments_attachment")."\";");
							}
						}
					}
					else
					{
						$validationcount++;
					}
				}
				if($post['thumblist'])
				{
					eval("\$post['attachedthumbs'] = \"".$templates->get("postbit_attachments_thumbnails")."\";");
				}
				if($post['imagelist'])
				{
					eval("\$post['attachedimages'] = \"".$templates->get("postbit_attachments_images")."\";");
				}
				if($post['attachmentlist'] || $post['thumblist'] || $post['imagelist'])
				{
					eval("\$post['attachments'] = \"".$templates->get("postbit_attachments")."\";");
				}
			}

			eval("\$announcements .= \"".$templates->get("portal_announcement")."\";");
			unset($post);
		}
	}
}

$plugins->run_hooks("portal_end");

eval("\$portal = \"".$templates->get("portal")."\";");
output_page($portal);
?>
