<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */

/**
 * The following class is based upon code by Eric Pollman
 * @ http://eric.pollman.net/work/public_domain/
 * and is licensed under the public domain license.
 */

class XMLParser {
	
	public $data;
	public $vals;
	public $collapse_dups = 1;
	public $index_numeric = 0;
	/**
	 * The parsed XML
	 * 
	 * 
	 * @var		string
	 */
	public $parsedXML = '';

	/**
	 * Initialize the parser and store the XML data to be parsed.
	 * 
	 * @param	string		Can be supplied with either data or Filename to be opened
	 * @return	void
	 */
	function __construct($fileordata, $init = false)
	{
		if(is_file($fileordata))
		{
			$data = @file_get_contents($fileordata);
			$this->data = $data;
		}
		else
		{
			$this->data = $data;
		}
		if($init === true)
		{
			$this->parse();
		}
	}
	
	/**
	 * Initiates parsing
	 * Usage:
	 * $xml = new XMLParser('c:/wamp/something/xml.xml');
	 * $xml->parse();
	 * $data = $xml->parsedXML;
	 * 
	 * Or even:
	 * $xml = new XMLParser('c:/wamp/something/xml.xml', true);
	 * $data = $xml->parsedXML;
	 * 
	 * So simple eh? But still, you guys have it complex.
	 * 
	 * @return 	void
	 */
	public function parse()
	{
		$this->parsedXML = $this->get_tree($this->data);
	}
	
	/**
	 * This function accepts an array and removes all base tags & returns the last children tags
	 * Usage:
	 * Example XML:
	 *	<?xml version="1.0" encoding="utf-8"?>
	 *	<info>
	 *		<data>
	 *			<name>INS Test Addon</name>
	 *			<author>AskAmn</author>
	 *			<description>Test build for testing INS Applications/Hooks Pages</description>
	 *		</data>
	 *	</info>
	 * $xml = new XMLParser;
	 * $xmlfile = @file_get_contents('abovexml.xml');
	 * $arrays = $xml->get_tree();
	 * $tag = array();
	 * $i = 1;
	 * foreach($arrays AS $array)
	 * {
	 * 	$tag.'_'.$i = $xml->getXMLElement($array);
	 * 	$i++;
	 * }
	 * // Lets print $tag_1 (infact there will be only this TAG)
	 * print_r($tag_1);
	 * Array
	 *	(
	 * 	    [name] => INS Test Addon
	 *	    [author] => AskAmn
	 *	    [description] => Test build for testing INS Applications/Hooks Pages
	 *	    [last] => 1
	 *	)
	 * 
	 * @param	array		The tag to parse
	 * @return	array		Parsed array
	 */
	function getXMLElement($xml)
	{
		foreach($xml AS $tag => $array)
		{
			if(is_array($array))
			{
				$_array = getAppInfo($array);
				if(array_key_exists('last', $_array))
				{
					return $_array;
				}
			}
			else
			{
				$myarray[$tag] = $array;
			}
		}
		if(is_array($myarray))
		{
			$myarray['last'] = 1;
	     		return $myarray;
		}	
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
	 * @param array Array of values from the current tag
	 * @param array Array of child nodes
	 * @param int Internal counter
	 * @param string Type of tag. Complete is a single line tag with attributes
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
	 * @param array Array of children
	 * @param int Internal counter
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
 * @param array Array of parsed XML data
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
?>
