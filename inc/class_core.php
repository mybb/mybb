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
	
	/**
	 * The current working directory.
	 *
	 * @var string
	 */
	var $cwd = ".";
	
	/**
	 * Input variables received from the outer world.
	 *
	 * @var array
	 */
	var $input = array();
	
	/**
	 * Information about the current user.
	 *
	 * @var array
	 */
	var $user = array();
	
	/**
	 * Information about the current usergroup.
	 *
	 * @var array
	 */
	var $usergroup = array();
	
	/**
	 * MyBB settings.
	 *
	 * @var array
	 */
	var $settings = array();
	
	/**
	 * Whether or not magic quotes are enabled.
	 *
	 * @var unknown_type
	 */
	var $magicquotes = 0;
	
	/**
	 * MyBB configuration.
	 *
	 * @var array
	 */
	var $config = array();
	
	/**
	 * The debug information.
	 *
	 * @var unknown_type
	 */
	var $debug;
	
	/**
	 * The request method that called this page.
	 *
	 * @var string.
	 */
	var $request_method = "";

	/**
	 * Variables that need to be clean.
	 *
	 * @var array
	 */
	var $clean_variables = array(
		"int" => array("tid", "pid", "uid", "eid", "pmid", "sid", "fid")
	);

	/**
	 * Constructor of class.
	 *
	 * @return MyBB
	 */
	function MyBB()
	{
		// Set up MyBB

		// Determine Magic Quotes Status
		if(get_magic_quotes_gpc())
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

		if($_SERVER['REQUEST_METHOD'] == "POST")
		{
			$this->request_method = "post";
		}
		elseif($_SERVER['REQUEST_METHOD'] == "GET")
		{
			$this->request_method = "get";
		}
		// If we've got register globals on, then kill them too
		if (@ini_get("register_globals") || !@ini_get("gpc_order"))
		{
			$this->unset_globals($_POST);
			$this->unset_globals($_GET);
			$this->unset_globals($_FILES);
			$this->unset_globals($_COOKIE);
		}
		if($this->input['debug'])
		{
			$this->debug = 1;
		}
		$this->clean_input();
	}

	/**
	 * Parses the incoming variables.
	 *
	 * @param array The array of incoming variables.
	 */
	function parse_incoming($array)
	{
		if(!is_array($array))
		{
			return;
		}

		foreach($array as $key => $val)
		{
			$this->input[$key] = $val;
			unset($GLOBALS[$key]);
		}
	}

	/**
	 * Strips slashes out of a given array.
	 *
	 * @param array The array to strip.
	 */
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

	/**
	 * Unsets globals from a specific array.
	 *
	 * @param array The array to unset from.
	 */
	function unset_globals($array)
	{
		if(!is_array($array))
		{
			return;
		}

		foreach(array_keys($array) as $key)
		{
			unset($GLOBALS[$key]);
		}
	}

	/**
	 * Cleans predefined input variables.
	 *
	 */
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

	/**
	 * Logs a message.
	 *
	 * @param string The file to log to.
	 * @param string The message to log.
	 */
	function log_message($file, $message)
	{
		$handle = fopen($file, 'a');
		fwrite($handle, $message);
		fclose($handle);
	}

	/**
	 * Triggers a generic error.
	 *
	 * @param string The error code.
	 * @param boolean Halt code execution, true for halt.
	 */
	function trigger_generic_error($code, $halt=true)
	{
		switch($code)
		{
			case "cache_no_write":
				$message = "The data cache directory (inc/cache/) needs to exist and be writable by the web server. Change its permissions so that it is writable (777 on Unix based servers).";
				break;
			case "install_directory":
				$message = "The install directory (install/) still exists on your server and is not locked. To access MyBB please either remove this directory or create an empty file in it called 'lock'.";
				break;
			case "board_not_installed":
				$message = "Your board has not yet been installed and configured. Please do so before attempting to browse it.";
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