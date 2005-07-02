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

class templates {
	var $total = 0;
	var $cache = array();
	var $templatelist = "";

	function cache($templates)
	{
		global $db, $extras, $theme;
		$names = explode(",", $templates);
		while(list($key, $title) = each($names))
		{
			$sql .= ",'".trim($title)."'";
		}
		if(is_array($extras))
		{
			while(list($extra, $val) = each($extras))
			{
				$sqladd .= " OR (title='cache_".trim($extra)."')";
			}
		}

		$query = $db->query("SELECT title,template FROM ".TABLE_PREFIX."templates WHERE title IN (''$sql) AND sid IN ('-2','-1','".$theme['templateset']."') $sqladd ORDER BY sid ASC");
		while($template = $db->fetch_array($query))
		{
			$this->cache[$template['title']] = $template['template'];
		}
	}

	function get($title, $eslashes=1, $htmlcomments=1)
	{
		global $db, $theme, $PHP_SELF;
		if(!isset($this->cache[$title]))
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='$title' AND sid IN ('-2','-1','".$theme['templateset']."') ORDER BY sid DESC LIMIT 0, 1");
			$gettemplate = $db->fetch_array($query);
			$this->cache[$title] = $gettemplate['template'];
//			$this->logit("uncached-templates.txt", $title);
		}
		$template = $this->cache[$title];
		if($htmlcomments)
		{
			$template = "<!-- start: $title -->\n$template\n<!-- end: $title -->";
		}
		if($eslashes)
		{
			$template = str_replace("\\'", "'", addslashes($template));
		}
		//if(!isset($this->cache[$title])) {
		//	echo "<b>Warning:</b> Missing template $title<br>";
		//}
		return $template;
	}

	function logit($file, $message)
	{
		global $PHP_SELF;
		$out = fopen($file, "a");
		fwrite($out, time()." $PHP_SELF $message\n");
		fclose($out);
	}

	function xhtmlfix($template)
	{
		$search  = array ("'(<\/?)(\w+)([^>]*>)'e",
                   "'(<\/?)(br|input|meta|link|img)([^>]*)( />)'ie", 
                   "'(<\/?)(br|input|meta|link|img)([^>]*)(/>)'ie", 
                   "'(<\/?)(br|input|meta|link|img)([^>]*)(>)'ie", 
                   "'(\w+=)(\w+)'ie", 
                   "'(\w+=)(.+?)'ie"); 
		$replace = array ("'\\1'.strtolower('\\2').'\\3'", 
                   "'\\1\\2\\3>'", 
                   "'\\1\\2\\3>'", 
                   "'\\1\\2\\3 /\\4'", 
                   "strtolower('\\1').'\"\\2\"'", 
                   "strtolower('\\1').'\\2'"); 
		$template = preg_replace($search, $replace, $template); 
		return $template;
	}

	// cache read/update functions :-D
	function readcache($name)
	{
		global $db, $templates;
		return $templates->get("cache_".$name, 0, 0);
	}

	function updatecache($name, $contents)
	{
		global $db, $templates;
		$name = "cache_".$name;
		$db->query("UPDATE templates SET ".TABLE_PREFIX."template='$contents' WHERE title='$name'");
		if($db->affected_rows() == 0)
		{
			$db->query("INSERT INTO ".TABLE_PREFIX."templates (title,template,sid) VALUES ('$name','$contents','-3')");
		}
	}
}
?>
