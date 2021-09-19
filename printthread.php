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
define('THIS_SCRIPT', 'printthread.php');

$templatelist = "printthread,printthread_post,printthread_nav,forumdisplay_password_wrongpass,forumdisplay_password,printthread_multipage,printthread_multipage_page,printthread_multipage_page_current";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("printthread");

$plugins->run_hooks("printthread_start");

$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));

if(!$thread || $thread['visible'] == -1)
{
	error($lang->error_invalidthread);
}

$plugins->run_hooks("printthread_start");

$thread['threadprefix'] = $thread['displaystyle'] = '';
if($thread['prefix'])
{
	$threadprefix = build_prefixes($thread['prefix']);
	if(!empty($threadprefix))
	{
		$thread['threadprefix'] = $threadprefix['prefix'];
		$thread['displaystyle'] = $threadprefix['displaystyle'];
	}
}

$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

$fid = $thread['fid'];
$tid = $thread['tid'];

// Is the currently logged in user a moderator of this forum?
$ismod = is_moderator($fid);

// Make sure we are looking at a real thread here.
if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
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
if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
{
	error_no_permission();
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

$page = $mybb->get_input('page', MyBB::INPUT_INT);

// Paginate this thread
if(!$mybb->settings['postsperpage'] || (int)$mybb->settings['postsperpage'] < 1)
{
	$mybb->settings['postsperpage'] = 20;
}
$perpage = $mybb->settings['postsperpage'];
$postcount = (int)$thread['replies']+1;
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
else
{
	$multipage = '';
}

$thread['threadlink'] = get_thread_link($tid);

$postrows = '';
if(is_moderator($forum['fid'], "canviewunapprove"))
{
	$visible = "AND (p.visible='0' OR p.visible='1')";
}
else
{
	$visible = "AND p.visible='1'";
}

$postrow_cache = $attachcache = array();

$query = $db->query("
	SELECT u.*, u.username AS userusername, p.*
	FROM ".TABLE_PREFIX."posts p
	LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
	WHERE p.tid='$tid' {$visible}
	ORDER BY p.dateline, p.pid
	LIMIT {$start}, {$perpage}
");

while($postrow = $db->fetch_array($query))
{
	$postrow_cache[$postrow['pid']] = $postrow;
}

$postrow_cache = array_filter($postrow_cache);

$pids = implode("','", array_keys($postrow_cache));

// Get the attachments for all posst.
if($mybb->settings['enableattachments'])
{
	$queryAttachments = $db->simple_select("attachments", "*", "pid IN ('{$pids}')");

	while($attachment = $db->fetch_array($queryAttachments))
	{
		$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
	}
}

foreach($postrow_cache as $postrow)
{
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

	if($mybb->user['uid'] != 0 && $mybb->user['showimages'] != 1 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
	{
		$parser_options['allow_imgcode'] = 0;
	}

	if($mybb->user['uid'] != 0 && $mybb->user['showvideos'] != 1 || $mybb->settings['guestvideos'] != 1 && $mybb->user['uid'] == 0)
	{
		$parser_options['allow_videocode'] = 0;
	}

	if($postrow['userusername'])
	{
		$postrow['username'] = $postrow['userusername'];
	}
	$postrow['username'] = htmlspecialchars_uni($postrow['username']);
	$postrow['subject'] = htmlspecialchars_uni($parser->parse_badwords($postrow['subject']));
	$postrow['date'] = my_date($mybb->settings['dateformat'], $postrow['dateline'], null, 0);
	$postrow['profilelink'] = build_profile_link($postrow['username'], $postrow['uid']);

	$postrow['message'] = $parser->parse_message($postrow['message'], $parser_options);

	if($mybb->settings['enableattachments'] == 1 && !empty($attachcache[$postrow['pid']]) && $thread['attachmentcount'] > 0 || is_moderator($fid, 'caneditposts'))
	{
		get_post_attachments($postrow['pid'], $postrow);
	}

	$plugins->run_hooks("printthread_post");
	eval("\$postrows .= \"".$templates->get("printthread_post")."\";");
}

$plugins->run_hooks("printthread_end");

eval("\$printable = \"".$templates->get("printthread")."\";");
output_page($printable);

/**
 * @param int $pid
 * @param string $depth
 *
 * @return string
 */
function makeprintablenav($pid=0, $depth="--")
{
	global $mybb, $db, $pforumcache, $fid, $forum, $lang, $templates;
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
	$forums = '';
	if(is_array($pforumcache[$pid]))
	{
		foreach($pforumcache[$pid] as $key => $forumnav)
		{
			$forumnav['link'] = get_forum_link($forumnav['fid']);
			eval("\$forums .= \"".$templates->get("printthread_nav")."\";");
			if(!empty($pforumcache[$forumnav['fid']]))
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
 * @param int $count The total number of items.
 * @param int $perpage The items per page.
 * @param int $current_page The current page.
 * @param string $url The URL base.
 *
 * @return string
*/
function printthread_multipage($count, $perpage, $current_page, $url)
{
	global $lang, $templates;
	$multipage = "";
	if($count > $perpage)
	{
		$pages = $count / $perpage;
		$pages = ceil($pages);

		$mppage = null;
		for($page = 1; $page <= $pages; ++$page)
		{
			if($page == $current_page)
			{
				eval("\$mppage .= \"".$templates->get("printthread_multipage_page_current")."\";");
			}
			else
			{
				eval("\$mppage .= \"".$templates->get("printthread_multipage_page")."\";");
			}
		}

		eval("\$multipage = \"".$templates->get("printthread_multipage")."\";");
	}
	return $multipage;
}

