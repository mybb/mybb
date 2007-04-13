<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

define("IN_MYBB", 1);

$templatelist = "forumdisplay,forumdisplay_thread,breadcrumb_bit,forumbit_depth1_cat,forumbit_depth1_forum,forumbit_depth2_cat,forumbit_depth2_forum,forumdisplay_subforums,forumdisplay_threadlist,forumdisplay_moderatedby_moderator,forumdisplay_moderatedby,forumdisplay_newthread,forumdisplay_searchforum,forumdisplay_orderarrow,forumdisplay_thread_rating,forumdisplay_announcement,forumdisplay_threadlist_rating,forumdisplay_threadlist_sortrating,forumdisplay_subforums_modcolumn,forumbit_moderators,forumbit_subforums,forumbit_depth2_forum_lastpost";
$templatelist .= ",forumbit_depth1_forum_lastpost,forumdisplay_thread_multipage_page,forumdisplay_thread_multipage,forumdisplay_thread_multipage_more";
$templatelist .= ",multipage_prevpage,multipage_nextpage,multipage_page_current,multipage_page,multipage_start,multipage_end,multipage";
$templatelist .= ",forumjump_advanced,forumjump_special,forumjump_bit";
$templatelist .= ",forumdisplay_usersbrowsing_guests,forumdisplay_usersbrowsing_user,forumdisplay_usersbrowsing,forumdisplay_inlinemoderation,forumdisplay_thread_modbit,forumdisplay_inlinemoderation_col";
$templatelist .= ",forumdisplay_announcements_announcement,forumdisplay_announcements,forumdisplay_threads_sep,forumbit_depth3_statusicon,forumbit_depth3,forumdisplay_sticky_sep,forumdisplay_thread_attachment_count,forumdisplay_threadlist_inlineedit_js,forumdisplay_rssdiscovery";
require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_forumlist.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("forumdisplay");

$plugins->run_hooks("forumdisplay_start");

$fid = intval($mybb->input['fid']);
if($fid < 0)
{
	switch($fid)
	{
		case "-1":
			$location = "index.php";
			break;
		case "-2":
			$location = "search.php";
			break;
		case "-3":
			$location = "usercp.php";
			break;
		case "-4":
			$location = "private.php";
			break;
		case "-5":
			$location = "online.php";
			break;
	}
	if($location)
	{
		header("Location: ".$location);
		exit;
	}
}

// Get forum info
$foruminfo = get_forum($fid);
if(!$foruminfo)
{
	error($lang->error_invalidforum);
}

$archive_url = build_archive_link("forum", $fid);

$currentitem = $fid;
build_forum_breadcrumb($fid);
$parentlist = $foruminfo['parentlist'];

$forumpermissions = forum_permissions();
$fpermissions = $forumpermissions[$fid];

// Get the forums we will need to show.
$query = $db->simple_select("forums", "*", "active != 'no'", array('order_by' => 'pid, disporder'));
// Build a forum cache.
while($forum = $db->fetch_array($query))
{
	$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;

}
$forumpermissions = forum_permissions();

// Get the forum moderators if the setting is enabled.
if($mybb->settings['modlist'] != "off")
{
	$query = $db->query("
		SELECT m.uid, m.fid, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."moderators m
		LEFT JOIN ".TABLE_PREFIX."users u ON (m.uid=u.uid)
		ORDER BY u.username
	");
	
	// Build a moderator cache.
	while($moderator = $db->fetch_array($query))
	{
		$moderatorcache[$moderator['fid']][$moderator['uid']] = $moderator;
	}
}

$bgcolor = "trow1";
if($mybb->settings['subforumsindex'] != 0)
{
	$showdepth = 3;
}
else
{
	$showdepth = 2;
}
$child_forums = build_forumbits($fid, 2);
$forums = $child_forums['forum_list'];
if($forums)
{
	$lang->sub_forums_in = sprintf($lang->sub_forums_in, $foruminfo['name']);
	eval("\$subforums =\"".$templates->get("forumdisplay_subforums")."\";");
}

$excols = "forumdisplay";

if($fpermissions['canview'] != "yes")
{
	error_no_permission();
}

// Password protected forums
check_forum_password($fid, $foruminfo['password']);

if($foruminfo['linkto'])
{
	header("Location: {$foruminfo['linkto']}");
	exit;
}

// Make forum jump...
$forumjump = build_forum_jump("", $fid, 1);

if($foruminfo['type'] == "f" && $foruminfo['open'] != "no")
{
	eval("\$newthread = \"".$templates->get("forumdisplay_newthread")."\";");
}

if($fpermissions['cansearch'] != "no" && $foruminfo['type'] == "f")
{
	eval("\$searchforum = \"".$templates->get("forumdisplay_searchforum")."\";");
}

$modcomma = '';
$modlist = '';
$parentlistexploded = explode(",", $parentlist);
foreach($parentlistexploded as $mfid)
{
	if($moderatorcache[$mfid])
	{
		reset($moderatorcache[$mfid]);
		foreach($moderatorcache[$mfid] as $moderator)
		{
			$moderator['username'] = format_name($moderator['username'], $moderator['usergroup'], $moderator['displaygroup']);
			$moderator['profilelink'] = build_profile_link($moderator['username'], $moderator['uid']);
			eval("\$modlist .= \"".$templates->get("forumdisplay_moderatedby_moderator", 1, 0)."\";");
			$modcomma=", ";
		}
	}
}

if($modlist)
{
	eval("\$moderatedby = \"".$templates->get("forumdisplay_moderatedby")."\";");
}

// Get the users browsing this forum.
if($mybb->settings['browsingthisforum'] != "off")
{
	$timecut = time() - $mybb->settings['wolcutoff'];
	$comma = '';
	$guestcount = 0;
	$membercount = 0;
	$inviscount = 0;
	$onlinemembers = '';
	$query = $db->query("
		SELECT s.ip, s.uid, u.username, s.time, u.invisible, u.usergroup, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."sessions s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.time>'$timecut' AND location1='$fid' AND nopermission!=1
		ORDER BY u.username
	");
	while($user = $db->fetch_array($query))
	{
		if($user['uid'] == 0)
		{
			++$guestcount;
		}
		else
		{
			if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
			{
				$doneusers[$user['uid']] = $user['time'];
				++$membercount;
				if($user['invisible'] == "yes")
				{
					$invisiblemark = "*";
					++$inviscount;
				}
				else
				{
					$invisiblemark = '';
				}
				
				if($user['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] =="yes" || $user['uid'] == $mybb->user['uid'])
				{
					$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
					$user['profilelink'] = build_profile_link($user['username'], $user['uid']);
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
	
	$invisonline = '';
	if($inviscount && $mybb->usergroup['canviewwolinvis'] != "yes" && ($inviscount != 1 && $mybb->user['invisible'] != "yes"))
	{
		$invisonline = sprintf($lang->users_browsing_forum_invis, $inviscount);
	}
	
	if($invisonline != '' && $guestcount)
	{
		$onlinesep2 = ", ";
	}
	eval("\$usersbrowsing = \"".$templates->get("forumdisplay_usersbrowsing")."\";");
}

// Do we have any forum rules to show for this forum?
$forumrules = '';
if($foruminfo['rulestype'] != 0 && $foruminfo['rules'])
{
	if(!$foruminfo['rulestitle'])
	{
		$foruminfo['rulestitle'] = sprintf($lang->forum_rules, $foruminfo['name']);
	}
	
	$rules_parser = array(
		"allow_html" => "yes",
		"allow_mycode" => "yes",
		"allow_smilies" => "yes",
		"allow_imgcode" => "yes"
	);

	$foruminfo['rules'] = $parser->parse_message($foruminfo['rules'], $rules_parser);
	if($foruminfo['rulestype'] == 1)
	{
		eval("\$rules = \"".$templates->get("forumdisplay_rules")."\";");
	}
	else if($foruminfo['rulestype'] == 2)
	{
		eval("\$rules = \"".$templates->get("forumdisplay_rules_link")."\";");
	}
}

$bgcolor = "trow1";

// Set here to fetch only approved topics (and then below for a moderator we change this).
$visibleonly = "AND visible='1'";

// Check if the active user is a moderator and get the inline moderation tools.
if(is_moderator($fid))
{
	eval("\$inlinemodcol = \"".$templates->get("forumdisplay_inlinemoderation_col")."\";");
	$ismod = true;
	$inlinecount = "0";
	$inlinecookie = "inlinemod_forum".$fid;
	$visibleonly = " AND (visible='1' OR visible='0')";
}
else
{
	$inlinemod = '';
	$ismod = false;
}

if(is_moderator($fid, "caneditposts") || $fpermissions['caneditposts'] == "yes")
{
	$can_edit_titles = 1;
}
else
{
	$can_edit_titles = 0;
}

unset($rating);

// Pick out some sorting options.
// First, the date cut for the threads.
if(!$mybb->input['datecut'])
{
	// If the user manually set a date cut, use it.
	if($mybb->user['daysprune'])
	{
		$datecut = $mybb->user['daysprune'];
	}
	else
	{
		// If the forum has a non-default date cut, use it.
		if(!empty($foruminfo['defaultdatecut']))
		{
			$datecut = $foruminfo['defaultdatecut'];
		}
		// Else set the date cut to 9999 days.
		else
		{
			$datecut = 9999;
		}
	}
}
// If there was a manual date cut override, use it.
else
{
	$datecut = intval($mybb->input['datecut']);
}

$datecut = intval($datecut);
$datecutsel[$datecut] = "selected=\"selected\"";
if($datecut != 9999)
{
	$checkdate = time() - ($datecut * 86400);
	$datecutsql = "AND (lastpost >= '$checkdate' OR sticky = '1')";
	$datecutsql2 = "AND (t.lastpost >= '$checkdate' OR t.sticky = '1')";
}
else
{
	$datecutsql = '';
	$datecutsql2 = '';
}

// Pick the sort order.
if(!isset($mybb->input['order']) && !empty($foruminfo['defaultsortorder']))
{
	$mybb->input['order'] = $foruminfo['defaultsortorder'];
}

switch(my_strtolower($mybb->input['order']))
{
	case "asc":
		$sortordernow = "asc";
        $ordersel['asc'] = "selected=\"selected\"";
		$oppsort = $lang->desc;
		$oppsortnext = "desc";
		break;
	default:
        $sortordernow = "desc";
		$ordersel['desc'] = "selected=\"selected\"";
        $oppsort = $lang->asc;
		$oppsortnext = "asc";
		break;
}

// Sort by which field?
if(!isset($mybb->input['sortby']) && !empty($foruminfo['defaultsortby']))
{
	$mybb->input['sortby'] = $foruminfo['defaultsortby'];
}

$sortby = $mybb->input['sortby'];
switch($mybb->input['sortby'])
{
	case "subject":
		$sortfield = "subject";
		break;
	case "replies":
		$sortfield = "replies";
		break;
	case "views":
		$sortfield = "views";
		break;
	case "starter":
		$sortfield = "username";
		break;
	case "rating":
		$sortfield = "averagerating";
		$sortfield2 = ", t.totalratings DESC";
		break;
	case "started":
		$sortfield = "dateline";
		break;
	default:
		$sortby = "lastpost";
		$sortfield = "lastpost";
		break;
}

$sortsel[$mybb->input['sortby']] = "selected=\"selected\"";

// Are we viewing a specific page?
if(isset($mybb->input['page']) && is_numeric($mybb->input['page']))
{
	$sorturl = "forumdisplay.php?fid=$fid&amp;datecut=$datecut&amp;page=".$mybb->input['page'];
}
else
{
	$sorturl = "forumdisplay.php?fid=$fid&amp;datecut=$datecut";
}
eval("\$orderarrow['$sortby'] = \"".$templates->get("forumdisplay_orderarrow")."\";");

// How many pages are there?
$query = $db->simple_select("threads", "COUNT(tid) AS threads", "fid = '$fid' $visibleonly $datecutsql");
$threadcount = $db->fetch_field($query, "threads");

if(!$mybb->settings['threadsperpage'])
{
	$mybb->settings['threadsperpage'] = 20;
}

$perpage = $mybb->settings['threadsperpage'];

if(intval($mybb->input['page']) > 0)
{
	$page = intval($mybb->input['page']);
	$start = ($page-1) * $perpage;
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

// Assemble page URL
if($mybb->input['sortby'] || $mybb->input['order'] || $mybb->input['datecut']) // Ugly URL
{
	$page_url = "forumdisplay.php?fid=$fid&sortby=$sortby&order=$sortordernow&datecut=$datecut";
}
else
{
	$page_url = str_replace("{fid}", $fid, FORUM_URL_PAGED);
}
$multipage = multipage($threadcount, $perpage, $page, $page_url);

if($foruminfo['allowtratings'] != "no")
{
	switch($db->type)
	{
		case "pgsql":
			$ratingadd = "";
			$query = $db->query("
				SELECT t.numratings, t.totalratings, t.tid
				FROM ".TABLE_PREFIX."threads t
				WHERE t.fid='$fid' $visibleonly $datecutsql2
				ORDER BY t.sticky DESC, t.$sortfield $sortordernow $sortfield2
				LIMIT $start, $perpage
			");
			while($thread = $db->fetch_array($query))
			{
				if($thread['totalratings'] == 0)
				{
					$rating = 0;
				}
				else				
				{
					$rating = $thread['totalratings'] / $thread['numratings'];
				}
				
				$avaragerating[$thread['tid']] = $rating;
			}
			break;
		default:
			$ratingadd = "(t.totalratings/t.numratings) AS averagerating, ";
	}
	$lpbackground = "trow2";
	eval("\$ratingcol = \"".$templates->get("forumdisplay_threadlist_rating")."\";");
	eval("\$ratingsort = \"".$templates->get("forumdisplay_threadlist_sortrating")."\";");
	$colspan = "7";
}
else
{
	$ratingadd = '';
	$lpbackground = "trow1";
	$colspan = "6";
}

if($ismod)
{
	++$colspan;
}

// Get Announcements
$limit = '';
$announcements = '';
if($mybb->settings['announcementlimit'])
{
	$limit = "LIMIT 0, ".$mybb->settings['announcementlimit'];
}

$sql = build_parent_list($fid, "fid", "OR", $parentlist);
$time = time();
$query = $db->query("
	SELECT a.*, u.username
	FROM ".TABLE_PREFIX."announcements a
	LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
	WHERE a.startdate<='$time' AND (a.enddate>='$time' OR a.enddate='0') AND ($sql OR fid='-1')
	ORDER BY a.startdate DESC $limit
");
while($announcement = $db->fetch_array($query))
{
	if($announcement['startdate'] > $mybb->user['lastvisit'])
	{
		$new_class = "subject_new";
		$folder = "newfolder.gif";
	}
	else
	{
		$new_class = "";
		$folder = "folder.gif";
	}
	
	$announcement['announcementlink'] = get_announcement_link($announcement['aid']);
	$announcement['profilelink'] = build_profile_link($announcement['uid']);
	$announcement['subject'] = $parser->parse_badwords($announcement['subject']);
	$announcement['subject'] = htmlspecialchars_uni($announcement['subject']);
	$postdate = my_date($mybb->settings['dateformat'], $announcement['startdate']);
	
	if($foruminfo['allowtratings'] != "no")
	{
		$thread['rating'] = "pixel.gif";
		eval("\$rating = \"".$templates->get("forumdisplay_thread_rating")."\";");
		$lpbackground = "trow2";
	}
	else
	{
		$rating = '';
		$lpbackground = "trow1";
	}
	
	if($ismod)
	{
		$modann = "<td align=\"center\" class=\"$bgcolor\">-</td>";
	}
	else
	{
		$modann = '';
	}
	
	eval("\$announcements  .= \"".$templates->get("forumdisplay_announcements_announcement")."\";");
	$bgcolor = alt_trow();
}

if($announcements)
{
	eval("\$announcementlist  = \"".$templates->get("forumdisplay_announcements")."\";");
	$shownormalsep = true;
}

$icon_cache = $cache->read("posticons");

// Start Getting Threads
$query = $db->query("
	SELECT t.*, $ratingadd t.username AS threadusername, u.username
	FROM ".TABLE_PREFIX."threads t
	LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
	WHERE t.fid='$fid' $visibleonly $datecutsql2
	ORDER BY t.sticky DESC, t.$sortfield $sortordernow $sortfield2
	LIMIT $start, $perpage
");
while($thread = $db->fetch_array($query))
{
	if($db->type == "pgsql")
	{
		$thread['averagerating'] = $averagerating[$thread['tid']];
	}
	
	$threadcache[$thread['tid']] = $thread;
	
	// If this is a moved thread - set the tid for participation marking and thread read marking to that of the moved thread
	if(substr($thread['closed'], 0, 5) == "moved")
	{
		$tid = substr($thread['closed'], 6);
		$moved_threads[$tid] = $thread['tid'];
		$tids[$thread['tid']] = $tid;
	}
	// Otherwise - set it to the plain thread ID
	else
	{
		$tids[$thread['tid']] = $thread['tid'];
	}
}

if($tids)
{
	$tids = implode(",", $tids);
}

// Check participation by the current user in any of these threads - for 'dot' folder icons
if($mybb->settings['dotfolders'] != "no" && $mybb->user['uid'] && $threadcache)
{
	$query = $db->simple_select("posts", "tid,uid", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
	while($post = $db->fetch_array($query))
	{
		if($moved_threads[$post['tid']])
		{
			$post['tid'] = $moved_threads[$post['tid']];
		}
		$threadcache[$post['tid']]['doticon'] = 1;
	}
}

// Read threads
if($mybb->user['uid'] && $mybb->settings['threadreadcut'] > 0 && $threadcache)
{
	$query = $db->simple_select("threadsread", "*", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})"); 
	while($readthread = $db->fetch_array($query))
	{
		if($moved_threads[$readthread['tid']]) 
		{ 
	 		$readthread['tid'] = $moved_threads[$readthread['tid']]; 
	 	} 
	 	$threadcache[$readthread['tid']]['lastread'] = $readthread['dateline']; 
	}
}

$forumread = my_get_array_cookie("forumread", $fid);
if($mybb->user['lastvisit'] > $forumread)
{
	$forumread = $mybb->user['lastvisit'];
}


$unreadpost = 0;
$threads = '';
$load_inline_edit_js = 0;
if(is_array($threadcache))
{
	foreach($threadcache as $thread)
	{
		$plugins->run_hooks("forumdisplay_thread");

		if($thread['visible'] == 0)
		{
			$bgcolor = "trow_shaded";
		}
		else
		{
			$bgcolor = alt_trow();
		}
		
		$folder = '';
		$prefix = '';

		$thread['author'] = $thread['uid'];
		if(!$thread['username'])
		{
			$thread['username'] = $thread['threadusername'];
			$thread['profilelink'] = $thread['threadusername'];
		}
		else
		{
			$thread['profilelink'] = build_profile_link($thread['username'], $thread['uid']);
		}
		
		$thread['subject'] = $parser->parse_badwords($thread['subject']);
		$thread['subject'] = htmlspecialchars_uni($thread['subject']);
		$thread['threadlink'] = get_thread_link($thread['tid']);
		$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");
		
		if($thread['icon'] > 0 && $icon_cache[$thread['icon']])
		{
			$icon = $icon_cache[$thread['icon']];
			$icon = "<img src=\"{$icon['path']}\" alt=\"{$icon['name']}\" />";
		}
		else
		{
			$icon = "&nbsp;";
		}
		
		$prefix = '';
		if($thread['poll'])
		{
			$prefix = $lang->poll_prefix;
		}
		
		if($thread['sticky'] == "1" && !$donestickysep)
		{
			eval("\$threads .= \"".$templates->get("forumdisplay_sticky_sep")."\";");
			$shownormalsep = true;
			$donestickysep = true;
		}
		else if($thread['sticky'] == 0 && $shownormalsep)
		{
			eval("\$threads .= \"".$templates->get("forumdisplay_threads_sep")."\";");
			$shownormalsep = false;
		}

		if($foruminfo['allowtratings'] != "no")
		{
			$thread['averagerating'] = round($thread['averagerating'], 2);
			$rateimg = intval(round($thread['averagerating']));
			$thread['rating'] = $rateimg."stars.gif";
			$thread['numratings'] = intval($thread['numratings']);
			
			if($thread['averagerating'] == 0 && $thread['numratings'] == 0)
			{
				$thread['rating'] = "pixel.gif";
			}
			
			$ratingvotesav = sprintf($lang->rating_votes_average, $thread['numratings'], $thread['averagerating']);
			eval("\$rating = \"".$templates->get("forumdisplay_thread_rating")."\";");
		}
		else
		{
			$rating = '';
		}

		$thread['pages'] = 0;
		$thread['multipage'] = '';
		$threadpages = '';
		$morelink = '';
		$thread['posts'] = $thread['replies'] + 1;
		
		if(!$mybb->settings['postsperpage'])
		{
			$mybb->settings['postperpage'] = 20;
		}
		
		if($thread['unapprovedposts'] > 0 && $ismod)
		{
			$thread['posts'] += $thread['unapprovedposts'];
		}
		
		if($thread['posts'] > $mybb->settings['postsperpage'])
		{
			$thread['pages'] = $thread['posts'] / $mybb->settings['postsperpage'];
			$thread['pages'] = ceil($thread['pages']);
			
			if($thread['pages'] > 4)
			{
				$pagesstop = 4;
				$page_link = get_thread_link($thread['tid'], "last");				
				eval("\$morelink = \"".$templates->get("forumdisplay_thread_multipage_more")."\";");
			}
			else
			{
				$pagesstop = $thread['pages'];
			}
			
			for($i = 1; $i <= $pagesstop; ++$i)
			{
				$page_link = get_thread_link($thread['tid'], $i);
				eval("\$threadpages .= \"".$templates->get("forumdisplay_thread_multipage_page")."\";");
			}
			
			eval("\$thread['multipage'] = \"".$templates->get("forumdisplay_thread_multipage")."\";");
		}
		else
		{
			$threadpages = '';
			$morelink = '';
			$thread['multipage'] = '';
		}

		if($ismod)
		{
			if(my_strpos($_COOKIE[$inlinecookie], "|{$thread['tid']}|"))
			{
				$inlinecheck = "checked=\"checked\"";
				++$inlinecount;
			}
			else
			{
				$inlinecheck = '';
			}
			
			$multitid = $thread['tid'];
			eval("\$modbit = \"".$templates->get("forumdisplay_thread_modbit")."\";");
		}
		else
		{
			$modbit = '';
		}

		$moved = explode("|", $thread['closed']);

		if($moved[0] == "moved")
		{
			$prefix = $lang->moved_prefix;
			$thread['tid'] = $moved[1];
			$thread['replies'] = "-";
			$thread['views'] = "-";
		}

		// Determine the folder
		$folder = '';
		$folder_label = '';
		
		if($thread['doticon'])
		{
			$folder = "dot_";
			$folder_label .= $lang->icon_dot;
		}
		
		$gotounread = '';
		$isnew = 0;
		$donenew = 0;
		$lastread = 0;
		
		if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'] && $thread['lastpost'] > $forumread)
		{
			$cutoff = time()-$mybb->settings['threadreadcut']*60*60*24;
		}
		
		if($thread['lastpost'] > $cutoff)
		{
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
			$readcookie = $threadread = my_get_array_cookie("threadread", $thread['tid']); 
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
			$folder_label .= $lang->icon_new;
			$new_class = "subject_new";
			$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost");
			eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
			$unreadpost = 1;
		}
		else
		{
			$folder_label .= $lang->icon_no_new;
			$new_class = "";
		}

		if($thread['replies'] >= $mybb->settings['hottopic'] || $thread['views'] >= $mybb->settings['hottopicviews'])
		{
			$folder .= "hot";
			$folder_label .= $lang->icon_hot;
		}
		
		if($thread['closed'] == "yes")
		{
			$folder .= "lock";
			$folder_label .= $lang->icon_lock;
		}

		if($moved[0] == "moved")
		{
			if($thread['doticon'])
			{
				$folder = "dot_";
				$folder_label .= $lang->icon_dot;
			}
			$folder .= "move";
			$gotounread = '';
		}

		$folder .= "folder";

		$inline_edit_tid = $thread['tid'];

		// If this user is the author of the thread and it is not closed or they are a moderator, they can edit
		if(($thread['uid'] == $mybb->user['uid'] && $thread['closed'] != "yes" && $mybb->user['uid'] != 0 && $can_edit_titles == 1) || $ismod == true)
		{
			$inline_edit_class = "subject_editable";
		}
		else
		{
			$inline_edit_class = "";
		}
		$load_inline_edit_js = 1;

		$lastpostdate = my_date($mybb->settings['dateformat'], $thread['lastpost']);
		$lastposttime = my_date($mybb->settings['timeformat'], $thread['lastpost']);
		$lastposter = $thread['lastposter'];
		$lastposteruid = $thread['lastposteruid'];

		// Don't link to guest's profiles (they have no profile).
		if($lastposteruid == 0)
		{
			$lastposterlink = $lastposter;
		}
		else
		{
			$lastposterlink = build_profile_link($lastposter, $lastposteruid);
		}
		
		$thread['replies'] = my_number_format($thread['replies']);
		$thread['views'] = my_number_format($thread['views']);

		// Threads and posts requiring moderation
		if($thread['visible'] == 0)
		{
			--$thread['unapprovedposts'];
		}
		
		if($thread['unapprovedposts'] > 0 && $ismod)
		{
			if($thread['unapprovedposts'] > 1)
			{
				$unapproved_posts_count = sprintf($lang->thread_unapproved_posts_count, $thread['unapprovedposts']);
			}
			else
			{
				$unapproved_posts_count = sprintf($lang->thread_unapproved_post_count, 1);
			}
			
			$unapproved_posts = " <span title=\"{$unapproved_posts_count}\">(".my_number_format($thread['unapprovedposts']).")</span>";
		}
		else
		{
			$unapproved_posts = '';
		}

		// If this thread has 1 or more attachments show the papperclip
		if($thread['attachmentcount'] > 0)
		{
			if($thread['attachmentcount'] > 1)
			{
				$attachment_count = sprintf($lang->attachment_count_multiple, $thread['attachmentcount']);
			}
			else
			{
				$attachment_count = $lang->attachment_count;
			}
			
			eval("\$attachment_count = \"".$templates->get("forumdisplay_thread_attachment_count")."\";");
		}
		else
		{
			$attachment_count = '';
		}

		eval("\$threads .= \"".$templates->get("forumdisplay_thread")."\";");
	}

	// Set the forum read cookie if all posts are read.
	if($unreadpost == 0 && ($page == 1 || !$page)) // Cheap modification
	{
		my_set_array_cookie("forumread", $fid, time());
	}

	$customthreadtools = '';
	if($ismod)
	{
		switch($db->type)
		{
			case "pgsql":
			case "sqlite3":
			case "sqlite2":
				$query = $db->simple_select("modtools", 'tid, name', "(','||forums||',' LIKE '%,$fid,%' OR ','||forums||',' LIKE '%,-1,%') AND type = 't'");
				break;
			default:
				$query = $db->simple_select("modtools", 'tid, name', "(CONCAT(',',forums,',') LIKE '%,$fid,%' OR CONCAT(',',forums,',') LIKE '%,-1,%') AND type = 't'");
		}
		
		
		while($tool = $db->fetch_array($query))
		{
			eval("\$customthreadtools .= \"".$templates->get("forumdisplay_inlinemoderation_custom_tool")."\";");
		}
		
		if(!empty($customthreadtools))
		{
			eval("\$customthreadtools = \"".$templates->get("forumdisplay_inlinemoderation_custom")."\";");
		}
		eval("\$inlinemod = \"".$templates->get("forumdisplay_inlinemoderation")."\";");
	}
}

// Is this a real forum with threads?
if($foruminfo['type'] != "c")
{
	if(!$threadcount)
	{
		eval("\$threads = \"".$templates->get("forumdisplay_nothreads")."\";");
	}
	
	if($foruminfo['password'] != '')
	{
		eval("\$clearstoredpass = \"".$templates->get("forumdisplay_threadlist_clearpass")."\";");
	}
	
	if($load_inline_edit_js == 1)
	{
		eval("\$inline_edit_js = \"".$templates->get("forumdisplay_threadlist_inlineedit_js")."\";");
	}
	
	$lang->rss_discovery_forum = sprintf($lang->rss_discovery_forum, htmlspecialchars_uni($foruminfo['name']));
	eval("\$rssdiscovery = \"".$templates->get("forumdisplay_rssdiscovery")."\";");
	eval("\$threadslist = \"".$templates->get("forumdisplay_threadlist")."\";");
}
else
{
	$rssdiscovery = '';
	$threadslist = '';
	
	if(empty($forums))
	{
		error($lang->error_containsnoforums);
	}
}

$plugins->run_hooks("forumdisplay_end");

eval("\$forums = \"".$templates->get("forumdisplay")."\";");
output_page($forums);
?>