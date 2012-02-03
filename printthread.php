<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: printthread.php 5297 2010-12-28 22:01:14Z Tomm $
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'printthread.php');

$templatelist = "printthread,printthread_post";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("printthread");

$plugins->run_hooks("printthread_start");

$query = $db->query("
	SELECT t.*, p.prefix AS threadprefix, p.displaystyle
	FROM ".TABLE_PREFIX."threads t
	LEFT JOIN ".TABLE_PREFIX."threadprefixes p ON (p.pid=t.prefix)
	WHERE t.tid='".intval($mybb->input['tid'])."' AND t.closed NOT LIKE 'moved|%'
");
$thread = $db->fetch_array($query);

$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

$fid = $thread['fid'];
$tid = $thread['tid'];

// Is the currently logged in user a moderator of this forum?
if(is_moderator($fid))
{
	$ismod = true;
}
else
{
	$ismod = false;
}

// Make sure we are looking at a real thread here.
if(!$tid || ($thread['visible'] == 0 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
{
	error($lang->error_invalidthread);
}

// Get forum info
$forum = get_forum($fid);
if(!$forum)
{
	error($lang->error_invalidforum);
}

$breadcrumb = makeprintablenav();

$parentsexp = explode(",", $forum['parentlist']);
$numparents = count($parentsexp);
$tdepth = "-";
for($i = 0; $i < $numparents; ++$i)
{
	$tdepth .= "-";
}
$forumpermissions = forum_permissions($forum['fid']);

if($forum['type'] != "f")
{
	error($lang->error_invalidforum);
}
if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
{
	error_no_permission();
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

$page = intval($mybb->input['page']);

// Paginate this thread
$perpage = $mybb->settings['postsperpage'];
$postcount = intval($thread['replies'])+1;
$pages = ceil($postcount/$perpage);

if($page > $pages)
{
	$page = 1;
}
if($page > 0)
{
	$start = ($page-1) * $perpage;
}
else
{
	$start = 0;
	$page = 1;
}

if($postcount > $perpage)
{
	$multipage = printthread_multipage($postcount, $perpage, $page, "printthread.php?tid={$tid}");
}

$thread['threadlink'] = get_thread_link($tid);

$postrows = '';
if(is_moderator($forum['fid']))
{
    $visible = "AND (p.visible='0' OR p.visible='1')";
}
else
{
    $visible = "AND p.visible='1'";
}
$query = $db->query("
    SELECT u.*, u.username AS userusername, p.*
    FROM ".TABLE_PREFIX."posts p
    LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
    WHERE p.tid='$tid' {$visible}
    ORDER BY p.dateline
	LIMIT {$start}, {$perpage}
"); 
while($postrow = $db->fetch_array($query))
{
	if($postrow['userusername'])
	{
		$postrow['username'] = $postrow['userusername'];
	}
	$postrow['subject'] = htmlspecialchars_uni($parser->parse_badwords($postrow['subject']));
	$postrow['date'] = my_date($mybb->settings['dateformat'], $postrow['dateline']);
	$postrow['time'] = my_date($mybb->settings['timeformat'], $postrow['dateline']);
	$postrow['profilelink'] = build_profile_link($postrow['username'], $postrow['uid']);
	$parser_options = array(
		"allow_html" => $forum['allowhtml'],
		"allow_mycode" => $forum['allowmycode'],
		"allow_smilies" => $forum['allowsmilies'],
		"allow_imgcode" => $forum['allowimgcode'],
		"allow_videocode" => $forum['allowvideocode'],
		"me_username" => $postrow['username'],
		"shorten_urls" => 0,
		"filter_badwords" => 1
	);
	if($postrow['smilieoff'] == 1)
	{
		$parser_options['allow_smilies'] = 0;
	}

	$postrow['message'] = $parser->parse_message($postrow['message'], $parser_options);
	$plugins->run_hooks("printthread_post");
	eval("\$postrows .= \"".$templates->get("printthread_post")."\";");
}

$plugins->run_hooks("printthread_end");

eval("\$printable = \"".$templates->get("printthread")."\";");
output_page($printable);

function makeprintablenav($pid="0", $depth="--")
{
	global $db, $pforumcache, $fid, $forum, $lang;
	if(!is_array($pforumcache))
	{
		$parlist = build_parent_list($fid, "fid", "OR", $forum['parentlist']);
		$query = $db->simple_select("forums", "name, fid, pid", "$parlist", array('order_by' => 'pid, disporder'));
		while($forumnav = $db->fetch_array($query))
		{
			$pforumcache[$forumnav['pid']][$forumnav['fid']] = $forumnav;
		}
		unset($forumnav);
	}
	if(is_array($pforumcache[$pid]))
	{
		foreach($pforumcache[$pid] as $key => $forumnav)
		{
			$forums .= "+".$depth." $lang->forum {$forumnav['name']} (<i>".$mybb->settings['bburl']."/".get_forum_link($forumnav['fid'])."</i>)<br />\n";
			if($pforumcache[$forumnav['fid']])
			{
				$newdepth = $depth."-";
				$forums .= makeprintablenav($forumnav['fid'], $newdepth);
			}
		}
	}
	return $forums;
}

/**
 * Output multipage navigation.
 *
 * @param int The total number of items.
 * @param int The items per page.
 * @param int The current page.
 * @param string The URL base.
*/
function printthread_multipage($count, $perpage, $page, $url)
{
	global $lang;
	$multipage = "";
	if($count > $perpage)
	{
		$pages = $count / $perpage;
		$pages = ceil($pages);

		for($i = 1; $i <= $pages; ++$i)
		{
			if($i == $page)
			{
				$mppage .= "<strong>$i</strong> ";
			}
			else
			{
				$mppage .= "<a href=\"$url&amp;page=$i\">$i</a> ";
			}
		}
		$multipage = "<div class=\"multipage\">{$lang->pages} <strong>".$lang->archive_pages."</strong> $mppage</div>";
	}
	return $multipage;
}

?>