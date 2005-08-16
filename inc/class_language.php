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

class MyLanguage {
	var $path = "./inc/languages";
	var $language;
	var $settings;

	function setPath($path)
	{
		$this->path = $path;
	}

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

	function setLanguage($language="english", $area="user")
	{
		if(!$this->languageExists($language))
		{
			die("Language $language ($this->path/$language) is not installed");
		}
		if($language == "")
		{
			$language = "english";
		}
		$this->language = $language;
		require $this->path."/".$language.".php";
		$this->settings = $langinfo;

		if($area == "admin")
		{
			if(!is_dir($this->path."/".$language."/admin"))
			{
				die("This language does not contain an Administration set");
			}
			$this->language = $language."/admin";
		}
	}

	function load($section)
	{
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

	function getLanguages($admin=0)
	{
		$dir = @opendir($this->path);
		while($lang = readdir($dir))
		{
			$ext = strtolower(getextention($lang));
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

	function parse($contents)
	{
		$contents = preg_replace("#<lang:([a-zA-Z0-9_]+)>#e", "\$this->$1", $contents);
		return $contents;
	}
}
?>