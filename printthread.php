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

$templatelist = "printthread,printthread_post";

require "./global.php";
require MYBB_ROOT."inc/functions_post.php";
require MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("printthread");

$plugins->run_hooks("printthread_start");

$query = $db->simple_select(TABLE_PREFIX."threads", "*", "tid='".intval($mybb->input['tid'])."' AND visible='1'");
$thread = $db->fetch_array($query);
$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
if(!$thread['tid'])
{
	error($lang->error_invalidthread);
}
$fid = $thread['fid'];
$tid = $thread['tid'];

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
for($i = 0; $i < $numparents; $i++)
{
	$tdepth .= "-";
}
$forumpermissions = forum_permissions($forum['fid']);

if($forum['type'] != "f")
{
	error($lang->error_invalidforum);
}
if($forumpermissions['canview'] == "no" || $forumpermissions['canviewthreads'] == "no")
{
	error_no_permission();
}

// Password protected forums ......... yhummmmy!
check_forum_password($fid, $forum['password']);

$postrows = '';
$query = $db->query("
	SELECT u.*, u.username AS userusername, p.*
	FROM ".TABLE_PREFIX."posts p
	LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
	WHERE p.tid='$tid' AND p.visible=1
	ORDER BY p.dateline
");
while($postrow = $db->fetch_array($query))
{
	if($postrow['userusername'])
	{
		$postrow['username'] = $postrow['userusername'];
	}
	$postrow['subject'] = htmlspecialchars_uni($parser->parse_badwords($postrow['subject']));
	$postrow['date'] = mydate($mybb->settings['dateformat'], $postrow['dateline']);
	$postrow['time'] = mydate($mybb->settings['timeformat'], $postrow['dateline']);
	$parser_options = array(
		"allow_html" => $forum['allow_html'],
		"allow_mycode" => $forum['allow_mycode'],
		"allow_smilies" => $forum['allowsmilies'],
		"allow_imgcode" => $forum['allowimgcode'],
		"me_username" => $postrow['username'],
		"shorten_urls" => "no"
	);
	if($postrow['smilieoff'] == "yes")
	{
		$parser_options['allow_smilies'] = "no";
	}

	$postrow['message'] = $parser->parse_message($postrow['message'], $parser_options);
	$plugins->run_hooks("printthread_post");
	eval("\$postrows .= \"".$templates->get("printthread_post")."\";");
}
eval("\$printable = \"".$templates->get("printthread")."\";");

$plugins->run_hooks("printthread_end");

output_page($printable);

function makeprintablenav($pid="0", $depth="--")
{
	global $db, $pforumcache, $fid, $forum, $lang;
	if(!is_array($pforumcache))
	{
		$parlist = build_parent_list($fid, "fid", "OR", $forum['parentlist']);
		$query = $db->simple_select(TABLE_PREFIX."forums", "name, fid, pid", "$parlist", array('order_by' => 'pid, disporder'));
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
			$forums .= "+".$depth." $lang->forum $forumnav[name] (<i>".$mybb->settings['bburl']."/forumdisplay.php?fid=$forumnav[fid]</i>)<br>\n";
			if($pforumcache[$forumnav['fid']])
			{
				$newdepth = $depth."-";
				$forums .= makeprintablenav($forumnav['fid'], $newdepth);
			}
		}
	}
	return $forums;
}

?>