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

 define("KILL_GLOBALS", 1);

// set the path to your forums directory here (without trailing slash)
$forumdir = "./";

// end editing

if(!chdir($forumdir) && $forumdir)
{
	die("\$forumdir is invalid!");
}

$templatelist = "portal_welcome,portal_welcome_membertext,portal_stats,portal_search,portal_whosonline_memberbit,portal_whosonline,portal_latestthreads_thread_lastpost,portal_latestthreads_thread,portal_latestthreads,portal_announcement_numcomments_no,portal_announcement,portal_announcement_numcomments,portal";

require "./global.php";
require "./inc/functions_post.php";
require "./inc/functions_user.php";

global $settings, $theme, $templates;

// Load global language phrases
$lang->load("portal");

addnav($lang->nav_portal, "portal.php");

// This allows users to login if the portal is stored offsite or in a different directory
if($mybb->input['action'] == "do_login")
{
	if(!username_exists($mybb->input['username']))
	{
		error($lang->error_invalidusername);
	}
	$user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
	if(!$user['uid'])
	{
		error($lang->error_invalidpassword);
	}

	$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE ip='".$session->ipaddress."' AND sid<>'".$session->sid."'");
	$newsession = array(
		"uid" => $user['uid'],
		);
	$db->update_query(TABLE_PREFIX."sessions", $newsession, "sid='".$session->sid."'");
	
	mysetcookie("mybbuser", $user['uid']."_".$user['loginkey']);
	mysetcookie("sid", $session->sid, -1);

	if(function_exists("loggedIn"))
	{
		loggedIn($user['uid']);
	}

	$plugins->run_hooks("logged_in", $user['uid']);

	redirect("portal.php", $lang->redirect_loggedin);
}

$plugins->run_hooks("portal_start");


// get forums user cannot view
$unviewable = getunviewableforums();
if($unviewable)
{
	$unviewwhere = " AND fid NOT IN ($unviewable)";
}
// If user is known, welcome them
if($mybb->settings['portal_showwelcome'] != "no")
{
	if($mybb->user['uid'] != 0)
	{
		if($mybb->user['receivepms'] != "no" && $mybb->usergroup['canusepms'] != "no" && $mybb->settings['portal_showpms'] != "no")
		{
			$query = $db->query("SELECT COUNT(*) AS pms_total, SUM(IF(dateline>'".$mybb->user['lastvisit']."' AND folder='1','1','0')) AS pms_new, SUM(IF(status='0' AND folder='1','1','0')) AS pms_unread FROM ".TABLE_PREFIX."privatemessages WHERE uid='".$mybb->user['uid']."'");
			$messages = $db->fetch_array($query);
			if(!$messages['pms_new'])
			{
				$messages['pms_new'] = 0;
			}
			// the SUM() thing returns "" instead of 0
			if($messages['pms_unread'] == "")
			{
				$messages['pms_unread'] = 0;
			}
			$lang->pms_received_new = sprintf($lang->pms_received_new, $mybb->user['username'], $messages['pms_new']);
			eval("\$pms = \"".$templates->get("portal_pms")."\";");
		}
		// get number of new posts, threads, announcements
		$query = $db->query("SELECT COUNT(pid) AS newposts FROM ".TABLE_PREFIX."posts WHERE dateline>'".$mybb->user['lastvisit']."' $unviewwhere");
		$newposts = $db->result($query, 0);
		if($newposts)
		{ // if there aren't any new posts, there is no point in wasting two more queries
			$query = $db->query("SELECT COUNT(tid) AS newthreads FROM ".TABLE_PREFIX."threads WHERE dateline>'".$mybb->user['lastvisit']."' $unviewwhere");
			$newthreads = $db->result($query, 0);
			$query = $db->query("SELECT COUNT(tid) AS newann FROM ".TABLE_PREFIX."threads WHERE dateline>'".$mybb->user['lastvisit']."' AND fid='".$mybb->settings['portal_announcementsfid']."' $unviewwhere");
			$newann = $db->result($query, 0);
			if(!$newthreads) { $newthreads = 0; }
			if(!$newann) { $newann = 0; }
		}
		else
		{
			$newposts = 0;
			$newthreads = 0;
			$newann = 0;
		}
		$lang->new_announcements = sprintf($lang->new_announcements, $newann);
		$lang->new_threads = sprintf($lang->new_threads, $newthreads);
		$lang->new_posts = sprintf($lang->new_posts, $newposts);
		eval("\$welcometext = \"".$templates->get("portal_welcome_membertext")."\";");

	}
	else
	{
		$lang->guest_welcome_registration = sprintf($lang->guest_welcome_registration, $mybb->settings['bburl'] . '/member.php?action=register');
		$mybb->user['username'] = $lang->guest;
		eval("\$welcometext = \"".$templates->get("portal_welcome_guesttext")."\";");
	}
	$lang->welcome = sprintf($lang->welcome, $mybb->user['username']);
	eval("\$welcome = \"".$templates->get("portal_welcome")."\";");
	if($mybb->user['uid'] == 0)
	{
		$mybb->user['username'] = "";
	}
}
// Get Forum Statistics
if($mybb->settings['portal_showstatsh'] != "no")
{
	$stats = $cache->read("stats");
	$threadsnum = $stats['numthreads'];
	$postsnum = $stats['numposts'];
	$membersnum = $stats['numusers'];
	if(!$stats['lastusername'])
	{
		$newestmember = "<b>no-one</b>";
	}
	else
	{
		$newestmember = "<a href=\"".$mybb->settings[bburl]."/member.php?action=profile&uid=$stats[lastuid]\">$stats[lastusername]</a>";
	}
	eval("\$stats = \"".$templates->get("portal_stats")."\";");
}
// Search box
if($mybb->settings['portal_showsearch'] != "no")
{
	eval("\$search = \"".$templates->get("portal_search")."\";");
}
// Get the online users
if($mybb->settings['portal_showwol'] != "no")
{
	$timesearch = time() - $mybb->settings['wolcutoff'];
	$comma = "";
	$guestcount = 0;
	$membercount = 0;
	$query = $db->query("SELECT s.sid, s.ip, s.uid, s.time, s.location, u.username, u.invisible, u.usergroup, u.displaygroup FROM ".TABLE_PREFIX."sessions s LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid) WHERE s.time>'$timesearch' ORDER BY u.username ASC, s.time DESC");
	while($user = $db->fetch_array($query))
	{
		if($user['uid'] == "0")
		{
			$guestcount++;
		}
		else
		{
			if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
			{
				$doneusers[$user['uid']] = $user['time'];
				$membercount++;
				if($user['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] == "yes")
				{
					if($user['invisible'] == "yes")
					{
						$invisiblemark = "*";
					}
					else
					{
						$invisiblemark = "";
					}
					$user['username'] = formatname($user['username'], $user['usergroup'], $user['displaygroup']);
					eval("\$onlinemembers .= \"".$templates->get("portal_whosonline_memberbit", 1, 0)."\";");
					$comma = ", ";
				}
			}
		}
	}
	$onlinecount = $membercount + $guestcount + $anoncount;

	// Most users online
	$mostonline = $cache->read("mostonline");
	if($onlinecount > $mostonline['numusers'])
	{
		$time = time();
		$mostonline['numusers'] = $onlinecount;
		$mostonline['time'] = $time;
		$cache->update("mostonline", $mostonline);
	}
	$recordcount = $mostonline['numusers'];
	$recorddate = mydate($mybb->settings['dateformat'], $mostonline['time']);
	$recordtime = mydate($mybb->settings['timeformat'], $mostonline['time']);

	$lang->online_users = sprintf($lang->online_users, $onlinecount);
	$lang->online_counts = sprintf($lang->online_counts, $membercount, $guestcount);
	eval("\$whosonline = \"".$templates->get("portal_whosonline")."\";");
}

// Latest forum discussions
if($mybb->settings['portal_showdiscussions'] != "no" && $mybb->settings['portal_showdiscussionsnum'])
{
	$altbg = "trow1";
	$query = $db->query("SELECT t.*, u.username FROM ".TABLE_PREFIX."threads t LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid) WHERE 1=1 $unviewwhere AND t.visible='1' AND t.closed NOT LIKE 'moved|%' ORDER BY t.lastpost DESC  LIMIT 0, ".$mybb->settings['portal_showdiscussionsnum']);
	while($thread = $db->fetch_array($query))
	{

		if($thread['lastpost'] != "" && $thread['lastposter'] != "")
		{
			$lastpostdate = mydate($mybb->settings['dateformat'], $thread['lastpost']);
			$lastposttime = mydate($mybb->settings['timeformat'], $thread['lastpost']);
			eval("\$lastpost = \"".$templates->get("portal_latestthreads_thread_lastpost")."\";");
		}
		else
		{
			$lastpost = "";
		}
		$thread['subject'] = stripslashes($thread['subject']);
		if(strlen($thread['subject']) > 25)
		{
			$thread['subject'] = substr($thread['subject'], 0, 25) . "...";
		}
		$thread['subject'] = htmlspecialchars_uni($thread['subject']);
		eval("\$threadlist .= \"".$templates->get("portal_latestthreads_thread")."\";");
		if($altbg == "trow1")
		{
			$altbg = "trow2";
		}
		else
		{
			$altbg = "trow1";
		}
	}
	if($threadlist)
	{ // show the table only if there are threads
		eval("\$latestthreads = \"".$templates->get("portal_latestthreads")."\";");
	}
}

// Get latest news announcements
$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='".$mybb->settings['portal_announcementsfid']."'");
$forum = $db->fetch_array($query);

$pids = "";
$comma="";
$query = $db->query("SELECT p.pid, p.message, p.tid FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid AND t.dateline=p.dateline) WHERE t.fid='".$mybb->settings['portal_announcementsfid']."' AND t.visible='1' AND t.closed NOT LIKE 'moved|%' ORDER BY t.dateline DESC LIMIT 0, ".$mybb->settings['portal_numannouncements']);
while($getid = $db->fetch_array($query))
{
	$pids .= ",'$getid[pid]'";
	$posts[$getid['tid']] = $getid;
}
$pids = "pid IN(0$pids)";
// Now lets fetch all of the attachments for these posts
$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE $pids");
while($attachment = $db->fetch_array($query))
{
	$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
}

$forumpermissions = forum_permissions($mybb->settings['portal_announcementsfid']);
$query = $db->query("SELECT t.*, i.name as iconname, i.path as iconpath, t.username AS threadusername, u.username, u.avatar FROM ".TABLE_PREFIX."threads t LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid = t.icon) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid) WHERE fid='".$mybb->settings['portal_announcementsfid']."' ORDER BY t.dateline DESC LIMIT 0, ".$mybb->settings['portal_numannouncements']);
while($announcement = $db->fetch_array($query))
{
	$announcement['message'] = $posts[$announcement['tid']]['message'];
	$announcement['pid'] = $posts[$announcement['tid']]['pid'];
	$announcement['author'] = $announcement['uid'];
	if(!$announcement['username'])
	{
		$announcement['username'] = $announcement['threadusername'];
	}
	$announcement['subject'] = htmlspecialchars_uni(stripslashes($announcement['subject']));
	if($announcement['iconpath'])
	{
		$icon = "<img src=\"$announcement[iconpath]\" alt=\"$announcement[iconname]\">";
	}
	else
	{
		$icon = "&nbsp;";
	}
	if($announcement['avatar'] != "")
	{
		$avatar = "<td class=\"trow\" class=\"trow1\" width=1 align=\"center\" valign=\"top\"><img src=\"$announcement[avatar]\"></td>";
	}
	else
	{
		$avatar = "";
	}
	$anndate = mydate($mybb->settings['dateformat'], $announcement['dateline']);
	$anntime = mydate($mybb->settings['timeformat'], $announcement['dateline']);

	if($announcement['replies'])
	{
		eval("\$numcomments = \"".$templates->get("portal_announcement_numcomments")."\";");
	}
	else
	{
		eval("\$numcomments = \"".$templates->get("portal_announcement_numcomments_no")."\";");
		$lastcomment = "";
	}
	if(is_array($attachcache[$announcement['pid']]))
	{ // This post has 1 or more attachments
		$validationcount = 0;
		$id = $announcement['pid'];
		foreach($attachcache[$id] as $aid => $attachment)
		{
			if($attachment['visible'])
			{ // There is an attachment thats visible!
				$attachment['name'] = htmlspecialchars_uni($attachment['name']);
				$attachment['filesize'] = getfriendlysize($attachment['filesize']);
				$ext = getextention($attachment['filename']);
				if($ext == "jpeg" || $ext == "gif" || $ext == "bmp" || $ext == "png" || $ext == "jpg")
				{
					$isimage = true;
				}
				else
				{
					$isimage = false;
				}
				$attachment['icon'] = getattachicon($ext);
				// Support for [attachment=id] code
				if(stripos($announcement['message'], "[attachment=".$attachment['aid']."]") !== false)
				{
					if($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != "")
					{ // We have a thumbnail to show (and its not the "SMALL" enough image
						eval("\$attbit = \"".$templates->get("postbit_attachments_thumbnails_thumbnail")."\";");
					}
					elseif($attachment['thumbnail'] == "SMALL" && $forumpermissions['candlattachments'] == "yes")
					{
						// Image is small enough to show - no thumbnail
						eval("\$attbit .= \"".$templates->get("postbit_attachments_images_image")."\";");
					}
					else
					{
						// Show standard link to attachment
						eval("\$attbit .= \"".$templates->get("postbit_attachments_attachment")."\";");
					}
					$announcement['message'] = preg_replace("#\[attachment=".$attachment['aid']."]#si", $attbit, $announcement['message']);
				}
				else
				{
					if($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != "")
					{ // We have a thumbnail to show
						eval("\$post['thumblist'] .= \"".$templates->get("postbit_attachments_thumbnails_thumbnail")."\";");
						if($tcount == 5)
						{
							$thumblist .= "<br />";
							$tcount = 0;
						}
						$tcount++;
					}
					elseif($attachment['thumbnail'] == "SMALL" && $forumpermissions['candlattachments'] == "yes")
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

	$plugins->run_hooks("portal_announcement");

	$message = postify($announcement['message'], $forum['allowhtml'], $forum['allowmycode'], $forum['allowsmilies'], $forum['allowimgcode']);
	eval("\$announcements .= \"".$templates->get("portal_announcement")."\";");
}
eval("\$portal = \"".$templates->get("portal")."\";");

$plugins->run_hooks("portal_end");

outputpage($portal);

?>
