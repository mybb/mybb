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

$templatelist = "portal,portal_welcome_membertext,portal_stats,portal_search,portal_whosonline_memberbit,portal_whosonline,portal_latestthreads_thread,portal_latestthreads,portal_announcement_numcomments_no,portal_announcement,portal_welcome";
$templatelist .= ",portal_welcome_guesttext,postbit_attachments_thumbnails_thumbnail,postbit_attachments_images_image,postbit_attachments_attachment,postbit_attachments_thumbnails,postbit_attachments_images,postbit_attachments,portal_pms";
$templatelist .= ",multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,portal_announcement_send_item,portal_announcement_icon,portal_announcement_avatar,portal_announcement_numcomments";

require_once $change_dir."/global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("portal");

if($mybb->settings['portal'] == 0)
{
	error($lang->portal_disabled);
}

// Fetch the current URL
$portal_url = get_current_location();
$file_name = strtok(my_strtolower(basename($portal_url)), '?');

add_breadcrumb($lang->nav_portal, $file_name);

$plugins->run_hooks("portal_start");

$tunviewwhere = $unviewwhere = '';
// get forums user cannot view
$unviewable = get_unviewable_forums(true);
if($unviewable)
{
	$unviewwhere = " AND fid NOT IN ($unviewable)";
	$tunviewwhere = " AND t.fid NOT IN ($unviewable)";
}

// get inactive forums
$inactive = get_inactive_forums();
if($inactive)
{
	$unviewwhere .= " AND fid NOT IN ($inactive)";
	$tunviewwhere .= " AND t.fid NOT IN ($inactive)";
}

$welcome = '';
// If user is known, welcome them
if($mybb->settings['portal_showwelcome'] != 0)
{
	if($mybb->user['uid'] != 0)
	{
		// Get number of new posts, threads, announcements
		$query = $db->simple_select("posts", "COUNT(pid) AS newposts", "visible=1 AND dateline>'".$mybb->user['lastvisit']."'{$unviewwhere}");
		$newposts = $db->fetch_field($query, "newposts");
		if($newposts)
		{
			// If there aren't any new posts, there is no point in wasting two more queries
			$query = $db->simple_select("threads", "COUNT(tid) AS newthreads", "visible=1 AND dateline>'".$mybb->user['lastvisit']."'{$unviewwhere}");
			$newthreads = $db->fetch_field($query, "newthreads");

			$newann = 0;
			if(!empty($mybb->settings['portal_announcementsfid']))
			{
				$annfidswhere = '';
				if($mybb->settings['portal_announcementsfid'] != -1)
				{
					$announcementsfids = explode(',', (string)$mybb->settings['portal_announcementsfid']);
					if(is_array($announcementsfids))
					{
						foreach($announcementsfids as &$fid)
						{
							$fid = (int)$fid;
						}
						unset($fid);

						$announcementsfids = implode(',', $announcementsfids);

						$annfidswhere = " AND fid IN (".$announcementsfids.")";
					}
				}

				$query = $db->simple_select("threads", "COUNT(tid) AS newann", "visible=1 AND dateline>'".$mybb->user['lastvisit']."'{$annfidswhere}{$unviewwhere}");
				$newann = $db->fetch_field($query, "newann");
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
		$lang->guest_welcome_registration = $lang->sprintf($lang->guest_welcome_registration, $mybb->settings['bburl'].'/member.php?action=register');
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
}

$pms = '';
// Private messages box
if($mybb->settings['portal_showpms'] != 0)
{
	if($mybb->user['uid'] != 0 && $mybb->user['receivepms'] != 0 && $mybb->usergroup['canusepms'] != 0 && $mybb->settings['enablepms'] != 0)
	{
		$messages['pms_total'] = $mybb->user['pms_total'];
		$messages['pms_unread'] = $mybb->user['pms_unread'];

		$lang->pms_received_new = $lang->sprintf($lang->pms_received_new, $mybb->user['username'], $messages['pms_unread']);
		eval("\$pms = \"".$templates->get("portal_pms")."\";");
	}
}

$stats = '';
// Get Forum Statistics
if($mybb->settings['portal_showstats'] != 0)
{
	$stats = $cache->read("stats");
	$stats['numthreads'] = my_number_format($stats['numthreads']);
	$stats['numposts'] = my_number_format($stats['numposts']);
	$stats['numusers'] = my_number_format($stats['numusers']);
	if(!$stats['lastusername'])
	{
		$newestmember = "<strong>" . $lang->nobody . "</strong>";
	}
	else
	{
		$newestmember = build_profile_link($stats['lastusername'], $stats['lastuid']);
	}
	eval("\$stats = \"".$templates->get("portal_stats")."\";");
}

$search = '';
// Search box
if($mybb->settings['portal_showsearch'] != 0)
{
	eval("\$search = \"".$templates->get("portal_search")."\";");
}

$whosonline = '';
// Get the online users
if($mybb->settings['portal_showwol'] != 0 && $mybb->usergroup['canviewonline'] != 0)
{
	if($mybb->settings['wolorder'] == 'username')
	{
		$order_by = 'u.username ASC';
		$order_by2 = 's.time DESC';
	}
	else
	{
		$order_by = 's.time DESC';
		$order_by2 = 'u.username ASC';
	}

	$timesearch = TIME_NOW - $mybb->settings['wolcutoff'];
	$comma = '';
	$guestcount = $membercount = $botcount = $anoncount = 0;
	$onlinemembers = '';
	$doneusers = array();
	$query = $db->query("
		SELECT s.sid, s.ip, s.uid, s.time, s.location, u.username, u.invisible, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."sessions s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.time>'$timesearch'
		ORDER BY {$order_by}, {$order_by2}
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
			if(empty($doneusers[$user['uid']]) || $doneusers[$user['uid']] < $user['time'])
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
	if($mybb->usergroup['canviewwolinvis'] != 1 && isset($mybb->user['invisible']) && $mybb->user['invisible'] == 1)
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
	$recorddate = my_date('relative', $mostonline['time']);

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

$latestthreads = '';
// Latest forum discussions
if($mybb->settings['portal_showdiscussions'] != 0 && $mybb->settings['portal_showdiscussionsnum'] && $mybb->settings['portal_excludediscussion'] != -1)
{
	$altbg = alt_trow();
	$threadlist = '';

	$excludeforums = '';
	if(!empty($mybb->settings['portal_excludediscussion']))
	{
		$excludeforums = "AND t.fid NOT IN ({$mybb->settings['portal_excludediscussion']})";
	}

	$query = $db->query("
		SELECT t.tid, t.fid, t.uid, t.lastpost, t.lastposteruid, t.lastposter, t.subject, t.replies, t.views, u.username
		FROM ".TABLE_PREFIX."threads t
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
		WHERE 1=1 {$excludeforums}{$tunviewwhere} AND t.visible='1' AND t.closed NOT LIKE 'moved|%'
		ORDER BY t.lastpost DESC
		LIMIT 0, ".$mybb->settings['portal_showdiscussionsnum']
	);
	while($thread = $db->fetch_array($query))
	{
		$forumpermissions[$thread['fid']] = forum_permissions($thread['fid']);

		// Make sure we can view this thread
		if(isset($forumpermissions[$thread['fid']]['canonlyviewownthreads']) && $forumpermissions[$thread['fid']]['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
		{
			continue;
		}

		$lastpostdate = my_date('relative', $thread['lastpost']);
		$thread['replies'] = my_number_format($thread['replies']);
		$thread['views'] = my_number_format($thread['views']);

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
		$thread['forumlink'] = get_forum_link($thread['fid']);
		$thread['forumname'] = $forum_cache[$thread['fid']]['name'];
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
	// Build where clause
	$annfidswhere = '';
	$announcementcount = 0;
	if($mybb->settings['portal_announcementsfid'] != -1)
	{
		// First validate announcement fids:
		$announcementsfids = explode(',', (string)$mybb->settings['portal_announcementsfid']);
		if(is_array($announcementsfids))
		{
			foreach($announcementsfids as $fid)
			{
				$fid_array[] = (int)$fid;
			}
			unset($fid);

			$announcementsfids = implode(',', $fid_array);

			$annfidswhere = " AND t.fid IN ($announcementsfids)";
		}
	}

	// And get them!
	foreach($forum_cache as $fid => $f)
	{
		if(empty($fid_array) || (is_array($fid_array) && in_array($fid, $fid_array)))
		{
			$forum[$fid] = $f;
		}
	}
	
	$query = $db->simple_select("threads t", "COUNT(t.tid) AS threads", "t.visible='1'{$annfidswhere}{$tunviewwhere} AND t.closed NOT LIKE 'moved|%'", array('limit' => 1));
	$announcementcount = $db->fetch_field($query, "threads");

	$numannouncements = (int)$mybb->settings['portal_numannouncements'];
	if(!$numannouncements)
	{
		$numannouncements = 10; // Default back to 10
	}

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	$pages = $announcementcount / $numannouncements;
	$pages = ceil($pages);

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}

	if($page)
	{
		$start = ($page-1) * $numannouncements;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$multipage = multipage($announcementcount, $numannouncements, $page, $file_name);

	$pids = '';
	$tids = '';
	$comma = '';
	$posts = array();
	$attachmentcount = array();
	$query = $db->query("
		SELECT p.pid, p.message, p.tid, p.smilieoff, t.attachmentcount
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		WHERE t.visible='1'{$annfidswhere}{$tunviewwhere} AND t.closed NOT LIKE 'moved|%' AND t.firstpost=p.pid
		ORDER BY t.dateline DESC
		LIMIT {$start}, {$numannouncements}"
	);
	while($getid = $db->fetch_array($query))
	{
		$attachmentcount[$getid['tid']] = $getid['attachmentcount'];
		foreach($attachmentcount as $tid => $attach_count)
		{
			if($attach_count > 0)
			{
				$pids .= ",'{$getid['pid']}'";
			}

			$posts[$getid['tid']] = $getid;
		}

		$tids .= ",'{$getid['tid']}'";
	}
	if(!empty($posts))
	{
		if($pids != '' && $mybb->settings['enableattachments'] == 1)
		{
			$pids = "pid IN(0{$pids})";
			// Now lets fetch all of the attachments for these posts
			$query = $db->simple_select("attachments", "*", $pids);
			while($attachment = $db->fetch_array($query))
			{
				$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
			}
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
			WHERE t.tid IN (0{$tids}){$annfidswhere}{$tunviewwhere} AND t.visible='1' AND t.closed NOT LIKE 'moved|%'
			ORDER BY t.dateline DESC
			LIMIT 0, {$numannouncements}"
		);
		while($announcement = $db->fetch_array($query))
		{
			// Make sure we can view this announcement
			if(isset($forumpermissions[$announcement['fid']]['canonlyviewownthreads']) && $forumpermissions[$announcement['fid']]['canonlyviewownthreads'] == 1 && $announcement['uid'] != $mybb->user['uid'])
			{
				continue;
			}

			$announcement['message'] = $posts[$announcement['tid']]['message'];
			$announcement['pid'] = $posts[$announcement['tid']]['pid'];
			$announcement['smilieoff'] = $posts[$announcement['tid']]['smilieoff'];
			$announcement['threadlink'] = get_thread_link($announcement['tid']);
			$announcement['forumlink'] = get_forum_link($announcement['fid']);
			$announcement['forumname'] = $forum_cache[$announcement['fid']]['name'];

			$announcement['username'] = htmlspecialchars_uni($announcement['username']);
			$announcement['threadusername'] = htmlspecialchars_uni($announcement['threadusername']);

			if($announcement['uid'] == 0)
			{
				$profilelink = $announcement['threadusername'];
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
				$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
				$icon['path'] = htmlspecialchars_uni($icon['path']);
				$icon['name'] = htmlspecialchars_uni($icon['name']);
				eval("\$icon = \"".$templates->get("portal_announcement_icon")."\";");
			}
			else
			{
				$icon = "&nbsp;";
			}

			$useravatar = format_avatar($announcement['avatar'], $announcement['avatardimensions']);
			eval("\$avatar = \"".$templates->get("portal_announcement_avatar")."\";");

			$anndate = my_date('relative', $announcement['dateline']);

			if($announcement['replies'])
			{
				eval("\$numcomments = \"".$templates->get("portal_announcement_numcomments")."\";");
			}
			else
			{
				eval("\$numcomments = \"".$templates->get("portal_announcement_numcomments_no")."\";");
				$lastcomment = '';
			}

			$senditem = '';
			if($mybb->user['uid'] > 0 && $mybb->usergroup['cansendemail'] == 1)
			{
				eval("\$senditem = \"".$templates->get("portal_announcement_send_item")."\";");
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

			if($mybb->user['showimages'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
			{
				$parser_options['allow_imgcode'] = 0;
			}

			if($mybb->user['showvideos'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestvideos'] != 1 && $mybb->user['uid'] == 0)
			{
				$parser_options['allow_videocode'] = 0;
			}

			$message = $parser->parse_message($announcement['message'], $parser_options);

			$post['attachments'] = '';
			if($mybb->settings['enableattachments'] == 1 && isset($attachcache[$announcement['pid']]) && is_array($attachcache[$announcement['pid']]))
			{ // This post has 1 or more attachments
				$validationcount = 0;
				$id = $announcement['pid'];
				$post['attachmentlist'] = $post['thumblist'] = $post['imagelist'] = $post['attachedthumbs'] = $post['attachedimages'] = '';
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
