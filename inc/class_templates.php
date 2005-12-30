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
		foreach($names as $key => $title)
		{
			$sql .= ",'".trim($title)."'";
		}
		if(is_array($extras))
		{
			foreach($extras as $val => $extra)
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
		return $template;
	}
}
?>
