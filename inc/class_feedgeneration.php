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
	 * The XML to output.
	 *
	 * @var string
	 */
	public $xml = "";

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
	 * @param string The feed type.
	 */
	function set_feed_format($feed_format)
	{
		if($feed_format == 'atom1.0')
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
	 * @param array The channel information
	 */
	function set_channel($channel)
	{
		$this->channel = $channel;
	}

	/**
	 * Adds an item to the RSS feed.
	 *
	 * @param array The item.
	 */
	function add_item($item)
	{
		$this->items[] = $item;
	}

	/**
	 * Generate and echo XML for the feed.
	 *
	 */
	function generate_feed()
	{
		global $lang;

		// First, add the feed metadata.
		switch($this->feed_format)
		{
			// Ouput an Atom 1.0 formatted feed.
			case "atom1.0":
				$this->channel['date'] = gmdate("Y-m-d\TH:i:s\Z", $this->channel['date']);
				$this->xml .= "<?xml version=\"1.0\" encoding=\"{$lang->settings['charset']}\"?>\n";
				$this->xml .= "<feed xmlns=\"http://www.w3.org/2005/Atom\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n";
				$this->xml .= "\t<title type=\"html\"><![CDATA[".$this->sanitize_content($this->channel['title'])."]]></title>\n";
				$this->xml .= "\t<subtitle type=\"html\"><![CDATA[".$this->sanitize_content($this->channel['description'])."]]></subtitle>\n";
				$this->xml .= "\t<link rel=\"self\" href=\"{$this->channel['link']}syndication.php\"/>\n";
				$this->xml .= "\t<id>{$this->channel['link']}</id>\n";
				$this->xml .= "\t<link rel=\"alternate\" type=\"text/html\" href=\"{$this->channel['link']}\"/>\n";
				$this->xml .= "\t<updated>{$this->channel['date']}</updated>\n";
				$this->xml .= "\t<generator uri=\"http://www.mybb.com\">MyBB</generator>\n";
				break;
			// The default is the RSS 2.0 format.
			default:
				$this->channel['date'] = gmdate("D, d M Y H:i:s O", $this->channel['date']);
				$this->xml .= "<?xml version=\"1.0\" encoding=\"{$lang->settings['charset']}\"?>\n";
				$this->xml .= "<rss version=\"2.0\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n";
				$this->xml .= "\t<channel>\n";
				$this->xml .= "\t\t<title><![CDATA[".$this->sanitize_content($this->channel['title'])."]]></title>\n";
				$this->xml .= "\t\t<link>".$this->channel['link']."</link>\n";
				$this->xml .= "\t\t<description><![CDATA[".$this->sanitize_content($this->channel['description'])."]]></description>\n";
				$this->xml .= "\t\t<pubDate>".$this->channel['date']."</pubDate>\n";
				$this->xml .= "\t\t<generator>MyBB</generator>\n";
		}

		// Now loop through all of the items and add them to the feed XML.
		foreach($this->items as $item)
		{
			if(!$item['date'])
			{
				$item['date'] = TIME_NOW;
			}
			switch($this->feed_format)
			{
				// Output Atom 1.0 format feed.
				case "atom1.0":
					$item['date'] = date("Y-m-d\TH:i:s\Z", $item['date']);
					$this->xml .= "\t<entry xmlns=\"http://www.w3.org/2005/Atom\">\n";
					if(!empty($item['author']))
					{
						$this->xml .= "\t\t<author>\n";
						$this->xml .= "\t\t\t<name type=\"html\" xml:space=\"preserve\"><![CDATA[".$this->sanitize_content($item['author'])."]]></name>\n";
						$this->xml .= "\t\t</author>\n";
					}
					$this->xml .= "\t\t<published>{$item['date']}</published>\n";
					if(empty($item['updated']))
					{
						$item['updated'] = $item['date'];
					}
					else
					{
						$item['updated'] = date("Y-m-d\TH:i:s\Z", $item['updated']);
					}
					$this->xml .= "\t\t<updated>{$item['updated']}</updated>\n";
					$this->xml .= "\t\t<link rel=\"alternate\" type=\"text/html\" href=\"{$item['link']}\" />\n";
					$this->xml .= "\t\t<id>{$item['link']}</id>\n";
					$this->xml .= "\t\t<title type=\"html\" xml:space=\"preserve\"><![CDATA[".$this->sanitize_content($item['title'])."]]></title>\n";
					$this->xml .= "\t\t<content type=\"html\" xml:space=\"preserve\" xml:base=\"{$item['link']}\"><![CDATA[".$this->sanitize_content($item['description'])."]]></content>\n";
					$this->xml .= "\t\t<draft xmlns=\"http://purl.org/atom-blog/ns#\">false</draft>\n";
					$this->xml .= "\t</entry>\n";
					break;

				// The default is the RSS 2.0 format.
				default:
					$item['date'] = date("D, d M Y H:i:s O", $item['date']);
					$this->xml .= "\t\t<item>\n";
					$this->xml .= "\t\t\t<title><![CDATA[".$this->sanitize_content($item['title'])."]]></title>\n";
					$this->xml .= "\t\t\t<link>".$item['link']."</link>\n";
					$this->xml .= "\t\t\t<pubDate>".$item['date']."</pubDate>\n";
					if(!empty($item['author']))
					{
						$this->xml .= "\t\t\t<dc:creator><![CDATA[".$this->sanitize_content($item['author'])."]]></dc:creator>\n";
					}
					$this->xml .= "\t\t\t<guid isPermaLink=\"false\">".$item['link']."</guid>\n";
					$this->xml .= "\t\t\t<description><![CDATA[".$item['description']."]]></description>\n";
					$this->xml .= "\t\t\t<content:encoded><![CDATA[".$item['description']."]]></content:encoded>\n";
					$this->xml .= "\t\t</item>\n";
					break;
			}
		}

		// Now, neatly end the feed XML.
		switch($this->feed_format)
		{
			case "atom1.0":
				$this->xml .= "</feed>";
				break;
			default:
				$this->xml .= "\t</channel>\n";
				$this->xml .= "</rss>";
		}
	}

	/**
	 * Sanitize content suitable for RSS feeds.
	 *
	 * @param  string The string we wish to sanitize.
	 * @return string The cleaned string.
	 */
	function sanitize_content($content)
	{
		$content = preg_replace("#&[^\s]([^\#])(?![a-z1-4]{1,10});#i", "&#x26;$1", $content);
		$content = str_replace("]]>", "]]]]><![CDATA[>", $content);

		return $content;
	}

	/**
	* Output the feed XML.
	*/
	function output_feed()
	{
		global $lang;
		// Send an appropriate header to the browser.
		switch($this->feed_format)
		{
			case "atom1.0":
				header("Content-Type: application/atom+xml; charset=\"{$lang->settings['charset']}\"");
				break;
			default:
				header("Content-Type: text/xml; charset=\"{$lang->settings['charset']}\"");
		}

		// Output the feed XML. If the feed hasn't been generated, do so.
		if($this->xml)
		{
			echo $this->xml;
		}
		else
		{
			$this->generate_feed();
			echo $this->xml;
		}
	}
}
