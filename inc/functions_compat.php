<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: functions_compat.php 4317 2009-02-04 02:30:25Z Tikitiki $
 */

/**
 * Below are compatibility functions which replicate functions in newer versions of PHP.
 *
 * This allows MyBB to continue working on older installations of PHP without these functions.
 */

if(!function_exists("stripos"))
{
	function stripos($haystack, $needle, $offset=0)
	{
		return my_strpos(my_strtoupper($haystack), my_strtoupper($needle), $offset);
	}
}


if(!function_exists("file_get_contents"))
{
	function file_get_contents($file)
	{
		$handle = @fopen($file, "rb");

		if($handle)
		{
			while(!@feof($handle))
			{
				$contents .= @fread($handle, 8192);
			}
			return $contents;
		}

		return false;
	}
}

if(!function_exists('html_entity_decode'))
{
	function html_entity_decode($string)
	{
	   // replace numeric entities
	   $string = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $string);
	   $string = preg_replace('~&#([0-9]+);~e', 'chr(\\1)', $string);

	   // replace literal entities
	   $trans_tbl = get_html_translation_table(HTML_ENTITIES);
	   $trans_tbl = array_flip($trans_tbl);

	   return strtr($string, $trans_tbl);
	}
}

if(!function_exists('htmlspecialchars_decode'))
{
	function htmlspecialchars_decode($text)
	{
		return strtr($text, array_flip(get_html_translation_table(HTML_SPECIALCHARS)));
	}
}

if(!function_exists('scandir'))
{
	function scandir($directory, $sorting_order=0)
	{
		if($handle = opendir($directory))
		{			
			while(false !== ($file = readdir($handle)))
			{
				$files[] = $file;
    		}
			
    		closedir($handle);
    		if($sorting_order == 1)
    		{
				rsort($files);
    		}
    		else
    		{
				sort($files);
    		}
			
			return $files;
		}
		return false;
	}
}

if(!function_exists('str_ireplace'))
{
	function build_str_ireplace(&$pattern)
	{
		$pattern = "#".preg_quote($pattern, "#")."#";
	}
	
	function str_ireplace($search, $replace, $subject)
	{
		if(is_array($search))
		{
			array_walk($search, 'build_str_ireplace');
		}
		else
		{
			build_str_ireplace($search);
		}
		return preg_replace($search, $replace, $subject);
	}
}

if(!function_exists('memory_get_peak_usage'))
{
	function memory_get_peak_usage($real_usage=false)
	{
		return memory_get_usage($real_usage);
	}
}

if(!function_exists('mb_substr'))
{
	/**
	 * Multibyte safe substr function. Based off of code from http://php.net/manual/en/function.substr.php#53199
	 *
	 * @param string The string to cut.
	 * @param int Where to start
	 * @param int (optional) How much to cut
	 * @return int The cut part of the string.
	 */
	function mb_substr($string, $start, $len=null)
	{
		// How many bytes per character does this string have? (ex. utf-8 is "3", gb2312 and big5 is "2")
		$byte = 3;
		$cut_string = "";
		$count = 0;
		$string_length = strlen($string);
		
		if($len == null)
		{
			$len = $string_length;
		}
		
		for($i=0; $i < $string_length; $i++)
		{
			$chr = substr($string, $i, 1);
			$ord = ord($chr);
			
			if(($count+1-$start) > $len)
			{
				break;
			}
			elseif($ord <= 128 && $count < $start)
			{
				$count++;
			}
			elseif($ord > 128 && $count < $start)
			{
				$count = $count+2;
				$i = $i+$byte-1;
			}
			elseif($ord <= 128 && $count >= $start)
			{
				$cut_string .= $chr;
				$count++;
			}
			elseif($ord > 128 && $count >= $start)
			{
				$cut_string .= substr($string, $i, $byte);
				$count = $count+2;
				$i = $i+$byte-1;
			}
		}
		return $cut_string;
	}
}

?>