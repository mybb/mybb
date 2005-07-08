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

class MyBB {
	var $cwd = ".";
	var $input = array();
	var $user = array();
	var $usergroup = array();
	var $settings = array();
	var $magicquotes = 0;
	var $config = array();

	var $clean_variables = array (
		"int" => array("tid", "pid", "uid", "eid", "pmid", "sid")
		);

	function MyBB()
	{
		// Set up MyBB

		// Determine Magic Quotes Status
		if (get_magic_quotes_gpc())
		{
			$this->magicquotes = 1;
			$this->strip_slashes_array($_POST);
			$this->strip_slashes_array($_GET);
			$this->strip_slashes_array($_COOKIE);
		}
		set_magic_quotes_runtime(0);
		@ini_set("magic_quotes_gpc", 0);
		@ini_set("magic_quotes_runtime", 0); 

		
		// Determine input
		$this->parse_incoming($_GET);
		$this->parse_incoming($_POST);

		// If we've got register globals on, then kill them too
		if (@ini_get("register_globals") || !@ini_get("gpc_order"))
		{
			$this->unset_globals($_POST);
			$this->unset_globals($_GET);
			$this->unset_globals($_FILES);
			//$this->unset_globals($_COOKIE);
		}

		$this->clean_input();
	}

	function parse_incoming($array)
	{
		if(!is_array($array))
		{
			return;
		}

		foreach($array as $key => $val)
		{
			$this->input[$key] = $val;
			if(defined("KILL_GLOBALS"))
			{
				unset($GLOBALS["$key"]);
			}
		}
	}

	function strip_slashes_array(&$array)
	{
		foreach($array as $key => $val)
		{
			if(is_array($array[$key]))
			{
				$this->strip_slashes_array($array[$key]);
			}
			else
			{
				$array[$key] = stripslashes($array[$key]);
			}
		}
	}

	function unset_globals($array)
	{
		if(!is_array($array))
		{
			return;
		}

		foreach(array_keys($array) as $key)
		{
			if(defined("KILL_GLOBALS"))
			{
				unset($GLOBALS["$key"]);
			}
/*
			$file = explode("/", $_SERVER['PHP_SELF']);
			$file = $file[count($file)-2]."/".$file[count($file)-1].".log";
			$this->log_message("./vars_to_unset/".$file, $key."\n"); */
		}
	}

	function clean_input()
	{
		foreach($this->clean_variables as $type => $variables)
		{
			foreach($variables as $var)
			{
				if($type == "int" && @$this->input[$var] && $this->input[$var] != "lastposter")
				{
					$this->input[$var] = intval($this->input[$var]);
				}
			}
		}
	}

	function log_message($file, $message)
	{
		$handle = fopen($file, 'a');
		fwrite($handle, $message);
		fclose($handle);
	}

	function trigger_generic_error($code, $halt=true)
	{
		switch($code)
		{
			case "cache_no_write":
				$message = "The data cache directory (inc/cache/) needs exist and be writable by the web server. Change its permissions so that it is writable (777 on Unix based servers).";
				break;
			default:
				$message = "MyBB has experienced an internal error. Please contact the MyBB Group for support. <a href=\"http://www.mybboard.com\">MyBB Website</a>";
		}
		include "./inc/generic_error.html";
		if($halt)
		{
			exit;
		}
	}
}
?>