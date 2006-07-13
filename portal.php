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

// set the path to your forums directory here (without trailing slash)
$forumdir = "./";

// end editing

if(!chdir($forumdir) && $forumdir)
{
	die("\$forumdir is invalid!");
}

$templatelist = "portal_welcome,portal_welcome_membertext,portal_stats,portal_search,portal_whosonline_memberbit,portal_whosonline,portal_latestthreads_thread_lastpost,portal_latestthreads_thread,portal_latestthreads,portal_announcement_numcomments_no,portal_announcement,portal_announcement_numcomments,portal";

require "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

global $settings, $theme, $templates;

// Load global language phrases
$lang->load("portal");

add_breadcrumb($lang->nav_portal, "portal.php");

// This allows users to login if the portal is stored offsite or in a different directory
if($mybb->input['action'] == "do_login" && $mybb->request_method == "post")
{
	$plugins->run_hooks("portal_do_login_start");

	//Checks to make sure the user can login; they haven't had too many tries at logging in.
	//Is a fatal call if user has had too many tries
	$logins = login_attempt_check();
	$login_text = '';

	if(!username_exists($mybb->input['username']))
	{
		mysetcookie('loginattempts', $logins + 1);
		$db->query("UPDATE ".TABLE_PREFIX."sessions SET loginattempts=loginattempts+1 WHERE sid = '{$session->sid}'");
		if($mybb->settings['failedlogintext'] == "yes")
		{
			$login_text = sprintf($lang->failed_login_again, $mybb->settings['failedlogincount'] - $logins);
		}
		error($lang->error_invalidusername.$login_text);
	}
	$user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
	if(!$user['uid'])
	{
		mysetcookie('loginattempts', $logins + 1);
		$db->query("UPDATE ".TABLE_PREFIX."sessions SET loginattempts=loginattempts+1 WHERE sid = '{$session->sid}'");
		if($mybb->settings['failedlogintext'] == "yes")
		{
			$login_text = sprintf($lang->failed_login_again, $mybb->settings['failedlogincount'] - $logins);
		}
		error($lang->error_invalidpassword.$login_text);
	}

	mysetcookie('loginattempts', 1);
	$db->delete_query(TABLE_PREFIX."sessions", "ip='".$session->ipaddress."' AND sid != '".$session->sid."'");
	$newsession = array(
		"uid" => $user['uid'],
		"loginattempts" => 1,
		);
	$db->update_query(TABLE_PREFIX."sessions", $newsession, "sid='".$session->sid."'");

	// Temporarily set the cookie remember option for the login cookies
	$mybb->user['remember'] = $user['remember'];

	mysetcookie("mybbuser", $user['uid']."_".$user['loginkey']);
	mysetcookie("sid", $session->sid, -1);

	if(function_exists("loggedIn"))
	{
		loggedIn($user['uid']);
	}

	$plugins->run_hooks("portal_do_login_end");

	redirect("portal.php", $lang->redirect_loggedin);
}

$plugins->run_hooks("portal_start");


// get forums user cannot view
$unviewable = get_unviewable_forums();
if($unviewable)
{
	$unviewwhere = " AND fid NOT IN ($unviewable)";
}
// If user is known, welcome them
if($mybb->settings['portal_showwelcome'] != "no")
{
	if($mybb->user['uid'] != 0)
	{
		if($mybb->user['receivepms'] != "no" && $mybb->usergroup['canusepms'] != "no" && $mybb->settings['portal_showpms'] != "no" && $mybb->settings['enablepms'] != "no")
		{
			$query = $db->simple_select("privatemessages", "COUNT(*) AS pms_total, SUM(IF(dateline>'".$mybb->user['lastvisit']."' AND folder='1','1','0')) AS pms_new, SUM(IF(status='0' AND folder='1','1','0')) AS pms_unread", "uid='".$mybb->user['uid']."'");
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
		$query = $db->simple_select("posts", "COUNT(pid) AS newposts", "dateline>'".$mybb->user['lastvisit']."' $unviewwhere");
		$newposts = $db->fetch_field($query, "newposts");
		if($newposts)
		{ // if there aren't any new posts, there is no point in wasting two more queries
			$query = $db->simple_select("threads", "COUNT(tid) AS newthreads", "dateline>'".$mybb->user['lastvisit']."' $unviewwhere");
			$newthreads = $db->fetch_field($query, "newthreads");
			$query = $db->simple_select("threads", "COUNT(tid) AS newann", "dateline>'".$mybb->user['lastvisit']."' AND fid IN (".$mybb->settings['portal_announcementsfid'].") $unviewwhere");
			$newann = $db->fetch_field($query, "newann");
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
			$lang->new_announcements = sprintf($lang->new_announcements, $newann);
		}
		if($newthreads == 1)
		{
			$lang->new_threads = $lang->new_thread;
		}
		else
		{
			$lang->new_threads = sprintf($lang->new_threads, $newthreads);
		}
		if($newposts == 1)
		{
			$lang->new_posts = $lang->new_post;
		}
		else
		{
			$lang->new_posts = sprintf($lang->new_posts, $newposts);
		}
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
if($mybb->settings['portal_showstats'] != "no")
{
	$stats = $cache->read("stats");
	$threadsnum = $stats['numthreads'];
	$postsnum = $stats['numposts'];
	$membersnum = $stats['numusers'];
	if(!$stats['lastusername'])
	{
		$newestmember = "<b>" . $lang->no_one . "</b>";
	}
	else
	{
		$newestmember = build_profile_link($stats['lastusername'], $stats['lastuid']);
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
						$invisiblemark = '';
					}
					$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
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
	$threadlist = '';
	$query = $db->query("
		SELECT t.*, u.username
		FROM ".TABLE_PREFIX."threads t
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
		WHERE 1=1 $unviewwhere AND t.visible='1' AND t.closed NOT LIKE 'moved|%'
		ORDER BY t.lastpost DESC 
		LIMIT 0, ".$mybb->settings['portal_showdiscussionsnum']
	);
	while($thread = $db->fetch_array($query))
	{
		$lastpostdate = mydate($mybb->settings['dateformat'], $thread['lastpost']);
		$lastposttime = mydate($mybb->settings['timeformat'], $thread['lastpost']);
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
		$thread['subject'] = htmlspecialchars_uni($thread['subject']);
		eval("\$threadlist .= \"".$templates->get("portal_latestthreads_thread")."\";");
		$altbg = alt_trow();
	}
	if($threadlist)
	{ // show the table only if there are threads
		eval("\$latestthreads = \"".$templates->get("portal_latestthreads")."\";");
	}
}

// Get latest news announcements
// First validate announcement fids:
$mybb->settings['portal_announcementsfid'] = explode(',', $mybb->settings['portal_announcementsfid']);
foreach($mybb->settings['portal_announcementsfid'] as $fid)
{
	$fid_array[] = intval($fid);
}
$mybb->settings['portal_announcementsfid'] = implode(',', $fid_array);
// And get them!
$query = $db->simple_select("forums", "*", "fid IN (".$mybb->settings['portal_announcementsfid'].")");
while($forumrow = $db->fetch_array($query))
{
    $forum[$forumrow['fid']] = $forumrow;
}

$pids = '';
$comma="";
$query = $db->query("
	SELECT p.pid, p.message, p.tid
	FROM ".TABLE_PREFIX."posts p
	LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
	WHERE t.fid IN (".$mybb->settings['portal_announcementsfid'].") AND t.visible='1' AND t.closed NOT LIKE 'moved|%' AND t.firstpost=p.pid
	ORDER BY t.dateline DESC 
	LIMIT 0, ".$mybb->settings['portal_numannouncements']
);
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

foreach($forum as $fid => $forumrow)
{
    $forumpermissions[$fid] = forum_permissions($fid);
}
$announcements = '';
$query = $db->query("
	SELECT t.*, i.name as iconname, i.path as iconpath, t.username AS threadusername, u.username, u.avatar
	FROM ".TABLE_PREFIX."threads t
	LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid = t.icon)
	LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
	WHERE fid IN (".$mybb->settings['portal_announcementsfid'].") AND t.visible='1' AND t.closed NOT LIKE 'moved|%'
	ORDER BY t.dateline DESC
	LIMIT 0, ".$mybb->settings['portal_numannouncements']
);
while($announcement = $db->fetch_array($query))
{
	$announcement['message'] = $posts[$announcement['tid']]['message'];
	$announcement['pid'] = $posts[$announcement['tid']]['pid'];
	$announcement['author'] = $announcement['uid'];
	if(!$announcement['username'])
	{
		$announcement['username'] = $announcement['threadusername'];
	}
	$announcement['subject'] = htmlspecialchars_uni($announcement['subject']);
	if($announcement['iconpath'])
	{
		$icon = "<img src=\"$announcement[iconpath]\" alt=\"$announcement[iconname]\" />";
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
		$avatar = "<td class=\"trow\" class=\"trow1\" width=1 align=\"center\" valign=\"top\"><img src=\"$announcement[avatar]\" {$avatar_width_height} /></td>";
	}
	else
	{
		$avatar = '';
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
		$lastcomment = '';
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
				if(stripos($announcement['message'], "[attachment=".$attachment['aid']."]") !== false)
				{
					if($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != '')
					{ // We have a thumbnail to show (and its not the "SMALL" enough image
						eval("\$attbit = \"".$templates->get("postbit_attachments_thumbnails_thumbnail")."\";");
					}
					elseif($attachment['thumbnail'] == "SMALL" && $forumpermissions[$announcement['fid']]['candlattachments'] == "yes")
					{
						// Image is small enough to show - no thumbnail
						eval("\$attbit = \"".$templates->get("postbit_attachments_images_image")."\";");
					}
					else
					{
						// Show standard link to attachment
						eval("\$attbit = \"".$templates->get("postbit_attachments_attachment")."\";");
					}
					$announcement['message'] = preg_replace("#\[attachment=".$attachment['aid']."]#si", $attbit, $announcement['message']);
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
						$tcount++;
					}
					elseif($attachment['thumbnail'] == "SMALL" && $forumpermissions[$announcement['fid']]['candlattachments'] == "yes")
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

	$parser_options = array(
		"allow_html" => $forum[$announcement['fid']]['allow_html'],
		"allow_mycode" => $forum[$announcement['fid']]['allow_mycode'],
		"allow_smilies" => $forum[$announcement['fid']]['allowsmilies'],
		"allow_imgcode" => $forum[$announcement['fid']]['allowimgcode']
	);
	if($announcement['smilieoff'] == "yes")
	{
		$parser_options['allow_smilies'] = "no";
	}

	$message = $parser->parse_message($announcement['message'], $parser_options);

	eval("\$announcements .= \"".$templates->get("portal_announcement")."\";");
	unset($post);
}
eval("\$portal = \"".$templates->get("portal")."\";");

$plugins->run_hooks("portal_end");

output_page($portal);

?>
