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

$templatelist = "printthread,printthread_post";

require "./global.php";
require "./inc/functions_post.php";
require "./inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("printthread");

$plugins->run_hooks("printthread_start");

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='".intval($mybb->input['tid'])."' AND visible='1'");
$thread = $db->fetch_array($query);
$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
if(!$thread['tid'])
{
	error($lang->error_invalidthread);
}
$fid = $thread['fid'];
$tid = $thread['tid'];
$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='".$thread['fid']."' AND active!='no'");
$forum = $db->fetch_array($query);
$breadcrumb = makeprintablenav();

$parentsexp = explode(",", $forum['parentlist']);
$numparents = count($parentsexp);
$tdepth = "-";
for($i=0;$i<$numparents;$i++)
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
	nopermission();
}

// Password protected forums ......... yhummmmy!
checkpwforum($fid, $forum['password']);

$query = $db->query("SELECT u.*, u.username AS userusername, p.* FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE p.tid='$tid' ORDER BY p.dateline");
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
		"allow_imgcode" => $forum['allowimgcode']
	);
	if($postrow['smilieoff'] == "yes")
	{
		$parser_options['allow_smilies'] = "no";
	}

	$postrow['message'] = $parser->parse_message($postrow['message'], $parser_options);
	/* Do /me code */
	if($forum['allowmycode'] != "no")
	{
		$postrow['message'] = domecode($postrow['message'], $postrow['username']);
	}
	$plugins->run_hooks("printthread_post");
	eval("\$postrows .= \"".$templates->get("printthread_post")."\";");
}
eval("\$printable = \"".$templates->get("printthread")."\";");

$plugins->run_hooks("printthread_end");

outputpage($printable);

function makeprintablenav($pid="0", $depth="--")
{
	global $db, $pforumcache, $fid, $forum, $lang;
	if(!is_array($pforumcache))
	{
		$parlist = buildparentlist($fid, "fid", "OR", $forum['parentlist']);
		$query = $db->query("SELECT name, fid, pid FROM ".TABLE_PREFIX."forums WHERE 1=1 AND $parlist ORDER BY pid, disporder");
		while($forumnav = $db->fetch_array($query))
		{
			$pforumcache[$forumnav['pid']][$forumnav['fid']] = $forumnav;
		}
		unset($forumnav);
	}
	if(is_array($pforumcache[$pid]))
	{
		while(list($key, $forumnav) = each($pforumcache[$pid]))
		{
			$forums .= "+".$depth." $lang->forum $forumnav[name] (<i>".$mybb->settings[bburl]."/forumdisplay.php?fid=$forumnav[fid]</i>)<br>\n";
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