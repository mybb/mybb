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
define("NO_ONLINE", 1);

require "./global.php";

// Load global language phrases
$lang->load("rss");

if($mybb->input['timeoffset'])
{
	$mybb->settings['timezoneoffset'] = $mybb->input['timeoffset'];
}

$unviewable = getunviewableforums();
if($unviewable)
{
	$unviewable = "AND f.fid NOT IN($unviewable)";
}

if(trim($mybb->input['fid']) > 0)
{
	$forums = explode(",", $mybb->input['fid']);
	$fidq = "'-1'";
	foreach($forums as $fid)
	{
		$fidq .= ",'".intval($fid)."'";
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

$mybb->input['limit'] = intval($mybb->input['limit']);
if($mybb->input['limit'] < 1 || !$mybb->input['limit'])
{
	$mybb->input['limit'] = 15;
}

if($mybb->input['type'] == "rss2")
{
	$mybb->input['type'] = "rss2.0";
}

switch($mybb->input['type'])
{
	case "rss2.0":
		header("Content-Type: text/xml");	
		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		echo "<rss version=\"2.0\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\" xmlns:wfw=\"http://wellformedweb.org/CommentAPI/\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\>\n";
		echo "\t<channel>\n";
		echo "\t\t<title><![CDATA[".$title."]]></title>\n";
		echo "\t\t<link>".$mybb->settings['bburl']."</link>\n";
		echo "\t\t<description><![CDATA[".$mybb->settings['bbname']." - ".$mybb->settings['bburl']."]]></description>\n";
		echo "\t\t<generator>MyBB</generator>\n";
		break;
	default:
		header("Content-Type: text/xml");	
		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		echo "<rss version=\"0.92\">\n";
		echo "\t<channel>\n";
		echo "\t\t<title><![CDATA[".$title."]]></title>\n";
		echo "\t\t<link>".$mybb->settings['bburl']."</link>\n";
		echo "\t\t<description><![CDATA[".$mybb->settings['bbname']." - ".$mybb->settings['bburl']."]]></description>\n";
		echo "\t\t<language>en</language>\n";
		break;
}

$query = $db->query("SELECT t.*, f.name AS forumname, p.message AS postmessage FROM ".TABLE_PREFIX."threads t LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid) LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=t.firstpost) WHERE 1=1 $forumlist $unviewable ORDER BY t.dateline DESC LIMIT 0, ".$mybb->input['limit']);
while($thread = $db->fetch_array($query))
{
	$thread['subject'] = htmlspecialchars_uni($thread['subject']);
	$thread['forumname'] = htmlspecialchars_uni($thread['forumname']);
	$postdate = mydate($mybb->settings['dateformat'], $thread['dateline'], "", 0);
	$posttime = mydate($mybb->settings['timeformat'], $thread['dateline'], "", 0);
	$thread['postmessage'] = nl2br(htmlspecialchars_uni($thread['postmessage']));
	$pubdate = mydate("r", $thread['dateline'], "", 0);
	switch($mybb->input['type'])
	{
		case "rss2.0";
			echo "\t\t<item>\n";
			echo "\t\t\t<guid>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</guid>\n";
			echo "\t\t\t<title>".$thread['subject']."</title>\n";
			echo "\t\t\t<author>".$thread['username']."</author>\n";
			$description = htmlspecialchars($lang->forum." ".$thread['forumname']."\r\n<br />".$lang->posted_by." ".$thread['username']." ".$lang->on." ".$postdate." ".$posttime);
			if($thread['postmessage'])
			{
				$description .= "\n<br />".$thread['postmessage'];
			}
			echo "\t\t\t<description><![CDATA[".$description."]]></description>";
			echo "\t\t\t<link>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</link>\n";
			echo "\t\t\t<category domain=\"".$mybb->settings['bburl']."/forumdisplay.php?fid=".$thread['fid']."\">".$thread['forumname']."</category>\n";
			echo "\t\t\t<pubDate>".$pubdate."</pubDate>\n";
			echo "\t\t</item>\n";
			break;
		default:
			echo "\t\t<item>\n";
			echo "\t\t\t<title>".$thread['subject']."</title>\n";
			echo "\t\t\t<author>".$thread['username']."</author>\n";
			$description = htmlspecialchars($lang->forum." ".$thread['forumname']."\r\n<br />".$lang->posted_by." ".$thread['username']." ".$lang->on." ".$postdate." ".$posttime);
			if($thread['postmessage'])
			{
				$description .= "\n<br />".$thread['postmessage'];
			}
			echo "\t\t\t<description><![CDATA[".$description."]]></description>";
			echo "\t\t\t<link>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</link>\n";
			echo "\t\t</item>\n";
			break;
	}
}
switch($mybb->input['type'])
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
