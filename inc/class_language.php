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

class MyLanguage
{

	/**
	 * The path to the languages folder.
	 *
	 * @var string
	 */
	var $path = "./inc/languages";

	/**
	 * The language we are using.
	 *
	 * @var string
	 */
	var $language;

	/**
	 * Information about the current language.
	 *
	 * @var array
	 */
	var $settings;

	/**
	 * Set the path for the language folder.
	 *
	 * @param string The path to the language folder.
	 */
	function setPath($path)
	{
		$this->path = $path;
	}

	/**
	 * Check if a specific language exists.
	 *
	 * @param string The language to check for.
	 * @return boolean True when exists, false when does not exist.
	 */
	function languageExists($language)
	{
		if(file_exists($this->path."/".$language.".php"))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Set the language for an area.
	 *
	 * @param string The language to use.
	 * @param string The area to set the language for.
	 */
	function setLanguage($language="english", $area="user")
	{
		// Check if the language exists.
		if(!$this->languageExists($language))
		{
			die("Language $language ($this->path/$language) is not installed");
		}

		// Default language is English.
		if($language == "")
		{
			$language = "english";
		}

		$this->language = $language;
		require $this->path."/".$language.".php";
		$this->settings = $langinfo;

		// Load the admin language files as well, if needed.
		if($area == "admin")
		{
			if(!is_dir($this->path."/".$language."/admin"))
			{
				die("This language does not contain an Administration set");
			}
			$this->language = $language."/admin";
		}
	}

	/**
	 * Load the language variables for a section.
	 *
	 * @param string The section name.
	 */
	function load($section)
	{
		// Assign language variables.
		$lfile = $this->path."/".$this->language."/".$section.".lang.php";
		if(file_exists($lfile))
		{
			require $lfile;
		}
		else
		{
			die("$lfile does not exist");
		}
		if(is_array($l))
		{
			foreach($l as $key => $val)
			{
				if(!$this->$key || $this->$key != $val)
				{
					$val = preg_replace("#\{([0-9]+)\}#", "%$1\$s", $val);
					$this->$key = $val;
				}
			}
		}
	}

	/**
	 * Get the language variables for a section.
	 *
	 * @param boolean Admin variables when true, user when false.
	 * @return array The language variables.
	 */
	function getLanguages($admin=0)
	{
		$dir = @opendir($this->path);
		while($lang = readdir($dir))
		{
			$ext = strtolower(getextension($lang));
			if($lang != "." && $lang != ".." && $ext == "php")
			{
				$lname = str_replace(".".$ext, "", $lang);
				require $this->path."/".$lang;
				if(!$admin || ($admin && $langinfo['admin']))
				{
					$languages[$lname] = $langinfo['name'];
				}
			}
		}
		@ksort($languages);
		return $languages;
	}

	/**
	 * Parse contents for language variables.
	 *
	 * @param string The contents to parse.
	 * @return string The parsed contents.
	 */
	function parse($contents)
	{
		$contents = preg_replace("#<lang:([a-zA-Z0-9_]+)>#e", "\$this->$1", $contents);
		return $contents;
	}
}
?>