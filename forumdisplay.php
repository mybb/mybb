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

$templatelist = "forumdisplay,forumdisplay_thread,breadcrumb_bit,forumbit_depth1_cat,forumbit_depth1_forum,forumbit_depth2_cat,forumbit_depth2_forum,forumdisplay_thread_lastpost,forumdisplay_subforums,forumdisplay_threadlist,forumdisplay_moderatedby_moderator,forumdisplay_moderatedby,forumdisplay_newthread,forumdisplay_searchforum,forumdisplay_orderarrow,forumdisplay_thread_rating,forumdisplay_announcement,forumdisplay_threadlist_rating,forumdisplay_threadlist_sortrating,forumdisplay_subforums_modcolumn,forumbit_moderators,forumbit_subforums,forumbit_depth2_forum_lastpost"; 
$templatelist .= ",forumbit_depth1_forum_lastpost,forumdisplay_thread_multipage_page,forumdisplay_thread_multipage,forumdisplay_thread_multipage_more";
$templatelist .= ",multipage_prevpage,multipage_nextpage,multipage_page_current,multipage_page,multipage_start,multipage_end,multipage";
$templatelist .= ",forumjump_advanced,forumjump_special,forumjump_bit";
$templatelist .= ",forumdisplay_usersbrowsing_guests,forumdisplay_usersbrowsing_user,forumdisplay_usersbrowsing,forumdisplay_inlinemoderation,forumdisplay_thread_modbit,forumdisplay_inlinemoderation_col";
$templatelist .= ",forumdisplay_announcements_announcement,forumdisplay_announcements,forumdisplay_threads_sep";
require "./global.php";
require "./inc/functions_post.php";

// Load global language phrases
$lang->load("forumdisplay");

$plugins->run_hooks("forumdisplay_start");

$fid = intval($mybb->input['fid']);
if($mybb->input['fid'] == "index" || $mybb->input['fid'] == "private" || $mybb->input['fid'] == "usercp" || $mybb->input['fid'] == "online" || $mybb->input['fid'] == "search")
{
	header("Location: " . $mybb->input['fid'] . ".php");
	exit;
}

cacheforums();

global $forumcache;

if($forumcache[$fid]['active'] != "no")
{
	$foruminfo = $forumcache[$fid];
}

if(!$foruminfo['fid'])
{
	error($lang->error_invalidforum);
}
$currentitem = $fid;
makeforumnav($fid);
$parentlist = $foruminfo['parentlist'];

$forumpermissions = forum_permissions();
$fpermissions = $forumpermissions[$fid];


// Get Forums
$query = $db->query("SELECT f.*, t.subject AS lastpostsubject FROM ".TABLE_PREFIX."forums f LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.lastposttid) WHERE active!='no' ORDER BY f.pid, f.disporder");
while($forum = $db->fetch_array($query))
{
	$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;

}

// Get forum moderators (we need to do this here so we can show the mods of this forum)
$query = $db->query("SELECT m.uid, m.fid, u.username, u.usergroup, u.displaygroup FROM ".TABLE_PREFIX."moderators m LEFT JOIN ".TABLE_PREFIX."users u ON (m.uid=u.uid) ORDER BY u.username");
while($moderator = $db->fetch_array($query))
{
	$moderatorcache[$moderator['fid']][] = $moderator;
}

$bgcolor = "trow1";
if($mybb->settings['subforumsindex'] != 0)
{
	$showdepth = 3;
}
else
{
	$showdepth =2;
}
$forums = getforums($fid, 1);
if($forums)
{
	$lang->sub_forums_in = sprintf($lang->sub_forums_in, $foruminfo['name']);
	eval("\$subforums =\"".$templates->get("forumdisplay_subforums")."\";");
}

// Expand (or Collapse) forums
if($mybb->input['action'] == "expand")
{
	mysetcookie("fcollapse[$fid]", "");
	$fcollapse[$fid] = "";
}
elseif($mybb->input['action'] == "collapse")
{
	mysetcookie("fcollapse[$fid]", "y");
	$fcollapse[$fid] = "y";
}
$excols = "forumdisplay";

if($fpermissions['canview'] != "yes")
{
	nopermission();
}
if($foruminfo['linkto'])
{
	header("Location: $foruminfo[linkto]");
	exit;
}
// Password protected forums
checkpwforum($fid, $foruminfo['password']);

// Make forum jump...
$forumjump = makeforumjump("", $fid, 1);

if($foruminfo['type'] == "f" && $foruminfo['open'] != "no")
{
	eval("\$newthread = \"".$templates->get("forumdisplay_newthread")."\";");
}
if($fpermissions['cansearch'] != "no" && $foruminfo['type'] == "f")
{
	eval("\$searchforum = \"".$templates->get("forumdisplay_searchforum")."\";");
}

$modcomma = "";
$parentlistexploded = explode(",", $parentlist);
while(list($key, $mfid) = each($parentlistexploded))
{
	if($moderatorcache[$mfid])
	{
		reset($moderatorcache[$mfid]);
		while(list($key2, $moderator) = each($moderatorcache[$mfid]))
		{
			$moderator['username'] = formatname($moderator['username'], $moderator['usergroup'], $moderator['displaygroup']);
			eval("\$modlist .= \"".$templates->get("forumdisplay_moderatedby_moderator", 1, 0)."\";");
			$modcomma=", ";
		}
	}
}

if($modlist)
{
	eval("\$moderatedby = \"".$templates->get("forumdisplay_moderatedby")."\";");
}

// Users browsing this forum..
if($mybb->settings['browsingthisforum'] != "off")
{
	$timecut = time() - $mybb->settings['wolcutoff'];
	$comma = "";
	$guestcount = 0;
	$membercount = 0;
	$inviscount = 0;
	$query = $db->query("SELECT s.ip, s.uid, u.username, s.time, u.invisible, u.usergroup, u.usergroup, u.displaygroup FROM ".TABLE_PREFIX."sessions s LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid) WHERE s.time>'$timecut' AND location1='$fid' ORDER BY u.username");
	while($user = $db->fetch_array($query))
	{
		if($user['uid'] == 0)
		{
			$guestcount++;
		}
		else
		{
			if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
			{
				$doneusers[$user['uid']] = $user['time'];
				$membercount++;
				if($user['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] =="yes")
				{
					if($user['invisible'] == "yes")
					{
						$invisiblemark = "*";
						$inviscount++;
					}
					else
					{
						$invisiblemark = "";
					}
					$user['username'] = formatname($user['username'], $user['usergroup'], $user['displaygroup']);
					eval("\$onlinemembers .= \"".$templates->get("forumdisplay_usersbrowsing_user", 1, 0)."\";");
					$comma = ", ";
				}
			}
		}
	}
	if($guestcount)
	{
		$guestsonline = sprintf($lang->users_browsing_forum_guests, $guestcount);
	}
	if($guestcount && $onlinemembers)
	{
		$onlinesep = ", ";
	}
	if($inviscount && $mybb->usergroup['canviewwolinvis'] != "yes")
	{
		$invisonline = sprintf($lang->users_browsing_forum_invis, $inviscount);
	}
	if($inviscount && $guestcount)
	{
		$onlinesep2 = ", ";
	}
	eval("\$usersbrowsing = \"".$templates->get("forumdisplay_usersbrowsing")."\";");
}

// Do we have any forum rules to show for this forum?
$forumrules = "";
if($foruminfo['rulestype'] != 0 && $foruminfo['rules'])
{
	if(!$foruminfo['rulestitle'])
	{
		$foruminfo['rulestitle'] = sprintf($lang->forum_rules, $foruminfo['name']);
	}
	$foruminfo['rules'] = postify($foruminfo['rules'], "yes", "yes", "yes", "yes");
	if($foruminfo['rulestype'] == 1)
	{
		eval("\$rules = \"".$templates->get("forumdisplay_rules")."\";");
	}
	elseif($foruminfo['rulestype'] == 2)
	{
		eval("\$rules = \"".$templates->get("forumdisplay_rules_link")."\";");
	}
}

$bgcolor = "trow1";

// Set here to fetch only approved topics (and then below for a moderator we change this)
$visibleonly = "AND t.visible='1'";

// Check if the active user is a moderator and get the inline moderation tools
if(ismod($fid) == "yes")
{
	eval("\$inlinemodcol = \"".$templates->get("forumdisplay_inlinemoderation_col")."\";");
	$ismod = true;
	$inlinecount = "0";
	$inlinecookie = "inlinemod_forum".$fid;
	$visibleonly = " AND (t.visible='1' OR t.visible='0')";
}
else
{
	$inlinemod = "";
	$ismod = false;
}

unset($rating);
// Sorting options
if(!$mybb->input['datecut'])
{
	if($mybb->user['daysprune'])
	{
		$datecut = $mybb->user['daysprune'];
	}
	else
	{
		if($foruminfo['daysprune'])
		{
			$datecut = $foruminfo['daysprune'];
		}
		else
		{
			$datecut = "1000";
		}
	}
}
else
{
	$datecut = intval($mybb->input['datecut']);
}
$datecut = intval($datecut);

$datecutsel[$datecut] = "selected=\"selected\"";
if($datecut != "1000")
{
	$checkdate = time() - ($datecut * 86400);
	$datecutsql = "AND t.lastpost >= '$checkdate'";
}
else
{
	$datecutsql = "";
}
switch($mybb->input['order'])
{
	case "asc":
		$sortordernow = "ASC";
        $ordersel['asc'] = "selected=\"selected\"";
		$oppsort = $lang->desc;
		$oppsortnext = "DESC";
		break;
	default:
        $sortordernow = "DESC";
		$ordersel['desc'] = "selected=\"selected\"";
        $oppsort = $lang->asc;
		$oppsortnext = "ASC";
}

switch($mybb->input['sortby'])
{
	case "subject":
		$sortfield = "t.subject";
		break;
	case "replies":
		$sortfield = "t.replies";
		break;
	case "views":
		$sortfield = "t.views";
		break;
	case "starter":
		$sortfield = "t.username";
		break;
	case "rating":
		$sortfield = "averagerating";
		break;
	case "started":
		$sortfield = "t.dateline";
		break;
	default:
		$mybb->input['sortby'] = $sortby = "lastpost";
		$sortfield = "t.lastpost";
		break;
}
$sortby = $mybb->input['sortby'];

$sortsel[$mybb->input['sortby']] = "selected=\"selected\"";

if(isset($mybb->input['page']) && is_numeric($mybb->input['page']))
{
	$sorturl = "forumdisplay.php?fid=$fid&amp;datecut=$datecut&amp;page=".$mybb->input['page'];
}
else
{
	$sorturl = "forumdisplay.php?fid=$fid&amp;datecut=$datecut";
}
eval("\$orderarrow[$sortby] = \"".$templates->get("forumdisplay_orderarrow")."\";");

// Do Multi Pages
$query = $db->query("SELECT COUNT(t.tid) AS threads FROM ".TABLE_PREFIX."threads t WHERE t.fid='$fid' $visibleonly $datecutsql");
$threadcount = $db->result($query, 0);

$perpage = $mybb->settings['threadsperpage'];

if(intval($mybb->input['page']) > 0)
{
	$page = $mybb->input['page'];
	$start = ($page-1) *$perpage;
	$pages = $threadcount / $perpage;
	$pages = ceil($pages);
	if($page > $pages)
	{
		$start = 0;
		$page = 1;
	}
}
else
{
	$start = 0;
	$page = 1;
}
$end = $start + $perpage;
$lower = $start + 1;
$upper = $end;
if($upper > $threadcount)
{
	$upper = $threadcount;
}
$multipage = multipage($threadcount, $perpage, $page, "forumdisplay.php?fid=$fid&sortby=$sortby&order=$order&datecut=$datecut");

if($foruminfo['allowtratings'] != "no")
{
	$ratingadd = "(t.totalratings/t.numratings) AS averagerating, ";
	$lpbackground = "trow2";
	eval("\$ratingcol = \"".$templates->get("forumdisplay_threadlist_rating")."\";");
	eval("\$ratingsort = \"".$templates->get("forumdisplay_threadlist_sortrating")."\";");
	$colspan = "8";
}
else
{
	$ratingadd = "";
	$lpbackground = "trow1";
	$colspan = "7";
}

if($ismod)
{
	$colspan++;
}

// Get Announcements
$limit = "";
if($mybb->settings['announcementlimit'])
{
	$limit = "LIMIT 0, ".$mybb->settings['announcementlimit'];
}
$sql = buildparentlist($fid, "fid", "OR", $parentlist);
$time = time();
$query = $db->query("SELECT a.*, u.username FROM ".TABLE_PREFIX."announcements a LEFT JOIN ".TABLE_PREFIX."users u ON u.uid=a.uid WHERE a.startdate<='$time' AND a.enddate>='$time' AND ($sql OR fid='-1') ORDER BY a.startdate DESC $limit");
while($announcement = $db->fetch_array($query))
{
	if($announcement['startdate'] > $mybb->user['lastvisit'])
	{
		$folder = "newfolder.gif";
	}
	else
	{
		$folder = "folder.gif";
	}
	$announcement['subject'] = htmlspecialchars_uni(dobadwords($announcement['subject']));
	$postdate = mydate($mybb->settings['dateformat'], $announcement['startdate']);
	if($foruminfo['allowtratings'] != "no")
	{
		$thread['rating'] = "pixel.gif";
		eval("\$rating = \"".$templates->get("forumdisplay_thread_rating")."\";");
		$lpbackground = "trow2";
	}
	else
	{
		$rating = "";
		$lpbackground = "trow1";
	}
	if($ismod)
	{
		$modann = "<td align=\"center\" class=\"$bgcolor\">-</td>";
	}
	else
	{
		$modann = "";
	}
	eval("\$announcements  .= \"".$templates->get("forumdisplay_announcements_announcement")."\";");
	if($bgcolor == "trow2")
	{
		$bgcolor = "trow1";
	}
	else
	{
		$bgcolor = "trow2";
	}
}
if($announcements)
{
	eval("\$announcementlist  = \"".$templates->get("forumdisplay_announcements")."\";");
	$shownormalsep = true;
}

// Start Getting Threads
$query = $db->query("SELECT t.*, $ratingadd i.name AS iconname, i.path AS iconpath, t.username AS threadusername, u.username FROM ".TABLE_PREFIX."threads t LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid = t.icon) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid) WHERE t.fid='$fid' $visibleonly $datecutsql ORDER BY t.sticky DESC, $sortfield $sortordernow LIMIT $start, $perpage");
while($thread = $db->fetch_array($query))
{
	$threadcache[$thread['tid']] = $thread;
	$tids[$thread['tid']] = $thread['tid'];
}
if($tids)
{
	$tids = implode(",", $tids);
}

// 'dot' Icons
if($mybb->settings['dotfolders'] != "no" && $mybb->user['uid'] && $threadcache)
{
	$query = $db->query("SELECT tid,uid FROM ".TABLE_PREFIX."posts WHERE uid='".$mybb->user[uid]."' AND tid IN($tids)");
	while($post = $db->fetch_array($query))
	{
		$threadcache[$post['tid']]['doticon'] = 1;
	}
}

// Read threads
if($mybb->user['uid'] && $mybb->settings['threadreadcut'] > 0 && $threadcache)
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threadsread WHERE uid='".$mybb->user[uid]."' AND tid IN($tids)");
	while($readthread = $db->fetch_array($query))
	{
		$threadcache[$readthread['tid']]['lastread'] = $readthread['dateline'];
	}
}

$forumread = mygetarraycookie("forumread", $fid);
if($mybb->user['lastvisit'] > $forumread)
{
	$forumread = $mybb->user['lastvisit'];
}


$unreadpost = 0;
if($threadcache)
{
	foreach($threadcache as $thread)
	{

		$plugins->run_hooks("forumdisplay_thread");

		if($thread['visible'] == 0)
		{
			$bgcolor = "trow_shaded";
		}

		elseif($bgcolor == "trow2")
		{
			$bgcolor = "trow1";
		}
		else
		{
			$bgcolor = "trow2";
		}
		$folder = "";
		$prefix = "";
		
		$thread['author'] = $thread['uid'];
		if(!$thread['username'])
		{
			$thread['username'] = $thread['threadusername'];
			$thread['profilelink'] = $thread['threadusername'];
		}
		else
		{
			$thread['profilelink'] = "<a href=\"".str_replace("{uid}", $thread['uid'], PROFILE_URL)."\">".$thread['username']."</a>";
		}

		$thread['subject'] = htmlspecialchars_uni(dobadwords($thread['subject']));
		if($thread['iconpath'])
		{
			$icon = "<img src=\"$thread[iconpath]\" alt=\"$thread[iconname]\">";
		}
		else
		{
			$icon = "&nbsp;";
		}
		$prefix = "";
		if($thread['poll']) {
			$prefix = $lang->poll_prefix;
		}
		if($thread['sticky'] == "1" && !$donestickysep)
		{
			eval("\$threads .= \"".$templates->get("forumdisplay_sticky_sep")."\";");
			$shownormalsep = true;
			$donestickysep = true;	
		}
		elseif($thread['sticky'] == 0 && $shownormalsep)
		{
			eval("\$threads  .= \"".$templates->get("forumdisplay_threads_sep")."\";");
			$shownormalsep = false;
		}
		
		// Determine the folder
		$folder = "";
		if($thread['doticon'])
		{
			$folder = "dot_";
		}
		$gotounread = "";
		$isnew = 0;
		$donenew = 0;
		$lastread = 0;

		if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'] && $thread['lastpost'] > $forumread)
		{
			$cutoff = time()-$mybb->settings['threadreadcut']*60*60*24;
			if($thread['lastpost'] > $cutoff)
			{
				if($thread['lastread'])
				{
					$lastread = $thread['lastread'];
				}
				else
				{
					$lastread = 1;
				}
			}
		}
		if(!$lastread)
		{
			$readcookie = $threadread = mygetarraycookie("threadread", $thread['tid']);
			if($readcookie > $forumread)
			{
				$lastread = $readcookie;
			}
			else
			{
				$lastread = $forumread;
			}
		}

		if($thread['lastpost'] > $lastread && $lastread)
		{
			$folder .= "new";
			eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
			$unreadpost = 1;
		}

		if($thread['replies'] >= $mybb->settings['hottopic'] || $thread['views'] >= $mybb->settings['hottopicviews']) {
			$folder .= "hot";
		}
		if($thread['closed'] == "yes") {
			$folder .= "lock";
		}
		if($foruminfo['allowtratings'] != "no") {
			$thread['averagerating'] = round($thread['averagerating'], 2);
			$rateimg = intval(round($thread['averagerating']));
			$thread['rating'] = $rateimg."stars.gif";
			$thread['numratings'] = intval($thread['numratings']);
			if($thread['averagerating'] == 0 && $thread['numratings'] == 0) {
				$thread['rating'] = "pixel.gif";
			}
			$ratingvotesav = sprintf($lang->rating_votes_average, $thread['numratings'], $thread['averagerating']);
			eval("\$rating = \"".$templates->get("forumdisplay_thread_rating")."\";");
		} else {
			$rating = "";
		}
		/* Woah, way too many queries here!

		   If we're going to do something like this then we would require another column, using one for visible reply count,
		   and one for invisible replies/posts.

		   Otherwise, it stays as a limitation
		 
		// Recount replies if user is a moderator to take into account unapproved posts.
		if($ismod)
		{
			$query = $db->query("SELECT COUNT(*) AS replies FROM ".TABLE_PREFIX."posts WHERE tid='$thread[tid]'");
			$qarray = $db->fetch_array($query);
			$thread['replies'] = $qarray['replies'] - 1;
		}

		*/

		$thread['pages'] = 0;
		$thread['multipage'] = "";
		$threadpages = "";
		$morelink = "";
		$thread['posts'] = $thread['replies'] + 1;
		if($thread['posts'] > $mybb->settings['postsperpage']) {
			$thread['pages'] = $thread['posts'] / $mybb->settings['postsperpage'];
			$thread['pages'] = ceil($thread['pages']);
			if($thread['pages'] > 4) {
				$pagesstop = 4;
				eval("\$morelink = \"".$templates->get("forumdisplay_thread_multipage_more")."\";");
			} else {
				$pagesstop = $thread['pages'];
			}
			for($i=1;$i<=$pagesstop;$i++) {
				eval("\$threadpages .= \"".$templates->get("forumdisplay_thread_multipage_page")."\";");
			}
			eval("\$thread[multipage] = \"".$templates->get("forumdisplay_thread_multipage")."\";");
		} else {
			$threadpages = "";
			$morelink = "";
			$thread['multipage'] = "";
		}

		if($ismod)
		{
			if(strstr($_COOKIE[$inlinecookie], "|$thread[tid]|"))
			{
				$inlinecheck = "checked=\"checked\"";
				$inlinecount++;
			}
			else
			{
				$inlinecheck = "";
			}
			$multitid = $thread['tid'];
			eval("\$modbit = \"".$templates->get("forumdisplay_thread_modbit")."\";");
		}
		else
		{
			$modbit = "";
		}
		
		$moved = explode("|", $thread['closed']);

		if($moved[0] == "moved") {
			$prefix = $lang->moved_prefix;
			$thread['tid'] = $moved[1];
			$thread['replies'] = "-";
			$thread['views'] = "-";
			$folder .= "lock";
			$gotounread = "";
		}

		$folder .= "folder";

		$lastpostdate = mydate($mybb->settings['dateformat'], $thread['lastpost']);
		$lastposttime = mydate($mybb->settings['timeformat'], $thread['lastpost']);
		$lastposter = $thread['lastposter'];
		$lastposteruid = $thread['lastposter'];
		eval("\$lastpost = \"".$templates->get("forumdisplay_thread_lastpost")."\";");
		$thread['replies'] = mynumberformat($thread['replies']);
		$thread['views'] = mynumberformat($thread['views']);
		eval("\$threads .= \"".$templates->get("forumdisplay_thread")."\";");
	}

	if(!$unreadpost && ($page == 1 || !$page)) // Cheap modification
	{
		mysetarraycookie("forumread", $fid, time());
	}

	if($ismod)
	{
		eval("\$inlinemod = \"".$templates->get("forumdisplay_inlinemoderation")."\";");
	}
}

if($foruminfo['type'] != "c") {
	if(!$threadcount) {
		eval("\$threads = \"".$templates->get("forumdisplay_nothreads")."\";");
	}
	if($foruminfo['password'] != "")
	{
		eval("\$clearstoredpass = \"".$templates->get("forumdisplay_threadlist_clearpass")."\";");
	}
	eval("\$threadslist = \"".$templates->get("forumdisplay_threadlist")."\";");
} else {
	$threadslist = "";
	if($forums == "") {
		error($lang->error_containsnoforums);
	}
}

function getforums($pid="0", $depth=1, $permissions="")
{
	global $fcache, $moderatorcache, $forumpermissions, $settings, $theme, $mybb, $mybbforumread, $mybbuser, $excols, $fcollapse, $templates, $bgcolor, $collapsed, $mybbgroup, $lang, $showdepth;
	if(is_array($fcache[$pid]))
	{
		while(list($key, $main) = each($fcache[$pid]))
		{
			while(list($key, $forum) = each($main))
			{
				$perms = $forumpermissions[$forum['fid']];
				if($perms['canview'] == "yes" || $mybb->settings['hideprivateforums'] == "no")
				{
					if($depth == 3)
					{
						eval("\$forumlisting .= \"".$templates->get("forumbit_depth3", 1, 0)."\";");
						$comma = ", ";
						$donecount++;
						if($donecount == $mybb->settings['subforumsindex'])
						{
							if(count($main) > $donecount)
							{
								$forumlisting .= $comma;
								$forumlisting .= sprintf($lang->more_subforums, (count($main) - $donecount));
							}
							return $forumlisting;
						}
						continue;
					}
					$forumread = mygetarraycookie("forumread", $forum['fid']);
					if($forum['lastpost'] > $mybb->user['lastvisit'] && $forum['lastpost'] > $forumread && $forum['lastpost'] != 0)
					{
						$folder = "on";
						$altonoff = $lang->new_posts;
					}
					else
					{
						$folder = "off";
						$altonoff = $lang->no_new_posts;
					}
					if($forum['open'] == "no")
					{
						$folder = "offlock";
						$altonoff = $lang->forum_locked;
					}
					$forumread = 0;
					if($forum['type'] == "c")
					{
						if($depth == 1)
						{
							$forumcat = "_cat_subforum";
						}
						else
						{
							$forumcat = "_cat";
						}
					}
					else
					{
						$forumcat = "_forum";
					}
					if($forum['type'] == "f" && $forum['linkto'] == "")
					{
						if($forum['lastpost'] == 0 || $forum['lastposter'] == "")
						{
							$lastpost = "<span style=\"text-align: center;\">".$lang->lastpost_never."</span>";
						}
						else
						{
							$lastpostdate = mydate($mybb->settings['dateformat'], $forum['lastpost']);
							$lastposttime = mydate($mybb->settings['timeformat'], $forum['lastpost']);
							$lastposter = $forum['lastposter'];
							$lastposttid = $forum['lastposttid'];
							$lastpostsubject = $fulllastpostsubject = $forum['lastpostsubject'];
							if(strlen($lastpostsubject) > 25)
							{
								$lastpostsubject = substr($lastpostsubject, 0, 25) . "...";
							}
							$lastpostsubject = htmlspecialchars_uni(dobadwords($lastpostsubject));
							$fulllastpostsubject = htmlspecialchars_uni(dobadwords($fulllastpostsubject));
							eval("\$lastpost = \"".$templates->get("forumbit_depth$depth$forumcat"."_lastpost")."\";");
						}
					}
					if($forum['linkto'] != "")
					{
						$lastpost = "<center>-</center>";
						$posts = "-";
						$threads = "-";
					}
					else
					{
						$posts = mynumberformat($forum['posts']);
						$threads = mynumberformat($forum['threads']);
					}
					if($mybb->settings['modlist'] != "off")
					{
						$moderators = "";
						$parentlistexploded = explode(",", $forum['parentlist']);
						$comma = "";
						while(list($key, $mfid) = each($parentlistexploded))
						{
							if($moderatorcache[$mfid])
							{
								reset($moderatorcache[$mfid]);
								while(list($key2, $moderator) = each($moderatorcache[$mfid]))
								{
									$moderators .= "$comma<a href=\"member.php?action=profile&uid=$moderator[uid]\">".$moderator['username']."</a>";
									$comma = ", ";
								}
							}
						}
						if($moderators)
						{
							eval("\$modlist = \"".$templates->get("forumbit_moderators")."\";");
						}
						else
						{
							$modlist = "";
						}
					}
					if($mybb->settings['showdescriptions'] == "no")
					{
						$forum['description'] = "";
					}
					$cname = "cat_".$forum['fid']."_c";
					if($collapsed[$cname] == "display: show;")
					{
						$expcolimage = "collapse_collapsed.gif";
						$expdisplay = "display: none;";
					}
					else
					{
						$expcolimage = "collapse.gif";
					}
					if($fcache[$forum['fid']] && $depth < $showdepth)
					{
						$newdepth = $depth + 1;
						$forums = getforums($forum['fid'], $newdepth, $perms);
						if($depth == 2 && $forums)
						{
							eval("\$subforums = \"".$templates->get("forumbit_subforums")."\";");
							$forums = "";
						}
					}
					if($depth != 2 && !$subforums)
					{
						if($bgcolor == "trow2")
						{
							$bgcolor = "trow1";
						}
						else
						{
							$bgcolor = "trow2";
						}
					}

					eval("\$forumlisting .= \"".$templates->get("forumbit_depth$depth$forumcat")."\";");
				}
				$forums = $subforums = "";
			}
		}
	}
	return $forumlisting;
}
if($rand == 5 && $mybb->settings['threadreadcut'] > 0)
{
	$cut = time()-($mybb->settings['threadreadcut']*60*60*24);
	$db->shutdown_query("DELETE FROM ".TABLE_PREFIX."threadsread WHERE dateline < '$cut'");
}
	
$plugins->run_hooks("forumdisplay_end");

eval("\$forums = \"".$templates->get("forumdisplay")."\";");
outputpage($forums);
?>
