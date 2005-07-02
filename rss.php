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

$noonline = 1;
require "./global.php";

// Load global language phrases
$lang->load("rss");

if($timeoffset)
{
	$mybb->settings['timezoneoffset'] = $timeoffset;
}

$unviewable = getunviewableforums();
if($unviewable)
{
	$unviewable = "AND f.fid NOT IN($unviewable)";
}

if(trim($fid) > 0)
{
	$forums = explode(",", $fid);
	$fidq = "'-1'";
	foreach($forums as $fid)
	{
		$fidq .= ",'$fid'";
	}
	$forumlist = "AND f.fid IN ($fidq) $unviewable";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums f WHERE 1=1 $forumlist");
	$comma = " - ";
	while($forum = $db->fetch_array($query))
	{
		$title .= $comma.$forum['name'];
		$forumcache[$forum['fid']] = $forum;
		$comma = ", ";
	}
}
$title = htmlentities($mybb->settings['bbname'].$title);

$limit = intval($limit);
if($limit < 1 || !$limit)
{
	$limit = 15;
}

if($type == "rss2")
{
	$type = "rss2.0";
}

switch($type)
{
	case "rss2.0":
		header("Content-Type: text/xml");	
		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		echo "<rss version=\"2.0\">\n";
		echo "\t<channel>\n";
		echo "\t\t<title>".$title."</title>\n";
		echo "\t\t<link>".$mybb->settings['bburl']."</link>\n";
		echo "\t\t<description>".$mybb->settings['bbname']." - ".$mybb->settings['bburl']."</description>\n";
		echo "\t\t<generator>MyBB ".$mybboard['internalver']."</generator>\n";
		break;
	default:
		header("Content-Type: text/xml");	
		echo "<rss version=\"0.92\">\n";
		echo "\t<channel>\n";
		echo "\t\t<title>".$title."</title>\n";
		echo "\t\t<link>".$mybb->settings['bburl']."</link>\n";
		echo "\t\t<description>".$mybb->settings['bbname']." - ".$mybb->settings['bburl']."</description>\n";
		echo "\t\t<language>en</language>\n";
		break;
}

$query = $db->query("SELECT t.*, f.name AS forumname FROM ".TABLE_PREFIX."threads t LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid) WHERE 1=1 $forumlist $unviewable ORDER BY t.dateline DESC LIMIT 0, $limit");
while($thread = $db->fetch_array($query))
{
	$thread['subject'] = htmlspecialchars(stripslashes($thread['subject']));
	$thread['forumnanme'] = htmlspecialchars(stripslashes($thread['forumname']));
	$postdate = mydate($mybb->settings['dateformat'], $thread['dateline'], "", 0);
	$posttime = mydate($mybb->settings['timeformat'], $thread['dateline'], "", 0);
	$pubdate = mydate("r", $thread['dateline'], "", 0);
	switch($type)
	{
		case "rss2.0";
			echo "\t\t<item>\n";
			echo "\t\t\t<guid>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</guid>\n";
			echo "\t\t\t<title>".$thread['subject']."</title>\n";
			echo "\t\t\t<author>".$thread['username']."</author>\n";
			echo "\t\t\t<description>".$lang->forum." ".$thread['forumname']."\r\n<br />".$lang->posted_by." ".$thread['username']." ".$lang->on." ".$postdate." ".$posttime."</description>\n";
			echo "\t\t\t<link>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</link>\n";
			echo "\t\t\t<category domain=\"".$mybb->settings['bburl']."/forumdisplay.php?fid=".$thread['fid']."\">".$thread['forumname']."</category>\n";
			echo "\t\t\t<pubDate>".$pubdate."</pubDate>\n";
			echo "\t\t</item>\n";
			break;
		default:
			echo "\t\t<item>\n";
			echo "\t\t\t<title>".$thread['subject']."</title>\n";
			echo "\t\t\t<author>".$thread['username']."</author>\n";
			echo "\t\t\t<description>".$lang->forum." ".$thread['forumname']."\r\n<br />".$lang->posted_by." ".$thread['username']." ".$lang->on." ".$postdate." ".$posttime."</description>\n";
			echo "\t\t\t<link>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</link>\n";
			echo "\t\t</item>\n";
			break;
	}
}
switch($type)
{
	case "rss2.0":
		echo "\t</channel>\n";
		echo " </rss>\n";
		break;
	default:
		echo "\t</channel>\n";
		echo " </rss>\n";
		break;
}
?>