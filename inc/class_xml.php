<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * The following class is based upon code by Eric Pollman
 * @ http://eric.pollman.net/work/public_domain/
 * and is licensed under the public domain license.
 */

class XMLParser {

	/**
	 * @var string
	 */
	public $data;
	/**
	 * @var array
	 */
	public $vals;
	/**
	 * @var int
	 */
	public $collapse_dups = 1;
	/**
	 * @var int
	 */
	public $index_numeric = 0;

	/**
	 * Initialize the parser and store the XML data to be parsed.
	 *
	 * @param string $data
	 */
	function __construct($data)
	{
		$this->data = $data;
	}

	/**
	 * Build a tree based structure based from the parsed data
	 *
	 * @return array The tree based structure
	 */
	function get_tree()
	{
		$parser = xml_parser_create();
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
		xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
		if(!xml_parse_into_struct($parser, $this->data, $vals, $index))
		{
			return false;
		}

		$i = -1;
		return $this->get_children($vals, $i);
	}

	/**
	 * Private: Build a completed tag by fetching all child nodes and attributes
	 *
	 * @param array $thisvals Array of values from the current tag
	 * @param array $vals Array of child nodes
	 * @param int $i Internal counter
	 * @param string $type Type of tag. Complete is a single line tag with attributes
	 * @return array Completed tag array
	 */
	function build_tag($thisvals, $vals, &$i, $type)
	{
		$tag = array('tag' => $thisvals['tag']);

		if(isset($thisvals['attributes']))
		{
			$tag['attributes'] = $thisvals['attributes'];
		}

		if($type == "complete")
		{
			if(isset($thisvals['value']))
			{
				$tag['value'] = $thisvals['value'];
			}
		}
		else
		{
			$tag = array_merge($tag, $this->get_children($vals, $i));
		}
		return $tag;
	}

	/**
	 * Fetch the children for from a specific node array
	 *
	 * @param array $vals Array of children
	 * @param int $i Internal counter
	 * @return array Array of child nodes
	 */
	function get_children($vals, &$i)
	{
		$children = array();

		if($i > -1 && isset($vals[$i]['value']))
		{
			$children['value'] = $vals[$i]['value'];
		}

		while(++$i < count($vals))
		{
			$type = $vals[$i]['type'];
			if($type == "cdata")
			{
				$children['value'] .= $vals[$i]['value'];
			}
			elseif($type == "complete" || $type == "open")
			{
				$tag = $this->build_tag($vals[$i], $vals, $i, $type);
				if($this->index_numeric)
				{
					$tag['tag'] = $vals[$i]['tag'];
					$children[] = $tag;
				}
				else
				{
					$children[$tag['tag']][] = $tag;
				}
			}
			else if($type == "close")
			{
				break;
			}
		}
		if($this->collapse_dups)
		{
			foreach($children as $key => $value)
			{
				if(is_array($value) && (count($value) == 1))
				{
					$children[$key] = $value[0];
				}
			}
		}
		return $children;
	}
}

/**
 * Kill off unnecessary tags and return a clean array of XML data
 *
 * @param array $array Array of parsed XML data
 * @return array Cleaned array of XML data
 */
function kill_tags($array)
{
	foreach($array as $key => $val)
	{
		if($key == "tag" || $key == "value")
		{
			unset($array[$key]);
		}
		else if(is_array($val))
		{
			// kill any nested tag or value indexes
			$array[$key] = kill_tags($val);

			// if the array no longer has any key/val sets
			// and therefore is at the deepest level, then
			// store the string value
			if(count($array[$key]) <= 0)
			{
				$array[$key] = $val['value'];
			}
		}
	}

	return $array;
}
