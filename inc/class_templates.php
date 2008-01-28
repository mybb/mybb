<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
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
	 * Array of templates loaded that were not loaded via the cache
	 *
	 * @var array
	 */
	var $uncached_templates = array();

	/**
	 * Cache the templates.
	 *
	 * @param string A list of templates to cache.
	 */
	function cache($templates)
	{
		global $db, $theme;
		$sql = $sqladd = "";
		$names = explode(",", $templates);
		foreach($names as $key => $title)
		{
			$sql .= " ,'".trim($title)."'";
		}

		$query = $db->simple_select("templates", "title,template", "title IN (''$sql) AND sid IN ('-2','-1','".$theme['templateset']."')", array('order_by' => 'sid', 'order_dir' => 'desc'));
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
		global $db, $theme, $mybb;

		//
		// DEVELOPMENT MODE
		//
		if($mybb->dev_mode == 1)
		{
			$template = $this->dev_get($title);
			if($template !== false)
			{
				$this->cache[$title] = $template;
			}
		}
		
		if(!isset($this->cache[$title]))
		{
			$query = $db->simple_select("templates", "template", "title='$title' AND sid IN ('-2','-1','".$theme['templateset']."')", array('order_by' => 'sid', 'order_dir' => 'DESC', 'limit' => 1));

			$gettemplate = $db->fetch_array($query);
			if($mybb->debug_mode)
			{
				$this->uncached_templates[$title] = $title;
			}
			///
			// REMOVE BEFORE RELEASE
			//
			if((is_writeable(MYBB_ROOT) && !file_exists(MYBB_ROOT."/uncached_templates.log") || is_writeable(MYBB_ROOT."/uncached_templates.log")))
			{
				if(!$this->fp)
				{
					$this->fp = fopen(MYBB_ROOT."/uncached_templates.log", "a+");
					fwrite($this->fp, "\n\nSELF: {$_SERVER['PHP_SELF']}\nQUERY: {$_SERVER['QUERY_STRING']}\n---\n");
				}
				fwrite($this->fp, $title."\n");
			}

			$this->cache[$title] = $gettemplate['template'];
		}
		$template = $this->cache[$title];

		if($htmlcomments)
		{
			if($mybb->settings['tplhtmlcomments'] == 1)
			{
				$template = "<!-- start: ".htmlspecialchars_uni($title)." -->\n{$template}\n<!-- end: ".htmlspecialchars_uni($title)." -->";
			}
			else
			{
				$template = "\n{$template}\n";
			}
		}
		
		if($eslashes)
		{
			$template = str_replace("\\'", "'", addslashes($template));
		}
		return $template;
	}

	/**
	 * Fetch a template directly from the install/resources/mybb_theme.xml directory if it exists (DEVELOPMENT MODE)
	 */
	function dev_get($title)
	{
		static $template_xml;

		if(!$template_xml)
		{
			if(@file_exists(MYBB_ROOT."install/resources/mybb_theme.xml"))
			{
				$template_xml = simplexml_load_file(MYBB_ROOT."install/resources/mybb_theme.xml");
			}
			else
			{
				return false;
			}
		}
		$res = $template_xml->xpath("//template[@name='{$title}']");
		return $res[0];
	}

}
?>