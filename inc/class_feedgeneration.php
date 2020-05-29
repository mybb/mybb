<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

class FeedGenerator
{
	/**
	 * The type of feed to generate.
	 *
	 * @var string
	 */
	public $feed_format = 'rss2.0';

	/**
	 * The feed to output.
	 *
	 * @var string
	 */
	public $feed = "";

	/**
	 * Array of all of the items
	 *
	 * @var array
	 */
	public $items = array();

	/**
	 * Array of the channel information.
	 *
	 * @var array
	 */
	public $channel = array();

	/**
	 * Set the type of feed to be used.
	 *
	 * @param string $feed_format The feed type.
	 */
	function set_feed_format($feed_format)
	{
		if($feed_format == 'json')
		{
			$this->feed_format = 'json';
		}
		elseif($feed_format == 'atom1.0')
		{
			$this->feed_format = 'atom1.0';
		}
		else
		{
			$this->feed_format = 'rss2.0';
		}
	}

	/**
	 * Sets the channel information for the RSS feed.
	 *
	 * @param array $channel The channel information
	 */
	function set_channel($channel)
	{
		$this->channel = $channel;
	}

	/**
	 * Adds an item to the RSS feed.
	 *
	 * @param array $item The item.
	 */
	function add_item($item)
	{
		$this->items[] = $item;
	}

	/** 
	 * Generate the feed.
	 *
	 */
	function generate_feed()
	{
		global $lang;

		// First, add the feed metadata.
		switch($this->feed_format)
		{
			// Ouput JSON formatted feed.
			case "json":
				$this->feed .= "{\n\t\"version\": ".json_encode('https://jsonfeed.org/version/1').",\n";
				$this->feed .= "\t\"title\": \"".$this->channel['title']."\",\n";
				$this->feed .= "\t\"home_page_url\": ".json_encode($this->channel['link']).",\n";
				$this->feed .= "\t\"feed_url\": ".json_encode($this->channel['link']."syndication.php").",\n";
				$this->feed .= "\t\"description\": ".json_encode($this->channel['description']).",\n";
				$this->feed .= "\t\"items\": [\n";
				$serial = 0;
				break;
			// Ouput Atom 1.0 formatted feed.
			case "atom1.0":
				$this->channel['date'] = gmdate("Y-m-d\TH:i:s\Z", $this->channel['date']);
				$this->feed .= "<?xml version=\"1.0\" encoding=\"{$lang->settings['charset']}\"?>\n";
				$this->feed .= "<feed xmlns=\"http://www.w3.org/2005/Atom\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n";
				$this->feed .= "\t<title type=\"html\"><![CDATA[".$this->sanitize_content($this->channel['title'])."]]></title>\n";
				$this->feed .= "\t<subtitle type=\"html\"><![CDATA[".$this->sanitize_content($this->channel['description'])."]]></subtitle>\n";
				$this->feed .= "\t<link rel=\"self\" href=\"{$this->channel['link']}syndication.php\"/>\n";
				$this->feed .= "\t<id>{$this->channel['link']}</id>\n";
				$this->feed .= "\t<link rel=\"alternate\" type=\"text/html\" href=\"{$this->channel['link']}\"/>\n";
				$this->feed .= "\t<updated>{$this->channel['date']}</updated>\n";
				$this->feed .= "\t<generator uri=\"https://mybb.com\">MyBB</generator>\n";
				break;
			// The default is the RSS 2.0 format.
			default:
				$this->channel['date'] = gmdate("D, d M Y H:i:s O", $this->channel['date']);
				$this->feed .= "<?xml version=\"1.0\" encoding=\"{$lang->settings['charset']}\"?>\n";
				$this->feed .= "<rss version=\"2.0\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n";
				$this->feed .= "\t<channel>\n";
				$this->feed .= "\t\t<title><![CDATA[".$this->sanitize_content($this->channel['title'])."]]></title>\n";
				$this->feed .= "\t\t<link>".$this->channel['link']."</link>\n";
				$this->feed .= "\t\t<description><![CDATA[".$this->sanitize_content($this->channel['description'])."]]></description>\n";
				$this->feed .= "\t\t<pubDate>".$this->channel['date']."</pubDate>\n";
				$this->feed .= "\t\t<generator>MyBB</generator>\n";
		}

		// Now loop through all of the items and add them to the feed.
		foreach($this->items as $item)
		{
			if(!$item['date'])
			{
				$item['date'] = TIME_NOW;
			}
			switch($this->feed_format)
			{
				// Output JSON formatted feed.
				case "json":
					++$serial;
					$end = $serial < count($this->items) ? "," : "";
					$item_id = explode('tid=', $item['link']);
					if(empty($item['updated']))
					{
						$item['updated'] = $item['date'];
					}
					$this->feed .= "\t\t{\n";
					$this->feed .= "\t\t\t\"id\": \"".end($item_id)."\",\n";
					$this->feed .= "\t\t\t\"url\": ".json_encode($item['link']).",\n";
					$this->feed .= "\t\t\t\"title\": ".json_encode($item['title']).",\n";
					if(!empty($item['author']))
					{
						$this->feed .= "\t\t\t\"author\": {\n\t\t\t\t\"name\": ".json_encode($item['author']['name']).",\n";
						$this->feed .= "\t\t\t\t\"url\": ".json_encode($this->channel['link']."member.php?action=profile&uid=".$item['author']['uid'])."\n";
						$this->feed .= "\t\t\t},\n";
					}
					$this->feed .= "\t\t\t\"content_html\": ".json_encode($item['description']).",\n";
					$this->feed .= "\t\t\t\"date_published\": \"".date('c', $item['date'])."\",\n";
					$this->feed .= "\t\t\t\"date_modified \": \"".date('c', $item['updated'])."\"\n";
					$this->feed .= "\t\t}".$end."\n";
					break;
				// Output Atom 1.0 formatted feed.
				case "atom1.0":
					$item['date'] = date("Y-m-d\TH:i:s\Z", $item['date']);
					$this->feed .= "\t<entry xmlns=\"http://www.w3.org/2005/Atom\">\n";
					if(!empty($item['author']))
					{
						$author = "<a href=\"".$this->channel['link']."member.php?action=profile&uid=".$item['author']['uid']."\">".$item['author']['name']."</a>";
						$this->feed .= "\t\t<author>\n";
						$this->feed .= "\t\t\t<name type=\"html\" xml:space=\"preserve\"><![CDATA[".$this->sanitize_content($author)."]]></name>\n";
						$this->feed .= "\t\t</author>\n";
					}
					$this->feed .= "\t\t<published>{$item['date']}</published>\n";
					if(empty($item['updated']))
					{
						$item['updated'] = $item['date'];
					}
					else
					{
						$item['updated'] = date("Y-m-d\TH:i:s\Z", $item['updated']);
					}
					$this->feed .= "\t\t<updated>{$item['updated']}</updated>\n";
					$this->feed .= "\t\t<link rel=\"alternate\" type=\"text/html\" href=\"{$item['link']}\" />\n";
					$this->feed .= "\t\t<id>{$item['link']}</id>\n";
					$this->feed .= "\t\t<title xml:space=\"preserve\"><![CDATA[".$this->sanitize_content($item['title'])."]]></title>\n";
					$this->feed .= "\t\t<content type=\"html\" xml:space=\"preserve\" xml:base=\"{$item['link']}\"><![CDATA[".$this->sanitize_content($item['description'])."]]></content>\n";
					$this->feed .= "\t\t<draft xmlns=\"http://purl.org/atom-blog/ns#\">false</draft>\n";
					$this->feed .= "\t</entry>\n";
					break;

				// The default is the RSS 2.0 format.
				default:
					$item['date'] = date("D, d M Y H:i:s O", $item['date']);
					$this->feed .= "\t\t<item>\n";
					$this->feed .= "\t\t\t<title><![CDATA[".$this->sanitize_content($item['title'])."]]></title>\n";
					$this->feed .= "\t\t\t<link>".$item['link']."</link>\n";
					$this->feed .= "\t\t\t<pubDate>".$item['date']."</pubDate>\n";
					if(!empty($item['author']))
					{
						$author = "<a href=\"".$this->channel['link']."member.php?action=profile&uid=".$item['author']['uid']."\">".$item['author']['name']."</a>";
						$this->feed .= "\t\t\t<dc:creator><![CDATA[".$this->sanitize_content($author)."]]></dc:creator>\n";
					}
					$this->feed .= "\t\t\t<guid isPermaLink=\"false\">".$item['link']."</guid>\n";
					$this->feed .= "\t\t\t<description><![CDATA[".$item['description']."]]></description>\n";
					$this->feed .= "\t\t\t<content:encoded><![CDATA[".$item['description']."]]></content:encoded>\n";
					$this->feed .= "\t\t</item>\n";
					break;
			}
		}

		// Now, neatly end the feed.
		switch($this->feed_format)
		{
			case "json":
				$this->feed .= "\t]\n}";
				break;
			case "atom1.0":
				$this->feed .= "</feed>";
				break;
			default:
				$this->feed .= "\t</channel>\n";
				$this->feed .= "</rss>";
		}
	}

	/**
	 * Sanitize content suitable for RSS feeds.
	 *
	 * @param  string $string The string we wish to sanitize.
	 * @return string The cleaned string.
	 */
	function sanitize_content($content)
	{
		$content = preg_replace("#&[^\s]([^\#])(?![a-z1-4]{1,10});#i", "&#x26;$1", $content);
		$content = str_replace("]]>", "]]]]><![CDATA[>", $content);

		return $content;
	}

	/**
	* Output the feed.
	*/
	function output_feed()
	{
		global $lang;
		// Send an appropriate header to the browser.
		switch($this->feed_format)
		{
			case "json":
				header("Content-Type: application/json; charset=\"{$lang->settings['charset']}\"");
				break;
			case "atom1.0":
				header("Content-Type: application/atom+xml; charset=\"{$lang->settings['charset']}\"");
				break;
			default:
				header("Content-Type: text/xml; charset=\"{$lang->settings['charset']}\"");
		}

		// If the feed hasn't been generated, do so.
		if($this->feed)
		{
			echo $this->feed;
		}
		else
		{
			$this->generate_feed();
			echo $this->feed;
		}
	}
}