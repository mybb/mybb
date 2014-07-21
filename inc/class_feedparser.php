<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

class FeedParser
{
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
	 * Any error the feed parser may encounter
	 *
	 * @var string
	 */
	public $error;

	/**
	 * Parses a feed with the specified filename (or URL)
	 *
	 * @param string The path or URL of the feed
	 * @return boolean True if parsing was a success, false if failure
	 */
	function parse_feed($feed)
	{
		// Include the XML parser
		require_once MYBB_ROOT."inc/class_xml.php";

		// Load the feed we want to parse
		$contents = fetch_remote_file($feed);

		// This is to work around some dodgy bug we've detected with certain installations of PHP
		// where certain characters would magically appear between the fetch_remote_file call
		// and here which break the feed being imported.
		if(strpos($contents, "<") !== 0)
		{
			$contents = substr($contents, strpos($contents, "<"));
		}
		if(strrpos($contents, ">")+1 !== strlen($contents))
		{
			$contents = substr($contents, 0, strrpos($contents, ">")+1);
		}

		// Could not load the feed, return an error
		if(!$contents)
		{
			$this->error = "invalid_file";
			return false;
		}

		// Parse the feed and get the tree
		$parser = new XMLParser($contents);
		$tree = $parser->get_tree();

		// If the feed is invalid, throw back an error
		if($tree == false)
		{
			$this->error = "invalid_feed_xml";
			return false;
		}

		// Change array key names to lower case
		$tree = $this->keys_to_lowercase($tree);

		// This is an RSS feed, parse it
		if(array_key_exists("rss", $tree))
		{
			$this->parse_rss($tree['rss']);
		}

		// We don't know how to parse this feed
		else
		{
			$this->error = "unknown_feed_type";
			return false;
		}
	}

	/**
	 * Parses an XML structure in the format of an RSS feed
	 *
	 * @param array PHP XML parser structure
	 * @return boolean true
	 */
	function parse_rss($feed_contents)
	{
		foreach(array('title', 'link', 'description', 'pubdate') as $value)
		{
			if(!isset($feed_contents['channel'][$value]['value']))
			{
				$feed_contents['channel'][$value]['value'] = '';
			}
		}

		// Fetch channel information from the parsed feed
		$this->channel = array(
			"title" => $feed_contents['channel']['title']['value'],
			"link" => $feed_contents['channel']['link']['value'],
			"description" => $feed_contents['channel']['description']['value'],
			"date" => $feed_contents['channel']['pubdate']['value'],
			"date_timestamp" => $this->get_rss_timestamp($feed_contents['channel']['pubdate']['value'])
		);

		// The XML parser does not create a multidimensional array of items if there is one item, so fake it
		if(!array_key_exists("0", $feed_contents['channel']['item']))
		{
			$feed_contents['channel']['item'] = array($feed_contents['channel']['item']);
		}

		// Loop through each of the items in the feed
		foreach($feed_contents['channel']['item'] as $feed_item)
		{
			// Here is a nice long stretch of code for parsing items, we do it this way because most elements are optional in an
			// item and we only want to assign what we have.

			$item = array();


			// Set the item title if we have it
			if(array_key_exists("title", $feed_item))
			{
				$item['title'] = $feed_item['title']['value'];
			}

			if(array_key_exists("description", $feed_item))
			{
				$item['description'] = $feed_item['description']['value'];
			}

			if(array_key_exists("link", $feed_item))
			{
				$item['link'] = $feed_item['link']['value'];
			}

			// If we have a pub date, store it and attempt to generate a unix timestamp from it
			if(array_key_exists("pubdate", $feed_item))
			{
				$item['date'] = $feed_item['pubdate']['value'];
				$item['date_timestamp'] = $this->get_rss_timestamp($item['date']);
			}

			// If we have a GUID
			if(array_key_exists("guid", $feed_item))
			{
				$item['guid'] = $feed_item['guid']['value'];
			}
			// Otherwise, attempt to generate one from the link and item title
			else
			{
				$item['guid'] = md5($item['link'].$item['title']);
			}

			// If we have some content, set it
			if(array_key_exists("content:encoded", $feed_item))
			{
				$item['content'] = $feed_item['content:encoded']['value'];
			}
			else if(array_key_exists("content", $feed_item))
			{
				$item['content'] = $feed_item['content']['value'];
			}

			// We have a DC based creator, set it
			if(array_key_exists("dc:creator", $feed_item))
			{
				$item['author'] = $feed_item['dc:creator']['value'];
			}
			// Otherwise, attempt to use the author if we have it
			else if(array_key_exists("author", $feed_item))
			{
				$item['author'] = $feed_item['author']['value'];
			}

			// Assign the item to our list of items
			$this->items[] = $item;
		}
		return true;
	}

	/**
	 * Convert all array keys within an array to lowercase
	 *
	 * @param array The array to be converted
	 * @return array The converted array
	 */
	function keys_to_lowercase($array)
	{
		$new_array = array();
		foreach($array as $key => $value)
		{
			$new_key = strtolower($key);
			if(is_array($value))
			{
				$new_array[$new_key] = $this->keys_to_lowercase($value);
			}
			else
			{
				$new_array[$new_key] = $value;
			}
		}
		return $new_array;
	}

	/**
	 * Converts an RSS date stamp in to a unix timestamp
	 *
	 * @param string The RSS date
	 * @return integer The unix timestamp (if successful), 0 if unsuccessful
	 */
	function get_rss_timestamp($date)
	{
		$stamp = strtotime($date);
		if($stamp <= 0)
		{
			if(preg_match("#\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}#", $date, $result))
			{
				$date = str_replace(array("T", "+"), array(" ", " +"), $date);
				$date[23] = "";
			}
			$stamp = strtotime($date);
		}
		return $stamp;
	}
}
