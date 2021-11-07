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

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_forumlist.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

$orderarrow = ['rating' => '', 'subject' => '', 'starter' => '', 'started' => '', 'replies' => '', 'views' => '', 'lastpost' => ''];

// Load global language phrases
$lang->load("forumdisplay");

$plugins->run_hooks('forumdisplay_start');

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
	$forumsread = [];
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
			$forumsread = [];
		}
 	}

	$query = $db->simple_select("forums", "*", "active != 0", ["order_by" => "pid, disporder"]);
}
else
{
	// Build a forum cache from the database
	$query = $db->query("
        SELECT f.*, fr.dateline AS lastread, u.avatar
        FROM ".TABLE_PREFIX."forums f
        LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
        LEFT JOIN ".TABLE_PREFIX."users u ON (f.lastposteruid = u.uid)
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

$child_forums = build_forumbits($fid, 2);
if(!empty($child_forums) && !empty($child_forums['forum_list']))
{
    $subforums = $child_forums['forum_list'];
}

// No forums available within a category forum, get out
if($foruminfo['type'] == 'c' && empty($subforums))
{
	error($lang->error_containsnoforums);
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

// Gather forum stats
$has_announcements = $hasModTools = false;
$forum_stats = $cache->read("forumsdisplay");

if(is_array($forum_stats))
{
	if(!empty($forum_stats[-1]['modtools']) || !empty($forum_stats[$fid]['modtools']))
	{
		// Mod tools are specific to forums, not parents
		$hasModTools = true;
	}

	if(!empty($forum_stats[-1]['announcements']) || !empty($forum_stats[$fid]['announcements']))
	{
		// Global or forum-specific announcements
		$has_announcements = true;
	}
}

$parentlistexploded = explode(",", $parentlist);
$moderators = [];

foreach($parentlistexploded as $mfid)
{
	// This forum has moderators
	if(isset($moderatorcache[$mfid]) && is_array($moderatorcache[$mfid]))
	{
		// Fetch each moderator from the cache and format it, appending it to the list
		foreach($moderatorcache[$mfid] as $modtype)
		{
			foreach($modtype as $moderator)
			{
				if($moderator['isgroup'])
				{
					$moderators['groups'][$moderator['id']] = $moderator;
				}
				else
				{
					$moderator['profilelink'] = get_profile_link($moderator['id']);
					$moderator['username'] = format_name(htmlspecialchars_uni($moderator['username']), $moderator['usergroup'], $moderator['displaygroup']);

					$moderator['users'][$moderator['id']] = $moderator;
				}
			}
		}
	}

	if(!empty($forum_stats[$mfid]['announcements']))
	{
		$has_announcements = true;
	}
}

// Get the users browsing this forum.
if($mybb->settings['browsingthisforum'] != 0)
{
	$timecut = TIME_NOW - $mybb->settings['wolcutoff'];

	$usersBrowsing = [];
	$usersBrowsingCounter = [];

	$query = $db->simple_select("sessions", "COUNT(DISTINCT ip) AS guestcount", "uid = 0 AND time > $timecut AND location1 = $fid AND nopermission != 1");
	$usersBrowsingCounter['guests'] = $db->fetch_field($query, 'guestcount');

	$query = $db->query("
		SELECT s.ip, s.uid, u.username, u.avatar, s.time, u.invisible, u.usergroup, u.usergroup, u.displaygroup
		FROM
			".TABLE_PREFIX."sessions s
			LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.uid != 0 AND s.time > $timecut AND location1 = $fid AND nopermission != 1
		ORDER BY u.username ASC, s.time DESC
	");

	while($user = $db->fetch_array($query))
	{
        if(empty($usersBrowsing[$user['uid']]) || $usersBrowsing[$user['uid']]['time'] < $user['time'])
		{
			++$usersBrowsingCounter['members'];
			if($user['invisible'] == 1 && $mybb->usergroup['canbeinvisible'] == 1)
			{
				++$usersBrowsingCounter['invisible'];
			}

			if($user['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $mybb->user['uid'])
			{
				$user['username'] = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
				$user['profilelink'] = build_profile_link($user['username'], $user['uid']);
			}
			$usersBrowsing[$user['uid']] = $user;
		}
	}

	// The user was counted as invisible user --> correct the inviscount
	if($mybb->user['invisible'] == 1)
	{
		$usersBrowsingCounter['invisible'] -= 1;
	}
}

// Do we have any forum rules to show for this forum?
if($foruminfo['rulestype'] != 0 && $foruminfo['rules'])
{
	$rules_parser = [
		"allow_html" => 1,
		"allow_mycode" => 1,
		"allow_smilies" => 1,
		"allow_imgcode" => 1
	];

	$foruminfo['rules'] = $parser->parse_message($foruminfo['rules'], $rules_parser);
}

$bgcolor = "trow1";

// Set here to fetch only approved/deleted topics (and then below for a moderator we change this).
$visible_states = array("1");

if($fpermissions['canviewdeletionnotice'] != 0)
{
	$visible_states[] = "-1";
}

// Determine this user's mod permissions
$modpermissions = [];
if(is_moderator($fid))
{
	$modpermissions = [
		'ismod' => true
	];
}

$permissionsToCheck = [
	'caneditposts',
	'canviewdeleted',
	'canviewunapprove',
	'canusecustomtools',
	'canopenclosethreads',
	'canstickunstickthreads',
	'cansoftdeletethreads',
	'canrestorethreads',
	'candeletethreads',
	'canmanagethreads',
	'canapproveunapprovethreads'
];

foreach($permissionsToCheck as $permission)
{
	$modpermissions[$permission] = is_moderator($fid, $permission);
}

// Check if the active user is a moderator and get the inline moderation tools.
if($modpermissions['ismod'])
{
	$inlinecount = 0;
	$inlinecookie = "inlinemod_forum".$fid;

	if(is_moderator($fid, "canviewdeleted") == true)
	{
		$visible_states[] = "-1";
	}
	if(is_moderator($fid, "canviewunapprove") == true)
	{
		$visible_states[] = "0";
	}
}

$visible_condition = "visible IN (".implode(',', array_unique($visible_states)).")";
$visibleonly = "AND ".$visible_condition;

// Allow viewing own unapproved threads for logged in users
if($mybb->user['uid'] && $mybb->settings['showownunapproved'])
{
	$visible_condition .= " OR (t.visible=0 AND t.uid=".(int)$mybb->user['uid'].")";
}

$tvisibleonly = "AND (t.".$visible_condition.")";

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
elseif($tprefix == -1)
{
	$prefixsql = "AND prefix = 0";
	$prefixsql2 = "AND t.prefix = 0";
}
elseif($tprefix == -2)
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

$sorting = [];
switch(my_strtolower($mybb->input['order']))
{
	case "asc":
		$sorting['sortordernow'] = "asc";
		$sorting['oppsort'] = $lang->desc;
		$sorting['oppsortnext'] = "desc";
		break;
	default:
		$sorting['sortordernow'] = "desc";
		$sorting['oppsort'] = $lang->asc;
		$sorting['oppsortnext'] = "asc";
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

$sorting['by'] = htmlspecialchars_uni($mybb->input['sortby']);

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
		$sorting['by'] = "lastpost";
		$sortfield = "lastpost";
		$mybb->input['sortby'] = "lastpost";
		break;
}

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
	$sorting['url'] = get_forum_link($fid, $mybb->input['page']).$string."datecut=$datecut&amp;prefix=$tprefix";
}
else
{
	$sorting['url'] = get_forum_link($fid).$string."datecut=$datecut&amp;prefix=$tprefix";
}

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
	if ($useronly === "" && $datecutsql === "" && $prefixsql === "")
	{
		$threadcount = 0;

		$query = $db->simple_select("forums", "threads, unapprovedthreads, deletedthreads", "fid=".(int)$fid);
		$forum_threads = $db->fetch_array($query);

		if(in_array(1, $visible_states))
		{
			$threadcount += $forum_threads['threads'];
		}

		if(in_array(-1, $visible_states))
		{
			$threadcount += $forum_threads['deletedthreads'];
		}

		if(in_array(0, $visible_states))
		{
			$threadcount += $forum_threads['unapprovedthreads'];
		}
		elseif($mybb->user['uid'] && $mybb->settings['showownunapproved'])
		{
			$query = $db->simple_select("threads t", "COUNT(tid) AS threads", "fid = '$fid' AND t.visible=0 AND t.uid=".(int)$mybb->user['uid']);
			$threadcount += $db->fetch_field($query, "threads");
		}
	}
	else
	{
		$query = $db->simple_select("threads t", "COUNT(tid) AS threads", "fid = '$fid' $tuseronly $tvisibleonly $datecutsql2 $prefixsql2");

		$threadcount = $db->fetch_field($query, "threads");
	}
}

$threadcount = (int)$threadcount;

// How many pages are there?
if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
{
	$mybb->settings['threadsperpage'] = 20;
}

$perpage = $mybb->settings['threadsperpage'];

if($mybb->input['page'] > 0)
{
	$page = $mybb->input['page'];
	$start = ($page - 1) * $perpage;
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

	if((!empty($foruminfo['defaultsortby']) && $sorting['by'] != $foruminfo['defaultsortby']) || (empty($foruminfo['defaultsortby']) && $sorting['by'] != "lastpost"))
	{
		$page_url .= "{$q}{$and}sortby={$sorting['by']}";
		$q = '';
		$and = "&";
	}

	if($sorting['sortordernow'] != "desc")
	{
		$page_url .= "{$q}{$and}order={$sorting['sortordernow']}";
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

$ratingcol = $ratingsort = '';
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
	$colspan = 7;
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
	$colspan = 6;
}

if($modpermissions['ismod'])
{
	++$colspan;
}

// Get Announcements
$announcements = [];
if($has_announcements == true)
{
	$limit = '';
	if($mybb->settings['announcementlimit'])
	{
		$limit = "LIMIT 0, ".$mybb->settings['announcementlimit'];
	}

	$sql = build_parent_list($fid, "fid", "OR", $parentlist);
	$time = TIME_NOW;
	$query = $db->query("
        SELECT a.*, u.username, u.avatar
        FROM ".TABLE_PREFIX."announcements a
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
        WHERE a.startdate<='$time' AND (a.enddate>='$time' OR a.enddate='0') AND ($sql OR fid='-1')
        ORDER BY a.startdate DESC $limit
    ");

	// See if this announcement has been read in our announcement array
	$cookie = [];
	if(isset($mybb->cookies['mybb']['announcements']))
	{
		$cookie = my_unserialize(stripslashes($mybb->cookies['mybb']['announcements']));
	}

	while($announcement = $db->fetch_array($query))
	{
		if($announcement['startdate'] > $mybb->user['lastvisit'] && !$cookie[$announcement['aid']])
		{
			$thread['newclass'] = 'subject_new';
			$thread['folder']['value'] = 'newfolder';
		}
		else
		{
			$thread['newclass'] = 'subject_old';
			$thread['folder']['value'] = 'folder';
		}

		// Mmm, eat those announcement cookies if they're older than our last visit
		if(isset($cookie[$announcement['aid']]) && $cookie[$announcement['aid']] < $mybb->user['lastvisit'])
		{
			unset($cookie[$announcement['aid']]);
		}

		$announcement['announcementlink'] = get_announcement_link($announcement['aid']);
		$announcement['subject'] = $parser->parse_badwords($announcement['subject']);
		$announcement['subject'] = htmlspecialchars_uni($announcement['subject']);
		$announcement['postdate'] = my_date('relative', $announcement['startdate']);

		$announcement['username'] = htmlspecialchars_uni($announcement['username']);

		$announcement['profilelink'] = build_profile_link($announcement['username'], $announcement['uid']);

		$plugins->run_hooks('forumdisplay_announcement');

		$announcements[] = $announcement;
	}

	if($announcements)
	{
		$shownormalsep = true;
	}

	if(empty($cookie))
	{
		// Clean up cookie crumbs
		my_setcookie('mybb[announcements]', 0, (TIME_NOW - (60 * 60 * 24 * 365)));
	}
	elseif(!empty($cookie))
	{
		my_setcookie("mybb[announcements]", addslashes(my_serialize($cookie)), -1);
	}
}

$tids = $threadCache = [];
$icon_cache = $cache->read("posticons");

if($fpermissions['canviewthreads'] != 0)
{
	$plugins->run_hooks('forumdisplay_get_threads');

	// Start Getting Threads
	$query = $db->query("
        SELECT t.*, {$ratingadd}t.username AS threadusername, u.username, u.avatar,
          lastposter.avatar AS last_poster_avatar
        FROM ".TABLE_PREFIX."threads t
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
        LEFT JOIN ".TABLE_PREFIX."users lastposter ON (lastposter.uid = t.lastposteruid)
        WHERE t.fid='$fid' $tuseronly $tvisibleonly $datecutsql2 $prefixsql2
        ORDER BY t.sticky DESC, {$t}{$sortfield} {$sorting['sortordernow']} $sortfield2
        LIMIT $start, $perpage
    ");

	$ratings = false;
	$moved_threads = [];
	while($thread = $db->fetch_array($query))
	{
		$threadCache[$thread['tid']] = $thread;

		if($thread['numratings'] > 0 && $ratings == false)
		{
			$ratings = true; // Looks for ratings in the forum
		}

		$icon_cache[$thread['icon']]['path'] = str_replace('{theme}', $theme['imgdir'], $icon_cache[$thread['icon']]['path']);

		// If this is a moved thread - set the tid for participation marking and thread read marking to that of the moved thread
		if($thread['moved'] != 0)
		{
			$tid = $thread['moved'];
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

	$args = array(
		'threadcache' => &$threadCache,
		'tids' => &$tids
	);

	$plugins->run_hooks("forumdisplay_before_thread", $args);

	if($mybb->settings['allowthreadratings'] != 0 && $foruminfo['allowtratings'] != 0 && $mybb->user['uid'] && !empty($threadCache) && $ratings == true)
	{
		// Check if we've rated threads on this page
		// Guests get the pleasure of not being ID'd, but will be checked when they try and rate
		$imp = implode(",", array_keys($threadCache));
		$query = $db->simple_select("threadratings", "tid, uid", "tid IN ({$imp}) AND uid = '{$mybb->user['uid']}'");

		while($rating = $db->fetch_array($query))
		{
			$threadCache[$rating['tid']]['rated'] = 1;
		}
	}
}

if(!empty($tids))
{
	$tids = implode(",", $tids);
}

// Check participation by the current user in any of these threads - for 'dot' folder icons
if($mybb->settings['dotfolders'] != 0 && $mybb->user['uid'] && !empty($threadCache))
{
	$query = $db->simple_select("posts", "DISTINCT tid,uid", "uid='{$mybb->user['uid']}' AND tid IN ({$tids}) {$visibleonly}");
	while($post = $db->fetch_array($query))
	{
		if(!empty($moved_threads[$post['tid']]))
		{
			$post['tid'] = $moved_threads[$post['tid']];
		}
		if($threadCache[$post['tid']])
		{
			$threadCache[$post['tid']]['doticon'] = 1;
		}
	}
}

// Read threads
if($mybb->user['uid'] && $mybb->settings['threadreadcut'] > 0 && !empty($threadCache))
{
	$query = $db->simple_select("threadsread", "*", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
	while($readthread = $db->fetch_array($query))
	{
		if(!empty($moved_threads[$readthread['tid']]))
		{
			$readthread['tid'] = $moved_threads[$readthread['tid']];
		}
		if($threadCache[$readthread['tid']])
		{
			$threadCache[$readthread['tid']]['lastread'] = $readthread['dateline'];
		}
	}
}

if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'])
{
	$forum_read = 0;
	$query = $db->simple_select("forumsread", "dateline", "fid='{$fid}' AND uid='{$mybb->user['uid']}'");
	if($db->num_rows($query) > 0)
	{
		$forum_read = $db->fetch_field($query, "dateline");
	}

	$read_cutoff = TIME_NOW - $mybb->settings['threadreadcut'] * 60 * 60 * 24;
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

if(!empty($threadCache) && is_array($threadCache))
{
	if(!$mybb->settings['maxmultipagelinks'])
	{
		$mybb->settings['maxmultipagelinks'] = 5;
	}

	if(!$mybb->settings['postsperpage'] || (int)$mybb->settings['postsperpage'] < 1)
	{
		$mybb->settings['postsperpage'] = 20;
	}

	foreach($threadCache as $k => $thread)
	{
		$plugins->run_hooks('forumdisplay_thread');

		if($thread['visible'] == 0)
		{
			$thread['bgcolor'] = "trow_shaded";
		}
		elseif($thread['visible'] == -1 && $modpermissions["canviewdeleted"])
		{
			$thread['bgcolor'] = "trow_shaded trow_deleted";
		}
		else
		{
			$thread['bgcolor'] = alt_trow();
		}

		$thread['author'] = $thread['uid'];
		if(!$thread['username'])
		{
			if(!$thread['threadusername'])
			{
				$thread['username'] = $thread['profilelink'] = $lang->guest;
			}
			else
			{
				$thread['username'] = $thread['profilelink'] = $thread['threadusername'];
			}
		}
		else
		{
			$thread['profilelink'] = build_profile_link($thread['username'], $thread['uid']);
		}

		// If this thread has a prefix, insert a space between prefix and subject
		$thread['threadprefix'] = $threadprefix = '';
		if($thread['prefix'] != 0)
		{
			$threadprefix = build_prefixes($thread['prefix']);
			if(!empty($threadprefix))
			{
				$thread['threadprefix'] = $threadprefix['displaystyle'];
			}
		}

		$thread['subject'] = $parser->parse_badwords($thread['subject']);

		if($mybb->settings['allowthreadratings'] != 0 && $foruminfo['allowtratings'] != 0)
		{
			if($thread['moved'] != 0 || ($fpermissions['canviewdeletionnotice'] != 0 && $thread['visible'] == -1))
			{
				$thread['rating'] = 'moved';
			}
			else
			{
				$thread['averagerating'] = (float)round($thread['averagerating'], 2);
				$thread['width'] = (int)round($thread['averagerating']) * 20;
				$thread['numratings'] = (int)$thread['numratings'];
				$thread['rating'] = 'normal';
			}
		}

		$thread['pages'] = 0;
		$thread['posts'] = $thread['replies'] + 1;
		if($modpermissions["canviewdeleted"] || $modpermissions["canviewunapprove"])
		{
			if($modpermissions["canviewdeleted"])
			{
				$thread['posts'] += $thread['deletedposts'];
			}
			if($modpermissions["canviewunapprove"])
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
		}

		if($modpermissions['ismod'] && isset($mybb->cookies[$inlinecookie]) && my_strpos($mybb->cookies[$inlinecookie], "|{$thread['tid']}|") !== false)
		{
			$thread['modChecked'] = true;
			++$inlinecount;
		}

		if($thread['moved'] != 0)
		{
			$thread['tid'] = $thread['moved'];
		}

		$thread['threadlink'] = get_thread_link($thread['tid']);
		$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");

		// Determine the folder
		$thread['folder'] = [];
		if(isset($thread['doticon']))
		{
			$thread['folder']['value'] = "dot_";
			$thread['folder']['label'] = $lang->icon_dot;
		}

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

		if($thread['lastpost'] > $last_read && $thread['moved'] == 0)
		{
			$thread['unreadpost'] = true;
			$thread['folder']['value'] .= "new";
			$thread['folder']['label'] .= $lang->icon_new;
			$thread['newclass'] = "subject_new";
			$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost");
		}
		else
		{
			$thread['folder']['label'] .= $lang->icon_no_new;
			$thread['newclass'] = "subject_old";
		}

		if($thread['replies'] >= $mybb->settings['hottopic'] || $thread['views'] >= $mybb->settings['hottopicviews'])
		{
			$thread['folder']['value'] .= "hot";
			$thread['folder']['label'] .= $lang->icon_hot;
		}

		if($thread['closed'] == 1)
		{
			$thread['folder']['value'] .= "lock";
			$thread['folder']['label'] .= $lang->icon_lock;
		}

		if($thread['moved'] != 0)
		{
			$thread['folder']['value'] = "move";
		}

		$thread['folder']['value'] .= "folder";

		// If this user is the author of the thread and it is not closed or they are a moderator, they can edit
		if(($thread['uid'] == $mybb->user['uid'] && $thread['closed'] != 1 && $mybb->user['uid'] != 0 && $can_edit_titles == 1) || $modpermissions['ismod'])
		{
			$thread['inlineEditClass'] = "subject_editable";
		}

		$thread['lastpostdate'] = my_date('relative', $thread['lastpost']);

		$thread['last_poster_name'] = $thread['lastposter'];

		if($thread['lastposteruid'] > 0)
		{
			$thread['lastposter'] = build_profile_link($thread['lastposter'], $thread['lastposteruid']);
		}

		$thread['replies'] = my_number_format($thread['replies']);
		$thread['views'] = my_number_format($thread['views']);

		if($thread['unapprovedposts'] > 0 && $modpermissions["canviewunapprove"])
		{
			$thread['unapprovedposts'] = my_number_format($thread['unapprovedposts']);
		}

		$plugins->run_hooks('forumdisplay_thread_end');

		$thread['start_datetime'] = my_date('relative', $thread['dateline']);

		$threadCache[$k] = $thread;
	}

	$customTools = [];
	if($modpermissions['ismod'] && $modpermissions["canusecustomtools"] && $hasModTools)
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
			$customTools[] = $tool;
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
$subAction = 'add';

if($mybb->user['uid'])
{
	$query = $db->simple_select("forumsubscriptions", "fid", "fid='".$fid."' AND uid='{$mybb->user['uid']}'", ['limit' => 1]);

	if($db->num_rows($query) > 0)
	{
		$subAction = 'remove';
	}
}

// Is this a real forum with threads?
if($foruminfo['type'] != "c")
{
	$prefixselect = build_forum_prefix_select($fid, $tprefix);

	// Populate Forumsort
	$forumsort = '';

	if($threadcount > 0)
	{
		eval("\$forumsort = \"".$templates->get("forumdisplay_forumsort")."\";");
	}

	$plugins->run_hooks("forumdisplay_threadlist");
}

$plugins->run_hooks('forumdisplay_end');

$foruminfo['name'] = strip_tags($foruminfo['name']);

output_page(\MyBB\template('forumdisplay/forumdisplay.twig', [
	'foruminfo' => $foruminfo,
	'subforums' => $subforums,
	'fpermissions' => $fpermissions,
	'modpermissions' => $modpermissions,
	'subAction' => $subAction,
	'multipage' => $multipage,
	'threadcount' => $threadcount,
	'announcements' => $announcements,
	'threadCache' => $threadCache,
	'perpage' => $perpage,
	'prefixselect' => $prefixselect,
	'forumjump' => $forumjump,
	'colspan' => $colspan,
	'moderators' => $moderators,
	'usersBrowsing' => $usersBrowsing,
	'usersBrowsingCounter' => $usersBrowsingCounter,
	'iconCache' => $icon_cache,
	'sorting' => $sorting,
	'customTools' => $customTools,
	'hasModTools' => $hasModTools
]));
