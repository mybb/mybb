<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/**
 * The following class is based upon code by Eric Pollman
 * @ http://eric.pollman.net/work/public_domain/
 * and is licensed under the public domain license.
 */

class XMLParser {
	var $data;
	var $vals;
	var $collapse_dups = 1;
	var $index_numeric = 0;

	function XMLParser($data)
	{
		$this->data = $data;
	}

	function getTree()
	{
		$parser = xml_parser_create();
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
		xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
		xml_parse_into_struct($parser, $this->data, $vals, $index);

		$i = -1;
		return $this->getChildren($vals, $i);
	}

	function buildTag($thisvals, $vals, &$i, $type)
	{
		$tag['tag'] = $thisvals['tag'];
		if(isset($thisvals['attributes']))
		{
			$tag['attributes'] = $thisvals['attributes'];
		}

		if($type == "complete")
		{
			$tag['value'] = $thisvals['value'];
		}
		else
		{
			$tag = array_merge($tag, $this->getChildren($vals, $i));
		}
		return $tag;
	}

	function getChildren($vals, &$i)
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
				$tag = $this->buildTag($vals[$i], $vals, $i, $type);
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
			elseif($type == "close")
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
 * Why this function did not work had me,
 * Chris, stumped for days. 2 Hours of
 * Matt Light's expertise and it was working
 * perfectly.

 * 	http://www.mephex.com
 * 	^ Visit him, he does great things with
 * 	  your code
 */
function killtags($array)
{
	foreach($array as $key => $val)
	{
		if($key == "tag" || $key == "value")
		{
			unset($array[$key]);
		}
		elseif(is_array($val))
		{
			// kill any nested tag or value indexes
			$array[$key] = killtags($val);

			// if the array no longer has any key/val sets
			// and therefore is at the deepest level, then
			// store the string value
			if (count($array[$key]) <= 0)
			{
				$array[$key] = $val['value'];
			}
		}
	}

	return $array;
}
?>