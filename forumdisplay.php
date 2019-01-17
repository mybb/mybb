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
define('THIS_SCRIPT', 'forumdisplay.php');

$templatelist = "forumdisplay,forumdisplay_thread,forumbit_depth1_cat,forumbit_depth2_cat,forumbit_depth2_forum,forumdisplay_subforums,forumdisplay_threadlist,forumdisplay_moderatedby,forumdisplay_searchforum,forumdisplay_thread_rating,forumdisplay_threadlist_rating";
$templatelist .= ",forumbit_depth1_forum_lastpost,forumdisplay_thread_multipage_page,forumdisplay_thread_multipage,forumdisplay_thread_multipage_more,forumdisplay_thread_gotounread,forumbit_depth2_forum_lastpost,forumdisplay_rules_link,forumdisplay_orderarrow,forumdisplay_newthread";
$templatelist .= ",multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,forumdisplay_thread_unapproved_posts,forumdisplay_nothreads";
$templatelist .= ",forumjump_advanced,forumjump_special,forumjump_bit,forumdisplay_password_wrongpass,forumdisplay_password,forumdisplay_inlinemoderation_custom_tool,forumbit_subforums,forumbit_moderators,forumbit_depth2_forum_lastpost_never,forumbit_depth2_forum_lastpost_hidden";
$templatelist .= ",forumdisplay_usersbrowsing_user,forumdisplay_usersbrowsing,forumdisplay_inlinemoderation,forumdisplay_thread_modbit,forumdisplay_inlinemoderation_col,forumdisplay_inlinemoderation_selectall,forumdisplay_threadlist_clearpass,forumdisplay_thread_rating_moved";
$templatelist .= ",forumdisplay_announcements_announcement,forumdisplay_announcements,forumdisplay_threads_sep,forumbit_depth3_statusicon,forumbit_depth3,forumdisplay_sticky_sep,forumdisplay_thread_attachment_count,forumdisplay_rssdiscovery,forumbit_moderators_group";
$templatelist .= ",forumdisplay_inlinemoderation_openclose,forumdisplay_inlinemoderation_stickunstick,forumdisplay_inlinemoderation_softdelete,forumdisplay_inlinemoderation_restore,forumdisplay_inlinemoderation_delete,forumdisplay_inlinemoderation_manage,forumdisplay_nopermission";
$templatelist .= ",forumbit_depth2_forum_unapproved_posts,forumbit_depth2_forum_unapproved_threads,forumbit_moderators_user,forumdisplay_inlinemoderation_standard,forumdisplay_threadlist_prefixes_prefix,forumdisplay_threadlist_prefixes,forumdisplay_thread_icon,forumdisplay_rules";
$templatelist .= ",forumdisplay_thread_deleted,forumdisplay_announcements_announcement_modbit,forumbit_depth2_forum_viewers,forumdisplay_threadlist_sortrating,forumdisplay_inlinemoderation_custom,forumdisplay_announcement_rating,forumdisplay_inlinemoderation_approveunapprove,forumdisplay_threadlist_subscription";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_forumlist.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

$orderarrow = $sortsel = array('rating' => '', 'subject' => '', 'starter' => '', 'started' => '', 'replies' => '', 'views' => '', 'lastpost' => '');
$ordersel = array('asc' => '', 'desc' => '');
$datecutsel = array(1 => '', 5 => '', 10 => '', 20 => '', 50 => '', 75 => '', 100 => '', 365 => '', 9999 => '');
$rules = '';

// Load global language phrases
$lang->load("forumdisplay");

$plugins->run_hooks("forumdisplay_start");

$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
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

// To validate, turn & to &amp; but support unicode
$foruminfo['name'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $foruminfo['name']);

$forumpermissions = forum_permissions();
$fpermissions = $forumpermissions[$fid];

if($fpermissions['canview'] != 1)
{
	error_no_permission();
}

if($mybb->user['uid'] == 0)
{
	// Cookie'd forum read time
	$forumsread = array();
	if(isset($mybb->cookies['mybb']['forumread']))
	{
		$forumsread = my_unserialize($mybb->cookies['mybb']['forumread']);
	}

 	if(is_array($forumsread) && empty($forumsread))
 	{
 		if(isset($mybb->cookies['mybb']['readallforums']))
		{
			$forumsread[$fid] = $mybb->cookies['mybb']['lastvisit'];
		}
		else
		{
 			$forumsread = array();
		}
 	}

	$query = $db->simple_select("forums", "*", "active != 0", array("order_by" => "pid, disporder"));
}
else
{
	// Build a forum cache from the database
	$query = $db->query("
		SELECT f.*, fr.dateline AS lastread
		FROM ".TABLE_PREFIX."forums f
		LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
		WHERE f.active != 0
		ORDER BY pid, disporder
	");
}

while($forum = $db->fetch_array($query))
{
	if($mybb->user['uid'] == 0 && isset($forumsread[$forum['fid']]))
	{
		$forum['lastread'] = $forumsread[$forum['fid']];
	}

	$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
}

// Get the forum moderators if the setting is enabled.
if($mybb->settings['modlist'] != 0)
{
	$moderatorcache = $cache->read("moderators");
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

$subforums = '';
$child_forums = build_forumbits($fid, 2);
$forums = $child_forums['forum_list'];

if($forums)
{
	$lang->sub_forums_in = $lang->sprintf($lang->sub_forums_in, $foruminfo['name']);
	eval("\$subforums = \"".$templates->get("forumdisplay_subforums")."\";");
}

$excols = "forumdisplay";

// Password protected forums
check_forum_password($foruminfo['fid']);

if($foruminfo['linkto'])
{
	header("Location: {$foruminfo['linkto']}");
	exit;
}

// Make forum jump...
if($mybb->settings['enableforumjump'] != 0)
{
	$forumjump = build_forum_jump("", $fid, 1);
}

if($foruminfo['type'] == "f" && $foruminfo['open'] != 0 && $fpermissions['canpostthreads'] != 0 && $mybb->user['suspendposting'] == 0)
{
	eval("\$newthread = \"".$templates->get("forumdisplay_newthread")."\";");
}

if($fpermissions['cansearch'] != 0 && $foruminfo['type'] == "f")
{
	eval("\$searchforum = \"".$templates->get("forumdisplay_searchforum")."\";");
}

// Gather forum stats
$has_announcements = $has_modtools = false;
$forum_stats = $cache->read("forumsdisplay");

if(is_array($forum_stats))
{
	if(!empty($forum_stats[-1]['modtools']) || !empty($forum_stats[$fid]['modtools']))
	{
		// Mod tools are specific to forums, not parents
		$has_modtools = true;
	}

	if(!empty($forum_stats[-1]['announcements']) || !empty($forum_stats[$fid]['announcements']))
	{
		// Global or forum-specific announcements
		$has_announcements = true;
	}
}

$done_moderators = array(
	"users" => array(),
	"groups" => array()
);

$moderators = '';
$parentlistexploded = explode(",", $parentlist);

foreach($parentlistexploded as $mfid)
{
	// This forum has moderators
	if(is_array($moderatorcache[$mfid]))
	{
		// Fetch each moderator from the cache and format it, appending it to the list
		foreach($moderatorcache[$mfid] as $modtype)
		{
			foreach($modtype as $moderator)
			{
				if($moderator['isgroup'])
				{
					if(in_array($moderator['id'], $done_moderators['groups']))
					{
						continue;
					}

					$moderator['title'] = htmlspecialchars_uni($moderator['title']);

					eval("\$moderators .= \"".$templates->get("forumbit_moderators_group", 1, 0)."\";");
					$done_moderators['groups'][] = $moderator['id'];
				}
				else
				{
					if(in_array($moderator['id'], $done_moderators['users']))
					{
						continue;
					}

					$moderator['profilelink'] = get_profile_link($moderator['id']);
					$moderator['username'] = format_name(htmlspecialchars_uni($moderator['username']), $moderator['usergroup'], $moderator['displaygroup']);

					eval("\$moderators .= \"".$templates->get("forumbit_moderators_user", 1, 0)."\";");
					$done_moderators['users'][] = $moderator['id'];
				}
				$comma = $lang->comma;
			}
		}
	}

	if(!empty($forum_stats[$mfid]['announcements']))
	{
		$has_announcements = true;
	}
}
$comma = '';

// If we have a moderators list, load the template
if($moderators)
{
	eval("\$moderatedby = \"".$templates->get("forumdisplay_moderatedby")."\";");
}
else
{
	$moderatedby = '';
}

// Get the users browsing this forum.
if($mybb->settings['browsingthisforum'] != 0)
{
	$timecut = TIME_NOW - $mybb->settings['wolcutoff'];

	$comma = '';
	$guestcount = 0;
	$membercount = 0;
	$inviscount = 0;
	$onlinemembers = '';
	$doneusers = array();

	$query = $db->query("
		SELECT s.ip, s.uid, u.username, s.time, u.invisible, u.usergroup, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."sessions s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.time > '$timecut' AND location1='$fid' AND nopermission != 1
		ORDER BY u.username ASC, s.time DESC
	");

	while($user = $db->fetch_array($query))
	{
		if($user['uid'] == 0)
		{
			++$guestcount;
		}
		else
		{
			if(empty($doneusers[$user['uid']]) || $doneusers[$user['uid']] < $user['time'])
			{
				$doneusers[$user['uid']] = $user['time'];
				++$membercount;
				if($user['invisible'] == 1)
				{
					$invisiblemark = "*";
					++$inviscount;
				}
				else
				{
					$invisiblemark = '';
				}

				if($user['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $mybb->user['uid'])
				{
					$user['username'] = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
					$user['profilelink'] = build_profile_link($user['username'], $user['uid']);
					eval("\$onlinemembers .= \"".$templates->get("forumdisplay_usersbrowsing_user", 1, 0)."\";");
					$comma = $lang->comma;
				}
			}
		}
	}

	$guestsonline = '';
	if($guestcount)
	{
		$guestsonline = $lang->sprintf($lang->users_browsing_forum_guests, $guestcount);
	}

	$invisonline = '';
	if($mybb->user['invisible'] == 1)
	{
		// the user was counted as invisible user --> correct the inviscount
		$inviscount -= 1;
	}
	if($inviscount && $mybb->usergroup['canviewwolinvis'] != 1)
	{
		$invisonline = $lang->sprintf($lang->users_browsing_forum_invis, $inviscount);
	}


	$onlinesep = '';
	if($invisonline != '' && $onlinemembers)
	{
		$onlinesep = $lang->comma;
	}

	$onlinesep2 = '';
	if($invisonline != '' && $guestcount || $onlinemembers && $guestcount)
	{
		$onlinesep2 = $lang->comma;
	}

	eval("\$usersbrowsing = \"".$templates->get("forumdisplay_usersbrowsing")."\";");
}

// Do we have any forum rules to show for this forum?
$forumrules = '';
if($foruminfo['rulestype'] != 0 && $foruminfo['rules'])
{
	if(!$foruminfo['rulestitle'])
	{
		$foruminfo['rulestitle'] = $lang->sprintf($lang->forum_rules, $foruminfo['name']);
	}

	$rules_parser = array(
		"allow_html" => 1,
		"allow_mycode" => 1,
		"allow_smilies" => 1,
		"allow_imgcode" => 1
	);

	$foruminfo['rules'] = $parser->parse_message($foruminfo['rules'], $rules_parser);
	if($foruminfo['rulestype'] == 1 || $foruminfo['rulestype'] == 3)
	{
		eval("\$rules = \"".$templates->get("forumdisplay_rules")."\";");
	}
	else if($foruminfo['rulestype'] == 2)
	{
		eval("\$rules = \"".$templates->get("forumdisplay_rules_link")."\";");
	}
}

$bgcolor = "trow1";

// Set here to fetch only approved/deleted topics (and then below for a moderator we change this).
if($fpermissions['canviewdeletionnotice'] != 0)
{
	$visibleonly = "AND visible IN (-1,1)";
	$tvisibleonly = "AND t.visible IN (-1,1)";
}
else
{
	$visibleonly = "AND visible='1'";
	$tvisibleonly = "AND t.visible='1'";
}

// Check if the active user is a moderator and get the inline moderation tools.
if(is_moderator($fid))
{
	eval("\$inlinemodcol = \"".$templates->get("forumdisplay_inlinemoderation_col")."\";");
	$ismod = true;
	$inlinecount = "0";
	$inlinemod = '';
	$inlinecookie = "inlinemod_forum".$fid;

	if(is_moderator($fid, "canviewdeleted") == true || is_moderator($fid, "canviewunapprove") == true)
	{
		if(is_moderator($fid, "canviewunapprove") == true && is_moderator($fid, "canviewdeleted") == false)
		{
			$visibleonly = "AND visible IN (0,1)";
			$tvisibleonly = "AND t.visible IN (0,1)";
		}
		elseif(is_moderator($fid, "canviewdeleted") == true && is_moderator($fid, "canviewunapprove") == false)
		{
			$visibleonly = "AND visible IN (-1,1)";
			$tvisibleonly = "AND t.visible IN (-1,1)";
		}
		else
		{
			$visibleonly = " AND visible IN (-1,0,1)";
			$tvisibleonly = " AND t.visible IN (-1,0,1)";
		}
	}
}
else
{
	$inlinemod = $inlinemodcol = '';
	$ismod = false;
}

if(is_moderator($fid, "caneditposts") || $fpermissions['caneditposts'] == 1)
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
$datecut = 9999;
if(empty($mybb->input['datecut']))
{
	// If the user manually set a date cut, use it.
	if(!empty($mybb->user['daysprune']))
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
	}
}
// If there was a manual date cut override, use it.
else
{
	$datecut = $mybb->get_input('datecut', MyBB::INPUT_INT);
}

$datecutsel[(int)$datecut] = ' selected="selected"';
if($datecut > 0 && $datecut != 9999)
{
	$checkdate = TIME_NOW - ($datecut * 86400);
	$datecutsql = "AND (lastpost >= '$checkdate' OR sticky = '1')";
	$datecutsql2 = "AND (t.lastpost >= '$checkdate' OR t.sticky = '1')";
}
else
{
	$datecutsql = '';
	$datecutsql2 = '';
}

// Sort by thread prefix
$tprefix = $mybb->get_input('prefix', MyBB::INPUT_INT);
if($tprefix > 0)
{
	$prefixsql = "AND prefix = {$tprefix}";
	$prefixsql2 = "AND t.prefix = {$tprefix}";
}
else if($tprefix == -1)
{
	$prefixsql = "AND prefix = 0";
	$prefixsql2 = "AND t.prefix = 0";
}
else if($tprefix == -2)
{
	$prefixsql = "AND prefix != 0";
	$prefixsql2 = "AND t.prefix != 0";
}
else
{
	$prefixsql = $prefixsql2 = '';
}

// Pick the sort order.
if(!isset($mybb->input['order']) && !empty($foruminfo['defaultsortorder']))
{
	$mybb->input['order'] = $foruminfo['defaultsortorder'];
}
else
{
	$mybb->input['order'] = $mybb->get_input('order');
}

$mybb->input['order'] = htmlspecialchars_uni($mybb->get_input('order'));

switch(my_strtolower($mybb->input['order']))
{
	case "asc":
		$sortordernow = "asc";
        $ordersel['asc'] = ' selected="selected"';
		$oppsort = $lang->desc;
		$oppsortnext = "desc";
		break;
	default:
        $sortordernow = "desc";
		$ordersel['desc'] = ' selected="selected"';
        $oppsort = $lang->asc;
		$oppsortnext = "asc";
		break;
}

// Sort by which field?
if(!isset($mybb->input['sortby']) && !empty($foruminfo['defaultsortby']))
{
	$mybb->input['sortby'] = $foruminfo['defaultsortby'];
}
else
{
	$mybb->input['sortby'] = $mybb->get_input('sortby');
}

$t = 't.';
$sortfield2 = '';

$sortby = htmlspecialchars_uni($mybb->input['sortby']);

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
		$t = "";
		$sortfield = "averagerating";
		$sortfield2 = ", t.totalratings DESC";
		break;
	case "started":
		$sortfield = "dateline";
		break;
	default:
		$sortby = "lastpost";
		$sortfield = "lastpost";
		$mybb->input['sortby'] = "lastpost";
		break;
}

$sortsel['rating'] = ''; // Needs to be initialized in order to speed-up things. Fixes #2031
$sortsel[$mybb->input['sortby']] = ' selected="selected"';

// Pick the right string to join the sort URL
if($mybb->seo_support == true)
{
	$string = "?";
}
else
{
	$string = "&amp;";
}

// Are we viewing a specific page?
$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);
if($mybb->input['page'] > 1)
{
	$sorturl = get_forum_link($fid, $mybb->input['page']).$string."datecut=$datecut&amp;prefix=$tprefix";
}
else
{
	$sorturl = get_forum_link($fid).$string."datecut=$datecut&amp;prefix=$tprefix";
}

eval("\$orderarrow['$sortby'] = \"".$templates->get("forumdisplay_orderarrow")."\";");

$threadcount = 0;
$useronly = $tuseronly = "";
if(isset($fpermissions['canonlyviewownthreads']) && $fpermissions['canonlyviewownthreads'] == 1)
{
	$useronly = "AND uid={$mybb->user['uid']}";
	$tuseronly = "AND t.uid={$mybb->user['uid']}";
}

if($fpermissions['canviewthreads'] != 0)
{
	// How many threads are there?
	$query = $db->simple_select("threads", "COUNT(tid) AS threads", "fid = '$fid' $useronly $visibleonly $datecutsql $prefixsql");
	$threadcount = $db->fetch_field($query, "threads");
}

// How many pages are there?
if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
{
	$mybb->settings['threadsperpage'] = 20;
}

$perpage = $mybb->settings['threadsperpage'];

if($mybb->input['page'] > 0)
{
	$page = $mybb->input['page'];
	$start = ($page-1) * $perpage;
	$pages = $threadcount / $perpage;
	$pages = ceil($pages);
	if($page > $pages || $page <= 0)
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
if($mybb->input['sortby'] || $mybb->input['order'] || $mybb->input['datecut'] || $mybb->input['prefix']) // Ugly URL
{
	$page_url = str_replace("{fid}", $fid, FORUM_URL_PAGED);

	if($mybb->seo_support == true)
	{
		$q = "?";
		$and = '';
	}
	else
	{
		$q = '';
		$and = "&";
	}

	if((!empty($foruminfo['defaultsortby']) && $sortby != $foruminfo['defaultsortby']) || (empty($foruminfo['defaultsortby']) && $sortby != "lastpost"))
	{
		$page_url .= "{$q}{$and}sortby={$sortby}";
		$q = '';
		$and = "&";
	}

	if($sortordernow != "desc")
	{
		$page_url .= "{$q}{$and}order={$sortordernow}";
		$q = '';
		$and = "&";
	}

	if($datecut > 0 && $datecut != 9999)
	{
		$page_url .= "{$q}{$and}datecut={$datecut}";
		$q = '';
		$and = "&";
	}

	if($tprefix != 0)
	{
		$page_url .= "{$q}{$and}prefix={$tprefix}";
	}
}
else
{
	$page_url = str_replace("{fid}", $fid, FORUM_URL_PAGED);
}
$multipage = multipage($threadcount, $perpage, $page, $page_url);

if($mybb->settings['allowthreadratings'] != 0 && $foruminfo['allowtratings'] != 0 && $fpermissions['canviewthreads'] != 0)
{
	$lang->load("ratethread");

	switch($db->type)
	{
		case "pgsql":
			$ratingadd = "CASE WHEN t.numratings=0 THEN 0 ELSE t.totalratings/t.numratings::numeric END AS averagerating, ";
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
	if($sortfield == "averagerating")
	{
		$t = "t.";
		$sortfield = "lastpost";
	}
	$ratingadd = '';
	$lpbackground = "trow1";
	$colspan = "6";
}

if($ismod)
{
	++$colspan;
}

// Get Announcements
$announcementlist = '';
if($has_announcements == true)
{
	$limit = '';
	$announcements = '';
	if($mybb->settings['announcementlimit'])
	{
		$limit = "LIMIT 0, ".$mybb->settings['announcementlimit'];
	}

	$sql = build_parent_list($fid, "fid", "OR", $parentlist);
	$time = TIME_NOW;
	$query = $db->query("
		SELECT a.*, u.username
		FROM ".TABLE_PREFIX."announcements a
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
		WHERE a.startdate<='$time' AND (a.enddate>='$time' OR a.enddate='0') AND ($sql OR fid='-1')
		ORDER BY a.startdate DESC $limit
	");

	// See if this announcement has been read in our announcement array
	$cookie = array();
	if(isset($mybb->cookies['mybb']['announcements']))
	{
		$cookie = my_unserialize(stripslashes($mybb->cookies['mybb']['announcements']));
	}

	$announcementlist = '';
	$bgcolor = alt_trow(true); // Reset the trow colors
	while($announcement = $db->fetch_array($query))
	{
		if($announcement['startdate'] > $mybb->user['lastvisit'] && !$cookie[$announcement['aid']])
		{
			$new_class = ' class="subject_new"';
			$folder = "newfolder";
		}
		else
		{
			$new_class = ' class="subject_old"';
			$folder = "folder";
		}

		// Mmm, eat those announcement cookies if they're older than our last visit
		if(isset($cookie[$announcement['aid']]) && $cookie[$announcement['aid']] < $mybb->user['lastvisit'])
		{
			unset($cookie[$announcement['aid']]);
		}

		$announcement['announcementlink'] = get_announcement_link($announcement['aid']);
		$announcement['subject'] = $parser->parse_badwords($announcement['subject']);
		$announcement['subject'] = htmlspecialchars_uni($announcement['subject']);
		$postdate = my_date('relative', $announcement['startdate']);

		$announcement['username'] = htmlspecialchars_uni($announcement['username']);

		$announcement['profilelink'] = build_profile_link($announcement['username'], $announcement['uid']);

		if($mybb->settings['allowthreadratings'] != 0 && $foruminfo['allowtratings'] != 0 && $fpermissions['canviewthreads'] != 0)
		{
			eval("\$rating = \"".$templates->get("forumdisplay_announcement_rating")."\";");
			$lpbackground = "trow2";
		}
		else
		{
			$rating = '';
			$lpbackground = "trow1";
		}

		if($ismod)
		{
			eval("\$modann = \"".$templates->get("forumdisplay_announcements_announcement_modbit")."\";");
		}
		else
		{
			$modann = '';
		}

		$plugins->run_hooks("forumdisplay_announcement");
		eval("\$announcements .= \"".$templates->get("forumdisplay_announcements_announcement")."\";");
		$bgcolor = alt_trow();
	}

	if($announcements)
	{
		eval("\$announcementlist = \"".$templates->get("forumdisplay_announcements")."\";");
		$shownormalsep = true;
	}

	if(empty($cookie))
	{
		// Clean up cookie crumbs
		my_setcookie('mybb[announcements]', 0, (TIME_NOW - (60*60*24*365)));
	}
	else if(!empty($cookie))
	{
		my_setcookie("mybb[announcements]", addslashes(my_serialize($cookie)), -1);
	}
}
else
{
	$announcementlist = '';
}

$tids = $threadcache = array();
$icon_cache = $cache->read("posticons");

if($fpermissions['canviewthreads'] != 0)
{
	$plugins->run_hooks("forumdisplay_get_threads");

	// Allow viewing unapproved threads for logged in users
	if($mybb->user['uid'] && $mybb->settings['showownunapproved'])
	{
		$tvisibleonly .= " OR (t.fid='$fid' AND t.uid=".$mybb->user['uid'].")";
	}

	// Start Getting Threads
	$query = $db->query("
		SELECT t.*, {$ratingadd}t.username AS threadusername, u.username
		FROM ".TABLE_PREFIX."threads t
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
		WHERE t.fid='$fid' $tuseronly $tvisibleonly $datecutsql2 $prefixsql2
		ORDER BY t.sticky DESC, {$t}{$sortfield} $sortordernow $sortfield2
		LIMIT $start, $perpage
	");

	$ratings = false;
	$moved_threads = array();
	while($thread = $db->fetch_array($query))
	{
		$threadcache[$thread['tid']] = $thread;

		if($thread['numratings'] > 0 && $ratings == false)
		{
			$ratings = true; // Looks for ratings in the forum
		}

		// If this is a moved thread - set the tid for participation marking and thread read marking to that of the moved thread
		if(substr($thread['closed'], 0, 5) == "moved")
		{
			$tid = substr($thread['closed'], 6);
			if(!isset($tids[$tid]))
			{
				$moved_threads[$tid] = $thread['tid'];
				$tids[$thread['tid']] = $tid;
			}
		}
		// Otherwise - set it to the plain thread ID
		else
		{
			$tids[$thread['tid']] = $thread['tid'];
			if(isset($moved_threads[$thread['tid']]))
			{
				unset($moved_threads[$thread['tid']]);
			}
		}
	}

	if($mybb->settings['allowthreadratings'] != 0 && $foruminfo['allowtratings'] != 0 && $mybb->user['uid'] && !empty($threadcache) && $ratings == true)
	{
		// Check if we've rated threads on this page
		// Guests get the pleasure of not being ID'd, but will be checked when they try and rate
		$imp = implode(",", array_keys($threadcache));
		$query = $db->simple_select("threadratings", "tid, uid", "tid IN ({$imp}) AND uid = '{$mybb->user['uid']}'");

		while($rating = $db->fetch_array($query))
		{
			$threadcache[$rating['tid']]['rated'] = 1;
		}
	}
}

// If user has moderation tools available, prepare the Select All feature
$selectall = '';
if(is_moderator($fid) && $threadcount > $perpage)
{
	$lang->page_selected = $lang->sprintf($lang->page_selected, count($threadcache));
	$lang->select_all = $lang->sprintf($lang->select_all, (int)$threadcount);
	$lang->all_selected = $lang->sprintf($lang->all_selected, (int)$threadcount);
	eval("\$selectall = \"".$templates->get("forumdisplay_inlinemoderation_selectall")."\";");
}

if(!empty($tids))
{
	$tids = implode(",", $tids);
}

// Check participation by the current user in any of these threads - for 'dot' folder icons
if($mybb->settings['dotfolders'] != 0 && $mybb->user['uid'] && !empty($threadcache))
{
	$query = $db->simple_select("posts", "DISTINCT tid,uid", "uid='{$mybb->user['uid']}' AND tid IN ({$tids}) {$visibleonly}");
	while($post = $db->fetch_array($query))
	{
		if(!empty($moved_threads[$post['tid']]))
		{
			$post['tid'] = $moved_threads[$post['tid']];
		}
		if($threadcache[$post['tid']])
		{
			$threadcache[$post['tid']]['doticon'] = 1;
		}
	}
}

// Read threads
if($mybb->user['uid'] && $mybb->settings['threadreadcut'] > 0 && !empty($threadcache))
{
	$query = $db->simple_select("threadsread", "*", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
	while($readthread = $db->fetch_array($query))
	{
		if(!empty($moved_threads[$readthread['tid']]))
		{
	 		$readthread['tid'] = $moved_threads[$readthread['tid']];
	 	}
		if($threadcache[$readthread['tid']])
		{
	 		$threadcache[$readthread['tid']]['lastread'] = $readthread['dateline'];
		}
	}
}

if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'])
{
	$query = $db->simple_select("forumsread", "dateline", "fid='{$fid}' AND uid='{$mybb->user['uid']}'");
	$forum_read = $db->fetch_field($query, "dateline");

	$read_cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
	if($forum_read == 0 || $forum_read < $read_cutoff)
	{
		$forum_read = $read_cutoff;
	}
}
else
{
	$forum_read = my_get_array_cookie("forumread", $fid);

	if(isset($mybb->cookies['mybb']['readallforums']) && !$forum_read)
	{
		$forum_read = $mybb->cookies['mybb']['lastvisit'];
	}
}

$unreadpost = 0;
$threads = '';
if(!empty($threadcache) && is_array($threadcache))
{
	if(!$mybb->settings['maxmultipagelinks'])
	{
		$mybb->settings['maxmultipagelinks'] = 5;
	}

	if(!$mybb->settings['postsperpage'] || (int)$mybb->settings['postsperpage'] < 1)
	{
		$mybb->settings['postsperpage'] = 20;
	}

	foreach($threadcache as $thread)
	{
		$plugins->run_hooks("forumdisplay_thread");

		$moved = explode("|", $thread['closed']);

		if($thread['visible'] == 0)
		{
			$bgcolor = "trow_shaded";
		}
		elseif($thread['visible'] == -1 && is_moderator($fid, "canviewdeleted"))
		{
			$bgcolor = "trow_shaded trow_deleted";
		}
		else
		{
			$bgcolor = alt_trow();
		}

		if($thread['sticky'] == 1)
		{
			$thread_type_class = " forumdisplay_sticky";
		}
		else
		{
			$thread_type_class = " forumdisplay_regular";
		}

		$folder = '';
		$prefix = '';

		$thread['author'] = $thread['uid'];
		if(!$thread['username'])
		{
			if(!$thread['threadusername'])
			{
				$thread['username'] = $thread['profilelink'] = htmlspecialchars_uni($lang->guest);
			}
			else
			{
				$thread['username'] = $thread['profilelink'] = htmlspecialchars_uni($thread['threadusername']);
			}
		}
		else
		{
			$thread['username'] = htmlspecialchars_uni($thread['username']);
			$thread['profilelink'] = build_profile_link($thread['username'], $thread['uid']);
		}

		// If this thread has a prefix, insert a space between prefix and subject
		$thread['threadprefix'] = $threadprefix = '';
		if($thread['prefix'] != 0)
		{
			$threadprefix = build_prefixes($thread['prefix']);
			if(!empty($threadprefix))
			{
				$thread['threadprefix'] = $threadprefix['displaystyle'].'&nbsp;';
			}
		}

		$thread['subject'] = $parser->parse_badwords($thread['subject']);
		$thread['subject'] = htmlspecialchars_uni($thread['subject']);

		if($thread['icon'] > 0 && $icon_cache[$thread['icon']])
		{
			$icon = $icon_cache[$thread['icon']];
			$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
			$icon['path'] = htmlspecialchars_uni($icon['path']);
			$icon['name'] = htmlspecialchars_uni($icon['name']);
			eval("\$icon = \"".$templates->get("forumdisplay_thread_icon")."\";");
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

		if($thread['sticky'] == "1" && !isset($donestickysep))
		{
			eval("\$threads .= \"".$templates->get("forumdisplay_sticky_sep")."\";");
			$shownormalsep = true;
			$donestickysep = true;
		}
		else if($thread['sticky'] == 0 && !empty($shownormalsep))
		{
			eval("\$threads .= \"".$templates->get("forumdisplay_threads_sep")."\";");
			$shownormalsep = false;
		}

		$rating = '';
		if($mybb->settings['allowthreadratings'] != 0 && $foruminfo['allowtratings'] != 0)
		{
			if($moved[0] == "moved" || ($fpermissions['canviewdeletionnotice'] != 0 && $thread['visible'] == -1))
			{
				eval("\$rating = \"".$templates->get("forumdisplay_thread_rating_moved")."\";");
			}
			else
			{
				$thread['averagerating'] = (float)round($thread['averagerating'], 2);
				$thread['width'] = (int)round($thread['averagerating'])*20;
				$thread['numratings'] = (int)$thread['numratings'];

				$not_rated = '';
				if(!isset($thread['rated']) || empty($thread['rated']))
				{
					$not_rated = ' star_rating_notrated';
				}

				$ratingvotesav = $lang->sprintf($lang->rating_votes_average, $thread['numratings'], $thread['averagerating']);
				eval("\$rating = \"".$templates->get("forumdisplay_thread_rating")."\";");
			}
		}

		$thread['pages'] = 0;
		$thread['multipage'] = '';
		$threadpages = '';
		$morelink = '';
		$thread['posts'] = $thread['replies'] + 1;
		if(is_moderator($fid, "canviewdeleted") == true || is_moderator($fid, "canviewunapprove") == true)
		{
			if(is_moderator($fid, "canviewdeleted") == true)
			{
				$thread['posts'] += $thread['deletedposts'];
			}
			if(is_moderator($fid, "canviewunapprove") == true)
			{
				$thread['posts'] += $thread['unapprovedposts'];
			}
		}
		elseif($fpermissions['canviewdeletionnotice'] != 0)
		{
			$thread['posts'] += $thread['deletedposts'];
		}

		if($thread['posts'] > $mybb->settings['postsperpage'])
		{
			$thread['pages'] = $thread['posts'] / $mybb->settings['postsperpage'];
			$thread['pages'] = ceil($thread['pages']);

			if($thread['pages'] > $mybb->settings['maxmultipagelinks'])
			{
				$pagesstop = $mybb->settings['maxmultipagelinks'] - 1;
				$page_link = get_thread_link($thread['tid'], $thread['pages']);
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
			if(isset($mybb->cookies[$inlinecookie]) && my_strpos($mybb->cookies[$inlinecookie], "|{$thread['tid']}|") !== false)
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

		if($moved[0] == "moved")
		{
			$prefix = $lang->moved_prefix;
			$thread['tid'] = $moved[1];
			$thread['replies'] = "-";
			$thread['views'] = "-";
		}

		$thread['threadlink'] = get_thread_link($thread['tid']);
		$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");

		// Determine the folder
		$folder = '';
		$folder_label = '';

		if(isset($thread['doticon']))
		{
			$folder = "dot_";
			$folder_label .= $lang->icon_dot;
		}

		$gotounread = '';
		$isnew = 0;
		$donenew = 0;

		if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'] && $thread['lastpost'] > $forum_read)
		{
			if(!empty($thread['lastread']))
			{
				$last_read = $thread['lastread'];
			}
			else
			{
				$last_read = $read_cutoff;
			}
		}
		else
		{
			$last_read = my_get_array_cookie("threadread", $thread['tid']);
		}

		if($forum_read > $last_read)
		{
			$last_read = $forum_read;
		}

		if($thread['lastpost'] > $last_read && $moved[0] != "moved")
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
			$new_class = "subject_old";
		}

		if($thread['replies'] >= $mybb->settings['hottopic'] || $thread['views'] >= $mybb->settings['hottopicviews'])
		{
			$folder .= "hot";
			$folder_label .= $lang->icon_hot;
		}

		if($thread['closed'] == 1)
		{
			$folder .= "close";
			$folder_label .= $lang->icon_close;
		}

		if($moved[0] == "moved")
		{
			$folder = "move";
			$gotounread = '';
		}

		$folder .= "folder";

		$inline_edit_tid = $thread['tid'];

		// If this user is the author of the thread and it is not closed or they are a moderator, they can edit
		$inline_edit_class = '';
		if(($thread['uid'] == $mybb->user['uid'] && $thread['closed'] != 1 && $mybb->user['uid'] != 0 && $can_edit_titles == 1) || $ismod == true)
		{
			$inline_edit_class = "subject_editable";
		}


		$lastposteruid = $thread['lastposteruid'];
		if(!$lastposteruid && !$thread['lastposter'])
		{
			$lastposter = htmlspecialchars_uni($lang->guest);
		}
		else
		{
			$lastposter = htmlspecialchars_uni($thread['lastposter']);
		}
		$lastpostdate = my_date('relative', $thread['lastpost']);

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
		if($thread['unapprovedposts'] > 0 && is_moderator($fid, "canviewunapprove"))
		{
			if($thread['unapprovedposts'] > 1)
			{
				$unapproved_posts_count = $lang->sprintf($lang->thread_unapproved_posts_count, $thread['unapprovedposts']);
			}
			else
			{
				$unapproved_posts_count = $lang->sprintf($lang->thread_unapproved_post_count, 1);
			}

			$thread['unapprovedposts'] = my_number_format($thread['unapprovedposts']);
			eval("\$unapproved_posts = \"".$templates->get("forumdisplay_thread_unapproved_posts")."\";");
		}
		else
		{
			$unapproved_posts = '';
		}

		// If this thread has 1 or more attachments show the papperclip
		if($mybb->settings['enableattachments'] == 1 && $thread['attachmentcount'] > 0)
		{
			if($thread['attachmentcount'] > 1)
			{
				$attachment_count = $lang->sprintf($lang->attachment_count_multiple, $thread['attachmentcount']);
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

		$plugins->run_hooks("forumdisplay_thread_end");

		if($fpermissions['canviewdeletionnotice'] != 0 && $thread['visible'] == -1 && !is_moderator($fid, "canviewdeleted"))
		{
			eval("\$threads .= \"".$templates->get("forumdisplay_thread_deleted")."\";");
		}
		else
		{
			eval("\$threads .= \"".$templates->get("forumdisplay_thread")."\";");
		}
	}

	$customthreadtools = $standardthreadtools = '';
	if($ismod)
	{
		if(is_moderator($fid, "canusecustomtools") && $has_modtools == true)
		{
			$gids = explode(',', $mybb->user['additionalgroups']);
			$gids[] = $mybb->user['usergroup'];
			$gids = array_filter(array_unique($gids));

			$gidswhere = '';
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					foreach($gids as $gid)
					{
						$gid = (int)$gid;
						$gidswhere .= " OR ','||groups||',' LIKE '%,{$gid},%'";
					}
					$query = $db->simple_select("modtools", 'tid, name', "(','||forums||',' LIKE '%,$fid,%' OR ','||forums||',' LIKE '%,-1,%' OR forums='') AND (groups='' OR ','||groups||',' LIKE '%,-1,%'{$gidswhere}) AND type = 't'");
					break;
				default:
					foreach($gids as $gid)
					{
						$gid = (int)$gid;
						$gidswhere .= " OR CONCAT(',',`groups`,',') LIKE '%,{$gid},%'";
					}
					$query = $db->simple_select("modtools", 'tid, name', "(CONCAT(',',forums,',') LIKE '%,$fid,%' OR CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='') AND (`groups`='' OR CONCAT(',',`groups`,',') LIKE '%,-1,%'{$gidswhere}) AND type = 't'");
					break;
			}

			while($tool = $db->fetch_array($query))
			{
				$tool['name'] = htmlspecialchars_uni($tool['name']);
				eval("\$customthreadtools .= \"".$templates->get("forumdisplay_inlinemoderation_custom_tool")."\";");
			}

			if($customthreadtools)
			{
				eval("\$customthreadtools = \"".$templates->get("forumdisplay_inlinemoderation_custom")."\";");
			}
		}

		$inlinemodopenclose = $inlinemodstickunstick = $inlinemodsoftdelete = $inlinemodrestore = $inlinemoddelete = $inlinemodmanage = $inlinemodapproveunapprove = '';

		if(is_moderator($fid, "canopenclosethreads"))
		{
			eval("\$inlinemodopenclose = \"".$templates->get("forumdisplay_inlinemoderation_openclose")."\";");
		}

		if(is_moderator($fid, "canstickunstickthreads"))
		{
			eval("\$inlinemodstickunstick = \"".$templates->get("forumdisplay_inlinemoderation_stickunstick")."\";");
		}

		if(is_moderator($fid, "cansoftdeletethreads"))
		{
			eval("\$inlinemodsoftdelete = \"".$templates->get("forumdisplay_inlinemoderation_softdelete")."\";");
		}

		if(is_moderator($fid, "canrestorethreads"))
		{
			eval("\$inlinemodrestore = \"".$templates->get("forumdisplay_inlinemoderation_restore")."\";");
		}

		if(is_moderator($fid, "candeletethreads"))
		{
			eval("\$inlinemoddelete = \"".$templates->get("forumdisplay_inlinemoderation_delete")."\";");
		}

		if(is_moderator($fid, "canmanagethreads"))
		{
			eval("\$inlinemodmanage = \"".$templates->get("forumdisplay_inlinemoderation_manage")."\";");
		}

		if(is_moderator($fid, "canapproveunapprovethreads"))
		{
			eval("\$inlinemodapproveunapprove = \"".$templates->get("forumdisplay_inlinemoderation_approveunapprove")."\";");
		}

		if(!empty($inlinemodopenclose) || !empty($inlinemodstickunstick) || !empty($inlinemodsoftdelete) || !empty($inlinemodrestore) || !empty($inlinemoddelete) || !empty($inlinemodmanage) || !empty($inlinemodapproveunapprove))
		{
			eval("\$standardthreadtools = \"".$templates->get("forumdisplay_inlinemoderation_standard")."\";");
		}

		// Only show inline mod menu if there's options to show
		if(!empty($standardthreadtools) || !empty($customthreadtools))
		{
			eval("\$inlinemod = \"".$templates->get("forumdisplay_inlinemoderation")."\";");
		}
	}
}

// If there are no unread threads in this forum and no unread child forums - mark it as read
require_once MYBB_ROOT."inc/functions_indicators.php";

$unread_threads = fetch_unread_count($fid);
if($unread_threads !== false && $unread_threads == 0 && empty($unread_forums))
{
	mark_forum_read($fid);
}

// Subscription status
$add_remove_subscription = 'add';
$add_remove_subscription_text = $lang->subscribe_forum;
$addremovesubscription = '';

if($mybb->user['uid'])
{
	$query = $db->simple_select("forumsubscriptions", "fid", "fid='".$fid."' AND uid='{$mybb->user['uid']}'", array('limit' => 1));

	if($db->fetch_field($query, 'fid'))
	{
		$add_remove_subscription = 'remove';
		$add_remove_subscription_text = $lang->unsubscribe_forum;
	}

	eval("\$addremovesubscription = \"".$templates->get("forumdisplay_threadlist_subscription")."\";");
}

$inline_edit_js = $clearstoredpass = '';

// Is this a real forum with threads?
if($foruminfo['type'] != "c")
{
	if($fpermissions['canviewthreads'] != 1)
	{
		eval("\$threads = \"".$templates->get("forumdisplay_nopermission")."\";");
	}

	if(!$threadcount && $fpermissions['canviewthreads'] == 1)
	{
		eval("\$threads = \"".$templates->get("forumdisplay_nothreads")."\";");
	}

	$clearstoredpass = '';
	if($foruminfo['password'] != '')
	{
		eval("\$clearstoredpass = \"".$templates->get("forumdisplay_threadlist_clearpass")."\";");
	}

	$prefixselect = build_forum_prefix_select($fid, $tprefix);

	$lang->rss_discovery_forum = $lang->sprintf($lang->rss_discovery_forum, htmlspecialchars_uni(strip_tags($foruminfo['name'])));
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

$foruminfo['name'] = strip_tags($foruminfo['name']);

eval("\$forums = \"".$templates->get("forumdisplay")."\";");
output_page($forums);
