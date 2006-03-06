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

class templates
{
	/**
	 * The total number of templates.
	 *
	 * @var int
	 */
	var $total = 0;
	
	/**
	 * The template cache.
	 *
	 * @var array
	 */
	var $cache = array();
	
	/**
	 * The current template list.
	 *
	 * @var string
	 */
	var $templatelist = "";

	/**
	 * Cache the templates.
	 *
	 * @param string A list of templates to cache.
	 */
	function cache($templates)
	{
		global $db, $extras, $theme;
		$sql = $sqladd = "";
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

	/**
	 * Gets templates.
	 *
	 * @param string The title of the template to get.
	 * @param boolean True if template contents must be escaped, false if not.
	 * @param boolean True to output HTML comments, false to not output.
	 * @return string The template HTML.
	 */
	function get($title, $eslashes=1, $htmlcomments=1)
	{
		global $db, $theme, $PHP_SELF, $mybb;
		if(!isset($this->cache[$title]))
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='$title' AND sid IN ('-2','-1','".$theme['templateset']."') ORDER BY sid DESC LIMIT 0, 1");
			$gettemplate = $db->fetch_array($query);
			$this->cache[$title] = $gettemplate['template'];
		}
		$template = $this->cache[$title];
		if($htmlcomments && $mybb->settings['tplhtmlcomments'] == "yes")
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
