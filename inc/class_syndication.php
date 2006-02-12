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

/*
Example code:

$syndication = new Syndication();
$syndication->set_feed_type('atom');
$syndication->set_limit(20);
$forums = array(1, 5, 6);
$syndication->set_forum_list($forums);
$syndication->generate_feed();

*/

class Syndication
{
	/**
	 * The type of feed to generate.
	 *
	 * @var string
	 */
	var $feed_type = 'rss0.92';
	
	/**
	 * The number of items to list.
	 *
	 * @var int
	 */
	var $limit = 15;
	
	/**
	 * The list of forums to grab from.
	 *
	 * @var array
	 */
	var $forumlist;
	
	/* Sets the type of feed */
	function set_feed_type($feed_type)
	{		
		if($feed_type == 'rss2.0')
		{
			$this->feed_type = 'rss2.0';
		}		
		elseif($feed_type == 'atom1.0')
		{
			$this->feed_type = 'atom1.0';
		}
		else
		{
			$this->feed_type = 'rss0.92';
		}		
	}
	
	/* Sets the number of posts to gather */
	function set_limit($limit)
	{
		if($limit < 1)
		{
			error($lang->error_invalid_limit);
		}
		else
		{
			$this->limit = intval($limit);
		}
	}
	
	/* Sets the forums from which to get the recent posts */
	function set_forum_list($forumlist = array())
	{
		$unviewable = getunviewableforums();
		if($unviewable)
		{
			$unviewable = "AND f.fid NOT IN($unviewable)";
		}
		
		if(!empty($forumlist))
		{
			$forum_ids = "'-1'";
			foreach($forumlist as $fid)
			{
				$forum_ids .= ",'".intval($fid)."'";
			}
			$this->forumlist = "AND f.fid IN ($forum_ids) $unviewable";
		}
		else
		{
			$this->forumlist = $unviewable;
		}
	}
	
	/* This generates and echos the XML for the feed */
	function generate_feed()
	{
		header("Content-Type: text/xml");	
		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		
		$this->build_header();		
		$this->build_entries();
		$this->build_footer();
	}
	
	/* Private function: used by generate_feed(), generates XML for header */
	function build_header()
	{
		global $mybb, $lang, $mybboard;
		switch($this->feed_type)
		{
			case "rss2.0":
				echo "<rss version=\"2.0\">\n";
				echo "\t<channel>\n";
				echo "\t\t<title>".htmlspecialchars($mybb->settings['bbname'])."</title>\n";
				echo "\t\t<link>".$mybb->settings['bburl']."</link>\n";
				echo "\t\t<description>".htmlspecialchars($mybb->settings['bbname'])." - ".$mybb->settings['bburl']."</description>\n";
				echo "\t\t<lastBuildDate>".date("Y-m-d H:i:s")."</lastBuildDate>\n";
				echo "\t\t<generator>MyBB ".$mybboard['internalver']."</generator>\n";
			break;
			
			case "atom1.0":
				echo "<feed xmlns=\"http://www.w3.org/2005/Atom\">\n";
				echo "\t<title>".htmlspecialchars($mybb->settings['bbname'])."</title>\n";
				echo "\t<id>".$mybb->settings['bburl']."</id>\n";
				echo "\t<link rel=\"self\" href=\"".$mybb->settings['bburl']."/syndication.php?type=atom1.0&amp;limit=".$this->limit."\"/>\n";
				echo "\t<updated>".date("Y-m-dTH:i:sZ")."</updated>\n";
				echo "\t<generator uri=\"http://mybboard.com\" version=\"".$mybboard['internalver']."\">MyBB</generator>\n";
			break;
			
			case "rss0.92":
				echo "<rss version=\"0.92\">\n";
				echo "\t<channel>\n";
				echo "\t\t<title>".htmlspecialchars($mybb->settings['bbname'])."</title>\n";
				echo "\t\t<link>".$mybb->settings['bburl']."</link>\n";
				echo "\t\t<description>".htmlspecialchars($mybb->settings['bbname'])." - ".$mybb->settings['bburl']."</description>\n";
				echo "\t\t<lastBuildDate>".date("Y-m-d H:i:s")."</lastBuildDate>\n";
				echo "\t\t<language>en</language>\n";
			break;
			
			default:
				echo "<rss version=\"0.92\">\n";
				echo "\t<channel>\n";
				echo "\t\t<title>".htmlspecialchars($mybb->settings['bbname'])."</title>\n";
				echo "\t\t<link>".$mybb->settings['bburl']."</link>\n";
				echo "\t\t<description>".htmlspecialchars($mybb->settings['bbname'])." - ".$mybb->settings['bburl']."</description>\n";
				echo "\t\t<lastBuildDate>".date("Y-m-d H:i:s")."</lastBuildDate>\n";
				echo "\t\t<language>en</language>\n";
			break;
		}
	}
	
	/* Private function: used by generate_feed(), generates XML for entries */
	function build_entries()
	{
		global $db, $mybb, $lang;
		$query = $db->query("
			SELECT t.*, f.name AS forumname, p.message AS postmessage 
			FROM ".TABLE_PREFIX."threads t 
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid) 
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=t.firstpost) 
			WHERE 1=1
			AND p.visible=1 $this->forumlist
			ORDER BY t.dateline DESC 
			LIMIT 0, ".$this->limit
		);
		while($thread = $db->fetch_array($query))
		{
			$thread['subject'] = htmlspecialchars_uni($thread['subject']);
			$thread['forumname'] = htmlspecialchars_uni($thread['forumname']);
			$postdate = mydate($mybb->settings['dateformat'], $thread['dateline'], "", 0);
			$posttime = mydate($mybb->settings['timeformat'], $thread['dateline'], "", 0);
			$thread['postmessage'] = nl2br(htmlspecialchars_uni($thread['postmessage']));
			$last_updated = mydate("r", $thread['dateline'], "", 0);
			$last_updated_atom = mydate("Y-m-dTH:i:sZ", $thread['dateline'], "", 0);
			switch($this->feed_type)
			{
				case "rss2.0";
					echo "\t\t<item>\n";
					echo "\t\t\t<guid>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</guid>\n";
					echo "\t\t\t<title>".$thread['subject']."</title>\n";
					echo "\t\t\t<author>".$thread['username']."</author>\n";
					$description = htmlspecialchars($lang->forum." ".$thread['forumname'])."\r\n<br />".htmlspecialchars($lang->posted_by." ".$thread['username']." ".$lang->on." ".$postdate." ".$posttime);
					if($thread['postmessage'])
					{
						$description .= "\n<br /><br />".$thread['postmessage'];
					}
					echo "\t\t\t<description><![CDATA[".$description."]]></description>";
					echo "\t\t\t<link>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</link>\n";
					echo "\t\t\t<category domain=\"".$mybb->settings['bburl']."/forumdisplay.php?fid=".$thread['fid']."\">".$thread['forumname']."</category>\n";
					echo "\t\t\t<pubDate>".$last_updated."</pubDate>\n";
					echo "\t\t</item>\n";
				break;
				
				case "atom1.0":
					echo "\t<entry>\n";
					echo "\t\t<id>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</id>\n";
					echo "\t\t<title>".$thread['subject']."</title>\n";
					echo "\t\t<updated>".$last_updated_atom."</updated>\n";
					echo "\t\t<author>\n";
					echo "\t\t\t<name>".$thread['username']."</name>\n";
					echo "\t\t</author>\n";
					$description = htmlspecialchars($lang->forum." ".$thread['forumname'])."\r\n<br />".htmlspecialchars($lang->posted_by." ".$thread['username']." ".$lang->on." ".$postdate." ".$posttime);
					if($thread['postmessage'])
					{
						$description .= "\n<br />".$thread['postmessage'];
					}
					echo "\t\t\t<content><![CDATA[".$description."]]></content>";
					echo "\t</entry>\n";
				break;
				
				case "rss0.92":
					echo "\t\t<item>\n";
					echo "\t\t\t<title>".$thread['subject']."</title>\n";
					echo "\t\t\t<author>".$thread['username']."</author>\n";
					$description = htmlspecialchars($lang->forum." ".$thread['forumname'])."\r\n<br />".htmlspecialchars($lang->posted_by." ".$thread['username']." ".$lang->on." ".$postdate." ".$posttime);
					if($thread['postmessage'])
					{
						$description .= "\n<br />".$thread['postmessage'];
					}
					echo "\t\t\t<description><![CDATA[".$description."]]></description>";
					echo "\t\t\t<link>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</link>\n";
					echo "\t\t</item>\n";
				break;
				
				default:
					echo "\t\t<item>\n";
					echo "\t\t\t<title>".$thread['subject']."</title>\n";
					echo "\t\t\t<author>".$thread['username']."</author>\n";
					$description = htmlspecialchars($lang->forum." ".$thread['forumname'])."\r\n<br />".htmlspecialchars($lang->posted_by." ".$thread['username']." ".$lang->on." ".$postdate." ".$posttime);
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
	}
	
	/* Private function: used by generate_feed(), generates XML for footer */
	function build_footer()
	{
		switch($this->feed_type)
		{
			case "rss2.0":
				echo "\t</channel>\n";
				echo "</rss>";
			break;
			
			case "atom1.0":
				echo "</feed>";
			break;
			
			case "rss0.92":
				echo "\t</channel>\n";
				echo "</rss>";
			break;
			
			default:
				echo "\t</channel>\n";
				echo "</rss>";
			break;
		}
	}
	
}
?>