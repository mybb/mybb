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

TO BE WRITTEN

*/

class Syndication
{
	/**
	 * The type of feed to generate.
	 *
	 * @var string
	 */
	var $feed_format = 'rss0.92';

	/**
	 * The XML to output.
	 *
	 * @var string
	 */
	var $xml = "";

	/**
	 * Set the type of feed to be used.
	 *
	 * @param string The feed type.
	 */
	function set_feed_format($feed_format)
	{
		if($feed_format == 'rss2.0')
		{
			$this->feed_format = 'rss2.0';
		}
		elseif($feed_format == 'atom1.0')
		{
			$this->feed_format = 'atom1.0';
		}
		else
		{
			$this->feed_format = 'rss0.92';
		}
	}

	/**
	 * Set the number of posts to generate in the feed.
	 *
	 * @param int The number of posts.
	 */
	function set_limit($limit)
	{
		if(intval($limit) < 1)
		{
			error($lang->error_invalid_limit);
		}
		else
		{
			$this->limit = intval($limit);
		}
	}

	/**
	 * Generate and echo XML for the feed.
	 *
	 */
	function output_feed()
	{
		// Output an appropriate header matching the feed type.
		switch($this->feed_format)
		{
			case "atom1.0":
				header("Content-Type: application/atom+xml");
			break;

			case "rss0.92":
			case "rss2.0":
			default:
				header("Content-Type: application/rss+xml");
			break;
		}

		$this->xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n".$this->xml;
	}

	/**
	 * Add the main wrapper to the feed.
	 *
	 */
	function start_wrapper()
	{
		global $mybb, $lang, $mybboard;

		// Output the appropriate header.
		switch($this->feed_format)
		{
			// Rss 2.0 wants a <channel>.
			case "rss2.0":
				$this->xml .= "<rss version=\"2.0\">\n";
				$this->xml .= "\t<channel>\n";
				$this->xml .= "\t\t<title>".htmlentities($mybb->settings['bbname'])."</title>\n";
				$this->xml .= "\t\t<link>".$mybb->settings['bburl']."</link>\n";
				$this->xml .= "\t\t<description>".htmlentities($mybb->settings['bbname'])." - ".$mybb->settings['bburl']."</description>\n";
				$this->xml .= "\t\t<lastBuildDate>".date("D, d M Y H:i:s O")."</lastBuildDate>\n";
				$this->xml .= "\t\t<generator>MyBB ".$mybboard['internalver']."</generator>\n";
			break;

			// Atom 1.0 needs nothing.
			case "atom1.0":
				$this->xml .= "<feed xmlns=\"http://www.w3.org/2005/Atom\">\n";
				$this->xml .= "\t<title>".htmlentities($mybb->settings['bbname'])."</title>\n";
				$this->xml .= "\t<id>".$mybb->settings['bburl']."/</id>\n";
				$this->xml .= "\t<link rel=\"self\" href=\"".$mybb->settings['bburl']."/syndication.php?type=atom1.0&amp;limit=".$this->limit."\"/>\n";
				$this->xml .= "\t<updated>".date("Y-m-d\TH:i:s\Z")."</updated>\n";
				$this->xml .= "\t<generator uri=\"http://mybboard.com\" version=\"".$mybboard['internalver']."\">MyBB</generator>\n";
			break;

			// Rss 0.92 wants a <channel>.
			case "rss0.92":
				$this->xml .= "<rss version=\"0.92\">\n";
				$this->xml .= "\t<channel>\n";
				$this->xml .= "\t\t<title>".htmlentities($mybb->settings['bbname'])."</title>\n";
				$this->xml .= "\t\t<link>".$mybb->settings['bburl']."</link>\n";
				$this->xml .= "\t\t<description>".htmlentities($mybb->settings['bbname'])." - ".$mybb->settings['bburl']."</description>\n";
				$this->xml .= "\t\t<lastBuildDate>".date("D, d M Y H:i:s O")."</lastBuildDate>\n";
				$this->xml .= "\t\t<language>en</language>\n";
			break;

			// Default is RSS 0.92.
			default:
				$this->xml .= "<rss version=\"0.92\">\n";
				$this->xml .= "\t<channel>\n";
				$this->xml .= "\t\t<title>".htmlentities($mybb->settings['bbname'])."</title>\n";
				$this->xml .= "\t\t<link>".$mybb->settings['bburl']."</link>\n";
				$this->xml .= "\t\t<description>".htmlentities($mybb->settings['bbname'])." - ".$mybb->settings['bburl']."</description>\n";
				$this->xml .= "\t\t<lastBuildDate>".date("D, d M Y H:i:s O")."</lastBuildDate>\n";
				$this->xml .= "\t\t<language>en</language>\n";
			break;
		}
	}

	/**
	 * Add a post to the feed.
	 *
	 */
	function add_post($post)
	{
		global $mybb, $lang;

		// Make a $post array containing the necessary details.
		$post['tid'] = $thread['tid'];
		$post['subject'] = htmlentities($thread['subject']);
		$post['fid'] = $thread['fid'];
		$post['forumname'] = htmlentities($thread['forumname']);
		$post['date'] = mydate($mybb->settings['dateformat'], $thread['dateline'], "", 0);
		$post['time'] = mydate($mybb->settings['timeformat'], $thread['dateline'], "", 0);
		$post['username'] = htmlentities($thread['username']);
		$post['message'] = nl2br(htmlentities($thread['postmessage']));
		$post['last_updated'] = mydate("D, d M Y H:i:s O", $thread['dateline'], "", 0);
		$post['last_updated_atom'] = mydate("Y-m-d\TH:i:s\Z", $thread['dateline'], "", 0);

		// Append to the feed XML, depending on the feed format.
		switch($this->feed_format)
		{
			// Add an <item> as entry for RSS 2.0.
			case "rss2.0";
				$this->xml .= "\t\t<item>\n";
				$this->xml .= "\t\t\t<guid>".$mybb->settings['bburl']."/showthread.php?tid=".$post['tid']."&amp;action=newpost</guid>\n";
				$this->xml .= "\t\t\t<title>".$post['subject']."</title>\n";
				$this->xml .= "\t\t\t<author>".$post['username']."</author>\n";
				$description = htmlentities($lang->forum)." ".$post['forumname']."\r\n<br />".$lang->posted_by." ".$post['username']." ".$lang->on." ".$post['date']." ".$post['time'];
				$description .= "\n<br />".$post['message'];
				$this->xml .= "\t\t\t<description><![CDATA[".$post['message']."]]></description>";
				$this->xml .= "\t\t\t<link>".$mybb->settings['bburl']."/showthread.php?tid=".$post['tid']."&amp;action=newpost</link>\n";
				$this->xml .= "\t\t\t<category domain=\"".$mybb->settings['bburl']."/forumdisplay.php?fid=".$post['fid']."\">".$post['forumname']."</category>\n";
				$this->xml .= "\t\t\t<pubDate>".$post['last_updated']."</pubDate>\n";
				$this->xml .= "\t\t</item>\n";
			break;

			// Add an <entry> as entry for Atom 1.0.
			case "atom1.0":
				$this->xml .= "\t<entry>\n";
				$this->xml .= "\t\t<id>".$mybb->settings['bburl']."/showthread.php?tid=".$post['tid']."&amp;action=newpost</id>\n";
				$this->xml .= "\t\t<title>".$post['subject']."</title>\n";
				$this->xml .= "\t\t<updated>".$post['last_updated_atom']."</updated>\n";
				$this->xml .= "\t\t<author>\n";
				$this->xml .= "\t\t\t<name>".$post['username']."</name>\n";
				$this->xml .= "\t\t</author>\n";
				$description = htmlentities($lang->forum)." ".$post['forumname']."\r\n<br />".$lang->posted_by." ".$post['username']." ".$lang->on." ".$post['date']." ".$post['time'];
				$description .= "\n<br />".$post['message'];
				$this->xml .= "\t\t\t<content type=\"html\"><![CDATA[".$description."]]></content>";
				$this->xml .= "\t</entry>\n";
			break;

			// Add an <item> as entry for RSS 0.92.
			case "rss0.92":
				$this->xml .= "\t\t<item>\n";
				$this->xml .= "\t\t\t<title>".$post['subject']."</title>\n";
				$this->xml .= "\t\t\t<author>".$post['username']."</author>\n";
				$description = htmlentities($lang->forum)." ".$post['forumname']."\r\n<br />".$lang->posted_by." ".$post['username']." ".$lang->on." ".$post['date']." ".$post['time'];
				$description .= "\n<br />".$post['message'];
				$this->xml .= "\t\t\t<description><![CDATA[".$post['message']."]]></description>";
				$this->xml .= "\t\t\t<link>".$mybb->settings['bburl']."/showthread.php?tid=".$post['tid']."&amp;action=newpost</link>\n";
				$this->xml .= "\t\t</item>\n";
			break;

			// Default is RSS 0.92.
			default:
				$this->xml .= "\t\t<item>\n";
				$this->xml .= "\t\t\t<title>".$post['subject']."</title>\n";
				$this->xml .= "\t\t\t<author>".$post['username']."</author>\n";
				$description = htmlentities($lang->forum)." ".$post['forumname']."\r\n<br />".$lang->posted_by." ".$post['username']." ".$lang->on." ".$post['date']." ".$post['time'];
				$description .= "\n<br />".$post['message'];
				$this->xml .= "\t\t\t<description><![CDATA[".$post['message']."]]></description>";
				$this->xml .= "\t\t\t<link>".$mybb->settings['bburl']."/showthread.php?tid=".$post['tid']."&amp;action=newpost</link>\n";
				$this->xml .= "\t\t</item>\n";
			break;
		}
	}

	/**
	 * Add the wrapper end.
	 *
	 */
	function end_wrapper()
	{
		switch($this->feed_format)
		{
			case "rss2.0":
				$this->xml .= "\t</channel>\n";
				$this->xml .= "</rss>";
			break;

			case "atom1.0":
				$this->xml .= "</feed>";
			break;

			case "rss0.92":
				$this->xml .= "\t</channel>\n";
				$this->xml .= "</rss>";
			break;

			default:
				$this->xml .= "\t</channel>\n";
				$this->xml .= "</rss>";
			break;
		}
	}

}
?>